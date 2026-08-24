<?php

namespace App\Models;

use Database\Factories\PayoutAccountFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[UseFactory(PayoutAccountFactory::class)]
#[Fillable(['user_id', 'bank_code', 'account_number', 'account_name', 'recipient_code'])]
#[Hidden(['account_number'])]
class PayoutAccount extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'account_number' => 'encrypted',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
