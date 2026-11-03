<?php

namespace App;

enum PaymentStatus: string
{
    case Pending = 'pending';
    case Paid = 'paid';
    case Review = 'review';
    case Expired = 'expired';
    case Failed = 'failed';
    case Refunded = 'refunded';
}
