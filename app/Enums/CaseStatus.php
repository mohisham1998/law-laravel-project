<?php

namespace App\Enums;

enum CaseStatus: string
{
    case Phase1Pending = 'phase1_pending';
    case Phase1Processing = 'phase1_processing';
    case Phase1Completed = 'phase1_completed';
    case AwaitingLaws = 'awaiting_laws';
    case Phase2Pending = 'phase2_pending';
    case Phase2Processing = 'phase2_processing';
    case Phase2Completed = 'phase2_completed';
    case Phase3Pending = 'phase3_pending';
    case Phase3Processing = 'phase3_processing';
    case Phase3Completed = 'phase3_completed';
    case CompletedWithWarnings = 'completed_with_warnings';
    case Failed = 'failed';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
}
