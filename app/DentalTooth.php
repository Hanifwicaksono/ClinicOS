<?php

namespace App;

enum DentalTooth: int
{
    case UpperRightThirdMolar = 18;
    case UpperRightSecondMolar = 17;
    case UpperRightFirstMolar = 16;
    case UpperRightSecondPremolar = 15;
    case UpperRightFirstPremolar = 14;
    case UpperRightCanine = 13;
    case UpperRightLateralIncisor = 12;
    case UpperRightCentralIncisor = 11;
    case UpperLeftCentralIncisor = 21;
    case UpperLeftLateralIncisor = 22;
    case UpperLeftCanine = 23;
    case UpperLeftFirstPremolar = 24;
    case UpperLeftSecondPremolar = 25;
    case UpperLeftFirstMolar = 26;
    case UpperLeftSecondMolar = 27;
    case UpperLeftThirdMolar = 28;
    case LowerLeftCentralIncisor = 31;
    case LowerLeftLateralIncisor = 32;
    case LowerLeftCanine = 33;
    case LowerLeftFirstPremolar = 34;
    case LowerLeftSecondPremolar = 35;
    case LowerLeftFirstMolar = 36;
    case LowerLeftSecondMolar = 37;
    case LowerLeftThirdMolar = 38;
    case LowerRightCentralIncisor = 41;
    case LowerRightLateralIncisor = 42;
    case LowerRightCanine = 43;
    case LowerRightFirstPremolar = 44;
    case LowerRightSecondPremolar = 45;
    case LowerRightFirstMolar = 46;
    case LowerRightSecondMolar = 47;
    case LowerRightThirdMolar = 48;

    /** @return list<int> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
