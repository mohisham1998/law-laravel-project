@php
    use App\Services\AgentDefinitions;
    $definitions = AgentDefinitions::all();
    // Group DB outputs by agent_number so JS can pre-populate on page load
    // Only include markdown outputs (md/markdown content_type) for display — skip raw JSON/JSONL blobs
    $outputsByAgent = $case->outputs->groupBy('agent_number')->map(function ($group) {
        return $group
            ->filter(fn ($o) => in_array($o->content_type, ['markdown', 'md', null]) && !empty(trim($o->content ?? '')))
            ->map(fn ($o) => [
                'filename' => $o->filename,
                'content'  => (string) ($o->content ?? ''),
            ])->values()->all();
    })->filter(fn ($group) => !empty($group))->all();

    // Agent numbers whose self-correction was exhausted (best-effort output, may be inaccurate)
    $exhaustedAgents = $case->agentExecutions
        ->where('self_correction_exhausted', true)
        ->pluck('agent_number')
        ->flip()
        ->all();

    // Execution status keyed by agent_number — used to restore UI for agents with no markdown outputs
    $executionStatusByAgent = $case->agentExecutions
        ->pluck('status', 'agent_number')
        ->all();
@endphp
<!-- Agent Timeline Live Component - Last Updated: {{ now()->format('Y-m-d H:i:s') }} -->
<style>
    /* Typewriter cursor animation */
    .typewriter-cursor {
        display: inline-block;
        width: 2px;
        height: 1.2em;
        background-color: #006b34;
        margin-right: 2px;
        animation: blink 0.7s infinite;
        vertical-align: text-bottom;
    }
    @keyframes blink {
        0%, 50% { opacity: 1; }
        51%, 100% { opacity: 0; }
    }
    
    /* Smooth text appearance with slide-in */
    .streaming-text {
        animation: fadeInSlide 0.2s ease-out;
    }
    @keyframes fadeInSlide {
        from { 
            opacity: 0; 
            transform: translateX(5px);
        }
        to { 
            opacity: 1;
            transform: translateX(0);
        }
    }
    
    /* Processing pulse effect with glow */
    .processing-glow {
        animation: processingGlow 2s ease-in-out infinite;
        transition: all 0.3s ease;
    }
    @keyframes processingGlow {
        0%, 100% { 
            box-shadow: 0 0 5px rgba(245, 158, 11, 0.3);
            border-color: rgba(245, 158, 11, 0.3);
        }
        50% { 
            box-shadow: 0 0 20px rgba(245, 158, 11, 0.6), 0 0 40px rgba(245, 158, 11, 0.3);
            border-color: rgba(245, 158, 11, 0.6);
        }
    }
    
    /* Progress bar shimmer effect */
    @keyframes shimmer {
        0% { transform: translateX(-100%); }
        100% { transform: translateX(100%); }
    }
    .animate-shimmer {
        animation: shimmer 2s infinite;
    }
    
    /* Smooth expand/collapse */
    .agent-expand-transition {
        transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        transform-origin: top;
    }
    
    /* Agent content scrollbar styling */
    .agent-output-content {
        scrollbar-width: thin;
        scrollbar-color: #cbd5e1 #f1f5f9;
    }
    .agent-output-content::-webkit-scrollbar {
        width: 6px;
    }
    .agent-output-content::-webkit-scrollbar-track {
        background: #f1f5f9;
        border-radius: 3px;
    }
    .agent-output-content::-webkit-scrollbar-thumb {
        background: #cbd5e1;
        border-radius: 3px;
        transition: background 0.2s;
    }
    .agent-output-content::-webkit-scrollbar-thumb:hover {
        background: #94a3b8;
    }
    
    /* Smooth status transitions */
    .status-transition {
        transition: all 0.4s cubic-bezier(0.4, 0, 0.2, 1);
    }
    
    /* Agent card hover effect */
    .agent-card {
        transition: all 0.2s ease;
    }
    .agent-card:hover {
        transform: translateY(-1px);
        box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1);
    }
</style>

<div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm" id="agentTimeline">
    <div class="flex justify-between items-center mb-4">
        <div class="flex items-center gap-3">
            <h3 class="font-bold flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">psychology</span>
                مراحل التحليل الذكي
            </h3>
            <!-- Pending status indicator -->
            <span id="pendingIndicator" class="hidden flex items-center gap-1.5 px-3 py-1.5 bg-blue-50 text-blue-700 text-xs font-bold rounded-lg border border-blue-200">
                <span class="material-symbols-outlined text-sm animate-spin">sync</span>
                <span>جارٍ التحضير...</span>
            </span>
            @if(in_array($statusVal, ['phase1_processing', 'phase2_processing', 'phase3_processing'], true))
                <button
                    onclick="stopProcessing()"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-red-500 text-white text-xs font-bold rounded-lg hover:bg-red-600 active:scale-95 transition-all shadow-md hover:shadow-lg"
                    id="stopButton"
                    title="إيقاف المعالجة">
                    <span class="material-symbols-outlined text-sm">stop_circle</span>
                    <span>إيقاف</span>
                </button>
            @endif
        </div>
        <div class="flex items-center gap-4">
            <span class="text-sm font-bold text-slate-600">
                <span id="currentStep">1</span> من <span id="totalSteps">{{ count($definitions) }}</span>
            </span>
            @if(in_array($statusVal, ['failed', 'paused'], true))
                <button 
                    onclick="retryCase()"
                    class="flex items-center gap-1.5 px-3 py-1.5 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary/90 active:scale-95 transition-all shadow-md hover:shadow-lg"
                    id="retryButton"
                    title="إعادة المحاولة">
                    <span class="material-symbols-outlined text-sm">refresh</span>
                    <span>إعادة المحاولة</span>
                </button>
            @endif
        </div>
    </div>
    <div class="h-2 bg-slate-100 rounded-full mb-6 overflow-hidden relative">
        <div id="progressBar" class="h-full bg-gradient-to-r from-primary to-emerald-500 rounded-full transition-all duration-700 ease-out relative" style="width: 0%">
            <div class="absolute inset-0 bg-white/20 animate-shimmer"></div>
        </div>
    </div>

    <div class="space-y-3" id="agentsContainer">
        @foreach ($definitions as $def)
            <div class="agent-card border rounded-xl status-transition border-slate-200 overflow-hidden" id="agent-container-{{ $def['number'] }}" data-agent="{{ $def['number'] }}">
                {{-- Agent Header (clickable to expand) — uses <div> not <button> to avoid nested button issue --}}
                <div
                    role="button"
                    tabindex="0"
                    onclick="toggleAgent({{ $def['number'] }})"
                    onkeydown="if(event.key==='Enter')toggleAgent({{ $def['number'] }})"
                    class="w-full flex items-center gap-4 p-4 text-right hover:bg-slate-50 transition-all duration-200 bg-slate-50 cursor-pointer select-none"
                    id="agent-header-{{ $def['number'] }}">
                    <div class="flex-shrink-0 size-9 rounded-full flex items-center justify-center bg-slate-300 text-slate-600 transition-all duration-300"
                         id="agent-icon-{{ $def['number'] }}">
                        <span class="material-symbols-outlined text-lg">schedule</span>
                    </div>
                    <div class="flex-1 min-w-0 text-right">
                        <p class="font-semibold text-sm">
                            @if($def['number'] === 0)
                                <span class="text-xs font-bold text-blue-600 bg-blue-50 px-1.5 py-0.5 rounded mr-1">م١</span>
                                {{ $def['name'] }}
                            @elseif($def['phase'] === 2)
                                <span class="text-xs font-bold text-emerald-600 bg-emerald-50 px-1.5 py-0.5 rounded mr-1">م٢</span>
                                {{ $def['number'] }}. {{ $def['name'] }} — {{ $def['name_en'] }}
                            @else
                                <span class="text-xs font-bold text-indigo-600 bg-indigo-50 px-1.5 py-0.5 rounded mr-1">م٣</span>
                                {{ $def['name'] }} — {{ $def['name_en'] }}
                            @endif
                        </p>
                        @if(!empty($def['outputs']))
                            <p class="text-xs text-slate-500 mt-0.5">{{ implode(', ', $def['outputs']) }}</p>
                        @endif
                    </div>
                    <div class="flex-shrink-0 flex items-center gap-2">
                        <button type="button"
                            onclick="event.stopPropagation(); retryFromAgent({{ $def['number'] }})"
                            class="hidden items-center gap-1 px-2 py-1 text-xs font-medium text-amber-700 bg-amber-50 border border-amber-200 rounded-lg hover:bg-amber-100 transition-all active:scale-95"
                            id="agent-rerun-{{ $def['number'] }}"
                            title="إعادة التشغيل من هذا الوكيل">
                            <span class="material-symbols-outlined text-sm">replay</span>
                            إعادة من هنا
                        </button>
                        {{-- Change model & rerun (visible only when completed) --}}
                        <button type="button"
                            onclick="event.stopPropagation(); openModelConfigForAgent({{ $def['number'] }})"
                            class="hidden items-center gap-1 px-2 py-1 text-xs font-medium text-primary bg-primary/5 border border-primary/20 rounded-lg hover:bg-primary/10 transition-all active:scale-95"
                            id="agent-model-btn-{{ $def['number'] }}"
                            title="تغيير النموذج وإعادة التشغيل">
                            <span class="material-symbols-outlined text-sm">memory</span>
                            نموذج
                        </button>
                        {{-- Edit system message (visible only when completed) --}}
                        <button type="button"
                            onclick="event.stopPropagation(); openSystemMessageEditor({{ $def['number'] }})"
                            class="hidden items-center gap-1 px-2 py-1 text-xs font-medium text-violet-700 bg-violet-50 border border-violet-200 rounded-lg hover:bg-violet-100 transition-all active:scale-95"
                            id="agent-sysmsg-btn-{{ $def['number'] }}"
                            title="تعديل رسالة النظام">
                            <span class="material-symbols-outlined text-sm">edit_note</span>
                            رسالة
                        </button>
                        <div class="text-xs font-bold" id="agent-status-{{ $def['number'] }}">
                            <span class="text-slate-400">في الانتظار</span>
                        </div>
                        <span class="material-symbols-outlined text-slate-400 text-base transition-transform duration-200" id="agent-expand-{{ $def['number'] }}" style="display:none;">expand_more</span>
                    </div>
                </div>

                {{-- Agent Live Output (expandable) --}}
                <div id="agent-output-{{ $def['number'] }}" class="hidden border-t border-slate-200 agent-expand-transition">
                    <div class="p-5 bg-gradient-to-b from-slate-50 to-white">
                        <h4 class="text-xs font-bold text-slate-700 uppercase flex items-center gap-2 mb-3 status-transition" id="agent-output-header-{{ $def['number'] }}">
                            <span class="material-symbols-outlined text-sm text-primary">terminal</span>
                            مخرجات الوكيل (مباشر)
                        </h4>

                        {{-- Accuracy warning — shown when self-correction was exhausted --}}
                        <div id="agent-accuracy-warning-{{ $def['number'] }}"
                             class="mb-3 flex items-start gap-3 bg-amber-50 border border-amber-300 rounded-xl px-4 py-3 text-sm text-amber-800 {{ isset($exhaustedAgents[$def['number']]) ? '' : 'hidden' }}">
                            <span class="material-symbols-outlined text-amber-500 mt-0.5 flex-shrink-0">warning</span>
                            <div>
                                <p class="font-bold mb-0.5">مخرجات غير مضمونة الدقة</p>
                                <p class="text-xs text-amber-700 leading-relaxed">
                                    استنفد الوكيل جميع محاولات التصحيح الذاتي (3 محاولات) دون التحقق من صحة المخرجات بالكامل.
                                    النتائج أدناه هي أفضل محاولة متاحة وقد تحتوي على معلومات غير دقيقة.
                                    يُنصح بمراجعة المخرجات يدوياً قبل الاعتماد عليها.
                                </p>
                            </div>
                        </div>

                        <div class="bg-white rounded-lg border border-slate-200 p-4 max-h-[500px] overflow-y-auto agent-output-content shadow-inner"
                             id="agent-content-{{ $def['number'] }}"
                             style="direction: rtl; white-space: pre-wrap; font-family: 'Cairo', system-ui; font-size: 14px; line-height: 1.9;">
                            <span class="text-slate-400 text-sm animate-pulse" id="agent-placeholder-{{ $def['number'] }}">جارٍ الانتظار...</span>
                            <span class="streaming-output" id="agent-stream-{{ $def['number'] }}"></span>
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
</div>

<script>
// ============================================
// AGENT TIMELINE VERSION: 2026-03-18-v4
// NO AUTO-RELOAD - PURE SSE STREAMING
// ============================================
(function() {
    var version = '2026-03-18-v5';
    console.log('%c[AGENT-TIMELINE] VERSION: ' + version, 'background: #4CAF50; color: white; padding: 4px 8px; border-radius: 4px; font-weight: bold;');
})();

let eventSource = null;
let agentStates = {};
let cursorElements = {};
let userStoppedManually = false; // Track if user clicked stop button
let sseReconnectAttempt = 0; // Exponential backoff counter
let currentCaseStatus = '{{ $case->status->value ?? $case->status }}'; // Updated dynamically from SSE

// DB outputs keyed by agent_number for pre-population on refresh
const dbOutputsByAgent = @json($outputsByAgent);
const currentAgent = {{ $case->current_agent ?? 'null' }};
// Agent definitions keyed by number (for name lookups in JS)
const agentDefinitions = @json(collect($definitions)->keyBy('number')->toArray());
// Agent numbers whose self-correction was exhausted (for page-load state)
const dbExhaustedAgents = @json(array_keys($exhaustedAgents));
// Execution status by agent_number — fallback for agents with no markdown outputs
const dbExecutionStatuses = @json($executionStatusByAgent);

/**
 * Simple markdown-to-HTML renderer for Arabic legal content.
 * Handles: headers, bold, code blocks, line breaks.
 */
function renderMarkdown(text) {
    if (!text) return '';
    // Escape HTML first (prevent XSS from LLM output)
    let html = text
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;');

    // Code blocks (``` ... ```)
    html = html.replace(/```[\w]*\n?([\s\S]*?)```/g, (_, code) =>
        '<pre class="bg-slate-100 rounded p-3 text-xs overflow-x-auto my-2 font-mono text-left" dir="ltr">' + code.trim() + '</pre>'
    );
    // Bold (**text**)
    html = html.replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>');
    // H1 (# heading)
    html = html.replace(/^# (.+)$/gm, '<h3 class="text-base font-bold text-primary mt-4 mb-2 pb-1 border-b border-primary/20">$1</h3>');
    // H2 (## heading)
    html = html.replace(/^## (.+)$/gm, '<h4 class="text-sm font-bold text-slate-700 mt-3 mb-1">$1</h4>');
    // H3 (### heading)
    html = html.replace(/^### (.+)$/gm, '<h5 class="text-sm font-semibold text-slate-600 mt-2 mb-1">$1</h5>');
    // Horizontal rules
    html = html.replace(/^---+$/gm, '<hr class="border-slate-200 my-3">');
    // Newlines → <br>
    html = html.replace(/\n/g, '<br>');
    return html;
}

function toggleAgent(agentNumber) {
    const output = document.getElementById(`agent-output-${agentNumber}`);
    const expandIcon = document.getElementById(`agent-expand-${agentNumber}`);
    if (output) {
        const isHidden = output.classList.contains('hidden');
        
        if (isHidden) {
            // Show with animation
            output.classList.remove('hidden');
            output.style.maxHeight = '0px';
            output.style.opacity = '0';
            setTimeout(() => {
                output.style.maxHeight = '2000px';
                output.style.opacity = '1';
            }, 10);
        } else {
            // Hide with animation
            output.style.maxHeight = '0px';
            output.style.opacity = '0';
            setTimeout(() => {
                output.classList.add('hidden');
                output.style.maxHeight = '';
                output.style.opacity = '';
            }, 300);
        }
        
        if (expandIcon) {
            expandIcon.style.transform = isHidden ? 'rotate(180deg)' : '';
            expandIcon.style.transition = 'transform 0.3s ease';
        }
    }
}

function showCursor(agentNumber) {
    const streamEl = document.getElementById(`agent-stream-${agentNumber}`);
    if (!streamEl) return;
    
    // Remove existing cursor
    hideCursor(agentNumber);
    
    // Add cursor
    const cursor = document.createElement('span');
    cursor.className = 'typewriter-cursor';
    cursor.id = `cursor-${agentNumber}`;
    streamEl.appendChild(cursor);
    cursorElements[agentNumber] = cursor;
}

function hideCursor(agentNumber) {
    const cursor = document.getElementById(`cursor-${agentNumber}`);
    if (cursor) {
        cursor.remove();
    }
    delete cursorElements[agentNumber];
}

function appendStreamContent(agentNumber, content) {
    const streamEl = document.getElementById(`agent-stream-${agentNumber}`);
    const contentEl = document.getElementById(`agent-content-${agentNumber}`);
    const placeholder = document.getElementById(`agent-placeholder-${agentNumber}`);
    
    if (!streamEl || !contentEl) return;
    
    // Skip empty content
    if (!content || !content.trim()) return;
    
    // Hide placeholder on first content
    if (placeholder && placeholder.style.display !== 'none') {
        placeholder.style.display = 'none';
    }
    
    // Remove cursor temporarily
    hideCursor(agentNumber);
    
    // Add content with smooth animation — render markdown as HTML
    const span = document.createElement('span');
    span.className = 'streaming-text';
    span.innerHTML = renderMarkdown(content);
    streamEl.appendChild(span);
    
    // Re-add cursor
    showCursor(agentNumber);
    
    // Auto-scroll smoothly
    setTimeout(() => {
        contentEl.scrollTo({
            top: contentEl.scrollHeight,
            behavior: 'smooth'
        });
    }, 10);
}

function updateAgentUI(agentNumber, status, content = null, duration = null) {
    const container = document.getElementById(`agent-container-${agentNumber}`);
    const header = document.getElementById(`agent-header-${agentNumber}`);
    const icon = document.getElementById(`agent-icon-${agentNumber}`);
    const statusEl = document.getElementById(`agent-status-${agentNumber}`);
    const expandIcon = document.getElementById(`agent-expand-${agentNumber}`);
    const contentEl = document.getElementById(`agent-content-${agentNumber}`);
    const outputHeader = document.getElementById(`agent-output-header-${agentNumber}`);
    const rerunBtn = document.getElementById(`agent-rerun-${agentNumber}`);

    if (!container) return;

    // Show re-run button for completed or failed agents (Phase 2 agents: 1-9)
    if (rerunBtn && agentNumber >= 1 && agentNumber <= 9) {
        if (status === 'completed' || status === 'failed') {
            rerunBtn.classList.remove('hidden');
            rerunBtn.classList.add('flex');
        } else {
            rerunBtn.classList.add('hidden');
            rerunBtn.classList.remove('flex');
        }
    }

    // Show model config button for any completed agent (all phases)
    const modelBtn = document.getElementById(`agent-model-btn-${agentNumber}`);
    if (modelBtn) {
        if (status === 'completed' || status === 'failed') {
            modelBtn.classList.remove('hidden');
            modelBtn.classList.add('flex');
        } else {
            modelBtn.classList.add('hidden');
            modelBtn.classList.remove('flex');
        }
    }

    // Show system message button for any completed agent
    const sysMsgBtn = document.getElementById(`agent-sysmsg-btn-${agentNumber}`);
    if (sysMsgBtn) {
        if (status === 'completed' || status === 'failed') {
            sysMsgBtn.classList.remove('hidden');
            sysMsgBtn.classList.add('flex');
        } else {
            sysMsgBtn.classList.add('hidden');
            sysMsgBtn.classList.remove('flex');
        }
    }
    
    // Reset classes with smooth transitions
    container.className = 'agent-card border rounded-xl status-transition overflow-hidden';
    header.className = 'w-full flex items-center gap-4 p-4 text-right hover:bg-slate-50 transition-all duration-200 cursor-pointer select-none';
    
    switch(status) {
        case 'in_progress':
        case 'retrying':
            container.classList.add('border-amber-300', 'processing-glow');
            header.classList.add('bg-amber-50');
            icon.className = 'flex-shrink-0 size-9 rounded-full flex items-center justify-center bg-amber-500 text-white transition-all duration-300';
            icon.innerHTML = '<span class="material-symbols-outlined text-lg animate-spin">sync</span>';
            statusEl.innerHTML = '<span class="text-amber-600 flex items-center gap-1"><span class="inline-block w-2 h-2 bg-amber-500 rounded-full animate-pulse"></span>جارٍ...</span>';
            expandIcon.style.display = 'block';
            if (outputHeader) {
                outputHeader.innerHTML = '<span class="material-symbols-outlined text-sm text-amber-500 animate-pulse">sync</span> مخرجات الوكيل (مباشر)';
            }
            showCursor(agentNumber);
            break;
            
        case 'completed':
            container.classList.remove('processing-glow');
            container.classList.add('border-emerald-200');
            header.classList.add('bg-emerald-50');
            icon.className = 'flex-shrink-0 size-9 rounded-full flex items-center justify-center bg-emerald-500 text-white transition-all duration-300';
            icon.innerHTML = '<span class="material-symbols-outlined text-lg">check</span>';
            statusEl.innerHTML = duration ? `<span class="text-emerald-600">${duration} ثانية</span>` : '<span class="text-emerald-600">مكتمل</span>';
            expandIcon.style.display = 'block';
            hideCursor(agentNumber);
            if (outputHeader) {
                outputHeader.innerHTML = '<span class="material-symbols-outlined text-sm text-emerald-500">check_circle</span> مخرجات الوكيل (مكتمل)';
            }
            break;
            
        case 'failed':
            container.classList.remove('processing-glow');
            container.classList.add('border-red-200');
            header.classList.add('bg-red-50');
            icon.className = 'flex-shrink-0 size-9 rounded-full flex items-center justify-center bg-red-500 text-white transition-all duration-300';
            icon.innerHTML = '<span class="material-symbols-outlined text-lg">error</span>';
            statusEl.innerHTML = '<span class="text-red-600">فشل</span>';
            expandIcon.style.display = 'block';
            hideCursor(agentNumber);
            if (outputHeader) {
                outputHeader.innerHTML = '<span class="material-symbols-outlined text-sm text-red-500">error</span> مخرجات الوكيل (فشل)';
            }
            // Show error content
            if (content && contentEl) {
                const streamEl = document.getElementById(`agent-stream-${agentNumber}`);
                const placeholder = document.getElementById(`agent-placeholder-${agentNumber}`);
                if (placeholder) placeholder.style.display = 'none';
                if (streamEl) {
                    streamEl.innerHTML = '<div class="text-red-600 font-medium bg-red-50 p-3 rounded-lg border border-red-200">' +
                        '<div class="flex items-center gap-2 mb-2"><span class="material-symbols-outlined">warning</span> خطأ</div>' +
                        '<p class="text-sm">' + content + '</p></div>';
                }
                // Auto-expand on failure
                const output = document.getElementById(`agent-output-${agentNumber}`);
                if (output && output.classList.contains('hidden')) {
                    output.classList.remove('hidden');
                }
            }
            break;
            
        default:
            container.classList.add('border-slate-200');
            header.classList.add('bg-slate-50');
    }
}

// Terminal statuses - these should NOT reconnect SSE
const terminalStatuses = ['failed', 'paused', 'cancelled', 'completed'];

// Active statuses - these should keep SSE connection alive
const activeStatuses = [
    'phase1_pending', 'phase1_processing', 'phase1_completed',
    'awaiting_laws',
    'phase2_pending', 'phase2_processing', 'phase2_completed',
    'phase3_pending', 'phase3_processing', 'phase3_completed',
    'completed_with_warnings'
];

function isTerminalStatus(status) {
    return terminalStatuses.includes(status);
}

function isActiveStatus(status) {
    return activeStatuses.includes(status);
}

function startSSE() {
    if (eventSource) {
        eventSource.close();
    }
    
    const caseStatus = '{{ $case->status->value ?? $case->status }}';

    // Don't start SSE if case is in a terminal status
    if (isTerminalStatus(caseStatus)) {
        console.log('Case in terminal status, skipping SSE:', caseStatus);
        return;
    }
    
    const sseUrl = `/cases/{{ $case->id }}/stream`;
    
    eventSource = new EventSource(sseUrl);
    
    eventSource.onopen = function() {
        sseReconnectAttempt = 0; // Reset backoff on successful connection
    };
    
    eventSource.onmessage = function(event) {
        try {
            const data = JSON.parse(event.data);
            const agentNum = data.agent_number;
            
            // Broadcast to output panel and other listeners
            window.dispatchEvent(new CustomEvent(`sse:${data.event_type}`, { detail: data }));

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
                    if (!agentStates[agentNum]) {
                        agentStates[agentNum] = { status: 'in_progress', content: '' };
                        updateAgentUI(agentNum, 'in_progress');
                    }
                    agentStates[agentNum].content += data.content || '';
                    appendStreamContent(agentNum, data.content || '');
                    break;

                case 'agent.completed':
                    const duration = data.metrics?.duration_ms ? (data.metrics.duration_ms / 1000).toFixed(1) : null;
                    if (agentStates[agentNum]) {
                        agentStates[agentNum].status = 'completed';
                    }
                    updateAgentUI(agentNum, 'completed', null, duration);
                    // Show accuracy warning if self-correction was exhausted
                    if (data.self_correction_exhausted) {
                        showAccuracyWarning(agentNum);
                    }
                    updateProgress();
                    break;

                case 'agent.correction':
                    // Self-correction event — show notification in agent panel
                    if (agentStates[agentNum]) {
                        agentStates[agentNum].corrections = (agentStates[agentNum].corrections || 0) + 1;
                    }
                    showCorrectionNotification(agentNum, data.attempt, data.violation_type, data.violation_detail);
                    break;

                case 'agent.failed':
                    if (agentStates[agentNum]) {
                        agentStates[agentNum].status = 'failed';
                    }
                    updateAgentUI(agentNum, 'failed', data.error || 'فشل غير معروف');
                    break;

                case 'pipeline.paused':
                    showPipelinePausedModal(data.failed_agent, data.reason);
                    break;

                case 'case.status_changed':
                    currentCaseStatus = data.status; // Keep dynamic status in sync
                    // Update pending indicator
                    updatePendingIndicator(data.status);
                    // Auto-launch Phase 3 when Phase 2 completes — no page refresh needed
                    if (data.status === 'phase2_completed') {
                        autoLaunchPhase3();
                    }
                    // Activate PDF export button when pipeline fully done
                    if (data.status === 'phase3_completed' || data.status === 'completed_with_warnings') {
                        activatePdfExportButton();
                        hidePhase3Banner();
                    }
                    break;

                case 'connection.established':
                case 'connection.timeout':
                    console.log('SSE connection event:', data.event_type);
                    break;
            }
        } catch (e) {
            console.error('SSE parse error:', e);
        }
    };
    
    eventSource.onerror = function(error) {
        const isExpectedClose = userStoppedManually || isTerminalStatus(currentCaseStatus) || !isActiveStatus(currentCaseStatus);
        if (isExpectedClose) {
            console.info('SSE closed (expected):', { status: currentCaseStatus, userStoppedManually });
            eventSource.close();
            return;
        }

        // Connection dropped while case is still active — retry with backoff.
        console.warn('SSE connection dropped, attempting reconnect:', error);
        const delay = Math.min(1000 * Math.pow(2, sseReconnectAttempt), 30000);
        sseReconnectAttempt++;
        eventSource.close();
        setTimeout(startSSE, delay);
    };
}

function updateProgress() {
    const total = {{ count($definitions) }};
    let completed = 0;
    let inProgress = 0;
    Object.values(agentStates).forEach(state => {
        if (state.status === 'completed') completed++;
        if (state.status === 'in_progress') inProgress++;
    });
    
    // Progress = completed + partial progress for in-progress agent
    const progressPercent = Math.min(100, Math.round(((completed + (inProgress * 0.5)) / total) * 100));
    const progressBar = document.getElementById('progressBar');
    const currentStepEl = document.getElementById('currentStep');
    if (progressBar) progressBar.style.width = `${progressPercent}%`;
    if (currentStepEl) currentStepEl.textContent = Math.min(completed + 1, total);
}

// No auto-refresh - we rely purely on SSE for real-time updates
// User requested no page reloads during processing

// Stop processing function
function stopProcessing() {
    if (!confirm('هل أنت متأكد من إيقاف معالجة القضية؟\n\nسيتم إيقاف العملية وحفظ التقدم الحالي.')) {
        return;
    }
    
    // Set flag to prevent SSE reconnection
    userStoppedManually = true;
    
    const stopBtn = document.getElementById('stopButton');
    if (stopBtn) {
        stopBtn.disabled = true;
        stopBtn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">sync</span><span>جارٍ الإيقاف...</span>';
    }
    
    // Close SSE connection immediately
    if (eventSource) {
        console.log('Closing SSE connection due to user stop');
        eventSource.close();
        eventSource = null;
    }
    
    // Update UI immediately to show stopped state
    updateStatusBadgeToStopped();
    
    // Send AJAX request to backend (no reload - UI already updated)
    fetch('/cases/{{ $case->id }}/abort', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        }
    })
    .then(response => response.json())
    .then(data => {
        // Stop successful - UI already updated
    })
    .catch(error => {
        console.error('Stop request failed:', error);
    });
}

// Update status badge immediately to stopped
function updateStatusBadgeToStopped() {
    const statusBadge = document.querySelector('.inline-flex.items-center.rounded-full.px-3.py-1');
    if (statusBadge) {
        statusBadge.className = 'inline-flex items-center rounded-full px-3 py-1 text-xs font-medium bg-red-100 text-red-800';
        statusBadge.innerHTML = '<span class="material-symbols-outlined text-sm ml-1">pause_circle</span>متوقف';
    }
    
    // Hide stop button, show retry button
    const stopBtn = document.getElementById('stopButton');
    const retryBtn = document.getElementById('retryButton');
    if (stopBtn) stopBtn.style.display = 'none';
    if (retryBtn) retryBtn.style.display = 'flex';
}

// Retry case function
function retryCase() {
    if (!confirm('هل تريد إعادة محاولة معالجة هذه القضية؟')) {
        return;
    }
    
    // Reset the manual stop flag since we're retrying
    userStoppedManually = false;

    const retryBtn = document.getElementById('retryButton');
    if (retryBtn) {
        retryBtn.disabled = true;
        retryBtn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">sync</span><span>جارٍ...</span>';
    }

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = '{{ route("cases.retry-agent", $case) }}';

    const csrfInput = document.createElement('input');
    csrfInput.type = 'hidden';
    csrfInput.name = '_token';
    csrfInput.value = document.querySelector('meta[name="csrf-token"]').content;
    form.appendChild(csrfInput);

    document.body.appendChild(form);
    form.submit(); // This will reload the page, which is expected for retry
}

// openModelConfigForAgent is defined in agent-model-config.blade.php

// Show accuracy warning banner when self-correction attempts were exhausted
function showAccuracyWarning(agentNumber) {
    const warning = document.getElementById(`agent-accuracy-warning-${agentNumber}`);
    if (warning) {
        warning.classList.remove('hidden');
        // Auto-expand the agent panel so the warning is visible
        const output = document.getElementById(`agent-output-${agentNumber}`);
        if (output && output.classList.contains('hidden')) {
            output.classList.remove('hidden');
        }
    }
}

// Show correction notification in the agent panel
function showCorrectionNotification(agentNumber, attempt, violationType, detail) {
    const streamEl = document.getElementById(`agent-stream-${agentNumber}`);
    if (!streamEl) return;

    const notification = document.createElement('div');
    notification.className = 'bg-amber-50 border border-amber-200 rounded-lg p-3 my-2 text-sm animate-pulse';
    notification.style.animation = 'fadeInSlide 0.3s ease-out';
    notification.innerHTML = `
        <div class="flex items-center gap-2 text-amber-700 font-medium mb-1">
            <span class="material-symbols-outlined text-sm">autorenew</span>
            تصحيح ذاتي — المحاولة ${attempt} من 3
        </div>
        <p class="text-amber-600 text-xs">${detail || violationType}</p>
    `;
    streamEl.appendChild(notification);

    // Remove pulse after 3s
    setTimeout(() => { notification.classList.remove('animate-pulse'); }, 3000);

    // Auto-scroll
    const contentEl = document.getElementById(`agent-content-${agentNumber}`);
    if (contentEl) {
        contentEl.scrollTo({ top: contentEl.scrollHeight, behavior: 'smooth' });
    }
}

// Show pipeline paused modal with Retry/Cancel options
function showPipelinePausedModal(failedAgent, reason) {
    // Close SSE since pipeline is paused
    if (eventSource) {
        eventSource.close();
        eventSource = null;
    }

    // Create modal overlay
    const overlay = document.createElement('div');
    overlay.id = 'pipeline-paused-modal';
    overlay.className = 'fixed inset-0 bg-black/50 flex items-center justify-center z-50';
    overlay.style.animation = 'fadeInSlide 0.3s ease-out';
    overlay.innerHTML = `
        <div class="bg-white rounded-2xl shadow-2xl p-8 max-w-md mx-4 text-center" dir="rtl">
            <div class="w-16 h-16 bg-amber-100 rounded-full flex items-center justify-center mx-auto mb-4">
                <span class="material-symbols-outlined text-amber-600 text-3xl">pause_circle</span>
            </div>
            <h3 class="text-xl font-bold text-slate-800 mb-2">توقف خط المعالجة</h3>
            <p class="text-slate-600 mb-1">${reason}</p>
            <p class="text-sm text-slate-500 mb-6">الوكيل رقم ${failedAgent}</p>
            <div class="flex gap-3 justify-center">
                <button onclick="retryFromAgent(${failedAgent})" class="px-6 py-2.5 bg-primary text-white rounded-xl font-bold hover:bg-primary/90 transition-all active:scale-95">
                    <span class="material-symbols-outlined text-sm align-middle ml-1">refresh</span>
                    إعادة المحاولة
                </button>
                <button onclick="closePausedModal()" class="px-6 py-2.5 bg-slate-200 text-slate-700 rounded-xl font-bold hover:bg-slate-300 transition-all active:scale-95">
                    إلغاء
                </button>
            </div>
        </div>
    `;
    document.body.appendChild(overlay);
}

function closePausedModal() {
    const modal = document.getElementById('pipeline-paused-modal');
    if (modal) modal.remove();
}

function retryFromAgent(agentNumber) {
    closePausedModal();
    fetch(`/cases/{{ $case->id }}/rerun-from`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content,
            'Accept': 'application/json'
        },
        body: JSON.stringify({ agent_number: agentNumber })
    })
    .then(response => response.json())
    .then(data => {
        sseReconnectAttempt = 0;
        startSSE();
    })
    .catch(error => {
        console.error('Re-run failed:', error);
        alert('فشل في إعادة المحاولة. يرجى تحديث الصفحة.');
    });
}

// Track page load time
window.pageLoadTime = Date.now();

/**
 * Pre-populate agent cards from DB outputs (restores data after page refresh).
 */
function initializeFromDB() {
    const isProcessing = ['phase2_processing', 'phase3_processing'].includes(currentCaseStatus);

    Object.entries(dbOutputsByAgent).forEach(([agentNum, outputs]) => {
        const num = parseInt(agentNum, 10);
        if (!outputs || outputs.length === 0) return;

        // If pipeline is actively processing, skip agents >= current_agent
        // (their DB data is stale and will be overwritten by SSE)
        if (isProcessing && currentAgent !== null && num >= currentAgent) return;

        // Combine all output content for this agent
        const combinedContent = outputs.map(o => {
            const header = o.filename ? `## ${o.filename}\n\n` : '';
            return header + (o.content || '');
        }).join('\n\n---\n\n');

        // Mark agent as completed in UI
        agentStates[num] = { status: 'completed', content: combinedContent };
        updateAgentUI(num, 'completed');

        // Show model-config button (visible for all completed agents)
        const modelBtn = document.getElementById(`agent-model-btn-${num}`);
        if (modelBtn) { modelBtn.classList.remove('hidden'); modelBtn.classList.add('flex'); }

        // Show system message button (visible for all completed agents)
        const sysMsgBtn = document.getElementById(`agent-sysmsg-btn-${num}`);
        if (sysMsgBtn) { sysMsgBtn.classList.remove('hidden'); sysMsgBtn.classList.add('flex'); }

        // Show accuracy warning for exhausted agents
        if (dbExhaustedAgents.includes(num)) {
            showAccuracyWarning(num);
        }

        // Populate the stream element
        const streamEl = document.getElementById(`agent-stream-${num}`);
        const placeholder = document.getElementById(`agent-placeholder-${num}`);
        const expandIcon = document.getElementById(`agent-expand-${num}`);

        if (placeholder) placeholder.style.display = 'none';
        if (expandIcon) expandIcon.style.display = 'block';
        if (streamEl && combinedContent.trim()) {
            streamEl.innerHTML = renderMarkdown(combinedContent);
        }
    });

    // Fallback: update agents that have execution records but no markdown outputs
    // (e.g., Agent 10/Judge in completed_with_warnings state where output is stored as JSON)
    Object.entries(dbExecutionStatuses).forEach(([agentNum, status]) => {
        const num = parseInt(agentNum, 10);
        if (agentStates[num]) return; // Already handled by outputs loop above
        if (status === 'completed') {
            agentStates[num] = { status: 'completed', content: '' };
            updateAgentUI(num, 'completed');
        } else if (status === 'failed') {
            agentStates[num] = { status: 'failed', content: '' };
            updateAgentUI(num, 'failed');
        }
    });

    // Agent 0 (Phase 1) fallback: if case has moved past Phase 1, mark it completed.
    // Phase 1 doesn't create execution records; infer from case status.
    const phase1ActiveStatuses = ['phase1_pending', 'phase1_processing', 'awaiting_laws'];
    if (!agentStates[0] && !phase1ActiveStatuses.includes(currentCaseStatus)) {
        agentStates[0] = { status: 'completed', content: '' };
        updateAgentUI(0, 'completed');
    }

    // Update progress bar based on DB state
    updateProgress();
}

// Start SSE and polling
document.addEventListener('DOMContentLoaded', function() {
    // Initialize from DB

    // Always pre-populate from DB first (handles refresh case)
    initializeFromDB();

    // Determine if case is in an active/pending state
    const isActive = isActiveStatus(currentCaseStatus);

    // Show/hide pending indicator based on status
    updatePendingIndicator(currentCaseStatus);

    if (isActive || !isTerminalStatus(currentCaseStatus)) {
        startSSE();
    }
});

// Update pending indicator based on case status
function updatePendingIndicator(status) {
    const indicator = document.getElementById('pendingIndicator');
    if (!indicator) return;

    // Show indicator for pending/preparation states
    const pendingStatuses = ['phase1_pending', 'awaiting_laws', 'phase2_pending', 'phase3_pending'];

    if (pendingStatuses.includes(status)) {
        indicator.classList.remove('hidden');
    } else {
        indicator.classList.add('hidden');
    }
}

// ============================================
// AUTO PHASE 3 LAUNCH
// ============================================
let _phase3Launched = false;

async function autoLaunchPhase3() {
    if (_phase3Launched) return;
    _phase3Launched = true;

    const url = '{{ route("cases.start-phase3", $case) }}';
    const csrf = document.querySelector('meta[name="csrf-token"]')?.content || '';

    // Hide the static Phase 3 banner (it was rendered server-side for page-load state)
    hidePhase3Banner();

    // Show a transient toast so user knows Phase 3 is auto-starting
    showAutoLaunchToast();

    try {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-TOKEN': csrf,
                'Accept': 'application/json',
            },
            body: JSON.stringify({ puter_token: '' }),
        });
        if (!res.ok) {
            console.warn('Auto Phase 3 launch returned', res.status);
        }
    } catch (err) {
        console.error('Auto Phase 3 launch failed:', err);
    }
}

function hidePhase3Banner() {
    // The Phase 3 gate banner rendered by show.blade.php
    const banner = document.getElementById('phase3GateBanner');
    if (banner) banner.style.display = 'none';
}

function showAutoLaunchToast() {
    const toast = document.createElement('div');
    toast.className = 'fixed bottom-6 left-1/2 -translate-x-1/2 z-50 bg-indigo-700 text-white px-6 py-3 rounded-xl shadow-xl flex items-center gap-3 text-sm font-semibold animate-fade-in';
    toast.innerHTML = '<span class="material-symbols-outlined animate-spin text-base">progress_activity</span> جارٍ تشغيل المرحلة الثالثة تلقائياً...';
    document.body.appendChild(toast);
    setTimeout(() => toast.remove(), 5000);
}

// ============================================
// PDF EXPORT BUTTON DYNAMIC ACTIVATION
// ============================================
function activatePdfExportButton() {
    const container = document.getElementById('pdfExportBtnContainer');
    if (!container) return;
    const url = container.dataset.pdfUrl;
    container.innerHTML = `
        <button type="button"
                onclick="handlePdfExport(this, '${url}')"
                class="w-full flex items-center gap-3 p-3 bg-primary/10 rounded-xl hover:bg-primary/20 transition-colors text-primary font-semibold"
                id="pdfExportBtn">
            <span class="material-symbols-outlined">picture_as_pdf</span>
            <span class="text-sm">تصدير PDF</span>
        </button>`;
}

// ============================================
// SYSTEM MESSAGE EDITOR
// ============================================
let _sysMsgCurrentAgent = null;

function openSystemMessageEditor(agentNumber) {
    _sysMsgCurrentAgent = agentNumber;
    const modal = document.getElementById('systemMessageModal');
    const drawer = document.getElementById('systemMessageDrawer');
    const textarea = document.getElementById('sysMsgTextarea');
    const charCount = document.getElementById('sysMsgCharCount');

    // Show loading state
    modal.classList.remove('hidden');
    setTimeout(() => drawer.classList.remove('translate-x-full'), 10);

    // Set agent label
    const def = agentDefinitions ? agentDefinitions[agentNumber] : null;
    document.getElementById('sysMsgAgentName').textContent = def ? def.name : `وكيل ${agentNumber}`;
    document.getElementById('sysMsgAgentLabel').textContent = `رقم الوكيل: ${agentNumber}`;
    textarea.value = 'جارٍ التحميل...';
    textarea.disabled = true;

    // Fetch current system message
    fetch(`/api/agents/${agentNumber}/system-message`, {
        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(r => r.json())
    .then(data => {
        textarea.value = data.system_message || '';
        textarea.disabled = false;
        charCount.textContent = `${textarea.value.length} / 5000`;
        const badge = document.getElementById('sysMsgOverrideBadge');
        if (data.is_override) badge.classList.remove('hidden');
        else badge.classList.add('hidden');
    })
    .catch(() => {
        textarea.value = '';
        textarea.disabled = false;
    });

    textarea.oninput = () => { charCount.textContent = `${textarea.value.length} / 5000`; };
}

function closeSystemMessageEditor() {
    const modal = document.getElementById('systemMessageModal');
    const drawer = document.getElementById('systemMessageDrawer');
    drawer.classList.add('translate-x-full');
    setTimeout(() => modal.classList.add('hidden'), 300);
    _sysMsgCurrentAgent = null;
}

function saveSystemMessage() {
    if (_sysMsgCurrentAgent === null) return;
    const textarea = document.getElementById('sysMsgTextarea');
    const saveBtn = document.getElementById('sysMsgSaveBtn');
    const msg = textarea.value.trim();
    if (!msg || msg.length < 10) { alert('الرسالة قصيرة جداً'); return; }

    saveBtn.disabled = true;
    saveBtn.innerHTML = '<span class="material-symbols-outlined text-sm animate-spin">sync</span> جارٍ الحفظ...';

    fetch(`/api/agents/${_sysMsgCurrentAgent}/system-message`, {
        method: 'PATCH',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify({ system_message: msg })
    })
    .then(r => r.json())
    .then(data => {
        if (data.ok) {
            const badge = document.getElementById('sysMsgOverrideBadge');
            badge.classList.remove('hidden');
            closeSystemMessageEditor();
        }
    })
    .catch(() => alert('حدث خطأ أثناء الحفظ'))
    .finally(() => {
        saveBtn.disabled = false;
        saveBtn.innerHTML = '<span class="material-symbols-outlined text-sm">save</span> حفظ التغييرات';
    });
}

function resetSystemMessage() {
    if (_sysMsgCurrentAgent === null || !confirm('هل تريد استعادة الرسالة الافتراضية للوكيل؟')) return;
    fetch(`/api/agents/${_sysMsgCurrentAgent}/system-message/override`, {
        method: 'DELETE',
        headers: {
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content || '',
            'X-Requested-With': 'XMLHttpRequest'
        }
    })
    .then(r => r.json())
    .then(() => {
        // Reload the message
        openSystemMessageEditor(_sysMsgCurrentAgent);
    });
}
</script>

{{-- System Message Editor Modal --}}
<div id="systemMessageModal"
     class="fixed inset-0 z-50 hidden"
     onclick="if(event.target===this)closeSystemMessageEditor()">
    <div class="absolute inset-0 bg-black/40 backdrop-blur-sm"></div>
    <div id="systemMessageDrawer"
         class="absolute top-0 right-0 h-full w-full max-w-xl bg-white shadow-2xl flex flex-col transform transition-transform duration-300 translate-x-full"
         dir="rtl">
        {{-- Header --}}
        <div class="flex items-center justify-between px-5 py-4 border-b border-slate-200 bg-violet-50">
            <div class="flex items-center gap-3">
                <div class="size-9 rounded-full bg-violet-600 text-white flex items-center justify-center">
                    <span class="material-symbols-outlined text-lg">edit_note</span>
                </div>
                <div>
                    <h2 class="font-bold text-slate-800 text-sm" id="sysMsgAgentName">رسالة النظام</h2>
                    <p class="text-xs text-slate-500" id="sysMsgAgentLabel">وكيل</p>
                </div>
            </div>
            <button onclick="closeSystemMessageEditor()" class="size-8 flex items-center justify-center rounded-lg hover:bg-violet-100 transition-colors" title="إغلاق">
                <span class="material-symbols-outlined text-slate-500">close</span>
            </button>
        </div>

        {{-- Override badge --}}
        <div id="sysMsgOverrideBadge" class="hidden px-5 py-2 bg-amber-50 border-b border-amber-200 flex items-center gap-2 text-xs text-amber-700">
            <span class="material-symbols-outlined text-sm">edit</span>
            هذه الرسالة مُعدَّلة يدوياً.
            <button onclick="resetSystemMessage()" class="underline font-medium hover:text-amber-900">استعادة الافتراضي</button>
        </div>

        {{-- Body --}}
        <div class="flex-1 overflow-y-auto p-5 space-y-4">
            <div>
                <label class="block text-xs font-bold text-slate-700 mb-2">رسالة النظام (الشخصية)</label>
                <p class="text-xs text-slate-500 mb-3 leading-relaxed">
                    هذه هي الرسالة التي تُحدد شخصية الوكيل وتُرسل كـ <code class="bg-slate-100 px-1 rounded">system message</code> قبل كل استدعاء. تؤثر مباشرةً على أسلوب استجابة النموذج.
                </p>
                <textarea
                    id="sysMsgTextarea"
                    rows="8"
                    class="w-full rounded-xl border border-slate-200 px-4 py-3 text-sm text-slate-700 leading-relaxed focus:outline-none focus:ring-2 focus:ring-violet-400 focus:border-transparent resize-none font-arabic"
                    dir="rtl"
                    placeholder="أدخل رسالة النظام للوكيل..."></textarea>
                <p class="text-xs text-slate-400 mt-1 text-left" id="sysMsgCharCount">0 / 5000</p>
            </div>

            <div class="bg-slate-50 rounded-xl p-4 border border-slate-200">
                <p class="text-xs font-bold text-slate-600 mb-1 flex items-center gap-1.5">
                    <span class="material-symbols-outlined text-sm text-slate-400">info</span>
                    ملاحظة
                </p>
                <p class="text-xs text-slate-500 leading-relaxed">
                    التغيير يُطبَّق على جميع القضايا المستقبلية. القضايا الجارية حالياً لن تتأثر.
                </p>
            </div>
        </div>

        {{-- Footer --}}
        <div class="flex items-center gap-3 px-5 py-4 border-t border-slate-200 bg-slate-50">
            <button onclick="saveSystemMessage()" id="sysMsgSaveBtn"
                    class="flex-1 flex items-center justify-center gap-2 px-4 py-2.5 bg-violet-600 text-white text-sm font-bold rounded-xl hover:bg-violet-700 transition-colors active:scale-95">
                <span class="material-symbols-outlined text-sm">save</span>
                حفظ التغييرات
            </button>
            <button onclick="closeSystemMessageEditor()"
                    class="px-4 py-2.5 text-sm font-medium text-slate-600 bg-white border border-slate-200 rounded-xl hover:bg-slate-50 transition-colors">
                إلغاء
            </button>
        </div>
    </div>
</div>
