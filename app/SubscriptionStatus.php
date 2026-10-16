<?php

namespace App;

enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Free = 'free';
    case Active = 'active';
    case Expired = 'expired';
    case Suspended = 'suspended';
}
