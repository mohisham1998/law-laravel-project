<?php

namespace App\Enums;

enum AgentStatus: string
{
    case Pending = 'pending';
    case InProgress = 'in_progress';
    case Completed = 'completed';
    case Failed = 'failed';
    case Retrying = 'retrying';
    case Skipped = 'skipped';
}
