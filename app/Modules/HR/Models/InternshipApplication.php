<?php

declare(strict_types=1);

namespace App\Modules\HR\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class InternshipApplication extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'hr_internship_applications';

    protected $fillable = [
        'application_type',
        'source',
        'full_name',
        'email',
        'phone',
        'national_id',
        'academic_institution',
        'academic_major',
        'required_training_hours',
        'position_id',
        'qualification',
        'experience_years',
        'internship_start_date',
        'internship_end_date',
        'photo_path',
        'cv_path',
        'tracking_code',
        'status',
        'approved_barcode',
        'interview_date',
        'interview_notes',
        'notes',
    ];

    protected $casts = [
        'required_training_hours' => 'integer',
        'experience_years' => 'integer',
        'internship_start_date' => 'date',
        'internship_end_date' => 'date',
        'interview_date' => 'datetime',
    ];

    /**
     * المسمى الوظيفي المستهدف في حال كان الطلب للتوظيف
     */
    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }
}