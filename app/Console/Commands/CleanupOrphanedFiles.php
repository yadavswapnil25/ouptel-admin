<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use App\Models\Event;

class CleanupOrphanedFiles extends Command
{
    protected $signature = 'storage:cleanup {--dry-run : Show what would be deleted without actually deleting}';
    protected $description = 'Clean up orphaned files in storage that are not referenced in the database';

    public function handle()
    {
        $this->info('Starting storage cleanup...');
        
        $disk = Storage::disk('public');
        $eventsPath = 'events/covers';
        
        if (!$disk->exists($eventsPath)) {
            $this->info('Events covers directory does not exist.');
            return 0;
        }
        
        $files = $disk->files($eventsPath);
        $this->info("Found " . count($files) . " files in {$eventsPath}");
        
        $orphanedFiles = [];
        
        foreach ($files as $file) {
            $filename = basename($file);
            
            // Check if this file is referenced in the database
            $exists = Event::where('cover', $filename)->exists();
            
            if (!$exists) {
                $orphanedFiles[] = $file;
            }
        }
        
        $this->info("Found " . count($orphanedFiles) . " orphaned files");
        
        if (empty($orphanedFiles)) {
            $this->info('No orphaned files found.');
            return 0;
        }
        
        if ($this->option('dry-run')) {
            $this->info('Dry run - would delete the following files:');
            foreach ($orphanedFiles as $file) {
                $this->line("  - {$file}");
            }
            return 0;
        }
        
        $this->warn('This will permanently delete orphaned files. Are you sure? (yes/no)');
        $confirm = $this->ask('Type "yes" to continue');
        
        if (strtolower($confirm) !== 'yes') {
            $this->info('Operation cancelled.');
            return 0;
        }
        
        $deletedCount = 0;
        foreach ($orphanedFiles as $file) {
            try {
                $disk->delete($file);
                $deletedCount++;
                $this->line("Deleted: {$file}");
            } catch (\Exception $e) {
                $this->error("Failed to delete {$file}: " . $e->getMessage());
            }
        }
        
        $this->info("Successfully deleted {$deletedCount} orphaned files.");
        return 0;
    }
}
