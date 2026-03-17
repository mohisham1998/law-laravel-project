@extends('layouts.app')

@section('title', 'إدارة القضايا - المستشار القانوني الذكي')

@section('content')
{{-- Page Header --}}
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-4 rounded-xl shadow-sm border border-primary/5 mb-8">
    <div class="flex flex-col">
        <h2 class="text-2xl font-black tracking-tight">إدارة القضايا القانونية</h2>
        <p class="text-slate-500">مرحباً بك في لوحة تحكم القضايا الخاصة بك</p>
    </div>
    <div class="flex items-center gap-3 w-full md:w-auto">
        <div class="relative flex-1 md:w-64">
            <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
            <input class="w-full pr-10 pl-4 py-2 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary" placeholder="بحث عن قضية..." type="text"/>
        </div>
        <a href="{{ route('cases.create') }}" class="flex items-center gap-2 bg-primary text-white px-6 py-2.5 rounded-xl font-bold hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
            <span class="material-symbols-outlined">add</span>
            <span>قضية جديدة</span>
        </a>
    </div>
</div>

{{-- Statistics Overview --}}
<div class="grid grid-cols-2 md:grid-cols-4 gap-4 mb-8">
    <div class="bg-white p-6 rounded-xl border border-primary/5 shadow-sm magnetic-element">
        <p class="text-slate-500 text-sm mb-1">قضايا جديدة</p>
        <div class="flex justify-between items-end">
            <p class="text-3xl font-bold">{{ $stats['new'] }}</p>
            <span class="text-blue-500 material-symbols-outlined text-4xl opacity-20">fiber_new</span>
        </div>
    </div>
    <div class="bg-white p-6 rounded-xl border border-primary/5 shadow-sm magnetic-element">
        <p class="text-slate-500 text-sm mb-1">قيد التحليل</p>
        <div class="flex justify-between items-end">
            <p class="text-3xl font-bold">{{ $stats['analyzing'] }}</p>
            <span class="text-amber-500 material-symbols-outlined text-4xl opacity-20">analytics</span>
        </div>
    </div>
    <div class="bg-white p-6 rounded-xl border border-primary/5 shadow-sm magnetic-element">
        <p class="text-slate-500 text-sm mb-1">قيد الصياغة</p>
        <div class="flex justify-between items-end">
            <p class="text-3xl font-bold">{{ $stats['drafting'] }}</p>
            <span class="text-primary material-symbols-outlined text-4xl opacity-20">edit_note</span>
        </div>
    </div>
    <div class="bg-white p-6 rounded-xl border border-primary/5 shadow-sm magnetic-element">
        <p class="text-slate-500 text-sm mb-1">مكتملة</p>
        <div class="flex justify-between items-end">
            <p class="text-3xl font-bold">{{ $stats['completed'] }}</p>
            <span class="text-emerald-500 material-symbols-outlined text-4xl opacity-20">check_circle</span>
        </div>
    </div>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    {{-- Case List Section --}}
    <div class="lg:col-span-2 flex flex-col gap-4">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-lg font-bold flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">list_alt</span>
                قائمة القضايا الحالية
            </h3>
            <a href="#" class="text-primary text-sm font-semibold hover:underline">عرض الكل</a>
        </div>
        
        <div class="flex flex-col gap-3">
            @forelse($cases ?? [] as $case)
                <div class="bg-white p-5 rounded-xl border border-primary/5 shadow-sm hover:border-primary transition-all group magnetic-element">
                    <div class="flex justify-between items-start">
                        <div class="flex items-start gap-4">
                            <div class="size-12 rounded-lg 
                                @if($case->status == 'completed') bg-emerald-50 text-emerald-600
                                @elseif($case->status == 'analyzing') bg-amber-50 text-amber-600
                                @else bg-blue-50 text-blue-600
                                @endif
                                flex items-center justify-center">
                                <span class="material-symbols-outlined">
                                    @if($case->status == 'completed') verified
                                    @elseif($case->status == 'analyzing') analytics
                                    @else balance
                                    @endif
                                </span>
                            </div>
                            <div>
                                <h4 class="font-bold text-lg group-hover:text-primary transition-colors">
                                    <a href="{{ route('cases.show', $case->id) }}">{{ $case->title }}</a>
                                </h4>
                                <p class="text-sm text-slate-500 mb-2">المرحلة: {{ $case->phase ?? '1' }}</p>
                                <div class="flex gap-4">
                                    <span class="flex items-center gap-1 text-xs text-slate-400">
                                        <span class="material-symbols-outlined text-xs">calendar_today</span>
                                        {{ $case->created_at->format('Y-m-d') }}
                                    </span>
                                    <span class="flex items-center gap-1 text-xs text-slate-400">
                                        <span class="material-symbols-outlined text-xs">folder</span>
                                        قضية
                                    </span>
                                </div>
                            </div>
                        </div>
                        <span class="px-3 py-1 rounded-full text-xs font-bold
                            @if($case->status == 'completed') bg-emerald-100 text-emerald-700
                            @elseif($case->status == 'analyzing') bg-amber-100 text-amber-700
                            @elseif($case->status == 'drafting') bg-primary/10 text-primary
                            @else bg-blue-100 text-blue-700
                            @endif
                        ">
                            @if($case->status == 'completed') مكتملة
                            @elseif($case->status == 'analyzing') قيد التحليل
                            @elseif($case->status == 'drafting') قيد الصياغة
                            @else جديدة
                            @endif
                        </span>
                    </div>
                </div>
            @empty
                {{-- Demo cases if no data --}}
                <div class="bg-white p-5 rounded-xl border border-primary/5 shadow-sm hover:border-primary transition-all group magnetic-element">
                    <div class="flex justify-between items-start">
                        <div class="flex items-start gap-4">
                            <div class="size-12 rounded-lg bg-blue-50 flex items-center justify-center text-blue-600">
                                <span class="material-symbols-outlined">balance</span>
                            </div>
                            <div>
                                <h4 class="font-bold text-lg group-hover:text-primary transition-colors">نزاع ملكية عقارية - الشيخ زايد</h4>
                                <p class="text-sm text-slate-500 mb-2">العميل: شركة الواحة للتطوير</p>
                                <div class="flex gap-4">
                                    <span class="flex items-center gap-1 text-xs text-slate-400">
                                        <span class="material-symbols-outlined text-xs">calendar_today</span>
                                        ٢٤ أكتوبر ٢٠٢٣
                                    </span>
                                    <span class="flex items-center gap-1 text-xs text-slate-400">
                                        <span class="material-symbols-outlined text-xs">folder</span>
                                        مدني
                                    </span>
                                </div>
                            </div>
                        </div>
                        <span class="px-3 py-1 bg-amber-100 text-amber-700 rounded-full text-xs font-bold">قيد التحليل</span>
                    </div>
                </div>
                
                <div class="bg-white p-5 rounded-xl border border-primary/5 shadow-sm hover:border-primary transition-all group magnetic-element">
                    <div class="flex justify-between items-start">
                        <div class="flex items-start gap-4">
                            <div class="size-12 rounded-lg bg-primary/10 flex items-center justify-center text-primary">
                                <span class="material-symbols-outlined">contract</span>
                            </div>
                            <div>
                                <h4 class="font-bold text-lg group-hover:text-primary transition-colors">مراجعة عقد شراكة دولية</h4>
                                <p class="text-sm text-slate-500 mb-2">العميل: السيد/ عمر خالد</p>
                                <div class="flex gap-4">
                                    <span class="flex items-center gap-1 text-xs text-slate-400">
                                        <span class="material-symbols-outlined text-xs">calendar_today</span>
                                        ٢١ أكتوبر ٢٠٢٣
                                    </span>
                                    <span class="flex items-center gap-1 text-xs text-slate-400">
                                        <span class="material-symbols-outlined text-xs">folder</span>
                                        تجاري
                                    </span>
                                </div>
                            </div>
                        </div>
                        <span class="px-3 py-1 bg-primary/10 text-primary rounded-full text-xs font-bold">قيد الصياغة</span>
                    </div>
                </div>
                
                <div class="bg-white p-5 rounded-xl border border-primary/5 shadow-sm hover:border-primary transition-all group magnetic-element">
                    <div class="flex justify-between items-start">
                        <div class="flex items-start gap-4">
                            <div class="size-12 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600">
                                <span class="material-symbols-outlined">verified</span>
                            </div>
                            <div>
                                <h4 class="font-bold text-lg group-hover:text-primary transition-colors">تأسيس شركة مساهمة</h4>
                                <p class="text-sm text-slate-500 mb-2">العميل: مجموعة الاستثمار المتحدة</p>
                                <div class="flex gap-4">
                                    <span class="flex items-center gap-1 text-xs text-slate-400">
                                        <span class="material-symbols-outlined text-xs">calendar_today</span>
                                        ١٥ أكتوبر ٢٠٢٣
                                    </span>
                                    <span class="flex items-center gap-1 text-xs text-slate-400">
                                        <span class="material-symbols-outlined text-xs">folder</span>
                                        إداري
                                    </span>
                                </div>
                            </div>
                        </div>
                        <span class="px-3 py-1 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold">مكتملة</span>
                    </div>
                </div>
            @endforelse
        </div>
    </div>
    
    {{-- Create Case Form & AI Insights --}}
    <div class="flex flex-col gap-4">
        <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-lg">
            <div class="flex items-center gap-2 mb-6">
                <div class="size-8 bg-primary/20 rounded-lg flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined">add_task</span>
                </div>
                <h3 class="text-lg font-bold">إنشاء قضية جديدة</h3>
            </div>
            
            <form action="{{ route('cases.store') }}" method="POST" enctype="multipart/form-data" class="flex flex-col gap-5">
                @csrf
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-slate-700">عنوان القضية</label>
                    <input name="title" class="w-full px-4 py-2.5 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary" placeholder="مثال: مراجعة عقد عقاري" type="text" required/>
                </div>
                
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-slate-700">اسم العميل</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">person</span>
                        <input name="client_name" class="w-full pr-10 pl-4 py-2.5 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary" placeholder="ابحث عن عميل أو أضف جديداً" type="text"/>
                    </div>
                </div>
                
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-slate-700">وصف القضية</label>
                    <textarea name="description" class="w-full px-4 py-2.5 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary resize-none" placeholder="اكتب تفاصيل وملخص القضية هنا..." rows="4"></textarea>
                </div>
                
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-slate-700">تصنيف القضية</label>
                    <select name="category" class="w-full px-4 py-2.5 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary appearance-none">
                        <option value="civil">مدني</option>
                        <option value="criminal">جنائي</option>
                        <option value="commercial">تجاري</option>
                        <option value="family">أحوال شخصية</option>
                        <option value="administrative">إداري</option>
                    </select>
                </div>

                <div class="flex flex-col gap-1.5">
                    <span class="text-sm font-semibold text-slate-700">مرفقات القضية (اختياري)</span>
                    <label for="index-attachments" class="block border-2 border-dashed border-primary/30 rounded-xl p-5 bg-primary/5 hover:border-primary/50 hover:bg-primary/10 transition-colors cursor-pointer mt-1 min-h-[120px]">
                        <input type="file" name="attachments[]" id="index-attachments" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.txt,.doc,.docx,.pdf,.ppt,.pptx,image/*,text/plain,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation" class="sr-only">
                        <div class="flex flex-col items-center justify-center gap-2 text-center">
                            <span class="material-symbols-outlined text-4xl text-primary">upload_file</span>
                            <span class="text-sm font-medium text-slate-700">انقر لاختيار الملفات أو اسحبها هنا</span>
                            <span class="text-xs text-slate-500">صور، TXT، DOC، PDF، PPT — حد أقصى 50 م.ب. تظهر في مستندات القضية.</span>
                        </div>
                        <ul id="indexFileList" class="mt-3 pt-3 border-t border-primary/20 space-y-1 text-xs text-slate-600 hidden"></ul>
                    </label>
                    @error('attachments.*')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror
                </div>
                
                <button class="mt-2 w-full bg-primary text-white font-bold py-3 rounded-xl hover:bg-primary/90 transition-all shadow-md" type="submit">
                    حفظ وإنشاء القضية
                </button>
            </form>
        </div>
        
        {{-- AI Insights Mini-Card --}}
        <div class="bg-gradient-to-br from-primary to-emerald-800 p-6 rounded-xl text-white shadow-lg overflow-hidden relative">
            <div class="relative z-10">
                <div class="flex items-center gap-2 mb-2">
                    <span class="material-symbols-outlined">auto_awesome</span>
                    <span class="text-sm font-bold uppercase tracking-wider">تحليل المستشار الذكي</span>
                </div>
                <p class="text-sm opacity-90 leading-relaxed">بناءً على نشاطك الأخير، هناك ٣ قضايا تتطلب مراجعة فورية للمستندات القانونية لضمان الامتثال.</p>
            </div>
            <div class="absolute -bottom-4 -left-4 opacity-10">
                <span class="material-symbols-outlined text-[100px]">psychology</span>
            </div>
        </div>
    </div>
</div>
@push('scripts')
<script>
document.getElementById('index-attachments') && document.getElementById('index-attachments').addEventListener('change', function() {
    var list = document.getElementById('indexFileList');
    if (!list) return;
    list.innerHTML = '';
    if (this.files.length) {
        list.classList.remove('hidden');
        for (var i = 0; i < this.files.length; i++) {
            var li = document.createElement('li');
            li.textContent = this.files[i].name + ' (' + (this.files[i].size / 1024 / 1024).toFixed(2) + ' م.ب)';
            list.appendChild(li);
        }
    } else {
        list.classList.add('hidden');
    }
});
</script>
@endpush
@endsection
