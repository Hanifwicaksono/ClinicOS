<?php

namespace App;

enum DentalCondition: string
{
    case Unknown = 'unknown';
    case Healthy = 'healthy';
    case Caries = 'caries';
    case Filled = 'filled';
    case Missing = 'missing';
    case Crown = 'crown';

    public function label(): string
    {
        return match ($this) {
            self::Unknown => 'Belum dicatat',
            self::Healthy => 'Sehat',
            self::Caries => 'Karies',
            self::Filled => 'Tambalan',
            self::Missing => 'Hilang',
            self::Crown => 'Mahkota',
        };
    }
}
