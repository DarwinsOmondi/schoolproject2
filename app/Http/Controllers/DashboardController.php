<?php

namespace App\Http\Controllers;

use App\Models\Doctor;
use App\Models\Patient;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function patient(Request $request): View
    {
        $patient = $request->user()->patient;

        abort_unless($patient !== null, 403, 'Patient profile is not configured.');

        $today = now();

        return view('dashboard.patient', [
            'user' => $request->user(),
            'today' => $today,
            'appointments' => $patient->appointments()
                ->with(['doctor.user:id,name'])
                ->withCount('messages')
                ->orderByRaw("CASE WHEN status IN ('pending', 'approved', 'rescheduled') AND scheduled_at >= ? THEN 0 ELSE 1 END", [$today])
                ->orderBy('scheduled_at')
                ->get(),
            'upcomingAppointmentsCount' => $patient->appointments()
                ->whereIn('status', ['pending', 'approved', 'rescheduled'])
                ->where('scheduled_at', '>=', $today)
                ->count(),
            'medicalRecordCount' => $patient->medicalRecords()->count(),
            'prescriptionCount' => $patient->prescriptions()->count(),
            'doctors' => Doctor::query()
                ->where('is_approved', true)
                ->where('is_accepting_patients', true)
                ->with('user:id,name')
                ->orderBy('specialty')
                ->get(),
            'medicalRecords' => $patient->medicalRecords()
                ->with('doctor.user:id,name')
                ->latest()
                ->limit(4)
                ->get(),
            'prescriptions' => $patient->prescriptions()
                ->with('doctor.user:id,name')
                ->latest()
                ->limit(3)
                ->get(),
        ]);
    }

    public function doctor(Request $request): View
    {
        $doctor = $request->user()->doctor;

        abort_unless($doctor !== null, 403, 'Doctor profile is not configured.');

        $today = now();

        return view('dashboard.doctor', [
            'user' => $request->user(),
            'doctor' => $doctor,
            'today' => $today,
            'appointments' => $doctor->is_approved
                ? $doctor->appointments()
                    ->with(['patient.user:id,name'])
                    ->withCount('messages')
                    ->orderByRaw("CASE WHEN status IN ('pending', 'approved', 'rescheduled') AND scheduled_at >= ? THEN 0 ELSE 1 END", [$today])
                    ->orderBy('scheduled_at')
                    ->get()
                : collect(),
            'pendingAppointmentCount' => $doctor->appointments()->where('status', 'pending')->count(),
            'upcomingAppointmentsCount' => $doctor->appointments()
                ->whereIn('status', ['pending', 'approved', 'rescheduled'])
                ->where('scheduled_at', '>=', $today)
                ->count(),
        ]);
    }

    public function admin(Request $request): View
    {
        return view('dashboard.admin', [
            'user' => $request->user(),
            'users' => User::query()
                ->select(['id', 'name', 'email', 'role', 'created_at'])
                ->with([
                    'doctor:id,user_id,is_approved',
                    'patient:id,user_id',
                ])
                ->latest()
                ->get(),
            'doctorsAwaitingApproval' => Doctor::query()
                ->where('is_approved', false)
                ->with('user:id,name,email')
                ->latest()
                ->get(),
            'patientsCount' => Patient::query()->count(),
            'doctorsCount' => Doctor::query()->count(),
        ]);
    }
}
