<?php

namespace App\Http\Middleware;

use App\Enums\UserRole;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureDoctorIsApproved
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        abort_unless(
            $user?->role === UserRole::Patient || $user?->doctor?->is_approved,
            Response::HTTP_FORBIDDEN,
            'Doctor profile is awaiting administrator approval.',
        );

        return $next($request);
    }
}
