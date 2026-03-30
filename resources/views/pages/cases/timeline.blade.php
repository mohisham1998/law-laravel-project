@extends('layouts.app')

@section('title', 'الجدول الزمني - ' . ($case->title ?? 'القضية'))

@section('content')
@php
    use App\Services\AgentDefinitions;
    $definitions = collect(AgentDefinitions::all())->keyBy('number');
    $executions = $case->agentExecutions->sortBy('started_at');
    $statusVal = $case->status->value ?? $case->status;
@endphp
{{-- Breadcrumb --}}
<div class="flex justify-between items-center mb-6">
    <a href="{{ route('cases.show', $case) }}" class="flex items-center gap-1.5 text-sm text-slate-600 hover:text-primary transition-colors" title="العودة للقضية">
        <span class="material-symbols-outlined text-lg">arrow_forward</span>
        <span>العودة للقضية</span>
    </a>
    <span class="text-sm text-slate-900 font-semibold">الجدول الزمني</span>
</div>

<div class="max-w-3xl">
    <h1 class="text-2xl font-black mb-2">الجدول الزمني للقضية</h1>
    <p class="text-slate-500 mb-8">{{ $case->title ?? 'القضية' }}</p>

    <div class="relative">
        <div class="absolute right-4 top-0 bottom-0 w-0.5 bg-primary/20"></div>

        <div class="space-y-6">
            {{-- Case Created --}}
            <div class="relative flex gap-6 pr-4">
                <div class="absolute right-2 w-4 h-4 rounded-full bg-primary border-4 border-white shadow"></div>
                <div class="flex-1 bg-white p-5 rounded-xl border border-primary/10 shadow-sm mr-6">
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="font-bold">تم إنشاء القضية</h4>
                        <span class="text-xs text-slate-500">{{ $case->created_at?->format('Y-m-d H:i') ?? '---' }}</span>
                    </div>
                    <p class="text-sm text-slate-600">تم إنشاء القضية وبدء عملية التحليل.</p>
                </div>
            </div>

            {{-- Agent Executions --}}
            @foreach($executions as $exec)
                @php
                    $agentNum = $exec->agent_number;
                    $def = $definitions->get($agentNum);
                    $agentName = $def ? $def['name'] . ' — ' . $def['name_en'] : 'وكيل ' . $agentNum;
                    $phase = $def['phase'] ?? 1;
                    $phaseLabel = match($phase) { 1 => 'م١', 2 => 'م٢', 3 => 'م٣', default => 'م' . $phase };
                    $st = $exec->status instanceof \BackedEnum ? $exec->status->value : (string) $exec->status;
                    $durationMs = $exec->duration_ms ?? 0;
                    $durationDisplay = $durationMs > 0
                        ? ($durationMs >= 60000 ? round($durationMs / 60000, 1) . ' د' : round($durationMs / 1000) . ' ث')
                        : null;
                    $tokensDisplay = $exec->total_tokens > 0 ? number_format($exec->total_tokens) : null;
                    $correctionsCount = $exec->corrections_count ?? 0;

                    // Colors by status
                    $dotColor = match($st) {
                        'completed' => 'bg-emerald-500',
                        'in_progress' => 'bg-amber-500 animate-pulse',
                        'failed' => 'bg-red-500',
                        default => 'bg-slate-300',
                    };
                    $borderColor = match($st) {
                        'completed' => 'border-emerald-200',
                        'in_progress' => 'border-amber-300 ring-2 ring-amber-100',
                        'failed' => 'border-red-200',
                        default => 'border-slate-200',
                    };
                    $titleColor = match($st) {
                        'completed' => 'text-emerald-700',
                        'in_progress' => 'text-amber-700',
                        'failed' => 'text-red-700',
                        default => 'text-slate-700',
                    };
                    $statusLabel = match($st) {
                        'completed' => 'مكتمل',
                        'in_progress' => 'جارٍ...',
                        'failed' => 'فشل',
                        'retrying' => 'إعادة المحاولة',
                        default => $st,
                    };
                @endphp
                <div class="relative flex gap-6 pr-4">
                    <div class="absolute right-2 w-4 h-4 rounded-full {{ $dotColor }} border-4 border-white shadow"></div>
                    <div class="flex-1 bg-white p-5 rounded-xl border {{ $borderColor }} shadow-sm mr-6">
                        <div class="flex justify-between items-start mb-2">
                            <h4 class="font-bold {{ $titleColor }} flex items-center gap-2">
                                <span class="text-xs font-bold px-1.5 py-0.5 rounded
                                    @if($phase === 1) text-blue-600 bg-blue-50
                                    @elseif($phase === 2) text-emerald-600 bg-emerald-50
                                    @else text-indigo-600 bg-indigo-50
                                    @endif">{{ $phaseLabel }}</span>
                                {{ $agentNum }}. {{ $agentName }}
                            </h4>
                            <span class="text-xs text-slate-500 whitespace-nowrap">{{ $exec->started_at?->format('H:i:s') ?? '---' }}</span>
                        </div>
                        <div class="flex items-center gap-3 text-xs text-slate-500 mt-1">
                            <span class="font-bold {{ $titleColor }}">{{ $statusLabel }}</span>
                            @if($durationDisplay)
                                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-xs">timer</span>{{ $durationDisplay }}</span>
                            @endif
                            @if($tokensDisplay)
                                <span class="flex items-center gap-1"><span class="material-symbols-outlined text-xs">token</span>{{ $tokensDisplay }} رمز</span>
                            @endif
                            @if($correctionsCount > 0)
                                <span class="flex items-center gap-1 text-amber-600"><span class="material-symbols-outlined text-xs">edit</span>{{ $correctionsCount }} تصحيح</span>
                            @endif
                        </div>
                        @if($st === 'failed' && $exec->error_message)
                            <p class="text-sm text-red-600 mt-2 bg-red-50 p-2 rounded">{{ \Illuminate\Support\Str::limit($exec->error_message, 150) }}</p>
                        @endif
                        @if(!empty($def['outputs']))
                            <div class="mt-2 flex flex-wrap gap-1">
                                @foreach($def['outputs'] as $outFile)
                                    <span class="text-[10px] px-1.5 py-0.5 bg-slate-100 text-slate-500 rounded">{{ $outFile }}</span>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </div>
            @endforeach

            {{-- Pending agents (not yet executed) --}}
            @php
                $executedAgents = $executions->pluck('agent_number')->toArray();
                $pendingDefs = $definitions->filter(fn($d) => !in_array($d['number'], $executedAgents));
            @endphp
            @foreach($pendingDefs as $def)
                <div class="relative flex gap-6 pr-4 opacity-40">
                    <div class="absolute right-2 w-4 h-4 rounded-full bg-slate-300 border-4 border-white shadow"></div>
                    <div class="flex-1 bg-slate-50 p-4 rounded-xl border border-slate-200 mr-6">
                        <h4 class="font-bold text-slate-400 text-sm">{{ $def['number'] }}. {{ $def['name'] }} — {{ $def['name_en'] }}</h4>
                        <p class="text-xs text-slate-400 mt-1">في الانتظار</p>
                    </div>
                </div>
            @endforeach

            {{-- Case completion marker --}}
            @if(in_array($statusVal, ['phase2_completed', 'phase3_completed', 'completed_with_warnings']))
                <div class="relative flex gap-6 pr-4">
                    <div class="absolute right-2 w-4 h-4 rounded-full bg-primary border-4 border-white shadow"></div>
                    <div class="flex-1 bg-primary/5 p-5 rounded-xl border border-primary/20 shadow-sm mr-6">
                        <h4 class="font-bold text-primary">
                            @if($statusVal === 'phase3_completed' || $statusVal === 'completed_with_warnings')
                                اكتملت جميع المراحل
                            @else
                                اكتملت المرحلة الثانية
                            @endif
                        </h4>
                        <p class="text-sm text-slate-600 mt-1">{{ $case->updated_at?->format('Y-m-d H:i') ?? '' }}</p>
                    </div>
                </div>
            @endif
            
            {{-- Failed case marker --}}
            @if($statusVal === 'failed')
                <div class="relative flex gap-6 pr-4">
                    <div class="absolute right-2 w-4 h-4 rounded-full bg-red-500 border-4 border-white shadow"></div>
                    <div class="flex-1 bg-red-50 p-5 rounded-xl border border-red-200 shadow-sm mr-6">
                        <h4 class="font-bold text-red-600">فشلت المعالجة</h4>
                        @if($case->last_error_message)
                            <p class="text-sm text-red-600 mt-1">{{ $case->last_error_message }}</p>
                        @endif
                        <p class="text-sm text-slate-600 mt-1">{{ $case->updated_at?->format('Y-m-d H:i') ?? '' }}</p>
                    </div>
                </div>
            @endif
            
            {{-- Paused case marker --}}
            @if($statusVal === 'paused')
                <div class="relative flex gap-6 pr-4">
                    <div class="absolute right-2 w-4 h-4 rounded-full bg-amber-500 border-4 border-white shadow"></div>
                    <div class="flex-1 bg-amber-50 p-5 rounded-xl border border-amber-200 shadow-sm mr-6">
                        <h4 class="font-bold text-amber-600">تم إيقاف المعالجة مؤقتاً</h4>
                        <p class="text-sm text-slate-600 mt-1">يمكنك استئناف المعالجة لاحقاً</p>
                        <p class="text-sm text-slate-500 mt-1">{{ $case->updated_at?->format('Y-m-d H:i') ?? '' }}</p>
                    </div>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection
