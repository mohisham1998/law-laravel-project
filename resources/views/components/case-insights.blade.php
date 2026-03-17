@props(['case'])
@php
    $m = $case->metrics;
    $duration = $m ? (int) $m->total_duration_seconds : 0;
    $tokens = $m ? (int) $m->total_tokens : 0;
    $statutes = $m ? (int) $m->statutes_matched : 0;
    $confidence = $m ? (float) $m->average_confidence : 0;
    $corrections = $m ? (int) $m->corrections_count : 0;
    $itemsForReview = $m && is_array($m->items_for_review) ? $m->items_for_review : [];
@endphp
<div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
    <h3 class="font-bold mb-4 flex items-center gap-2">
        <span class="material-symbols-outlined text-primary">insights</span>
        رؤى القضية
    </h3>
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
        <div class="p-3 bg-slate-50 rounded-xl">
            <p class="text-xs text-slate-500">إجمالي وقت المعالجة</p>
            <p class="font-bold text-primary">{{ $duration }} ثانية</p>
        </div>
        <div class="p-3 bg-slate-50 rounded-xl">
            <p class="text-xs text-slate-500">عدد المواد المطابقة</p>
            <p class="font-bold text-primary">{{ $statutes }}</p>
        </div>
        <div class="p-3 bg-slate-50 rounded-xl">
            <p class="text-xs text-slate-500">متوسط الثقة</p>
            <div class="flex items-center gap-2 mt-1">
                <div class="flex-1 h-2 bg-slate-200 rounded-full overflow-hidden">
                    <div class="h-full bg-primary rounded-full" style="width: {{ min(100, (int) ($confidence * 100)) }}%"></div>
                </div>
                <span class="font-bold text-sm">{{ number_format($confidence * 100, 0) }}%</span>
            </div>
        </div>
        <div class="p-3 bg-slate-50 rounded-xl">
            <p class="text-xs text-slate-500">عدد التصحيحات</p>
            <p class="font-bold text-primary">{{ $corrections }}</p>
        </div>
    </div>
    @if($tokens > 0)
        <p class="text-sm text-slate-500 mt-3">إجمالي الرموز: {{ number_format($tokens) }}</p>
    @endif
    @if(count($itemsForReview) > 0)
        <div class="mt-4">
            <p class="text-sm font-semibold text-slate-700 mb-2">عناصر للمراجعة</p>
            <ul class="list-disc list-inside text-sm text-slate-600 space-y-1">
                @foreach(array_slice($itemsForReview, 0, 10) as $item)
                    <li>{{ is_array($item) ? ($item['label'] ?? json_encode($item)) : $item }}</li>
                @endforeach
            </ul>
        </div>
    @endif
</div>
