@php
    use App\Services\AgentDefinitions;
    $agents = AgentDefinitions::all();
@endphp
<div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
    <details class="group">
        <summary class="list-none cursor-pointer flex items-center justify-between text-right">
            <span class="font-bold flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">account_tree</span>
                سلسلة المخرجات
            </span>
            <span class="material-symbols-outlined transition-transform group-open:rotate-180">expand_more</span>
        </summary>
        <div class="mt-4">
        <div class="space-y-1 text-sm font-medium text-slate-700">
            <div class="flex items-center gap-2">
                <span class="text-slate-500">intake.txt + docs/</span>
            </div>
            <div class="flex items-center justify-center text-slate-400">↓</div>
            @foreach ($agents as $a)
                <div class="flex items-center gap-2 pl-2 border-r-2 border-primary/20">
                    <span class="text-primary font-semibold">[{{ $a['number'] === 0 ? 'Phase 1' : 'Agent ' . $a['number'] }}]</span>
                    <span>→</span>
                    <span>{{ implode(', ', $a['outputs']) ?: '—' }}</span>
                </div>
                @if(!$loop->last)
                    <div class="flex items-center justify-center text-slate-400">↓</div>
                @endif
            @endforeach
            <div class="flex items-center justify-center text-slate-400">↓</div>
            <div class="flex items-center gap-2 text-primary font-bold">
                <span class="material-symbols-outlined">picture_as_pdf</span>
                تصدير PDF
            </div>
        </div>
        </div>
    </details>
</div>
