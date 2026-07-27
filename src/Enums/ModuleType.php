<?php

declare(strict_types=1);

namespace HoceineEl\UsageBilling\Enums;

enum ModuleType: string
{
    /** Usage accumulates from events that happened inside the period. */
    case Metered = 'metered';

    /** Usage is a state measured at the end of the period, such as a seat count. */
    case Snapshot = 'snapshot';
}
