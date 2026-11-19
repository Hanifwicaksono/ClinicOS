<?php

namespace App;

enum VisitStatus: string
{
    case InProgress = 'IN_PROGRESS';
    case Paused = 'PAUSED';
    case Interrupted = 'INTERRUPTED';
    case Completed = 'COMPLETED';

    public function label(): string
    {
        return match ($this) {
            self::InProgress => 'Dalam pemeriksaan', self::Paused => 'Dijeda',
            self::Interrupted => 'Pelayanan terinterupsi', self::Completed => 'Selesai',
        };
    }
}
