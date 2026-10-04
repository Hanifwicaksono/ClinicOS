<?php

namespace App;

enum MedicalRecordStatus: string
{
    case Draft = 'DRAFT';
    case Final = 'FINAL';
}
