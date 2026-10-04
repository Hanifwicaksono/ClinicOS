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

    public function label(): string
    {
        return match ($this) {
            self::Booked => 'Terdaftar',
            self::Waiting => 'Menunggu',
            self::Called => 'Dipanggil',
            self::InProgress => 'Diperiksa',
            self::Completed => 'Selesai',
            self::Cancelled => 'Dibatalkan',
            self::Skipped => 'Dilewati',
            self::NoShow => 'Tidak hadir',
        };
    }

    public function isTerminal(): bool
    {
        return in_array($this, [self::Completed, self::Cancelled, self::NoShow], true);
    }
}
