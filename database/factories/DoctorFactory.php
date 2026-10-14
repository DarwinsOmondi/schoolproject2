<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Models\Doctor;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Doctor>
 */
class DoctorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory()->state(['role' => UserRole::Doctor]),
            'license_number' => fake()->unique()->bothify('LIC-####'),
            'specialty' => fake()->jobTitle(),
            'bio' => fake()->paragraph(),
            'is_accepting_patients' => true,
            'is_approved' => false,
        ];
    }
}
