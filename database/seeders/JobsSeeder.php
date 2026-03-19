<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class JobsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!Schema::hasTable('Wo_Job')) {
            $this->command->warn('Wo_Job table not found, skipping JobsSeeder.');
            return;
        }

        $owner = User::query()
            ->where('email', 'admin@ouptel.com')
            ->orWhere('admin', '1')
            ->first()
            ?: User::query()->first();

        if (!$owner) {
            $this->command->warn('No users found, skipping JobsSeeder.');
            return;
        }

        $ownerId = (string) $owner->user_id;

        $pageId = null;
        if (Schema::hasTable('Wo_Pages')) {
            $page = Page::query()
                ->where('user_id', $ownerId)
                ->first()
                ?: Page::query()->first();
            $pageId = $page?->page_id;
        }

        $categoryIds = [];
        if (Schema::hasTable('Wo_Job_Categories')) {
            $categoryIds = DB::table('Wo_Job_Categories')->pluck('id')->map(fn ($id) => (int) $id)->all();
        }
        if (empty($categoryIds)) {
            $categoryIds = [1];
        }

        $jobs = [
            ['title' => 'Frontend React Developer', 'location' => 'Remote', 'job_type' => 'full_time', 'min' => 60000, 'max' => 90000],
            ['title' => 'Laravel Backend Engineer', 'location' => 'Bangalore', 'job_type' => 'full_time', 'min' => 70000, 'max' => 110000],
            ['title' => 'UI/UX Designer', 'location' => 'Mumbai', 'job_type' => 'contract', 'min' => 45000, 'max' => 80000],
            ['title' => 'Digital Marketing Specialist', 'location' => 'Delhi', 'job_type' => 'part_time', 'min' => 30000, 'max' => 50000],
            ['title' => 'Customer Support Executive', 'location' => 'Remote', 'job_type' => 'full_time', 'min' => 22000, 'max' => 38000],
            ['title' => 'HR Recruiter', 'location' => 'Hyderabad', 'job_type' => 'full_time', 'min' => 35000, 'max' => 60000],
            ['title' => 'Data Analyst Intern', 'location' => 'Pune', 'job_type' => 'internship', 'min' => 12000, 'max' => 20000],
            ['title' => 'Operations Coordinator', 'location' => 'Chennai', 'job_type' => 'full_time', 'min' => 28000, 'max' => 46000],
        ];

        $now = time();
        foreach ($jobs as $index => $job) {
            $categoryId = $categoryIds[$index % count($categoryIds)];

            $payload = [];
            if (Schema::hasColumn('Wo_Job', 'title')) {
                $payload['title'] = $job['title'];
            }
            if (Schema::hasColumn('Wo_Job', 'description')) {
                $payload['description'] = "Sample opening for {$job['title']} at Ouptel. This is seeded demo data for testing jobs listing and apply flow.";
            }
            if (Schema::hasColumn('Wo_Job', 'location')) {
                $payload['location'] = $job['location'];
            }
            if (Schema::hasColumn('Wo_Job', 'status')) {
                $payload['status'] = '1';
            }
            if (Schema::hasColumn('Wo_Job', 'time')) {
                $payload['time'] = (string) ($now - ($index * 3600));
            }
            if (Schema::hasColumn('Wo_Job', 'page_id') && $pageId) {
                $payload['page_id'] = (int) $pageId;
            }
            if (Schema::hasColumn('Wo_Job', 'category')) {
                $payload['category'] = $categoryId;
            }
            if (Schema::hasColumn('Wo_Job', 'job_type')) {
                $payload['job_type'] = $job['job_type'];
            }
            if (Schema::hasColumn('Wo_Job', 'minimum')) {
                $payload['minimum'] = $job['min'];
            }
            if (Schema::hasColumn('Wo_Job', 'maximum')) {
                $payload['maximum'] = $job['max'];
            }
            if (Schema::hasColumn('Wo_Job', 'salary_date')) {
                $payload['salary_date'] = 'per_month';
            }
            if (Schema::hasColumn('Wo_Job', 'currency')) {
                $payload['currency'] = 'USD';
            }
            if (Schema::hasColumn('Wo_Job', 'image')) {
                $payload['image'] = 'images/placeholders/job-avatar.svg';
            }

            if (Schema::hasColumn('Wo_Job', 'user_id')) {
                $payload['user_id'] = $ownerId;
            } elseif (Schema::hasColumn('Wo_Job', 'user')) {
                $payload['user'] = $ownerId;
            }

            // Optional simple application questions.
            if (Schema::hasColumn('Wo_Job', 'question_one')) {
                $payload['question_one'] = 'Why are you interested in this role?';
            }
            if (Schema::hasColumn('Wo_Job', 'question_one_type')) {
                $payload['question_one_type'] = 'free_text_question';
            }
            if (Schema::hasColumn('Wo_Job', 'question_two')) {
                $payload['question_two'] = 'Years of relevant experience?';
            }
            if (Schema::hasColumn('Wo_Job', 'question_two_type')) {
                $payload['question_two_type'] = 'free_text_question';
            }

            if (empty($payload)) {
                continue;
            }

            DB::table('Wo_Job')->updateOrInsert(
                ['title' => $job['title']],
                $payload
            );
        }

        $this->command->info('Sample jobs seeded successfully.');
    }
}

