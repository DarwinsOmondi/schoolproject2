<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorAvailability;
use App\Models\Patient;
use App\Models\User;
use Database\Seeders\DatabaseSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class TelemedicineAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_redirects_each_role_to_its_dashboard(): void
    {
        $user = User::factory()->create(['role' => UserRole::Doctor]);
        $user->doctor()->create([
            'license_number' => fake()->unique()->bothify('LIC-####'),
            'specialty' => 'General practice',
        ]);

        $this->get('/login')->assertOk()->assertSee('Sign in');

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect(route('doctor.dashboard'));

        $this->get(route('doctor.dashboard'))->assertOk()->assertSee('Doctor dashboard');
    }

    public function test_patient_books_consultation_and_doctor_issues_prescription_after_completion(): void
    {
        [$patientUser, $patient] = $this->createPatient();
        [, $doctor] = $this->createDoctor();

        $this->actingAs($patientUser)
            ->postJson('/patient/appointments', [
                'doctor_id' => $doctor->id,
                'scheduled_at' => now()->addDays(2)->startOfDay()->setTime(10, 0)->format('Y-m-d H:i:s'),
                'reason' => 'Follow-up',
            ])
            ->assertCreated()
            ->assertJsonPath('status', 'pending');

        $appointment = Appointment::query()->firstOrFail();
        $doctorUser = $doctor->user;

        $this->actingAs($doctorUser)
            ->patchJson("/doctor/appointments/{$appointment->id}", [
                'status' => 'approved',
            ])
            ->assertOk()
            ->assertJsonPath('status', 'approved');

        $this->get("/doctor/appointments/{$appointment->id}/video")
            ->assertOk()
            ->assertSee('external_api.js')
            ->assertSee('not configured as a HIPAA-compliant service');

        $this->actingAs($doctorUser)
            ->patchJson("/doctor/appointments/{$appointment->id}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('status', 'completed');

        $this->postJson('/doctor/prescriptions', [
            'appointment_id' => $appointment->id,
            'medication_name' => 'Medication A',
            'dosage' => '10 mg',
            'instructions' => 'Take as directed.',
        ])->assertCreated()
            ->assertJsonPath('patient_id', $patient->id)
            ->assertJsonPath('doctor_id', $doctor->id);

        $this->actingAs($patientUser)
            ->getJson('/patient/prescriptions')
            ->assertOk()
            ->assertJsonPath('data.0.medication_name', 'Medication A');
    }

    public function test_users_cannot_read_another_patients_appointment_or_medical_record(): void
    {
        [$patientUser, $patient] = $this->createPatient();
        [, $doctor] = $this->createDoctor();
        [, $otherPatient] = $this->createPatient();
        $appointment = $patient->appointments()->create([
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->addDays(2),
            'status' => 'completed',
        ]);
        $record = $doctor->medicalRecords()->create([
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'record_type' => 'consultation',
            'title' => 'Consultation notes',
            'content' => 'Private clinical notes.',
        ]);
        $this->assertNotSame('Private clinical notes.', $record->getRawOriginal('content'));

        $this->actingAs($patientUser)
            ->getJson("/patient/medical-records/{$record->id}")
            ->assertOk()
            ->assertJsonPath('content', 'Private clinical notes.');

        $this->actingAs($otherPatient->user)
            ->getJson("/patient/appointments/{$appointment->id}")
            ->assertNotFound();

        $this->getJson("/patient/medical-records/{$record->id}")->assertNotFound();
        $this->get(route('patient.appointments.messages.index', $appointment))->assertNotFound();
    }

    public function test_patient_cannot_access_admin_routes(): void
    {
        [$patientUser] = $this->createPatient();

        $this->actingAs($patientUser)
            ->getJson('/admin/users')
            ->assertForbidden();
    }

    public function test_admin_can_create_a_user_and_role_profile(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);

        $this->actingAs($admin)
            ->postJson('/admin/users', [
                'name' => 'New patient',
                'email' => 'patient@example.test',
                'password' => 'a-secure-password',
                'role' => 'patient',
                'date_of_birth' => '1990-01-01',
            ])
            ->assertCreated()
            ->assertJsonPath('role', 'patient');

        $this->assertDatabaseHas('patients', [
            'user_id' => User::query()->where('email', 'patient@example.test')->value('id'),
        ]);
        $this->assertSame(
            '1990-01-01',
            Patient::query()
                ->whereHas('user', fn ($query) => $query->where('email', 'patient@example.test'))
                ->firstOrFail()
                ->date_of_birth
                ->toDateString(),
        );
    }

    public function test_doctor_cannot_reschedule_an_appointment_into_an_occupied_slot(): void
    {
        [, $patient] = $this->createPatient();
        [$doctorUser, $doctor] = $this->createDoctor();
        $bookedAt = now()->addDays(2)->startOfDay()->setTime(10, 0);
        $appointment = $patient->appointments()->create([
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->addDays(1),
            'status' => 'pending',
        ]);
        Appointment::query()->create([
            'patient_id' => $patient->id,
            'doctor_id' => $doctor->id,
            'scheduled_at' => $bookedAt,
            'status' => 'approved',
        ]);

        $this->actingAs($doctorUser)
            ->patchJson("/doctor/appointments/{$appointment->id}", [
                'status' => 'rescheduled',
                'scheduled_at' => $bookedAt->format('Y-m-d\TH:i'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scheduled_at');
    }

    public function test_demo_seeder_creates_the_three_reusable_login_accounts(): void
    {
        Artisan::call('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--no-interaction' => true,
        ]);
        Artisan::call('db:seed', [
            '--class' => DatabaseSeeder::class,
            '--no-interaction' => true,
        ]);

        foreach ([
            'admin@telemed.test' => UserRole::Admin,
            'doctor@telemed.test' => UserRole::Doctor,
            'patient@telemed.test' => UserRole::Patient,
        ] as $email => $role) {
            $user = User::query()->where('email', $email)->firstOrFail();
            $this->assertSame($role, $user->role);
            $this->assertTrue(Hash::check('password123', $user->password));
        }

        $this->assertSame(3, User::query()->count());
        $this->assertFalse(User::query()->where('email', 'doctor@telemed.test')->firstOrFail()->doctor->is_approved);
    }

    public function test_browser_journey_admin_approves_doctor_patient_books_and_messages_then_doctor_completes_visit(): void
    {
        $admin = User::factory()->create(['role' => UserRole::Admin]);
        [$doctorUser, $doctor] = $this->createDoctor(approved: false);
        [$patientUser, $patient] = $this->createPatient();

        $this->actingAs($admin)
            ->get('/admin/dashboard')
            ->assertOk()
            ->assertSee('Approve doctor');

        $this->patch(route('admin.doctors.approve', $doctor))
            ->assertRedirect(route('admin.dashboard'));
        $this->assertTrue($doctor->refresh()->is_approved);

        $this->actingAs($patientUser)
            ->get('/patient/dashboard')
            ->assertOk()
            ->assertSee('Request an appointment')
            ->assertSee($doctorUser->name);

        $this->post(route('patient.appointments.store'), [
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->addDays(2)->startOfDay()->setTime(10, 0)->format('Y-m-d\TH:i'),
            'reason' => 'Follow-up consultation',
        ])->assertRedirect(route('patient.dashboard'));

        $appointment = Appointment::query()->firstOrFail();
        $this->assertSame('pending', $appointment->status);

        $this->actingAs($doctorUser)
            ->patch(route('doctor.appointments.update', $appointment), [
                'status' => 'approved',
            ])->assertRedirect(route('doctor.dashboard'));

        $this->actingAs($patientUser)
            ->get(route('appointments.video', $appointment))
            ->assertOk()
            ->assertSee('Mute microphone')
            ->assertSee('Turn camera off')
            ->assertSee('external_api.js');

        $this->actingAs($patientUser)
            ->post(route('patient.appointments.messages.store', $appointment), [
                'body' => 'I will join at the scheduled time.',
            ])
            ->assertRedirect(route('patient.appointments.messages.index', $appointment));

        $message = $appointment->messages()->firstOrFail();
        $this->assertNotSame('I will join at the scheduled time.', $message->getRawOriginal('body'));
        $this->assertSame(2, $doctorUser->unreadNotifications()->count());

        $this->actingAs($doctorUser)
            ->get(route('doctor.appointments.messages.index', $appointment))
            ->assertOk()
            ->assertSee('I will join at the scheduled time.');

        $this->patch(route('doctor.appointments.update', $appointment), [
            'status' => 'completed',
        ])->assertRedirect(route('doctor.dashboard'));

        $this->post(route('doctor.medical-records.store'), [
            'patient_id' => $patient->id,
            'appointment_id' => $appointment->id,
            'record_type' => 'consultation',
            'title' => 'Consultation notes',
            'content' => 'Patient reports feeling well.',
        ])->assertRedirect(route('doctor.dashboard'));

        $this->post(route('doctor.prescriptions.store'), [
            'appointment_id' => $appointment->id,
            'medication_name' => 'Demo medication',
            'dosage' => '10 mg',
            'frequency' => 'Once daily',
            'duration' => '7 days',
        ])->assertRedirect(route('doctor.dashboard'));

        $prescription = $patient->prescriptions()->firstOrFail();
        $this->actingAs($patientUser)
            ->get(route('patient.prescriptions.download', $prescription))
            ->assertDownload('prescription-'.$prescription->id.'.txt');
        $this->get(route('patient.medical-records.index'))
            ->assertOk()
            ->assertSee('Consultation notes');
        $this->get(route('patient.dashboard'))
            ->assertOk()
            ->assertSee('Demo medication')
            ->assertSee('Follow-up consultation');
    }

    public function test_unapproved_doctor_cannot_access_consultation_records(): void
    {
        [$doctorUser] = $this->createDoctor(approved: false);
        $patientUser = User::factory()->create([
            'name' => 'Private Patient Name',
            'role' => UserRole::Patient,
        ]);
        $patient = Patient::query()->create(['user_id' => $patientUser->id]);
        $patient->appointments()->create([
            'doctor_id' => $doctorUser->doctor->id,
            'scheduled_at' => now()->addDays(1),
            'status' => 'approved',
        ]);

        $this->actingAs($doctorUser)
            ->get('/doctor/appointments')
            ->assertForbidden();

        $this->get('/doctor/dashboard')
            ->assertOk()
            ->assertSee('awaiting administrator approval')
            ->assertDontSee('Private Patient Name');
    }

    private function createPatient(): array
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);
        $patient = Patient::query()->create(['user_id' => $user->id]);

        return [$user, $patient];
    }

    private function createDoctor(bool $approved = true): array
    {
        $user = User::factory()->create(['role' => UserRole::Doctor]);
        $doctor = Doctor::query()->create([
            'user_id' => $user->id,
            'license_number' => fake()->unique()->bothify('LIC-####'),
            'specialty' => 'General practice',
            'is_approved' => $approved,
        ]);
        foreach (range(0, 6) as $dayOfWeek) {
            DoctorAvailability::query()->create([
                'doctor_id' => $doctor->id,
                'day_of_week' => $dayOfWeek,
                'start_time' => '09:00',
                'end_time' => '17:00',
            ]);
        }

        return [$user, $doctor];
    }
}
