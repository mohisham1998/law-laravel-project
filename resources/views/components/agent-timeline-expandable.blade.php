@php
    use App\Services\AgentDefinitions;
    $definitions = AgentDefinitions::all();
    $executionsByNumber = $case->agentExecutions?->keyBy('agent_number') ?? collect();
    $outputsByAgent = $case->outputs?->groupBy('agent_number') ?? collect();
    $hasFailed = $executionsByNumber->contains(fn ($ex) => (is_object($ex->status) ? $ex->status->value : $ex->status) === 'failed');
    $currentStep = 1;
    $totalSteps = count($definitions);
    foreach ($definitions as $def) {
        $ex = $executionsByNumber->get($def['number']);
        if ($ex && in_array($ex->status->value ?? $ex->status, ['in_progress', 'retrying'], true)) {
            $currentStep = $def['number'] + 1;
            break;
        }
        if ($ex && ($ex->status->value ?? $ex->status) === 'completed') {
            $currentStep = $def['number'] + 2;
        }
    }
    $progressPercent = $totalSteps > 0 ? (int) round(($currentStep / $totalSteps) * 100) : 0;
@endphp
<div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
    <div class="flex justify-between items-center mb-4">
        <h3 class="font-bold flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">psychology</span>
            مراحل التحليل الذكي
        </h3>
        <span class="text-sm font-bold text-slate-600">الخطوة {{ $currentStep }} من {{ $totalSteps }}</span>
    </div>
    <div class="h-2 bg-slate-100 rounded-full mb-6 overflow-hidden">
        <div class="h-full bg-primary rounded-full transition-all duration-300" style="width: {{ $progressPercent }}%"></div>
    </div>

    <div class="space-y-2">
        @foreach ($definitions as $def)
            @php
                $ex = $executionsByNumber->get($def['number']);
                $status = $ex ? (is_object($ex->status) ? $ex->status->value : $ex->status) : 'pending';
                $isLocked = $def['phase'] === 3 && $case->phase < 3;
                $displayStatus = $isLocked ? 'locked' : $status;
                $duration = $ex && $ex->duration_ms ? round($ex->duration_ms / 1000, 1) : null;
                $agentOutputs = $outputsByAgent->get($def['number'], collect());
                $hasOutputs = $agentOutputs->isNotEmpty();
                $agentId = 'agent-' . $def['number'];
            @endphp
            <div class="border rounded-xl transition-colors
                @if($displayStatus === 'completed') border-emerald-200
                @elseif($displayStatus === 'in_progress' || $displayStatus === 'retrying') border-amber-200
                @elseif($displayStatus === 'failed') border-red-200
                @elseif($displayStatus === 'locked') border-slate-200
                @else border-slate-200
                @endif">
                
                {{-- Agent Header (clickable to expand) --}}
                <button 
                    type="button"
                    onclick="document.getElementById('{{ $agentId }}').classList.toggle('hidden')"
                    class="w-full flex items-center gap-4 p-3 text-right hover:bg-slate-50 transition-colors
                        @if($displayStatus === 'completed') bg-emerald-50
                        @elseif($displayStatus === 'in_progress' || $displayStatus === 'retrying') bg-amber-50 animate-pulse
                        @elseif($displayStatus === 'failed') bg-red-50
                        @elseif($displayStatus === 'locked') bg-slate-100
                        @else bg-slate-50
                        @endif">
                    <div class="flex-shrink-0 size-9 rounded-full flex items-center justify-center
                        @if($displayStatus === 'completed') bg-emerald-500 text-white
                        @elseif($displayStatus === 'in_progress' || $displayStatus === 'retrying') bg-amber-500 text-white
                        @elseif($displayStatus === 'failed') bg-red-500 text-white
                        @elseif($displayStatus === 'locked') bg-slate-400 text-white
                        @else bg-slate-300 text-slate-600
                        @endif">
                        @if($displayStatus === 'completed')
                            <span class="material-symbols-outlined text-lg">check</span>
                        @elseif($displayStatus === 'in_progress' || $displayStatus === 'retrying')
                            <span class="material-symbols-outlined text-lg animate-spin">sync</span>
                        @elseif($displayStatus === 'failed')
                            <span class="material-symbols-outlined text-lg">error</span>
                        @elseif($displayStatus === 'locked')
                            <span class="material-symbols-outlined text-lg">lock</span>
                        @else
                            <span class="material-symbols-outlined text-lg">schedule</span>
                        @endif
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
                        <div class="text-xs font-bold">
                            @if($displayStatus === 'completed' && $duration)
                                {{ $duration }} ثانية
                            @elseif($displayStatus === 'in_progress' || $displayStatus === 'retrying')
                                <span class="text-amber-600">جارٍ...</span>
                            @elseif($displayStatus === 'failed')
                                <span class="text-red-600">فشل</span>
                            @elseif($displayStatus === 'locked')
                                <span class="text-slate-500">مقفل</span>
                            @else
                                <span class="text-slate-400">في الانتظار</span>
                            @endif
                        </div>
                        @if($hasOutputs)
                            <span class="material-symbols-outlined text-slate-400 text-base">expand_more</span>
                        @endif
                    </div>
                </button>

                {{-- Agent Outputs (expandable) --}}
                @if($hasOutputs)
                    <div id="{{ $agentId }}" class="hidden border-t border-slate-200">
                        <div class="p-4 bg-slate-50/50 space-y-3">
                            <h4 class="text-xs font-bold text-slate-700 uppercase flex items-center gap-1">
                                <span class="material-symbols-outlined text-sm">description</span>
                                مخرجات الوكيل
                            </h4>
                            @foreach($agentOutputs as $output)
                                <div class="bg-white rounded-lg border border-slate-200 p-3">
                                    <div class="flex items-center justify-between mb-2">
                                        <span class="text-xs font-bold text-slate-700">{{ $output->output_file }}</span>
                                        @if($output->file_size)
                                            <span class="text-xs text-slate-500">{{ number_format($output->file_size / 1024, 1) }} KB</span>
                                        @endif
                                    </div>
                                    @if($output->content_preview)
                                        <div class="text-xs text-slate-600 font-mono bg-slate-50 p-2 rounded border border-slate-200 max-h-32 overflow-y-auto" style="direction: rtl; white-space: pre-wrap;">{{ Str::limit($output->content_preview, 300) }}</div>
                                    @endif
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
            </div>
        @endforeach
    </div>
    @if($hasFailed)
        <div class="mt-4 p-3 bg-red-50 rounded-xl border border-red-200 flex flex-wrap gap-3 items-center">
            <span class="text-sm font-semibold text-red-800">فشل أحد الوكلاء. يمكنك إعادة المحاولة أو إلغاء القضية.</span>
            <form method="post" action="{{ route('cases.retry-agent', $case) }}" class="inline">
                @csrf
                <button type="submit" class="px-3 py-1.5 bg-primary text-white text-sm font-bold rounded-lg hover:bg-primary/90">إعادة المحاولة</button>
            </form>
            <form method="post" action="{{ route('cases.abort', $case) }}" class="inline" onsubmit="return confirm('هل تريد إلغاء القضية؟');">
                @csrf
                <button type="submit" class="px-3 py-1.5 bg-red-600 text-white text-sm font-bold rounded-lg hover:bg-red-700">إلغاء</button>
            </form>
        </div>
    @endif
</div>
