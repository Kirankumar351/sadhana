<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\CreditLedgerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CreditLedger extends Model
{
    /** @use HasFactory<CreditLedgerFactory> */
    use HasFactory;

    protected $table = 'credit_ledger';

    protected $guarded = ['id'];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
