<?php

namespace App\Enums;

enum RiskResponse: string
{
    case Mitigate = 'Mitigate';
    case Accept = 'Accept';
    case Avoid = 'Avoid';
    case Transfer = 'Transfer';
}
