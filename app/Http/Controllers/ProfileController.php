<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();

        return view('profiles.edit', [
            'user' => $user,
            'profile' => $user->role === UserRole::Patient ? $user->patient : $user->doctor,
            'availability' => $user->doctor?->availabilities()->get()->keyBy('day_of_week') ?? collect(),
            'days' => [
                0 => 'Sunday',
                1 => 'Monday',
                2 => 'Tuesday',
                3 => 'Wednesday',
                4 => 'Thursday',
                5 => 'Friday',
                6 => 'Saturday',
            ],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $rules = [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user->id)],
        ];

        if ($user->role === UserRole::Patient) {
            $rules += [
                'date_of_birth' => ['nullable', 'date', 'before:today'],
                'phone' => ['nullable', 'string', 'max:32'],
                'address' => ['nullable', 'string', 'max:2000'],
            ];
        } elseif ($user->role === UserRole::Doctor) {
            $rules += [
                'specialty' => ['required', 'string', 'max:255'],
                'bio' => ['nullable', 'string', 'max:5000'],
                'is_accepting_patients' => ['required', 'boolean'],
            ];
        }

        $data = $request->validate($rules);

        DB::transaction(function () use ($user, $data, $request): void {
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
            ]);

            if ($user->role === UserRole::Patient) {
                $user->patient?->update([
                    'date_of_birth' => $data['date_of_birth'] ?? null,
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                ]);
            } elseif ($user->role === UserRole::Doctor) {
                $user->doctor?->update([
                    'specialty' => $data['specialty'],
                    'bio' => $data['bio'] ?? null,
                    'is_accepting_patients' => $request->boolean('is_accepting_patients'),
                ]);
            }
        });

        return redirect()->route('profile.edit')->with('status', 'Profile updated.');
    }
}
