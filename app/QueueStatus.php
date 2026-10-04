<?php

namespace App;

enum QueueStatus: string
{
    case Booked = 'BOOKED';
    case Waiting = 'WAITING';
    case Called = 'CALLED';
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
    case Cancelled = 'CANCELLED';
    case Skipped = 'SKIPPED';
    case NoShow = 'NO_SHOW';
}
