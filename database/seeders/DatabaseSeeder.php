<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        DB::transaction(function (): void {
            User::query()->updateOrCreate(
                ['email' => 'admin@telemed.test'],
                ['name' => 'Telemedicine Admin', 'password' => 'password123', 'role' => UserRole::Admin],
            );

            $doctorUser = User::query()->updateOrCreate(
                ['email' => 'doctor@telemed.test'],
                ['name' => 'Dr. Jordan Lee', 'password' => 'password123', 'role' => UserRole::Doctor],
            );
            $doctorUser->doctor()->updateOrCreate(
                ['user_id' => $doctorUser->id],
                [
                    'license_number' => 'DEMO-LICENSE-001',
                    'specialty' => 'Family medicine',
                    'bio' => 'Demo physician profile for local journey testing.',
                    'is_accepting_patients' => true,
                    'is_approved' => false,
                ],
            );
            foreach (range(1, 5) as $dayOfWeek) {
                $doctorUser->doctor->availabilities()->updateOrCreate(
                    ['day_of_week' => $dayOfWeek],
                    ['start_time' => '09:00', 'end_time' => '17:00'],
                );
            }

            $patientUser = User::query()->updateOrCreate(
                ['email' => 'patient@telemed.test'],
                ['name' => 'Taylor Patient', 'password' => 'password123', 'role' => UserRole::Patient],
            );
            $patientUser->patient()->updateOrCreate(
                ['user_id' => $patientUser->id],
                ['date_of_birth' => '1990-01-15', 'phone' => '555-0100'],
            );
        });
    }
}
