<?php

namespace App\Enums;

enum AdStatus: string
{
    case Pending = 'pending';   // waiting for admin approval
    case Live = 'live';
    case Rejected = 'rejected';
    case Sold = 'sold';
    case Expired = 'expired';

    public function label(): string
    {
        return __('ads.status.'.$this->value);
    }
}
