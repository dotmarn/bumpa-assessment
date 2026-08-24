<?php

namespace App\Enums;

enum CashbackPaymentStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Submitted = 'submitted';
    case Succeeded = 'succeeded';
    case Failed = 'failed';
}
