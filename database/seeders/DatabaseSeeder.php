<?php

namespace Database\Seeders;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ServiceType;
use App\Models\Setting;
use App\Models\Slot;
use App\Models\StaffServiceType;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Carbon;

class DatabaseSeeder extends Seeder
{

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $data = json_decode(
            file_get_contents(database_path('seeders/seed_data.json')),
            true
        );

        // Seed branches
        foreach ($data['branches'] as $branch) {
            Branch::updateOrCreate(
                ['id' => $branch['id']],
                $branch
            );
        }

        // Seed users (nested by role: admin, branch_managers, staff, customers)
        foreach ($data['users'] as $group) {
            foreach ($group as $userData) {
                $userData['password'] = Hash::make($userData['password']);
                User::updateOrCreate(
                    ['id' => $userData['id']],
                    $userData
                );
            }
        }

        // Seed service types
        foreach ($data['service_types'] as $serviceType) {
            ServiceType::updateOrCreate(
                ['id' => $serviceType['id']],
                $serviceType
            );
        }

        // Seed staff service type assignments
        foreach ($data['staff_service_types'] as $assignment) {
            StaffServiceType::updateOrCreate(
                [
                    'staff_id' => $assignment['staff_id'],
                    'service_type_id' => $assignment['service_type_id'],
                ],
                $assignment
            );
        }

        // Seed slots in the next 3-7 days to keep sample availability fresh.
        foreach ($data['slots'] as $index => $slot) {
            $startTemplate = Carbon::parse($slot['start_at']);
            $endTemplate = Carbon::parse($slot['end_at']);
            $daysAhead = 3 + ($index % 5);
            $targetDate = now()->timezone('Asia/Muscat')->startOfDay()->addDays($daysAhead);

            $slot['start_at'] = $targetDate->copy()
                ->setTime($startTemplate->hour, $startTemplate->minute, 0)
                ->toIso8601String();

            $slot['end_at'] = $targetDate->copy()
                ->setTime($endTemplate->hour, $endTemplate->minute, 0)
                ->toIso8601String();

            Slot::updateOrCreate(
                ['id' => $slot['id']],
                $slot
            );
        }

        Setting::set('soft_delete_retention_days', '30');

        // Seed appointments
        foreach ($data['appointments'] as $appointment) {
            DB::table('appointments')->updateOrInsert(
                ['id' => $appointment['id']],
                $appointment
            );
        }

        // Seed audit logs
        foreach ($data['audit_logs'] as $log) {
            AuditLog::updateOrCreate(
                ['id' => $log['id']],
                $log
            );
        }

        $this->command->info('Database seeded successfully!');
    }
}
