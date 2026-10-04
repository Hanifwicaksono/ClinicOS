<?php

namespace App;

enum AppointmentStatus: string
{
    case Booked = 'BOOKED';
    case Cancelled = 'CANCELLED';
    case Completed = 'COMPLETED';
    case NoShow = 'NO_SHOW';
}
