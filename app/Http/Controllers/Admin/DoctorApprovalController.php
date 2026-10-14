<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Doctor;
use Illuminate\Http\RedirectResponse;

class DoctorApprovalController extends Controller
{
    public function update(Doctor $doctor): RedirectResponse
    {
        $doctor->update(['is_approved' => true]);

        return redirect()->route('admin.dashboard')->with('status', 'Doctor profile approved.');
    }
}
