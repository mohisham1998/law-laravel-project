@extends('layouts.app')

@section('title', $case->title ?? 'تفاصيل القضية')

@section('content')
@php $statusVal = $case->status->value ?? $case->status; @endphp
@if(session('success'))
    <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center gap-3">
        <span class="material-symbols-outlined text-emerald-600">check_circle</span>
        <p class="font-medium text-emerald-800">{{ session('success') }}</p>
    </div>
@endif
@if(session('error'))
    <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 flex items-center gap-3">
        <span class="material-symbols-outlined text-red-600">error</span>
        <p class="font-medium text-red-800">{{ session('error') }}</p>
    </div>
@endif
@if(session('info'))
    <div class="mb-6 p-4 rounded-xl bg-blue-50 border border-blue-200 flex items-center gap-3">
        <span class="material-symbols-outlined text-blue-600">info</span>
        <p class="font-medium text-blue-800">{{ session('info') }}</p>
    </div>
@endif
{{-- Breadcrumb: back on the left, current page on the right (no duplicate arrow) --}}
<div class="flex justify-between items-center mb-6">
    <a href="{{ route('cases.index') }}" class="flex items-center gap-1.5 text-sm text-slate-600 hover:text-primary transition-colors" title="العودة للقضايا">
        <span class="material-symbols-outlined text-lg">arrow_forward</span>
        <span>العودة</span>
    </a>
    <span class="text-sm text-slate-900 font-semibold">{{ $case->title ?? 'تفاصيل القضية' }}</span>
</div>

{{-- Pipeline Tracker (full-width, above grid) --}}
@include('components.pipeline-tracker', ['case' => $case, 'statusVal' => $statusVal])

{{-- Phase 3 Gate: Judicial Arbitration (full-width, prominent banner) --}}
@if(in_array($statusVal, ['phase2_completed', 'completed_with_warnings']) && $case->phase < 3)
<div id="phase3GateBanner" class="bg-gradient-to-r from-indigo-50 to-indigo-100 p-6 rounded-xl border-2 border-indigo-300 shadow-sm mb-6">
    <div class="flex items-center gap-4 flex-wrap">
        <div class="flex-shrink-0 w-12 h-12 bg-indigo-200 rounded-full flex items-center justify-center">
            <span class="material-symbols-outlined text-indigo-700 text-2xl">balance</span>
        </div>
        <div class="flex-1 min-w-0">
            <h3 class="font-bold text-lg text-slate-900 mb-1">المرحلة الثالثة — التحكيم القضائي</h3>
            <p class="text-sm text-slate-600">
                اكتملت المرحلة الثانية بنجاح. يمكنك الآن تشغيل المرحلة الثالثة: القاضي → محامي الخصم → وكيل التحصين.
            </p>
        </div>
        <form action="{{ route('cases.start-phase3', $case) }}" method="POST" class="flex-shrink-0 puter-form">
            @csrf
            <input type="hidden" name="puter_token" class="puter-token-input" value="">
            <button type="submit" class="flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 active:scale-95 transition-all shadow-lg text-base">
                <span class="material-symbols-outlined text-xl">gavel</span>
                بدء التحكيم القضائي
            </button>
        </form>
    </div>
</div>
@endif

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    {{-- Main Content --}}
    <div class="lg:col-span-2 space-y-6">
        {{-- Case Header --}}
        <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h1 class="text-2xl font-black mb-2">{{ $case->title ?? 'عنوان القضية' }}</h1>
                    <div class="flex items-center gap-4 text-sm text-slate-500">
                        <span class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">calendar_today</span>
                            {{ $case->created_at?->format('Y-m-d') ?? '---' }}
                        </span>
                        <span class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">person</span>
                            {{ $case->client_name ?? 'غير محدد' }}
                        </span>
                    </div>
                </div>
                <span class="px-4 py-2 rounded-full text-sm font-bold
                    @if($statusVal === 'phase3_completed' || $statusVal === 'completed_with_warnings') bg-emerald-100 text-emerald-700
                    @elseif(in_array($statusVal, ['phase1_pending', 'phase1_processing', 'phase2_pending', 'phase2_processing', 'phase3_pending', 'phase3_processing'], true)) bg-amber-100 text-amber-700
                    @elseif($statusVal === 'awaiting_laws') bg-blue-100 text-blue-700
                    @elseif($statusVal === 'failed' || $statusVal === 'paused') bg-red-100 text-red-700
                    @elseif($statusVal === 'halted' || $statusVal === 'timed_out') bg-orange-100 text-orange-700
                    @else bg-blue-100 text-blue-700
                    @endif
                ">
                    @if($statusVal === 'completed_with_warnings') مكتملة بتحذيرات
                    @elseif($statusVal === 'phase3_completed') مكتملة
                    @elseif($statusVal === 'phase1_pending' || $statusVal === 'phase1_processing') جاري التحليل...
                    @elseif($statusVal === 'phase2_pending' || $statusVal === 'phase2_processing') قيد التحليل (المرحلة ٢)
                    @elseif($statusVal === 'awaiting_laws') بانتظار الموافقة
                    @elseif($statusVal === 'phase2_completed') المرحلة ٢ مكتملة
                    @elseif($statusVal === 'phase3_pending' || $statusVal === 'phase3_processing') قيد التحكيم (المرحلة ٣)
                    @elseif($statusVal === 'failed') فشل
                    @elseif($statusVal === 'paused') متوقف
                    @elseif($statusVal === 'halted') توقف المعالجة
                    @elseif($statusVal === 'timed_out') انتهت المهلة
                    @else جديدة
                    @endif
                </span>
            </div>
            @if($case->model_used ?? null)
                <p class="text-sm text-slate-500 mt-1">النموذج: {{ $case->model_used }}</p>
            @endif

            @if(in_array($statusVal, ['phase1_pending', 'phase1_processing', 'phase2_pending', 'phase2_processing', 'phase3_pending', 'phase3_processing'], true))
                <div class="mt-4 p-4 rounded-xl bg-amber-50 border border-amber-200 flex items-center gap-3">
                    <span class="material-symbols-outlined text-amber-600 animate-pulse">progress_activity</span>
                    <div>
                        <p class="font-semibold text-amber-800">
                            @if(in_array($statusVal, ['phase3_pending', 'phase3_processing']))
                                جاري التحكيم القضائي (المرحلة الثالثة)
                            @else
                                جاري تحليل القضية
                            @endif
                        </p>
                        <p class="text-sm text-amber-700">يتم عرض المخرجات بشكل مباشر أدناه.</p>
                    </div>
                </div>
            @endif

            @include('pages.cases.show-retry-section', ['case' => $case, 'statusVal' => $statusVal])

            <div class="prose prose-slate max-w-none">
                <h4 class="font-bold text-slate-900">وصف القضية</h4>
                <p class="text-slate-600">{{ $case->intake_text ?? 'لا يوجد وصف متاح' }}</p>
            </div>
        </div>
        
        {{-- Phase 2 Approval Modal (shows when awaiting_laws) --}}
        @include('components.phase2-approval-modal', ['case' => $case])

        {{-- Phase 3 gate moved to full-width above grid (see above) --}}

        {{-- Live Agent Dashboard (real-time streaming with auto-refresh) --}}
        @include('components.agent-timeline-live', ['case' => $case, 'statusVal' => $statusVal])
        {{-- agent-output-panel removed (FR-003: single consolidated output in agent cards) --}}
        {{-- output-chain removed (superseded by pipeline-tracker) --}}
        {{-- pdf-export-button moved to sidebar Quick Actions (FR-008) --}}

        @if(in_array($case->status->value ?? $case->status, ['phase2_completed', 'phase3_completed', 'completed_with_warnings'], true))
            @include('components.case-insights', ['case' => $case])
        @endif

        {{-- Required laws (informational; analysis uses RAG law library) --}}
        @if($case->requiredLaws && $case->requiredLaws->count())
        <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
            <h3 class="font-bold mb-2 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">gavel</span>
                الأنظمة المطلوبة للتحليل
            </h3>
            <p class="text-sm text-slate-500 mb-3">التحليل يستخدم مكتبة الأنظمة والقوانين (الأنظمة المعرّفة في النظام)، وليس مرفقات خاصة بهذه القضية.</p>
            <ul class="flex flex-wrap gap-2">
                @foreach($case->requiredLaws as $rl)
                    <li class="px-3 py-1.5 bg-slate-100 rounded-lg text-sm font-medium text-slate-700">{{ $rl->law_name }}</li>
                @endforeach
            </ul>
        </div>
        @endif

        {{-- Documents --}}
        <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">description</span>
                    المستندات المرفقة
                </h3>
                <a href="{{ route('documents.index', ['case_id' => $case->id]) }}" class="text-primary text-sm font-bold flex items-center gap-1 hover:underline">
                    <span class="material-symbols-outlined text-sm">folder_open</span>
                    عرض في المستندات
                </a>
            </div>
            
            @if($case->documents && $case->documents->count())
                <div class="space-y-2">
                    @foreach($case->documents as $doc)
                        <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl">
                            @if($doc->isPdf())
                                <span class="material-symbols-outlined text-red-500">picture_as_pdf</span>
                            @elseif($doc->isImage())
                                <span class="material-symbols-outlined text-emerald-600">image</span>
                            @else
                                <span class="material-symbols-outlined text-slate-500">description</span>
                            @endif
                            <span class="text-sm font-semibold flex-1 truncate">{{ $doc->filename }}</span>
                            <a href="{{ route('documents.preview', $doc) }}" target="_blank" rel="noopener" class="text-primary" title="معاينة">
                                <span class="material-symbols-outlined">visibility</span>
                            </a>
                            <a href="{{ route('documents.download', $doc) }}" class="text-primary" title="تحميل">
                                <span class="material-symbols-outlined">download</span>
                            </a>
                        </div>
                    @endforeach
                </div>
            @else
                <p class="text-center text-slate-400 py-8">لا توجد مستندات مرفقة. يمكنك رفع مرفقات من صفحة المستندات.</p>
            @endif
        </div>
    </div>
    
    {{-- Sidebar --}}
    <div class="space-y-6">
        {{-- Retry/Resume Card (only for failed/paused/halted/timed_out) --}}
        @if(in_array($statusVal, ['failed', 'paused', 'halted', 'timed_out'], true))
        @php
            $haltedAtAgent = $case->halted_at_agent ?? $case->current_agent ?? null;
            $canResume = $haltedAtAgent && $haltedAtAgent > 1;
        @endphp
        <div class="rounded-xl shadow-lg overflow-hidden border border-red-200">
            {{-- Header --}}
            <div class="bg-gradient-to-br from-red-500 to-red-600 p-5 text-white">
                <div class="flex items-center gap-2 mb-1">
                    <span class="material-symbols-outlined text-2xl">
                        @if(in_array($statusVal, ['halted', 'timed_out'])) stop_circle @else error @endif
                    </span>
                    <span class="font-bold text-base">
                        @if($statusVal === 'halted') توقف عند الوكيل {{ $haltedAtAgent }}
                        @elseif($statusVal === 'timed_out') انتهت مهلة المعالجة
                        @else القضية فشلت
                        @endif
                    </span>
                </div>
                <p class="text-xs opacity-80">المخرجات السابقة محفوظة</p>
                @if($case->last_error_message)
                <p class="text-xs mt-1 bg-white/20 rounded px-2 py-1">{{ $case->last_error_message }}</p>
                @endif
            </div>

            {{-- Actions --}}
            <div class="bg-white p-4 space-y-2">
                @if($canResume)
                {{-- Resume: green, primary --}}
                <form action="{{ route('cases.resume', $case) }}" method="POST" class="puter-form">
                    @csrf
                    <input type="hidden" name="puter_token" class="puter-token-input" value="">
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-2 bg-emerald-600 text-white font-bold py-3 rounded-xl hover:bg-emerald-700 active:scale-95 transition-all shadow-md">
                        <span class="material-symbols-outlined">play_arrow</span>
                        <span>استئناف من الوكيل {{ $haltedAtAgent }}</span>
                    </button>
                </form>
                <p class="text-xs text-slate-500 text-center px-1">يستأنف من حيث توقف — مخرجات الوكلاء السابقين محفوظة</p>
                @endif

                {{-- Full retry: secondary --}}
                <form action="{{ route('cases.retry-agent', $case) }}" method="POST" class="puter-form">
                    @csrf
                    <input type="hidden" name="puter_token" class="puter-token-input" value="">
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-2 {{ $canResume ? 'bg-slate-100 text-slate-600 hover:bg-slate-200 text-sm' : 'bg-white border-2 border-red-500 text-red-600 hover:bg-red-50 font-bold' }} py-2.5 rounded-xl active:scale-95 transition-all">
                        <span class="material-symbols-outlined {{ $canResume ? 'text-sm' : '' }}">refresh</span>
                        <span>إعادة من البداية</span>
                    </button>
                </form>
            </div>
        </div>
        @endif
        
        {{-- Quick Actions --}}
        <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
            <h3 class="font-bold mb-4">إجراءات سريعة</h3>
            <div class="space-y-2">
                @include('components.pdf-export-button', ['case' => $case])

                {{-- Model Configuration Button --}}
                <button onclick="openModelConfig()"
                        class="w-full flex items-center gap-3 p-3 bg-slate-50 rounded-xl hover:bg-primary/10 transition-colors cursor-pointer group">
                    <span class="material-symbols-outlined text-primary">memory</span>
                    <div class="flex-1 text-right">
                        <span class="text-sm font-semibold block">إعداد نماذج الوكلاء</span>
                        <span class="text-xs text-slate-400">{{ $case->model_used ?? config('openrouter.default_model') }}</span>
                    </div>
                    @if(!empty($case->agent_model_overrides))
                        <span class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-md font-bold flex-shrink-0">
                            {{ count($case->agent_model_overrides) }} مخصص
                        </span>
                    @endif
                </button>

                <a href="{{ route('cases.timeline', $case) }}" class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl hover:bg-primary/10 transition-colors">
                    <span class="material-symbols-outlined text-primary">timeline</span>
                    <span class="text-sm font-semibold">عرض الجدول الزمني</span>
                </a>
                <button class="w-full flex items-center gap-3 p-3 bg-slate-50 rounded-xl hover:bg-primary/10 transition-colors">
                    <span class="material-symbols-outlined text-primary">edit</span>
                    <span class="text-sm font-semibold">تعديل القضية</span>
                </button>
                <button class="w-full flex items-center gap-3 p-3 bg-red-50 rounded-xl hover:bg-red-100 transition-colors text-red-600">
                    <span class="material-symbols-outlined">delete</span>
                    <span class="text-sm font-semibold">حذف القضية</span>
                </button>
            </div>
        </div>
        
        {{-- AI Insights (dynamic from QA summary or lead counsel) --}}
        @php
            $aiRecommendation = null;
            // Try QA summary first (agent 9)
            $qaOutput = $case->outputs->where('agent_number', 9)->whereIn('content_type', ['markdown', 'md'])->sortByDesc('id')->first();
            if ($qaOutput && !empty(trim($qaOutput->content ?? ''))) {
                $aiRecommendation = \Illuminate\Support\Str::limit(strip_tags($qaOutput->content), 200);
            }
            // Fallback to lead counsel plan (agent 1)
            if (!$aiRecommendation) {
                $leadOutput = $case->outputs->where('agent_number', 1)->where('content_type', 'markdown')->first();
                if ($leadOutput && !empty(trim($leadOutput->content ?? ''))) {
                    $aiRecommendation = \Illuminate\Support\Str::limit(strip_tags($leadOutput->content), 200);
                }
            }
            // Final fallback: case analysis (agent 0)
            if (!$aiRecommendation) {
                $analysisOutput = $case->outputs->where('agent_number', 0)->where('content_type', 'markdown')->first();
                if ($analysisOutput && !empty(trim($analysisOutput->content ?? ''))) {
                    $aiRecommendation = \Illuminate\Support\Str::limit(strip_tags($analysisOutput->content), 200);
                }
            }
        @endphp
        @if($aiRecommendation)
        <div class="bg-gradient-to-br from-primary to-emerald-800 p-6 rounded-xl text-white shadow-lg">
            <div class="flex items-center gap-2 mb-3">
                <span class="material-symbols-outlined">auto_awesome</span>
                <span class="font-bold">توصيات الذكاء الاصطناعي</span>
            </div>
            <p class="text-sm opacity-90 leading-relaxed">{{ $aiRecommendation }}</p>
        </div>
        @elseif(in_array($statusVal, ['phase1_pending','phase1_processing','phase2_pending','phase2_processing','phase3_pending','phase3_processing']))
        <div class="bg-gradient-to-br from-slate-600 to-slate-700 p-6 rounded-xl text-white shadow-lg">
            <div class="flex items-center gap-2 mb-3">
                <span class="material-symbols-outlined animate-pulse">auto_awesome</span>
                <span class="font-bold">توصيات الذكاء الاصطناعي</span>
            </div>
            <p class="text-sm opacity-90">جارٍ تحليل القضية... ستظهر التوصيات بعد اكتمال المعالجة.</p>
        </div>
        @endif
    </div>
</div>

{{-- Model Config Drawer --}}
@include('components.agent-model-config', ['case' => $case])

{{-- Inject final brief for the output modal (server-composed, post-processed) --}}
@php
$finalBriefMd = null;
if (in_array($statusVal, ['phase3_completed', 'completed_with_warnings'])) {
    try {
        $finalBriefMd = \App\Services\Output\FinalArabicBriefComposer::compose($case);
    } catch (\Throwable $e) {
        $finalBriefMd = null;
    }
}
@endphp
<script>
window.finalBriefContent  = @json($finalBriefMd ?? '');
window.finalBriefAutoOpen = {{ json_encode(!empty($finalBriefMd)) }};
window.caseTitle          = @json($case->title ?? 'مذكرة قانونية');
</script>

{{-- Case Output Modal (Markdown results viewer) --}}
@include('components.case-output-modal', ['case' => $case])

{{-- No auto-reload - SSE provides real-time updates without page refresh --}}
@push('scripts')
<script>
// Inject Puter token into retry/resume form submits
document.querySelectorAll('form.puter-form').forEach(function(form) {
    form.addEventListener('submit', function() {
        try {
            if (typeof puter !== 'undefined' && puter.authToken) {
                form.querySelector('.puter-token-input').value = puter.authToken;
            }
        } catch(e) {}
    });
});
</script>
@endpush
@endsection
