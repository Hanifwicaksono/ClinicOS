<?php

namespace App;

enum VisitStatus: string
{
    case InProgress = 'IN_PROGRESS';
    case Completed = 'COMPLETED';
}
