<?php

namespace App\Models;

use App\Models\Concerns\BelongsToGym;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** One entry in a gym's prepaid SMS / WhatsApp credit ledger. */
class CreditTransaction extends Model
{
    use BelongsToGym;

    protected $guarded = ['id'];

    public function messageLog(): BelongsTo
    {
        return $this->belongsTo(MessageLog::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
