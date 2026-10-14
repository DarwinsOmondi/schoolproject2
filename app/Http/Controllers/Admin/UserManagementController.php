<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class UserManagementController extends Controller
{
    public function index(Request $request): JsonResponse|View
    {
        $users = User::query()
            ->select(['id', 'name', 'email', 'role', 'created_at'])
            ->latest()
            ->paginate(20);

        return $request->expectsJson()
            ? response()->json($users)
            : view('admin.users.index', ['users' => $users]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'min:12'],
            'role' => ['required', Rule::enum(UserRole::class)],
            'date_of_birth' => [
                Rule::requiredIf(fn () => $request->input('role') === UserRole::Patient->value),
                'nullable',
                'date',
                'before:today',
            ],
            'phone' => ['nullable', 'string', 'max:32'],
            'address' => ['nullable', 'string', 'max:2000'],
            'license_number' => [
                Rule::requiredIf(fn () => $request->input('role') === UserRole::Doctor->value),
                'nullable',
                'string',
                'max:255',
                'unique:doctors,license_number',
            ],
            'specialty' => [
                Rule::requiredIf(fn () => $request->input('role') === UserRole::Doctor->value),
                'nullable',
                'string',
                'max:255',
            ],
            'bio' => ['nullable', 'string', 'max:5000'],
        ]);

        $user = DB::transaction(function () use ($data): User {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => $data['password'],
                'role' => $data['role'],
            ]);

            match ($user->role) {
                UserRole::Patient => $user->patient()->create([
                    'date_of_birth' => $data['date_of_birth'],
                    'phone' => $data['phone'] ?? null,
                    'address' => $data['address'] ?? null,
                ]),
                UserRole::Doctor => $user->doctor()->create([
                    'license_number' => $data['license_number'],
                    'specialty' => $data['specialty'],
                    'bio' => $data['bio'] ?? null,
                ]),
                UserRole::Admin => null,
            };

            return $user;
        });

        if ($request->expectsJson()) {
            return response()->json(
                $user->only(['id', 'name', 'email', 'role', 'created_at']),
                201,
            );
        }

        return redirect()->route('admin.dashboard')->with('status', 'User created successfully.');
    }
}
