@extends('layouts.app')

@section('title', $case->title ?? 'تفاصيل القضية')

@section('content')
{{-- Breadcrumb: back on the left, current page on the right (no duplicate arrow) --}}
<div class="flex justify-between items-center mb-6">
    <a href="{{ route('cases.index') }}" class="flex items-center gap-1.5 text-sm text-slate-600 hover:text-primary transition-colors" title="العودة للقضايا">
        <span class="material-symbols-outlined text-lg">arrow_forward</span>
        <span>العودة</span>
    </a>
    <span class="text-sm text-slate-900 font-semibold">{{ $case->title ?? 'تفاصيل القضية' }}</span>
</div>

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
                    @if($case->status == 'phase3_completed') bg-emerald-100 text-emerald-700
                    @elseif($case->status == 'phase2_processing') bg-amber-100 text-amber-700
                    @else bg-blue-100 text-blue-700
                    @endif
                ">
                    @if($case->status == 'phase3_completed') مكتملة
                    @elseif($case->status == 'phase2_processing') قيد التحليل
                    @else جديدة
                    @endif
                </span>
            </div>
            @if($case->model_used ?? null)
                <p class="text-sm text-slate-500 mt-1">النموذج: {{ $case->model_used }}</p>
            @endif

            <div class="prose prose-slate max-w-none">
                <h4 class="font-bold text-slate-900">وصف القضية</h4>
                <p class="text-slate-600">{{ $case->intake_text ?? 'لا يوجد وصف متاح' }}</p>
            </div>
        </div>
        
        {{-- Phase 2 Approval Modal (shows when awaiting_laws) --}}
        @include('components.phase2-approval-modal', ['case' => $case])

        {{-- Live Agent Dashboard (real-time streaming with auto-refresh) --}}
        @include('components.agent-timeline-live', ['case' => $case])
        @include('components.agent-output-panel', ['case' => $case, 'agentNumber' => $case->agentExecutions?->whereIn('status', ['in_progress', 'retrying'])->first()?->agent_number ?? 3, 'content' => $sampleOutputText ?? ''])
        @include('components.output-chain', ['case' => $case])
        @include('components.pdf-export-button', ['case' => $case])

        @if(in_array($case->status->value ?? $case->status, ['phase2_completed', 'phase3_completed'], true))
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
        {{-- Quick Actions --}}
        <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
            <h3 class="font-bold mb-4">إجراءات سريعة</h3>
            <div class="space-y-2">
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
        
        {{-- AI Insights --}}
        <div class="bg-gradient-to-br from-primary to-emerald-800 p-6 rounded-xl text-white shadow-lg">
            <div class="flex items-center gap-2 mb-3">
                <span class="material-symbols-outlined">auto_awesome</span>
                <span class="font-bold">توصيات الذكاء الاصطناعي</span>
            </div>
            <p class="text-sm opacity-90">يُنصح بمراجعة نظام العمل المادة ٧٧ لتعزيز موقف القضية.</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    var caseId = @json($case->id);
    var status = @json($case->status->value ?? $case->status);
    var processing = ['phase1_processing', 'phase1_pending', 'phase2_processing', 'phase2_pending'].indexOf(status) !== -1;
    if (!processing) return;
    var url = @json(route('cases.stream', $case));
    var es = new EventSource(url);
    var typewriterSpeed = 80;
    es.onmessage = function(ev) {
        try {
            var d = JSON.parse(ev.data);
            if (d.event_type === 'agent.started') {
                console.log('Agent started', d.agent_number);
            } else if (d.event_type === 'agent.output' && d.content) {
                console.log('Output', d.agent_number, d.content);
            } else if (d.event_type === 'agent.completed') {
                console.log('Agent completed', d.agent_number);
            } else if (d.event_type === 'agent.failed') {
                console.log('Agent failed', d.agent_number);
            }
        } catch (e) { console.warn(e); }
    };
    es.onerror = function() { es.close(); };
})();
</script>
@endpush
@endsection
