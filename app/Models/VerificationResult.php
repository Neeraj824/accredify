<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class VerificationResult extends Model
{
    use HasFactory;

    protected $table = 'verification_results';

    protected $fillable = [
        'user_id',
        'file_type',
        'verification_result',
    ];
}
