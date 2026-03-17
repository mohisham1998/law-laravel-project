@props(['case'])
@php
    $status = $case->status->value ?? $case->status;
    $enabled = in_array($status, ['phase2_completed', 'phase3_completed'], true);
@endphp
<div class="mt-4">
    @if($enabled)
        <a href="{{ route('cases.pdf', $case) }}" target="_blank" rel="noopener"
           class="inline-flex items-center gap-2 px-4 py-2.5 bg-primary text-white font-bold rounded-xl shadow-sm hover:bg-primary/90 transition-colors">
            <span class="material-symbols-outlined">picture_as_pdf</span>
            تصدير PDF
        </a>
    @else
        <span class="inline-flex items-center gap-2 px-4 py-2.5 bg-slate-200 text-slate-500 font-bold rounded-xl cursor-not-allowed"
              title="يتوفر تصدير PDF بعد اكتمال المعالجة">
            <span class="material-symbols-outlined">picture_as_pdf</span>
            تصدير PDF (غير متاح)
        </span>
    @endif
</div>
