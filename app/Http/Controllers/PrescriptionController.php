<?php

namespace App\Http\Controllers;

use App\Models\Prescription;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PrescriptionController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $user = $request->user();
        abort_if($user->role->value === 'doctor' && ! $user->doctor?->is_approved, 403);
        $prescriptions = match ($user->role->value) {
            'patient' => $user->patient?->prescriptions(),
            'doctor' => $user->doctor?->prescriptions(),
            default => null,
        };

        abort_unless($prescriptions !== null, 403);

        $prescriptions = $prescriptions->with(['appointment', 'doctor.user:id,name', 'patient.user:id,name'])
            ->latest()
            ->paginate(20);

        return $request->expectsJson()
            ? response()->json($prescriptions)
            : view('prescriptions.index', ['prescriptions' => $prescriptions]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $doctor = $request->user()->doctor;

        abort_unless($doctor !== null, 403, 'Doctor profile is not configured.');
        abort_unless($doctor->is_approved, 403, 'Doctor profile is awaiting administrator approval.');

        $data = $request->validate([
            'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
            'medication_name' => ['required', 'string', 'max:255'],
            'dosage' => ['required', 'string', 'max:255'],
            'frequency' => ['nullable', 'string', 'max:255'],
            'duration' => ['nullable', 'string', 'max:255'],
            'instructions' => ['nullable', 'string', 'max:5000'],
            'refills' => ['sometimes', 'integer', 'between:0,255'],
        ]);

        $appointment = $doctor->appointments()->findOrFail($data['appointment_id']);

        abort_unless($appointment->status === 'completed', 409, 'Prescriptions require a completed consultation.');

        $prescription = $appointment->prescriptions()->create([
            ...$data,
            'patient_id' => $appointment->patient_id,
            'doctor_id' => $doctor->id,
        ]);

        if ($request->expectsJson()) {
            return response()->json($prescription, 201);
        }

        return redirect()->route('doctor.dashboard')->with('status', 'Prescription issued.');
    }

    public function show(Request $request, Prescription $prescription): JsonResponse|View
    {
        $user = $request->user();
        $authorized = match ($user->role->value) {
            'patient' => $user->patient?->prescriptions()->whereKey($prescription->id)->exists(),
            'doctor' => $user->doctor?->is_approved
                && $user->doctor?->prescriptions()->whereKey($prescription->id)->exists(),
            default => false,
        };

        abort_unless($authorized, 404);

        $prescription->load(['appointment', 'doctor.user:id,name', 'patient.user:id,name']);

        return $request->expectsJson()
            ? response()->json($prescription)
            : view('prescriptions.show', ['prescription' => $prescription]);
    }

    public function download(Request $request, Prescription $prescription)
    {
        $user = $request->user();
        $authorized = match ($user->role->value) {
            'patient' => $user->patient?->prescriptions()->whereKey($prescription->id)->exists(),
            'doctor' => $user->doctor?->is_approved
                && $user->doctor?->prescriptions()->whereKey($prescription->id)->exists(),
            default => false,
        };

        abort_unless($authorized, 404);
        $prescription->load(['doctor.user:id,name', 'patient.user:id,name']);

        $contents = implode(PHP_EOL, [
            'Prescription',
            'Patient: '.$prescription->patient->user->name,
            'Prescribing doctor: '.$prescription->doctor->user->name,
            'Medication: '.$prescription->medication_name,
            'Dosage: '.$prescription->dosage,
            'Frequency: '.($prescription->frequency ?: 'Not specified'),
            'Duration: '.($prescription->duration ?: 'Not specified'),
            'Refills: '.$prescription->refills,
            'Instructions: '.($prescription->instructions ?: 'None'),
            'Issued: '.$prescription->created_at->toDateString(),
        ]);

        return response()->streamDownload(
            static function () use ($contents): void {
                echo $contents;
            },
            'prescription-'.$prescription->id.'.txt',
            ['Content-Type' => 'text/plain; charset=UTF-8'],
        );
    }
}
