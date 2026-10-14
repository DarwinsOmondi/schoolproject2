<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['patient_id', 'doctor_id', 'scheduled_at', 'status', 'reason', 'meeting_url'])]
#[Hidden(['meeting_url'])]
class Appointment extends Model
{
    protected function casts(): array
    {
        return [
            'scheduled_at' => 'datetime',
            'reason' => 'encrypted',
            'meeting_url' => 'encrypted',
        ];
    }

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function doctor(): BelongsTo
    {
        return $this->belongsTo(Doctor::class);
    }

    public function prescriptions(): HasMany
    {
        return $this->hasMany(Prescription::class);
    }

    public function medicalRecords(): HasMany
    {
        return $this->hasMany(MedicalRecord::class);
    }

    public function messages(): HasMany
    {
        return $this->hasMany(Message::class);
    }
}
