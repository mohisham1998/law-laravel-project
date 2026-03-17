@props(['case', 'agentNumber' => null, 'content' => ''])
@php
    $agentNumber = $agentNumber ?? 0;
    $defaultOpen = $agentNumber === 3;
@endphp
<div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
    <details class="group" {{ $defaultOpen ? 'open' : '' }}>
        <summary class="list-none cursor-pointer flex items-center justify-between text-right">
            <span class="font-bold flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">terminal</span>
                مخرجات الوكيل
            </span>
            <span class="material-symbols-outlined transition-transform group-open:rotate-180">expand_more</span>
        </summary>
        <div class="mt-4">
        <div class="bg-slate-50 rounded-xl p-4 font-mono text-sm text-slate-800 whitespace-pre-wrap break-words min-h-[120px] max-h-96 overflow-y-auto" dir="ltr">
            @if($content)
                {{-- Syntax hints: CASE: and LAW: references can be styled --}}
                {!! preg_replace_callback(
                    '/\b(CASE:[a-zA-Z0-9_-]+|LAW:[a-zA-Z0-9_-]+)/',
                    fn ($m) => '<span class="text-primary font-semibold">' . e($m[1]) . '</span>',
                    e($content)
                ) !!}
            @else
                <span class="text-slate-400 italic">الكتابة مباشرة... (سيظهر النص هنا مع تأثير الآلة الكاتبة)</span>
            @endif
        </div>
        <p class="text-xs text-slate-500 mt-2">CASE:xxx و LAW:xxx للمراجع القانونية</p>
        <button type="button" class="mt-2 text-sm text-primary font-semibold hover:underline">عرض المزيد</button>
        </div>
    </details>
</div>
