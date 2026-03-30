<?php

namespace App\Services;

use Illuminate\Support\Facades\Redis;

class CaseEventService
{
    /** Buffer to accumulate chunks before emitting (reduces Redis writes) */
    protected array $chunkBuffers = [];
    
    /** Minimum chars before flushing buffer */
    protected int $chunkBufferThreshold = 20;
    
    /** Last flush time per case/agent */
    protected array $lastFlushTimes = [];
    
    /** Max time between flushes in ms */
    protected int $maxFlushIntervalMs = 100;

    public function emit(string $caseId, int $agentNumber, string $agentName, string $eventType, array $data = []): void
    {
        $payload = array_merge([
            'case_id' => $caseId,
            'agent_number' => $agentNumber,
            'agent_name' => $agentName,
            'event_type' => $eventType,
            'timestamp' => now()->toISOString(),
        ], $data);

        $redisKey = "case:{$caseId}:events";
        Redis::rpush($redisKey, json_encode($payload));
        // Keep events for 2 hours so page refreshes can replay missed events
        Redis::expire($redisKey, 7200);
    }
    
    /**
     * Emit a streaming output chunk (buffered for performance).
     */
    public function emitChunk(string $caseId, int $agentNumber, string $agentName, string $chunk): void
    {
        $key = "{$caseId}:{$agentNumber}";
        
        if (!isset($this->chunkBuffers[$key])) {
            $this->chunkBuffers[$key] = '';
            $this->lastFlushTimes[$key] = microtime(true) * 1000;
        }
        
        $this->chunkBuffers[$key] .= $chunk;
        
        $now = microtime(true) * 1000;
        $timeSinceFlush = $now - $this->lastFlushTimes[$key];
        
        // Flush if buffer is large enough or enough time has passed
        if (strlen($this->chunkBuffers[$key]) >= $this->chunkBufferThreshold || $timeSinceFlush >= $this->maxFlushIntervalMs) {
            $this->flushChunkBuffer($caseId, $agentNumber, $agentName);
        }
    }
    
    /**
     * Flush accumulated chunks to Redis.
     */
    public function flushChunkBuffer(string $caseId, int $agentNumber, string $agentName): void
    {
        $key = "{$caseId}:{$agentNumber}";
        
        if (!isset($this->chunkBuffers[$key]) || $this->chunkBuffers[$key] === '') {
            return;
        }
        
        $this->emit($caseId, $agentNumber, $agentName, 'agent.output', [
            'content' => $this->chunkBuffers[$key],
        ]);
        
        $this->chunkBuffers[$key] = '';
        $this->lastFlushTimes[$key] = microtime(true) * 1000;
    }
    
    /**
     * Create a callback for streaming that emits chunks.
     */
    public function createStreamCallback(string $caseId, int $agentNumber, string $agentName): callable
    {
        return function (string $chunk) use ($caseId, $agentNumber, $agentName): void {
            $this->emitChunk($caseId, $agentNumber, $agentName, $chunk);
        };
    }
    
    /**
     * Helper: emit agent.started event.
     */
    public function emitStarted(string $caseId, int $agentNumber, string $agentName): void
    {
        $this->emit($caseId, $agentNumber, $agentName, 'agent.started', []);
    }
    
    /**
     * Helper: emit agent.completed event.
     */
    public function emitCompleted(string $caseId, int $agentNumber, string $agentName, array $metrics = []): void
    {
        // Flush any remaining chunks
        $this->flushChunkBuffer($caseId, $agentNumber, $agentName);
        
        $this->emit($caseId, $agentNumber, $agentName, 'agent.completed', [
            'metrics' => $metrics,
        ]);
    }
    
    /**
     * Helper: emit agent.failed event.
     */
    public function emitFailed(string $caseId, int $agentNumber, string $agentName, string $error): void
    {
        // Flush any remaining chunks
        $this->flushChunkBuffer($caseId, $agentNumber, $agentName);

        $this->emit($caseId, $agentNumber, $agentName, 'agent.failed', [
            'error' => $error,
            'error_type' => 'agent_failure',
            'can_retry' => true,
        ]);
    }

    /**
     * Helper: emit agent.correction event when self-correction triggers.
     */
    public function emitCorrection(string $caseId, int $agentNumber, string $agentName, int $attempt, string $violationType, string $violationDetail, string $action = 'Re-running with error context'): void
    {
        $this->emit($caseId, $agentNumber, $agentName, 'agent.correction', [
            'attempt' => $attempt,
            'violation_type' => $violationType,
            'violation_detail' => $violationDetail,
            'action' => $action,
        ]);
    }

    /**
     * Helper: emit pipeline.paused event when correction attempts are exhausted.
     */
    public function emitPipelinePaused(string $caseId, int $failedAgent, string $reason): void
    {
        $this->emit($caseId, $failedAgent, '', 'pipeline.paused', [
            'failed_agent' => $failedAgent,
            'reason' => $reason,
            'options' => ['retry', 'cancel'],
        ]);
    }

    /**
     * Helper: emit pipeline.halted event when pipeline halts due to agent failure.
     */
    public function emitPipelineHalted(string $caseId, int $haltedAtAgent, string $haltReason, array $completedAgents = [], array $skippedAgents = []): void
    {
        $this->emit($caseId, $haltedAtAgent, '', 'pipeline.halted', [
            'halted_at_agent' => $haltedAtAgent,
            'halt_reason' => $haltReason,
            'completed_agents' => $completedAgents,
            'skipped_agents' => $skippedAgents,
            'can_retry' => true,
        ]);
    }

    /**
     * Helper: emit agent.low_confidence event when agent produces low-confidence output.
     */
    public function emitLowConfidence(string $caseId, int $agentNumber, string $agentName, float $confidenceScore, float $threshold): void
    {
        $this->emit($caseId, $agentNumber, $agentName, 'agent.low_confidence', [
            'confidence_score' => $confidenceScore,
            'threshold' => $threshold,
            'below_threshold' => $confidenceScore < $threshold,
        ]);
    }

    /**
     * Helper: emit pipeline.timeout_warning event when pipeline approaches timeout threshold.
     */
    public function emitTimeoutWarning(string $caseId, int $elapsedMinutes, int $timeoutMinutes, int $remainingMinutes, ?int $currentAgent = null): void
    {
        $this->emit($caseId, $currentAgent ?? 0, '', 'pipeline.timeout_warning', [
            'elapsed_minutes' => $elapsedMinutes,
            'timeout_minutes' => $timeoutMinutes,
            'remaining_minutes' => $remainingMinutes,
            'current_agent' => $currentAgent,
        ]);
    }

    /**
     * Emit a case status change event.
     */
    public function emitStatusChanged(string $caseId, string $oldStatus, string $newStatus): void
    {
        $this->emit($caseId, 0, '', 'case.status_changed', [
            'old_status' => $oldStatus,
            'status' => $newStatus,
        ]);
    }
}
