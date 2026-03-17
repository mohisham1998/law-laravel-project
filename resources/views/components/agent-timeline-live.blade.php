@php
    use App\Services\AgentDefinitions;
    $definitions = AgentDefinitions::all();
@endphp
<div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm" id="agentTimeline">
    <div class="flex justify-between items-center mb-4">
        <h3 class="font-bold flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">psychology</span>
            مراحل التحليل الذكي
        </h3>
        <span class="text-sm font-bold text-slate-600">
            <span id="currentStep">1</span> من <span id="totalSteps">{{ count($definitions) }}</span>
        </span>
    </div>
    <div class="h-2 bg-slate-100 rounded-full mb-6 overflow-hidden">
        <div id="progressBar" class="h-full bg-primary rounded-full transition-all duration-300" style="width: 0%"></div>
    </div>

    <div class="space-y-2" id="agentsContainer">
        @foreach ($definitions as $def)
            <div class="border rounded-xl transition-colors border-slate-200" id="agent-container-{{ $def['number'] }}" data-agent="{{ $def['number'] }}">
                {{-- Agent Header (clickable to expand) --}}
                <button 
                    type="button"
                    onclick="toggleAgent({{ $def['number'] }})"
                    class="w-full flex items-center gap-4 p-3 text-right hover:bg-slate-50 transition-colors bg-slate-50"
                    id="agent-header-{{ $def['number'] }}">
                    <div class="flex-shrink-0 size-9 rounded-full flex items-center justify-center bg-slate-300 text-slate-600"
                         id="agent-icon-{{ $def['number'] }}">
                        <span class="material-symbols-outlined text-lg">schedule</span>
                    </div>
                    <div class="flex-1 min-w-0 text-right">
                        <p class="font-semibold text-sm">
                            @if($def['number'] === 0)
                                المرحلة الأولى: {{ $def['name'] }}
                            @elseif($def['phase'] === 2)
                                {{ $def['number'] }}. {{ $def['name'] }} ({{ $def['name_en'] }})
                            @else
                                {{ $def['name'] }} ({{ $def['name_en'] }})
                            @endif
                        </p>
                        @if(!empty($def['outputs']))
                            <p class="text-xs text-slate-500 mt-0.5">{{ implode(', ', $def['outputs']) }}</p>
                        @endif
                    </div>
                    <div class="flex-shrink-0 flex items-center gap-2">
                        <div class="text-xs font-bold" id="agent-status-{{ $def['number'] }}">
                            <span class="text-slate-400">في الانتظار</span>
                        </div>
                        <span class="material-symbols-outlined text-slate-400 text-base" id="agent-expand-{{ $def['number'] }}" style="display:none;">expand_more</span>
                    </div>
                </button>

                {{-- Agent Live Output (expandable) --}}
                <div id="agent-output-{{ $def['number'] }}" class="hidden border-t border-slate-200">
                    <div class="p-4 bg-slate-50/50">
                        <h4 class="text-xs font-bold text-slate-700 uppercase flex items-center gap-1 mb-3">
                            <span class="material-symbols-outlined text-sm animate-pulse">sync</span>
                            مخرجات الوكيل (مباشر)
                        </h4>
                        <div class="bg-white rounded-lg border border-slate-200 p-3 max-h-96 overflow-y-auto" 
                             id="agent-content-{{ $def['number'] }}" 
                             style="direction: rtl; white-space: pre-wrap; font-family: 'Cairo', monospace; font-size: 13px; line-height: 1.6;">
                            <span class="text-slate-400 text-xs">جارٍ الانتظار...</span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
let eventSource = null;
let agentStates = {};

function toggleAgent(agentNumber) {
    const output = document.getElementById(`agent-output-${agentNumber}`);
    if (output) {
        output.classList.toggle('hidden');
    }
}

function updateAgentUI(agentNumber, status, content = null, duration = null) {
    const container = document.getElementById(`agent-container-${agentNumber}`);
    const header = document.getElementById(`agent-header-${agentNumber}`);
    const icon = document.getElementById(`agent-icon-${agentNumber}`);
    const statusEl = document.getElementById(`agent-status-${agentNumber}`);
    const expandIcon = document.getElementById(`agent-expand-${agentNumber}`);
    const contentEl = document.getElementById(`agent-content-${agentNumber}`);
    
    if (!container) return;
    
    // Update border and background
    container.className = 'border rounded-xl transition-colors';
    header.className = 'w-full flex items-center gap-4 p-3 text-right hover:bg-slate-50 transition-colors';
    
    switch(status) {
        case 'in_progress':
        case 'retrying':
            container.classList.add('border-amber-200');
            header.classList.add('bg-amber-50', 'animate-pulse');
            icon.className = 'flex-shrink-0 size-9 rounded-full flex items-center justify-center bg-amber-500 text-white';
            icon.innerHTML = '<span class="material-symbols-outlined text-lg animate-spin">sync</span>';
            statusEl.innerHTML = '<span class="text-amber-600">جارٍ...</span>';
            expandIcon.style.display = 'block';
            break;
        case 'completed':
            container.classList.add('border-emerald-200');
            header.classList.add('bg-emerald-50');
            icon.className = 'flex-shrink-0 size-9 rounded-full flex items-center justify-center bg-emerald-500 text-white';
            icon.innerHTML = '<span class="material-symbols-outlined text-lg">check</span>';
            statusEl.innerHTML = duration ? `${duration} ثانية` : '<span class="text-emerald-600">مكتمل</span>';
            expandIcon.style.display = 'block';
            break;
        case 'failed':
            container.classList.add('border-red-200');
            header.classList.add('bg-red-50');
            icon.className = 'flex-shrink-0 size-9 rounded-full flex items-center justify-center bg-red-500 text-white';
            icon.innerHTML = '<span class="material-symbols-outlined text-lg">error</span>';
            statusEl.innerHTML = '<span class="text-red-600">فشل</span>';
            break;
        default:
            container.classList.add('border-slate-200');
            header.classList.add('bg-slate-50');
    }
    
    // Update content if provided
    if (content && contentEl) {
        if (status === 'in_progress' || status === 'retrying') {
            contentEl.textContent += content;
            contentEl.scrollTop = contentEl.scrollHeight; // Auto-scroll
        } else if (status === 'completed') {
            contentEl.textContent = content;
        }
    }
}

function startSSE() {
    if (eventSource) {
        eventSource.close();
    }
    
    eventSource = new EventSource(`/cases/{{ $case->id }}/stream`);
    
    eventSource.onmessage = function(event) {
        try {
            const data = JSON.parse(event.data);
            const agentNum = data.agent_number;
            
            switch(data.event_type) {
                case 'agent.started':
                    agentStates[agentNum] = { status: 'in_progress', content: '' };
                    updateAgentUI(agentNum, 'in_progress');
                    // Auto-expand when agent starts
                    const output = document.getElementById(`agent-output-${agentNum}`);
                    if (output && output.classList.contains('hidden')) {
                        output.classList.remove('hidden');
                    }
                    break;
                    
                case 'agent.output':
                    if (!agentStates[agentNum]) agentStates[agentNum] = { status: 'in_progress', content: '' };
                    agentStates[agentNum].content += data.content || '';
                    updateAgentUI(agentNum, 'in_progress', data.content);
                    break;
                    
                case 'agent.completed':
                    const duration = data.metrics?.duration_ms ? (data.metrics.duration_ms / 1000).toFixed(1) : null;
                    updateAgentUI(agentNum, 'completed', agentStates[agentNum]?.content, duration);
                    break;
                    
                case 'agent.failed':
                    updateAgentUI(agentNum, 'failed');
                    break;
            }
            
            // Update progress
            updateProgress();
        } catch (e) {
            console.error('SSE parse error:', e);
        }
    };
    
    eventSource.onerror = function() {
        console.log('SSE connection lost, reconnecting...');
        setTimeout(startSSE, 2000);
    };
}

function updateProgress() {
    const total = {{ count($definitions) }};
    let completed = 0;
    Object.values(agentStates).forEach(state => {
        if (state.status === 'completed') completed++;
    });
    const percent = Math.round((completed / total) * 100);
    document.getElementById('progressBar').style.width = `${percent}%`;
    document.getElementById('currentStep').textContent = completed + 1;
}

// Auto-refresh case status every 5 seconds
function refreshCaseStatus() {
    fetch(`/api/v1/cases/{{ $case->id }}`, {
        headers: {
            'Accept': 'application/json',
            'Authorization': 'Bearer {{ auth()->user()->createToken("temp")->plainTextToken ?? "" }}'
        }
    })
    .then(r => r.json())
    .then(data => {
        // Update agent executions
        if (data.agent_executions) {
            data.agent_executions.forEach(exec => {
                if (!agentStates[exec.agent_number] || agentStates[exec.agent_number].status !== exec.status) {
                    agentStates[exec.agent_number] = { status: exec.status, content: agentStates[exec.agent_number]?.content || '' };
                    const duration = exec.duration_ms ? (exec.duration_ms / 1000).toFixed(1) : null;
                    updateAgentUI(exec.agent_number, exec.status, null, duration);
                }
            });
            updateProgress();
        }
    })
    .catch(e => console.error('Status refresh failed:', e));
}

// Start SSE and polling
document.addEventListener('DOMContentLoaded', function() {
    startSSE();
    setInterval(refreshCaseStatus, 5000);
    refreshCaseStatus(); // Initial load
});
</script>
