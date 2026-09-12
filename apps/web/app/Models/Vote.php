<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\VoteFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Vote extends Model
{
    /** @use HasFactory<VoteFactory> */
    use HasFactory;

    protected $guarded = ['id'];
}
