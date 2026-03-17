<?php

namespace App\Enums;

enum ErrorType: string
{
    case LowConfidence = 'low_confidence';
    case MissingReference = 'missing_reference';
    case AbrogatedStatute = 'abrogated_statute';
    case TemporalContradiction = 'temporal_contradiction';
    case GateValidationFailure = 'gate_validation_failure';
    case ApiTimeout = 'api_timeout';
    case ApiError = 'api_error';
}
