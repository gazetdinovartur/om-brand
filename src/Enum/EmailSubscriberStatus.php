<?php

namespace App\Enum;

enum EmailSubscriberStatus: string
{
    case Pending = 'pending';
    case Confirmed = 'confirmed';
    case Unsubscribed = 'unsubscribed';
}
