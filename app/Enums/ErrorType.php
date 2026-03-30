<?php

namespace App\Enums;

enum ErrorType: string
{
    case AgentFailed = 'agent_failed';
    case AgentFailure = 'agent_failure';
    case AgentTimeout = 'agent_timeout';
    case UserRequestedChange = 'user_requested_change';
    case FortificationFinding = 'fortification_finding';
    case LowConfidence = 'low_confidence';
    case MissingReference = 'missing_reference';
    case AbrogatedStatute = 'abrogated_statute';
    case TemporalContradiction = 'temporal_contradiction';
    case GateValidationFailure = 'gate_validation_failure';
    case ApiTimeout = 'api_timeout';
    case ApiError = 'api_error';
    case QuoteMismatch = 'quote_mismatch';
    case MissingDualCitation = 'missing_dual_citation';
    case SelfCorrectionExhausted = 'self_correction_exhausted';
}
