<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class InternshipApplication extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_internship_applications';

    protected $fillable = [
        'full_name',
        'email',
        'phone',
        'national_id',
        'academic_institution',
        'academic_major',
        'required_training_hours',
        'internship_start_date',
        'internship_end_date',
        'photo_path',
        'tracking_code',
        'status',
        'approved_barcode',
        'notes',
    ];

    protected $casts = [
        'internship_start_date' => 'date',
        'internship_end_date' => 'date',
    ];
}
