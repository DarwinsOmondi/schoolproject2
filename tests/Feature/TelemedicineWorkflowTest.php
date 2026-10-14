<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\Appointment;
use App\Models\Doctor;
use App\Models\DoctorAvailability;
use App\Models\Patient;
use App\Models\User;
use App\Notifications\AppointmentActivityNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class TelemedicineWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_patient_books_a_published_slot_and_doctor_receives_an_in_app_notification(): void
    {
        [$patientUser, $patient] = $this->createPatient();
        [$doctorUser, $doctor] = $this->createDoctor();
        $slot = $this->futureSlot();

        $this->actingAs($patientUser)
            ->getJson(route('patient.doctors.availability', [
                'doctor' => $doctor,
                'date' => $slot->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('timezone', 'UTC')
            ->assertJsonPath('slots', [
                $slot->format('Y-m-d\TH:i'),
                $slot->copy()->addMinutes(30)->format('Y-m-d\TH:i'),
            ]);

        $bookingResponse = $this->actingAs($patientUser)
            ->postJson(route('patient.appointments.store'), [
                'doctor_id' => $doctor->id,
                'scheduled_at' => $slot->format('Y-m-d\TH:i'),
                'reason' => 'Follow-up',
            ]);

        $bookingResponse
            ->assertCreated()
            ->assertJsonPath('status', 'pending')
            ->assertJsonMissingPath('meeting_url');

        $this->assertDatabaseHas('notifications', [
            'notifiable_id' => $doctorUser->id,
            'notifiable_type' => User::class,
        ]);
        $this->assertSame(1, $doctorUser->unreadNotifications()->count());
        $this->assertSame(1, $patient->appointments()->count());
        $this->assertStringStartsWith(
            'https://meet.jit.si/',
            $patient->appointments()->firstOrFail()->meeting_url,
        );
    }

    public function test_patient_cannot_book_outside_the_doctors_published_hours(): void
    {
        [$patientUser] = $this->createPatient();
        [, $doctor] = $this->createDoctor();
        $outsideHours = $this->futureSlot('09:30');

        $this->actingAs($patientUser)
            ->postJson(route('patient.appointments.store'), [
                'doctor_id' => $doctor->id,
                'scheduled_at' => $outsideHours->format('Y-m-d\TH:i'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scheduled_at');

        $this->assertDatabaseCount('appointments', 0);
    }

    public function test_patient_cannot_book_a_time_that_overlaps_an_existing_appointment(): void
    {
        [$patientUser, $patient] = $this->createPatient();
        [, $doctor] = $this->createDoctor();
        $slot = $this->futureSlot();
        $patient->appointments()->create([
            'doctor_id' => $doctor->id,
            'scheduled_at' => $slot->copy()->addMinutes(15),
            'status' => 'approved',
        ]);

        $this->actingAs($patientUser)
            ->postJson(route('patient.appointments.store'), [
                'doctor_id' => $doctor->id,
                'scheduled_at' => $slot->format('Y-m-d\TH:i'),
            ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('scheduled_at');

        $this->assertDatabaseCount('appointments', 1);
    }

    public function test_patient_can_cancel_an_upcoming_appointment_and_the_doctor_is_notified(): void
    {
        [$patientUser, $patient] = $this->createPatient();
        [$doctorUser, $doctor] = $this->createDoctor();
        $appointment = $this->createAppointment($patient, $doctor, 'approved');

        $this->actingAs($patientUser)
            ->patch(route('patient.appointments.cancel', $appointment))
            ->assertRedirect(route('patient.appointments.index'));

        $this->assertSame('cancelled', $appointment->refresh()->status);
        $this->assertSame(1, $doctorUser->unreadNotifications()->count());
        $this->assertSame(
            'An appointment was cancelled by the other participant.',
            $doctorUser->unreadNotifications()->firstOrFail()->data['message'],
        );
    }

    public function test_patient_cannot_cancel_another_patients_appointment(): void
    {
        [, $patient] = $this->createPatient();
        [$otherPatientUser] = $this->createPatient();
        [, $doctor] = $this->createDoctor();
        $appointment = $this->createAppointment($patient, $doctor, 'approved');

        $this->actingAs($otherPatientUser)
            ->patch(route('patient.appointments.cancel', $appointment))
            ->assertNotFound();

        $this->assertSame('approved', $appointment->refresh()->status);
    }

    public function test_doctor_can_cancel_an_upcoming_appointment_and_the_patient_is_notified(): void
    {
        [$patientUser, $patient] = $this->createPatient();
        [$doctorUser, $doctor] = $this->createDoctor();
        $appointment = $this->createAppointment($patient, $doctor, 'pending');

        $this->actingAs($doctorUser)
            ->patch(route('doctor.appointments.cancel', $appointment))
            ->assertRedirect(route('doctor.appointments.index'));

        $this->assertSame('cancelled', $appointment->refresh()->status);
        $this->assertSame(1, $patientUser->unreadNotifications()->count());
    }

    public function test_doctor_can_reschedule_an_appointment_to_an_open_slot(): void
    {
        [$patientUser, $patient] = $this->createPatient();
        [$doctorUser, $doctor] = $this->createDoctor();
        $appointment = $this->createAppointment($patient, $doctor, 'pending');
        $newSlot = $appointment->scheduled_at->copy()->addMinutes(30);

        $this->actingAs($doctorUser)
            ->getJson(route('doctor.appointments.available-slots', [
                'appointment' => $appointment,
                'date' => $newSlot->toDateString(),
            ]))
            ->assertOk()
            ->assertJsonPath('slots', [
                $appointment->scheduled_at->format('Y-m-d\TH:i'),
                $newSlot->format('Y-m-d\TH:i'),
            ]);

        $this->actingAs($doctorUser)
            ->patchJson(route('doctor.appointments.update', $appointment), [
                'status' => 'rescheduled',
                'scheduled_at' => $newSlot->format('Y-m-d\TH:i'),
            ])
            ->assertOk()
            ->assertJsonPath('status', 'rescheduled');

        $this->assertSame(
            $newSlot->format('Y-m-d H:i:s'),
            $appointment->refresh()->scheduled_at->format('Y-m-d H:i:s'),
        );
        $this->assertSame(1, $patientUser->unreadNotifications()->count());
    }

    public function test_cannot_cancel_an_appointment_after_its_start_time(): void
    {
        $this->travelTo('2026-12-01 10:00:00');
        [$patientUser, $patient] = $this->createPatient();
        [, $doctor] = $this->createDoctor();
        $appointment = $patient->appointments()->create([
            'doctor_id' => $doctor->id,
            'scheduled_at' => now()->subMinute(),
            'status' => 'approved',
        ]);

        $this->actingAs($patientUser)
            ->patch(route('patient.appointments.cancel', $appointment))
            ->assertStatus(409);

        $this->assertSame('approved', $appointment->refresh()->status);
    }

    public function test_doctor_cannot_remove_hours_that_include_an_existing_future_appointment(): void
    {
        [, $patient] = $this->createPatient();
        [$doctorUser, $doctor] = $this->createDoctor();
        $appointment = $this->createAppointment($patient, $doctor, 'approved');
        $availability = array_fill_keys(range(0, 6), ['start_time' => '', 'end_time' => '']);
        $availability[$appointment->scheduled_at->dayOfWeek] = [
            'start_time' => '11:00',
            'end_time' => '12:00',
        ];

        $this->actingAs($doctorUser)
            ->put(route('doctor.availability.update'), ['availability' => $availability])
            ->assertSessionHasErrors('availability');

        $this->assertSame(7, $doctor->availabilities()->count());
        $this->assertDatabaseHas('doctor_availabilities', [
            'doctor_id' => $doctor->id,
            'day_of_week' => $appointment->scheduled_at->dayOfWeek,
            'start_time' => '10:00',
            'end_time' => '11:00',
        ]);
    }

    public function test_patient_can_update_their_profile_and_encrypted_contact_details(): void
    {
        [$patientUser, $patient] = $this->createPatient();

        $this->actingAs($patientUser)
            ->put(route('profile.update'), [
                'name' => 'Updated Patient',
                'email' => $patientUser->email,
                'date_of_birth' => '1990-01-01',
                'phone' => '555-0199',
                'address' => '10 Main Street',
            ])
            ->assertRedirect(route('profile.edit'));

        $this->assertSame('Updated Patient', $patientUser->refresh()->name);
        $this->assertSame('555-0199', $patient->refresh()->phone);
        $this->assertNotSame('555-0199', $patient->getRawOriginal('phone'));
        $this->assertSame('10 Main Street', $patient->address);
    }

    public function test_patient_profile_page_renders_editable_contact_fields(): void
    {
        [$patientUser] = $this->createPatient();

        $this->actingAs($patientUser)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Profile')
            ->assertSee('Phone')
            ->assertSee('Address');
    }

    public function test_doctor_profile_page_renders_weekly_availability(): void
    {
        [$doctorUser] = $this->createDoctor();

        $this->actingAs($doctorUser)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertSee('Weekly appointment hours')
            ->assertSee('10:00');
    }

    public function test_users_cannot_read_another_users_notification(): void
    {
        [$owner] = $this->createPatient();
        [$otherUser] = $this->createPatient();
        $owner->notify(new AppointmentActivityNotification(
            'Your appointment changed.',
            route('patient.appointments.index'),
        ));
        $notification = $owner->notifications()->firstOrFail();

        $this->actingAs($otherUser)
            ->patch(route('notifications.read', $notification->id))
            ->assertNotFound();

        $this->assertNull($notification->fresh()->read_at);
    }

    public function test_user_can_mark_all_their_own_notifications_as_read(): void
    {
        [$user] = $this->createPatient();
        $user->notify(new AppointmentActivityNotification('First update.', route('patient.appointments.index')));
        $user->notify(new AppointmentActivityNotification('Second update.', route('patient.appointments.index')));

        $this->actingAs($user)
            ->patch(route('notifications.read-all'))
            ->assertRedirect();

        $this->assertSame(0, $user->unreadNotifications()->count());
    }

    public function test_user_can_view_their_in_app_notifications(): void
    {
        [$user] = $this->createPatient();
        $user->notify(new AppointmentActivityNotification(
            'Your appointment was rescheduled.',
            route('patient.appointments.index'),
        ));

        $this->actingAs($user)
            ->get(route('notifications.index'))
            ->assertOk()
            ->assertSee('Your appointment was rescheduled.')
            ->assertSee('Mark all as read');
    }

    public function test_video_page_uses_jitsi_iframe_api_and_warns_that_public_video_is_demo_only(): void
    {
        [$patientUser, $patient] = $this->createPatient();
        [, $doctor] = $this->createDoctor();
        $appointment = $this->createAppointment($patient, $doctor, 'approved');

        $this->actingAs($patientUser)
            ->get(route('appointments.video', $appointment))
            ->assertOk()
            ->assertSee('external_api.js')
            ->assertSee('Join video call')
            ->assertSee('not configured as a HIPAA-compliant service');

        $this->assertStringStartsWith('https://meet.jit.si/', $appointment->refresh()->meeting_url);
        $this->assertNotSame(
            $appointment->meeting_url,
            $appointment->getRawOriginal('meeting_url'),
        );
    }

    /**
     * @return array{User, Patient}
     */
    private function createPatient(): array
    {
        $user = User::factory()->create(['role' => UserRole::Patient]);
        $patient = Patient::query()->create(['user_id' => $user->id]);

        return [$user, $patient];
    }

    /**
     * @return array{User, Doctor}
     */
    private function createDoctor(): array
    {
        $doctor = Doctor::factory()->create(['is_approved' => true]);

        foreach (range(0, 6) as $dayOfWeek) {
            DoctorAvailability::query()->create([
                'doctor_id' => $doctor->id,
                'day_of_week' => $dayOfWeek,
                'start_time' => '10:00',
                'end_time' => '11:00',
            ]);
        }

        return [$doctor->user, $doctor];
    }

    private function futureSlot(string $time = '10:00'): Carbon
    {
        return now()->addDay()->startOfDay()->setTimeFromTimeString($time);
    }

    private function createAppointment(Patient $patient, Doctor $doctor, string $status): Appointment
    {
        return $patient->appointments()->create([
            'doctor_id' => $doctor->id,
            'scheduled_at' => $this->futureSlot(),
            'status' => $status,
        ]);
    }
}
