@if($case->status->value === 'awaiting_laws' || $case->status === 'awaiting_laws')
<div id="phase2ApprovalModal" class="fixed inset-0 bg-black/50 backdrop-blur-sm flex items-center justify-center z-50" style="display: block;">
    <div class="bg-white rounded-2xl shadow-2xl max-w-2xl w-full mx-4 p-8">
        <div class="flex items-center gap-4 mb-6">
            <div class="w-16 h-16 bg-primary/10 rounded-full flex items-center justify-center">
                <span class="material-symbols-outlined text-primary text-4xl">gavel</span>
            </div>
            <div>
                <h2 class="text-2xl font-black text-slate-900">المرحلة الأولى مكتملة</h2>
                <p class="text-slate-600">تم تحليل القضية وتحديد الأنظمة المطلوبة</p>
            </div>
        </div>

        <div class="bg-slate-50 rounded-xl p-6 mb-6">
            <h3 class="font-bold text-slate-900 mb-3 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">description</span>
                الأنظمة والقوانين المطلوبة للتحليل
            </h3>
            @if($case->requiredLaws && $case->requiredLaws->count())
                <ul class="space-y-2">
                    @foreach($case->requiredLaws as $law)
                        <li class="flex items-center gap-2 text-sm">
                            <span class="material-symbols-outlined text-green-600 text-base">check_circle</span>
                            <span class="font-medium">{{ $law->law_name }}</span>
                        </li>
                    @endforeach
                </ul>
            @else
                <p class="text-sm text-slate-600">سيتم استخدام جميع الأنظمة المتوفرة في مكتبة الأنظمة والقوانين (RAG).</p>
            @endif
        </div>

        <div class="bg-amber-50 rounded-xl p-4 mb-6 border border-amber-200">
            <p class="text-sm text-amber-900">
                <span class="material-symbols-outlined text-amber-600 text-base align-middle">info</span>
                <strong>ملاحظة:</strong> سيتم الآن بدء المرحلة الثانية التي تتضمن 9 وكلاء ذكاء اصطناعي لتحليل القضية وبناء المذكرة القانونية. قد تستغرق العملية عدة دقائق.
            </p>
        </div>

        <div class="flex gap-3">
            <form method="POST" action="{{ route('cases.start-phase2', $case) }}" class="flex-1">
                @csrf
                <button type="submit" class="w-full px-6 py-3 bg-primary text-white font-bold rounded-xl hover:bg-primary/90 transition-colors flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">play_arrow</span>
                    بدء المرحلة الثانية (9 وكلاء)
                </button>
            </form>
            <a href="{{ route('cases.index') }}" class="px-6 py-3 bg-slate-200 text-slate-700 font-bold rounded-xl hover:bg-slate-300 transition-colors flex items-center justify-center">
                إلغاء
            </a>
        </div>
    </div>
</div>
@endif
