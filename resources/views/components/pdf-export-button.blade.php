@props(['case'])
@php
    $status = $case->status->value ?? $case->status;
    $enabled = in_array($status, ['phase2_completed', 'phase3_completed', 'completed_with_warnings'], true);
@endphp
<div id="outputModalBtnContainer">
    @if($enabled)
        <button type="button"
                onclick="openOutputModal()"
                class="w-full flex items-center gap-3 p-3 bg-primary/10 rounded-xl hover:bg-primary/20 transition-colors text-primary font-semibold"
                id="outputModalBtnEl">
            <span class="material-symbols-outlined">article</span>
            <span class="text-sm">عرض النتائج</span>
        </button>
    @else
        <span class="w-full flex items-center gap-3 p-3 bg-slate-100 rounded-xl cursor-not-allowed text-slate-400"
              title="تتوفر النتائج بعد اكتمال المعالجة">
            <span class="material-symbols-outlined">article</span>
            <span class="text-sm">عرض النتائج (غير متاح)</span>
        </span>
    @endif
</div>
