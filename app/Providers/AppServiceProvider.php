<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\Appointment;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;
use Illuminate\View\View as ViewInstance;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        View::composer('layouts.app', function (ViewInstance $view): void {
            $user = auth()->user();

            $view->with(
                'unreadNotificationCount',
                $user?->unreadNotifications()->count() ?? 0,
            );

            $view->with(
                'pendingAppointmentCount',
                $user?->role === UserRole::Doctor
                    ? Appointment::query()
                        ->where('doctor_id', $user->doctor?->id)
                        ->where('status', 'pending')
                        ->count()
                    : 0,
            );
        });
    }
}
