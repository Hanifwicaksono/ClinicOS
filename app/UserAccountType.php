<?php

namespace App;

enum UserAccountType: string
{
    case Patient = 'patient';
    case ClinicOwner = 'clinic_owner';
}
