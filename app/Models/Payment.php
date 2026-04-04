<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'amount',
        'payment_date',
        'notes',
        'project_id',
    ];

    // علاقة "ينتمي إلى" مشروع واحد
    public function project()
    {
        return $this->belongsTo(Project::class);
    }
}
