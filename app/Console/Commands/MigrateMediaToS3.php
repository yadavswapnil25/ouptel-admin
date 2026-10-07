<?php

namespace App\Console\Commands;

use App\Support\MediaUrl;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\FileAttributes;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

/**
 * Copies existing local media to the S3 bucket under the same relative keys,
 * so database paths keep working once MEDIA_DISK=s3.
 *
 *   storage/app/public/<key>  ->  s3://<bucket>/<key>
 *   public/upload/<rest>      ->  s3://<bucket>/upload/<rest>   (legacy WoWonder files)
 *
 * Idempotent: objects already in the bucket with the same size are skipped, so
 * it can be re-run after switching MEDIA_DISK to catch late uploads.
 */
class MigrateMediaToS3 extends Command
{
    protected $signature = 'media:migrate-to-s3
        {--dry-run : Only report what would be copied}
        {--prefix= : Only copy keys starting with this prefix, e.g. posts/ or upload/photos/2025}
        {--force : Overwrite bucket objects whose size differs from the local file}';

    protected $description = 'Copy local user media (storage/app/public, public/upload) to the S3 bucket';

    private const IGNORED = ['.gitignore', '.gitkeep', '.DS_Store', 'Thumbs.db', 'desktop.ini', '.htaccess', 'index.html'];

    public function handle(): int
    {
        $dryRun = (bool) $this->option('dry-run');
        $prefix = ltrim((string) $this->option('prefix'), '/');
        $force = (bool) $this->option('force');

        if (!$dryRun && !config('filesystems.disks.s3.bucket')) {
            $this->error('AWS_BUCKET is not set. Configure AWS_* in .env first.');
            return self::FAILURE;
        }

        // The disk is configured with throw=false, which turns AWS errors into a
        // bare `false`. Throw here so failures report AWS's actual reason.
        config(['filesystems.disks.s3.throw' => true]);
        Storage::forgetDisk('s3');

        $files = $this->collectLocalFiles($prefix);
        $this->info(sprintf('Found %d local files%s.', count($files), $prefix !== '' ? " under '{$prefix}'" : ''));
        if ($files === []) {
            return self::SUCCESS;
        }

        $remote = $this->listBucket($prefix);
        if ($remote === null) {
            if (!$dryRun) {
                return self::FAILURE;
            }
            $remote = [];
        }

        $s3 = Storage::disk('s3');
        $copied = $skipped = $failed = $conflicts = 0;
        $bytes = 0;

        $bar = $this->output->createProgressBar(count($files));
        foreach ($files as $key => $file) {
            $size = $file->getSize();
            $existing = $remote[$key] ?? null;

            if ($existing !== null && ($existing === $size || !$force)) {
                if ($existing !== $size) {
                    $conflicts++;
                    $this->newLine();
                    $this->warn("Size differs, kept bucket copy (use --force to overwrite): {$key}");
                } else {
                    $skipped++;
                }
                $bar->advance();
                continue;
            }

            if ($dryRun) {
                $copied++;
                $bytes += $size;
                $bar->advance();
                continue;
            }

            $stream = @fopen($file->getPathname(), 'rb');
            $ok = false;
            if ($stream !== false) {
                try {
                    $ok = $s3->writeStream($key, $stream, ['visibility' => MediaUrl::visibilityFor($key)]);
                } catch (\Throwable $e) {
                    $aws = $e->getPrevious();
                    $reason = $aws && method_exists($aws, 'getAwsErrorCode')
                        ? $aws->getAwsErrorCode() . ': ' . $aws->getAwsErrorMessage()
                        : $e->getMessage();
                    $this->newLine();
                    $this->error("Failed {$key}: {$reason}");
                    $failed++;
                    $bar->advance();
                    continue;
                } finally {
                    if (is_resource($stream)) {
                        fclose($stream);
                    }
                }
            }

            if ($ok) {
                $copied++;
                $bytes += $size;
            } else {
                $failed++;
                $this->newLine();
                $this->error("Failed to upload {$key}");
            }
            $bar->advance();
        }
        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Result', 'Count'],
            [
                [$dryRun ? 'Would copy' : 'Copied', $copied . ' (' . $this->humanBytes($bytes) . ')'],
                ['Already in bucket (skipped)', $skipped],
                ['Size conflicts (kept bucket copy)', $conflicts],
                ['Failed', $failed],
            ]
        );

        return $failed > 0 ? self::FAILURE : self::SUCCESS;
    }

    /**
     * @return array<string, SplFileInfo> bucket key => local file
     */
    private function collectLocalFiles(string $prefix): array
    {
        $files = [];
        // Legacy public/upload first so a same-key file from storage (current
        // writers) wins.
        $sources = [
            public_path('upload') => 'upload/',
            storage_path('app/public') => '',
        ];

        foreach ($sources as $root => $keyPrefix) {
            if (!is_dir($root)) {
                continue;
            }
            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root, RecursiveDirectoryIterator::SKIP_DOTS)
            );
            foreach ($iterator as $file) {
                /** @var SplFileInfo $file */
                if (!$file->isFile() || in_array($file->getFilename(), self::IGNORED, true)) {
                    continue;
                }
                $relative = str_replace('\\', '/', substr($file->getPathname(), strlen($root) + 1));
                $key = $keyPrefix . $relative;
                if ($prefix !== '' && !str_starts_with($key, $prefix)) {
                    continue;
                }
                $files[$key] = $file;
            }
        }

        ksort($files);

        return $files;
    }

    /**
     * One paginated listing instead of a HEAD request per file.
     *
     * @return array<string, int>|null key => size, or null if the bucket can't be listed
     */
    private function listBucket(string $prefix): ?array
    {
        try {
            $sizes = [];
            $listing = Storage::disk('s3')->getDriver()->listContents($prefix, true);
            foreach ($listing as $item) {
                if ($item instanceof FileAttributes) {
                    $sizes[$item->path()] = (int) $item->fileSize();
                }
            }
            $this->line(sprintf('Bucket already holds %d objects%s.', count($sizes), $prefix !== '' ? " under '{$prefix}'" : ''));

            return $sizes;
        } catch (\Throwable $e) {
            $this->warn('Could not list the bucket: ' . $e->getMessage());

            return null;
        }
    }

    private function humanBytes(int $bytes): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $i = 0;
        $value = (float) $bytes;
        while ($value >= 1024 && $i < count($units) - 1) {
            $value /= 1024;
            $i++;
        }

        return round($value, 1) . ' ' . $units[$i];
    }
}
