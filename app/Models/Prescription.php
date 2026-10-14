<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['appointment_id', 'patient_id', 'doctor_id', 'medication_name', 'dosage', 'frequency', 'duration', 'instructions', 'refills'])]
class Prescription extends Model
{
    protected function casts(): array
    {
        return [
            'medication_name' => 'encrypted',
            'dosage' => 'encrypted',
            'frequency' => 'encrypted',
            'duration' => 'encrypted',
            'instructions' => 'encrypted',
        ];
    }

    public function appointment(): BelongsTo
    {
        return $this->belongsTo(Appointment::class);
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }
}
