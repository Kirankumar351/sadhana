<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CircleMemberFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class CircleMember extends Model
{
    /** @use HasFactory<CircleMemberFactory> */
    use HasFactory;

    protected $guarded = ['id'];
}
