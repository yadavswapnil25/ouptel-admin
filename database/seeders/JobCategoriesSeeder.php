<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class JobCategoriesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!Schema::hasTable('Wo_Job_Categories')) {
            $this->command->warn('Wo_Job_Categories table not found, skipping JobCategoriesSeeder.');
            return;
        }

        $categories = [
            'software_development' => 'Software Development',
            'design_creative' => 'Design & Creative',
            'sales_marketing' => 'Sales & Marketing',
            'customer_support' => 'Customer Support',
            'human_resources' => 'Human Resources',
            'finance_accounting' => 'Finance & Accounting',
            'operations' => 'Operations',
            'healthcare' => 'Healthcare',
            'education_training' => 'Education & Training',
            'construction' => 'Construction',
            'hospitality' => 'Hospitality',
            'legal_compliance' => 'Legal & Compliance',
        ];

        foreach ($categories as $langKey => $label) {
            $langId = $this->upsertLangKey($langKey, $label, 'job');

            $payload = [];
            if (Schema::hasColumn('Wo_Job_Categories', 'lang_key')) {
                // WoWonder usually stores Wo_Langs.id in this field.
                $payload['lang_key'] = $langId;
            }
            if (Schema::hasColumn('Wo_Job_Categories', 'name')) {
                $payload['name'] = $label;
            }
            if (Schema::hasColumn('Wo_Job_Categories', 'description')) {
                $payload['description'] = $label . ' jobs and opportunities';
            }

            if (empty($payload)) {
                continue;
            }

            // Prefer unique match by lang_key if available.
            if (Schema::hasColumn('Wo_Job_Categories', 'lang_key')) {
                DB::table('Wo_Job_Categories')->updateOrInsert(
                    ['lang_key' => $langId],
                    $payload
                );
            } elseif (Schema::hasColumn('Wo_Job_Categories', 'name')) {
                DB::table('Wo_Job_Categories')->updateOrInsert(
                    ['name' => $label],
                    $payload
                );
            }
        }

        $this->command->info('Job categories seeded successfully.');
    }

    /**
     * Create or update a Wo_Langs entry and return its ID.
     */
    private function upsertLangKey(string $langKey, string $englishLabel, string $type): int
    {
        if (!Schema::hasTable('Wo_Langs')) {
            // Fallback pseudo ID when language table is unavailable.
            return crc32($langKey) & 0x7fffffff;
        }

        $query = DB::table('Wo_Langs')->where('lang_key', $langKey);
        if (Schema::hasColumn('Wo_Langs', 'type')) {
            $query->where('type', $type);
        }

        $existing = $query->first();
        if ($existing) {
            DB::table('Wo_Langs')
                ->where('id', $existing->id)
                ->update(['english' => $englishLabel] + (Schema::hasColumn('Wo_Langs', 'type') ? ['type' => $type] : []));

            return (int) $existing->id;
        }

        $insert = [
            'lang_key' => $langKey,
            'english' => $englishLabel,
        ];
        if (Schema::hasColumn('Wo_Langs', 'type')) {
            $insert['type'] = $type;
        }

        return (int) DB::table('Wo_Langs')->insertGetId($insert);
    }
}

