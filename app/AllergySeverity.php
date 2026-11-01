<?php

namespace App;

enum AllergySeverity: string
{
    case Mild = 'MILD';
    case Moderate = 'MODERATE';
    case Severe = 'SEVERE';
    case Critical = 'CRITICAL';

    public function label(): string
    {
        return match ($this) {
            self::Mild => 'Ringan',
            self::Moderate => 'Sedang',
            self::Severe => 'Berat',
            self::Critical => 'Kritis',
        };
    }
}
