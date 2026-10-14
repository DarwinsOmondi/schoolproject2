<?php

use App\Http\Controllers\Admin\DoctorApprovalController;
use App\Http\Controllers\Admin\UserManagementController;
use App\Http\Controllers\AppointmentCancellationController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DoctorAvailabilityController;
use App\Http\Controllers\MedicalRecordController;
use App\Http\Controllers\MessageController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\PrescriptionController;
use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    if (! auth()->check()) {
        return redirect()->route('login');
    }

    return redirect()->route(auth()->user()->role->value.'.dashboard');
});

Route::get('/login', [AuthenticatedSessionController::class, 'create'])->name('login');
Route::post('/login', [AuthenticatedSessionController::class, 'store'])
    ->middleware('throttle:5,1')
    ->name('login.store');
Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])
    ->middleware('auth')
    ->name('logout');

Route::middleware('auth')->group(function (): void {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::put('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::patch('/notifications/read-all', [NotificationController::class, 'markAllAsRead'])->name('notifications.read-all');
    Route::patch('/notifications/{notification}', [NotificationController::class, 'markAsRead'])->name('notifications.read');
});

Route::middleware(['auth', 'role:patient'])
    ->prefix('patient')
    ->name('patient.')
    ->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'patient'])->name('dashboard');
        Route::get('/appointments', [AppointmentController::class, 'index'])->name('appointments.index');
        Route::post('/appointments', [AppointmentController::class, 'store'])->name('appointments.store');
        Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])->name('appointments.show');
        Route::patch('/appointments/{appointment}/cancel', [AppointmentCancellationController::class, 'update'])->name('appointments.cancel');
        Route::get('/appointments/{appointment}/video', [AppointmentController::class, 'video'])->name('appointments.video');
        Route::get('/appointments/{appointment}/messages', [MessageController::class, 'index'])->name('appointments.messages.index');
        Route::post('/appointments/{appointment}/messages', [MessageController::class, 'store'])->name('appointments.messages.store');
        Route::get('/prescriptions', [PrescriptionController::class, 'index'])->name('prescriptions.index');
        Route::get('/prescriptions/{prescription}', [PrescriptionController::class, 'show'])->name('prescriptions.show');
        Route::get('/prescriptions/{prescription}/download', [PrescriptionController::class, 'download'])->name('prescriptions.download');
        Route::get('/medical-records', [MedicalRecordController::class, 'index'])->name('medical-records.index');
        Route::get('/medical-records/{medicalRecord}', [MedicalRecordController::class, 'show'])->name('medical-records.show');
        Route::get('/doctors/{doctor}/availability', [DoctorAvailabilityController::class, 'slots'])->name('doctors.availability');
    });

Route::middleware(['auth', 'role:doctor'])
    ->prefix('doctor')
    ->name('doctor.')
    ->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'doctor'])->name('dashboard');
        Route::put('/availability', [DoctorAvailabilityController::class, 'update'])->name('availability.update');
        Route::get('/appointments', [AppointmentController::class, 'index'])->middleware('doctor.approved')->name('appointments.index');
        Route::get('/appointments/{appointment}', [AppointmentController::class, 'show'])->middleware('doctor.approved')->name('appointments.show');
        Route::get('/appointments/{appointment}/available-slots', [DoctorAvailabilityController::class, 'appointmentSlots'])->middleware('doctor.approved')->name('appointments.available-slots');
        Route::patch('/appointments/{appointment}/cancel', [AppointmentCancellationController::class, 'update'])->middleware('doctor.approved')->name('appointments.cancel');
        Route::get('/appointments/{appointment}/video', [AppointmentController::class, 'video'])->middleware('doctor.approved')->name('appointments.video');
        Route::patch('/appointments/{appointment}', [AppointmentController::class, 'update'])->middleware('doctor.approved')->name('appointments.update');
        Route::get('/appointments/{appointment}/messages', [MessageController::class, 'index'])->middleware('doctor.approved')->name('appointments.messages.index');
        Route::post('/appointments/{appointment}/messages', [MessageController::class, 'store'])->middleware('doctor.approved')->name('appointments.messages.store');
        Route::get('/prescriptions', [PrescriptionController::class, 'index'])->middleware('doctor.approved')->name('prescriptions.index');
        Route::post('/prescriptions', [PrescriptionController::class, 'store'])->middleware('doctor.approved')->name('prescriptions.store');
        Route::get('/prescriptions/{prescription}', [PrescriptionController::class, 'show'])->middleware('doctor.approved')->name('prescriptions.show');
        Route::get('/prescriptions/{prescription}/download', [PrescriptionController::class, 'download'])->middleware('doctor.approved')->name('prescriptions.download');
        Route::get('/medical-records', [MedicalRecordController::class, 'index'])->middleware('doctor.approved')->name('medical-records.index');
        Route::post('/medical-records', [MedicalRecordController::class, 'store'])->middleware('doctor.approved')->name('medical-records.store');
        Route::get('/medical-records/{medicalRecord}', [MedicalRecordController::class, 'show'])->middleware('doctor.approved')->name('medical-records.show');
    });

Route::middleware(['auth', 'role:admin'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function (): void {
        Route::get('/dashboard', [DashboardController::class, 'admin'])->name('dashboard');
        Route::get('/users', [UserManagementController::class, 'index'])->name('users.index');
        Route::post('/users', [UserManagementController::class, 'store'])->name('users.store');
        Route::patch('/doctors/{doctor}/approve', [DoctorApprovalController::class, 'update'])->name('doctors.approve');
    });

Route::middleware(['auth', 'role:patient,doctor'])
    ->get('/appointments/{appointment}/video', [AppointmentController::class, 'video'])
    ->middleware('doctor.approved')
    ->name('appointments.video');
