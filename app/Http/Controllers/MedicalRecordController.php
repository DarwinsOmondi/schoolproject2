<?php

namespace App\Http\Controllers;

use App\Models\MedicalRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class MedicalRecordController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $user = $request->user();
        abort_if($user->role->value === 'doctor' && ! $user->doctor?->is_approved, 403);
        $records = match ($user->role->value) {
            'patient' => $user->patient?->medicalRecords(),
            'doctor' => $user->doctor?->medicalRecords(),
            default => null,
        };

        abort_unless($records !== null, 403);

        $records = $records->with(['doctor.user:id,name', 'appointment'])
            ->latest()
            ->paginate(20);

        return $request->expectsJson()
            ? response()->json($records)
            : view('medical-records.index', ['records' => $records]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $doctor = $request->user()->doctor;

        abort_unless($doctor !== null, 403, 'Doctor profile is not configured.');
        abort_unless($doctor->is_approved, 403, 'Doctor profile is awaiting administrator approval.');

        $data = $request->validate([
            'patient_id' => ['required', 'integer', 'exists:patients,id'],
            'appointment_id' => ['required', 'integer', 'exists:appointments,id'],
            'record_type' => ['required', Rule::in(['consultation', 'medical_history', 'condition', 'allergy', 'other'])],
            'title' => ['required', 'string', 'max:255'],
            'content' => ['required', 'string', 'max:100000'],
        ]);

        $appointment = $doctor->appointments()
            ->whereKey($data['appointment_id'])
            ->where('patient_id', $data['patient_id'])
            ->where('status', 'completed')
            ->first();

        if ($appointment === null) {
            throw ValidationException::withMessages([
                'appointment_id' => 'Clinical notes must be attached to a completed consultation for this patient.',
            ]);
        }

        $record = $doctor->medicalRecords()->create($data);

        if ($request->expectsJson()) {
            return response()->json($record, 201);
        }

        return redirect()->route('doctor.dashboard')->with('status', 'Clinical notes saved.');
    }

    public function show(Request $request, MedicalRecord $medicalRecord): JsonResponse|View
    {
        $user = $request->user();
        $authorized = match ($user->role->value) {
            'patient' => $user->patient?->medicalRecords()->whereKey($medicalRecord->id)->exists(),
            'doctor' => $user->doctor?->is_approved
                && $user->doctor?->medicalRecords()->whereKey($medicalRecord->id)->exists(),
            default => false,
        };

        abort_unless($authorized, 404);

        $medicalRecord->load(['doctor.user:id,name', 'appointment']);

        return $request->expectsJson()
            ? response()->json($medicalRecord)
            : view('medical-records.show', ['record' => $medicalRecord]);
    }
}
