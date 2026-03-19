<?php

namespace Database\Seeders;

use App\Models\Event;
use App\Models\EventGoing;
use App\Models\EventInterested;
use App\Models\EventInvited;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Schema;

class EventsSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (!Schema::hasTable('Wo_Events')) {
            $this->command->warn('Wo_Events table not found, skipping EventsSeeder.');
            return;
        }

        $owner = User::query()
            ->where('email', 'admin@ouptel.com')
            ->orWhere('admin', '1')
            ->first()
            ?: User::query()->first();

        if (!$owner) {
            $this->command->warn('No users found, skipping EventsSeeder.');
            return;
        }

        $ownerId = (string) $owner->user_id;
        $users = User::query()->where('user_id', '!=', $ownerId)->limit(12)->get();

        $today = now()->startOfDay();
        $samples = [
            [
                'name' => 'Ouptel Product Meetup 2026',
                'location' => 'Bangalore, India',
                'description' => 'Meet product builders and discuss roadmap highlights for 2026.',
                'start_date' => $today->copy()->addDays(7)->toDateString(),
                'start_time' => '10:00:00',
                'end_date' => $today->copy()->addDays(7)->toDateString(),
                'end_time' => '13:00:00',
                'cover' => 'images/placeholders/event-cover.svg',
            ],
            [
                'name' => 'UPSC Strategy Session',
                'location' => 'Delhi, India',
                'description' => 'Focused strategy and mentoring session for UPSC aspirants.',
                'start_date' => $today->copy()->toDateString(),
                'start_time' => '15:00:00',
                'end_date' => $today->copy()->addDay()->toDateString(),
                'end_time' => '18:00:00',
                'cover' => 'images/placeholders/event-cover.svg',
            ],
            [
                'name' => 'Community Health Camp',
                'location' => 'Lucknow, India',
                'description' => 'A local health awareness event with wellness talks and checkups.',
                'start_date' => $today->copy()->subDays(10)->toDateString(),
                'start_time' => '09:30:00',
                'end_date' => $today->copy()->subDays(10)->toDateString(),
                'end_time' => '12:30:00',
                'cover' => 'images/placeholders/event-cover.svg',
            ],
            [
                'name' => 'Tech Creator Roundtable',
                'location' => 'Pune, India',
                'description' => 'Creator-focused panel on social media growth and monetization.',
                'start_date' => $today->copy()->addDays(3)->toDateString(),
                'start_time' => '17:30:00',
                'end_date' => $today->copy()->addDays(3)->toDateString(),
                'end_time' => '20:00:00',
                'cover' => 'images/placeholders/event-cover.svg',
            ],
        ];

        foreach ($samples as $index => $sample) {
            $event = Event::query()->updateOrCreate(
                [
                    'name' => $sample['name'],
                    'poster_id' => $ownerId,
                ],
                [
                    'location' => $sample['location'],
                    'description' => $sample['description'],
                    'start_date' => $sample['start_date'],
                    'start_time' => $sample['start_time'],
                    'end_date' => $sample['end_date'],
                    'end_time' => $sample['end_time'],
                    'cover' => $sample['cover'],
                ]
            );

            if (Schema::hasTable('Wo_Egoing')) {
                EventGoing::query()->where('event_id', $event->id)->delete();
                EventGoing::query()->create([
                    'event_id' => $event->id,
                    'user_id' => $ownerId,
                ]);

                foreach ($users->take(min(3 + $index, max(0, $users->count()))) as $u) {
                    EventGoing::query()->firstOrCreate([
                        'event_id' => $event->id,
                        'user_id' => (string) $u->user_id,
                    ]);
                }
            }

            if (Schema::hasTable('Wo_Einterested')) {
                EventInterested::query()->where('event_id', $event->id)->delete();
                foreach ($users->skip(2)->take(min(4 + $index, max(0, $users->count()))) as $u) {
                    EventInterested::query()->firstOrCreate([
                        'event_id' => $event->id,
                        'user_id' => (string) $u->user_id,
                    ]);
                }
            }

            if (Schema::hasTable('Wo_Einvited')) {
                EventInvited::query()->where('event_id', $event->id)->delete();
                foreach ($users->take(min(5 + $index, max(0, $users->count()))) as $u) {
                    EventInvited::query()->firstOrCreate([
                        'event_id' => $event->id,
                        'inviter_id' => $ownerId,
                        'invited_id' => (string) $u->user_id,
                    ]);
                }
            }
        }

        $this->command->info('Sample events seeded successfully.');
    }
}

