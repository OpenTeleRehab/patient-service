<?php

namespace App\Enums;

enum ExportStatus: string
{
    case IN_PROGRESS = 'IN_PROGRESS';

    case FAILED = 'FAILED';

    case SUCCESS = 'SUCCESS';
}
