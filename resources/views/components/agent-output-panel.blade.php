@props(['case', 'agentNumber' => null, 'content' => ''])
@php
    $agentNumber = $agentNumber ?? 0;
    $statusVal = $case->status->value ?? $case->status;
    $isProcessing = in_array($statusVal, ['phase1_pending', 'phase1_processing', 'phase2_pending', 'phase2_processing', 'phase3_pending', 'phase3_processing']);
    $defaultOpen = $isProcessing;
@endphp

{{-- Only show this panel during processing --}}
@if($isProcessing)
<div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm" id="mainOutputPanel">
    <details class="group" {{ $defaultOpen ? 'open' : '' }}>
        <summary class="list-none cursor-pointer flex items-center justify-between text-right">
            <span class="font-bold flex items-center gap-2">
                <span class="material-symbols-outlined text-primary animate-pulse">terminal</span>
                مخرجات الوكيل المباشر
                <span class="text-xs bg-amber-100 text-amber-700 px-2 py-0.5 rounded-full">مباشر</span>
            </span>
            <span class="material-symbols-outlined transition-transform group-open:rotate-180">expand_more</span>
        </summary>
        <div class="mt-4">
            <div class="bg-gradient-to-b from-slate-900 to-slate-800 rounded-xl p-5 font-mono text-sm text-emerald-400 whitespace-pre-wrap break-words min-h-[200px] max-h-[500px] overflow-y-auto shadow-inner" 
                 dir="rtl" 
                 id="liveOutputTerminal"
                 style="line-height: 1.8; font-family: 'Cairo', 'Menlo', monospace;">
                <div class="text-slate-500 text-xs mb-3 pb-3 border-b border-slate-700">
                    <span class="text-emerald-500">●</span> متصل بالخادم | جارٍ استقبال المخرجات...
                </div>
                <div id="liveOutputContent">
                    <span class="text-slate-500" id="liveOutputPlaceholder">في انتظار بدء التحليل...</span>
                </div>
                <span class="typewriter-cursor" id="mainCursor"></span>
            </div>
            <div class="flex items-center justify-between mt-3">
                <p class="text-xs text-slate-500">
                    <span class="inline-block w-2 h-2 bg-emerald-500 rounded-full animate-pulse mr-1"></span>
                    يتم عرض المخرجات بشكل مباشر أثناء التحليل
                </p>
                <button type="button" onclick="document.getElementById('liveOutputTerminal').scrollTop = 0" 
                        class="text-xs text-primary font-semibold hover:underline flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">vertical_align_top</span>
                    الانتقال للأعلى
                </button>
            </div>
        </div>
    </details>
</div>

<style>
#mainOutputPanel .typewriter-cursor {
    display: inline-block;
    width: 8px;
    height: 18px;
    background-color: #10b981;
    animation: mainBlink 0.8s infinite;
    vertical-align: text-bottom;
    margin-right: 2px;
}
@keyframes mainBlink {
    0%, 40% { opacity: 1; }
    41%, 100% { opacity: 0; }
}
#mainOutputPanel .streaming-text {
    animation: mainFadeIn 0.15s ease-out;
}
@keyframes mainFadeIn {
    from { opacity: 0; transform: translateX(3px); }
    to { opacity: 1; transform: translateX(0); }
}
#mainOutputPanel .correction-badge {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    background: rgba(245, 158, 11, 0.15);
    border: 1px solid rgba(245, 158, 11, 0.4);
    border-radius: 8px;
    padding: 6px 12px;
    margin: 8px 0;
    font-size: 12px;
    color: #d97706;
    animation: mainFadeIn 0.3s ease-out;
}
</style>

<script>
(function() {
    var liveOutput = document.getElementById('liveOutputContent');
    var placeholder = document.getElementById('liveOutputPlaceholder');
    var terminal = document.getElementById('liveOutputTerminal');
    var mainCursor = document.getElementById('mainCursor');
    
    // Listen to SSE events dispatched by the timeline component (avoids duplicate connection)
    window.addEventListener('sse:agent.started', function(ev) {
        var d = ev.detail;
        if (placeholder) placeholder.style.display = 'none';
        var header = document.createElement('div');
        header.className = 'text-amber-400 mt-2 mb-1 font-bold';
        header.innerHTML = '▶ بدء تنفيذ: ' + d.agent_name;
        liveOutput.appendChild(header);
    });

    window.addEventListener('sse:agent.output', function(ev) {
        var d = ev.detail;
        if (!d.content) return;
        if (placeholder) placeholder.style.display = 'none';
        if (mainCursor && mainCursor.parentNode === liveOutput) {
            liveOutput.removeChild(mainCursor);
        }
        var span = document.createElement('span');
        span.className = 'streaming-text';
        // Simple text render for terminal (escape HTML only)
        span.textContent = d.content;
        liveOutput.appendChild(span);
        liveOutput.appendChild(mainCursor);
        terminal.scrollTop = terminal.scrollHeight;
    });

    window.addEventListener('sse:agent.completed', function(ev) {
        var d = ev.detail;
        var footer = document.createElement('div');
        footer.className = 'text-emerald-500 mt-2 mb-3 pb-2 border-b border-slate-700';
        var duration = d.metrics?.duration_ms ? (d.metrics.duration_ms / 1000).toFixed(1) + ' ثانية' : '';
        footer.innerHTML = '✓ اكتمل: ' + d.agent_name + (duration ? ' (' + duration + ')' : '');
        liveOutput.appendChild(footer);
        if (mainCursor) mainCursor.style.display = 'none';
    });

    window.addEventListener('sse:agent.failed', function(ev) {
        var d = ev.detail;
        var error = document.createElement('div');
        error.className = 'text-red-400 mt-2 bg-red-900/30 p-2 rounded';
        error.innerHTML = '✗ فشل: ' + (d.agent_name || '') + '<br><span class="text-xs">' + (d.error || '') + '</span>';
        liveOutput.appendChild(error);
        if (mainCursor) mainCursor.style.display = 'none';
    });

    window.addEventListener('sse:agent.correction', function(ev) {
        var d = ev.detail;
        var badge = document.createElement('div');
        badge.className = 'correction-badge';
        badge.innerHTML = '⟳ تصحيح ذاتي — المحاولة ' + d.attempt + ' من 3: ' + (d.violation_detail || d.violation_type || '');
        liveOutput.appendChild(badge);
        terminal.scrollTop = terminal.scrollHeight;
    });
})();
</script>
@endif
