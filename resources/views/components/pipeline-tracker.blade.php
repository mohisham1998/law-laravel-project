@php
    use App\Services\AgentDefinitions;
    $definitions = AgentDefinitions::all();
    $executionsByAgent = $case->agentExecutions
        ->keyBy('agent_number')
        ->map(fn($e) => [
            'status'       => $e->status instanceof \BackedEnum ? $e->status->value : (string) $e->status,
            'started_at'   => $e->started_at?->toISOString(),
            'completed_at' => $e->completed_at?->toISOString(),
        ]);
    $completedCount = $executionsByAgent->filter(fn($e) => $e['status'] === 'completed')->count();
    $totalAgents = count($definitions);
    $progressPct = $totalAgents > 0 ? round(($completedCount / $totalAgents) * 100) : 0;

    // Group definitions by phase
    $phases = collect($definitions)->groupBy('phase');
    $phaseLabels = [1 => 'المرحلة الأولى', 2 => 'المرحلة الثانية', 3 => 'المرحلة الثالثة'];
    $phaseColors = [1 => 'blue', 2 => 'emerald', 3 => 'indigo'];
@endphp

<div class="bg-white p-5 rounded-xl border border-primary/10 shadow-sm mb-6" id="pipelineTracker">
    <div class="flex items-center justify-between mb-4">
        <h3 class="font-bold flex items-center gap-2 text-base">
            <span class="material-symbols-outlined text-primary">conversion_path</span>
            مسار التحليل
        </h3>
        <span class="text-sm font-bold text-slate-600" id="trackerProgressLabel">{{ $completedCount }} / {{ $totalAgents }} مكتمل</span>
    </div>

    {{-- Phase sections --}}
    <div class="space-y-4 mb-4">
        @foreach($phases as $phaseNum => $phaseAgents)
            @php $color = $phaseColors[$phaseNum] ?? 'slate'; @endphp
            <div>
                <div class="flex items-center gap-2 mb-2">
                    <span class="text-xs font-bold text-{{ $color }}-600 bg-{{ $color }}-50 px-2 py-0.5 rounded">
                        {{ $phaseLabels[$phaseNum] ?? "المرحلة $phaseNum" }}
                    </span>
                    <span class="text-xs text-slate-400">({{ $phaseAgents->count() }} {{ $phaseAgents->count() === 1 ? 'وكيل' : 'وكلاء' }})</span>
                </div>
                <div class="flex flex-wrap gap-2 overflow-x-auto pb-1">
                    @foreach($phaseAgents as $agent)
                        @php
                            $exec = $executionsByAgent->get($agent['number']);
                            $agentStatus = $exec['status'] ?? 'pending';
                        @endphp
                        <div class="flex items-center gap-1.5 px-3 py-1.5 rounded-lg border text-sm transition-all duration-300
                            @if($agentStatus === 'completed') bg-emerald-50 border-emerald-200
                            @elseif(in_array($agentStatus, ['in_progress', 'retrying'])) bg-amber-50 border-amber-300 processing-glow-tracker
                            @elseif($agentStatus === 'failed') bg-red-50 border-red-200
                            @else bg-slate-50 border-slate-200
                            @endif"
                            id="tracker-bubble-{{ $agent['number'] }}"
                            title="{{ $agent['name'] }} — {{ $agent['name_en'] }}">
                            <span class="material-symbols-outlined text-base
                                @if($agentStatus === 'completed') text-emerald-600
                                @elseif(in_array($agentStatus, ['in_progress', 'retrying'])) text-amber-500 animate-spin
                                @elseif($agentStatus === 'failed') text-red-500
                                @else text-slate-400
                                @endif"
                                id="tracker-icon-{{ $agent['number'] }}">
                                @if($agentStatus === 'completed') check_circle
                                @elseif($agentStatus === 'in_progress') progress_activity
                                @elseif($agentStatus === 'retrying') refresh
                                @elseif($agentStatus === 'failed') error
                                @else schedule
                                @endif
                            </span>
                            <span class="font-semibold text-xs whitespace-nowrap
                                @if($agentStatus === 'completed') text-emerald-700
                                @elseif(in_array($agentStatus, ['in_progress', 'retrying'])) text-amber-700
                                @elseif($agentStatus === 'failed') text-red-700
                                @else text-slate-500
                                @endif">
                                {{ $agent['number'] }}. {{ $agent['name'] }}
                            </span>
                        </div>
                    @endforeach
                </div>
            </div>
        @endforeach
    </div>

    {{-- Progress bar --}}
    <div class="h-2.5 bg-slate-100 rounded-full overflow-hidden relative">
        <div id="trackerProgressBar"
             class="h-full bg-gradient-to-r from-primary to-emerald-500 rounded-full transition-all duration-700 ease-out relative"
             style="width: {{ $progressPct }}%">
            <div class="absolute inset-0 bg-white/20 animate-shimmer"></div>
        </div>
    </div>
</div>

<style>
.processing-glow-tracker {
    animation: trackerGlow 2s ease-in-out infinite;
}
@keyframes trackerGlow {
    0%, 100% { box-shadow: 0 0 4px rgba(245, 158, 11, 0.2); }
    50% { box-shadow: 0 0 12px rgba(245, 158, 11, 0.5); }
}
@keyframes shimmer {
    0% { transform: translateX(-100%); }
    100% { transform: translateX(100%); }
}
.animate-shimmer { animation: shimmer 2s infinite; }
</style>

<script>
(function() {
    var executionsByAgent = @json($executionsByAgent);
    var totalAgents = {{ $totalAgents }};

    function updateTrackerBubble(agentNum, status) {
        var bubble = document.getElementById('tracker-bubble-' + agentNum);
        var icon = document.getElementById('tracker-icon-' + agentNum);
        if (!bubble || !icon) return;

        // Reset classes
        bubble.className = bubble.className
            .replace(/bg-\S+/g, '')
            .replace(/border-\S+/g, '')
            .replace(/processing-glow-tracker/g, '')
            .trim();
        icon.className = icon.className
            .replace(/text-\S+/g, '')
            .replace(/animate-spin/g, '')
            .trim();

        var nameSpan = bubble.querySelector('span:last-child');
        if (nameSpan) {
            nameSpan.className = nameSpan.className.replace(/text-\S+/g, '').trim();
        }

        if (status === 'completed') {
            bubble.classList.add('bg-emerald-50', 'border-emerald-200', 'border');
            icon.classList.add('text-emerald-600');
            icon.textContent = 'check_circle';
            if (nameSpan) nameSpan.classList.add('text-emerald-700');
        } else if (status === 'in_progress' || status === 'retrying') {
            bubble.classList.add('bg-amber-50', 'border-amber-300', 'border', 'processing-glow-tracker');
            icon.classList.add('text-amber-500', 'animate-spin');
            icon.textContent = status === 'retrying' ? 'refresh' : 'progress_activity';
            if (nameSpan) nameSpan.classList.add('text-amber-700');
        } else if (status === 'failed') {
            bubble.classList.add('bg-red-50', 'border-red-200', 'border');
            icon.classList.add('text-red-500');
            icon.textContent = 'error';
            if (nameSpan) nameSpan.classList.add('text-red-700');
        } else {
            bubble.classList.add('bg-slate-50', 'border-slate-200', 'border');
            icon.classList.add('text-slate-400');
            icon.textContent = 'schedule';
            if (nameSpan) nameSpan.classList.add('text-slate-500');
        }

        // Update internal tracking
        executionsByAgent[agentNum] = { status: status };
    }

    function recalcProgress() {
        var completed = 0;
        for (var key in executionsByAgent) {
            if (executionsByAgent[key] && executionsByAgent[key].status === 'completed') completed++;
        }
        var pct = totalAgents > 0 ? Math.round((completed / totalAgents) * 100) : 0;
        var bar = document.getElementById('trackerProgressBar');
        var label = document.getElementById('trackerProgressLabel');
        if (bar) bar.style.width = pct + '%';
        if (label) label.textContent = completed + ' / ' + totalAgents + ' مكتمل';
    }

    // SSE event listeners — listens to events dispatched by agent-timeline-live
    window.addEventListener('sse:agent.started', function(ev) {
        var d = ev.detail;
        if (d.agent_number !== undefined) {
            updateTrackerBubble(d.agent_number, 'in_progress');
            recalcProgress();
        }
    });

    window.addEventListener('sse:agent.completed', function(ev) {
        var d = ev.detail;
        if (d.agent_number !== undefined) {
            updateTrackerBubble(d.agent_number, 'completed');
            recalcProgress();
        }
    });

    window.addEventListener('sse:agent.failed', function(ev) {
        var d = ev.detail;
        if (d.agent_number !== undefined) {
            updateTrackerBubble(d.agent_number, 'failed');
            recalcProgress();
        }
    });

    window.addEventListener('sse:agent.correction', function(ev) {
        var d = ev.detail;
        if (d.agent_number !== undefined) {
            updateTrackerBubble(d.agent_number, 'retrying');
        }
    });
})();
</script>
