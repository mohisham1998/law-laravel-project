@extends('layouts.app')

@section('title', 'تحليل الذكاء الاصطناعي - المستشار القانوني الذكي')

@section('content')
{{-- Header Section --}}
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
    <div>
        <h1 class="text-3xl font-black tracking-tight mb-2">سير عملية التحليل بالذكاء الاصطناعي</h1>
        <p class="text-slate-500">متابعة مراحل التحليل القانوني التلقائي</p>
    </div>
    <div class="flex gap-2">
        <button class="bg-primary text-white px-6 py-2.5 rounded-lg font-bold flex items-center gap-2 shadow-lg shadow-primary/20 hover:brightness-110 transition-all">
            <span class="material-symbols-outlined text-sm">pause</span>
            إيقاف مؤقت
        </button>
        <button class="bg-white border border-slate-200 px-4 py-2.5 rounded-lg font-bold flex items-center justify-center text-slate-700 hover:bg-slate-50">
            <span class="material-symbols-outlined">refresh</span>
        </button>
    </div>
</div>

{{-- Global Progress --}}
<div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm mb-8">
    <div class="flex justify-between items-center mb-4">
        <div class="flex items-center gap-3">
            <div class="p-2 bg-primary/10 rounded-lg">
                <span class="material-symbols-outlined text-primary">auto_awesome</span>
            </div>
            <div>
                <h3 class="text-lg font-bold">إجمالي التقدم في التحليل</h3>
                <p class="text-sm text-slate-500">جاري استخراج الوقائع القانونية من المستندات المرفوعة</p>
            </div>
        </div>
        <span class="text-2xl font-black text-primary">65%</span>
    </div>
    <div class="h-4 w-full bg-primary/10 rounded-full overflow-hidden">
        <div class="h-full bg-primary transition-all duration-1000 ease-in-out relative" style="width: 65%;">
            <div class="absolute inset-0 bg-white/20 animate-pulse"></div>
        </div>
    </div>
</div>

{{-- Pipeline Grid --}}
<div class="grid grid-cols-1 lg:grid-cols-2 xl:grid-cols-3 gap-6">
    {{-- Stage 1: Document Analysis --}}
    <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
        <div class="flex items-center gap-3 mb-4">
            <div class="size-10 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-600">
                <span class="material-symbols-outlined">description</span>
            </div>
            <div>
                <h4 class="font-bold">تحليل المستندات</h4>
                <span class="text-xs text-emerald-600 font-bold">مكتمل</span>
            </div>
            <span class="mr-auto text-emerald-600 material-symbols-outlined">check_circle</span>
        </div>
        <div class="h-2 w-full bg-emerald-100 rounded-full overflow-hidden mb-3">
            <div class="h-full bg-emerald-500 w-full"></div>
        </div>
        <p class="text-xs text-slate-500">تم تحليل ١٢ مستند قانوني واستخراج النصوص</p>
    </div>
    
    {{-- Stage 2: Facts Extraction --}}
    <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
        <div class="flex items-center gap-3 mb-4">
            <div class="size-10 rounded-lg bg-emerald-100 flex items-center justify-center text-emerald-600">
                <span class="material-symbols-outlined">fact_check</span>
            </div>
            <div>
                <h4 class="font-bold">استخراج الوقائع</h4>
                <span class="text-xs text-emerald-600 font-bold">مكتمل</span>
            </div>
            <span class="mr-auto text-emerald-600 material-symbols-outlined">check_circle</span>
        </div>
        <div class="h-2 w-full bg-emerald-100 rounded-full overflow-hidden mb-3">
            <div class="h-full bg-emerald-500 w-full"></div>
        </div>
        <p class="text-xs text-slate-500">تم تحديد ٨ وقائع قانونية رئيسية</p>
    </div>
    
    {{-- Stage 3: Law Matching --}}
    <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm ring-2 ring-primary">
        <div class="flex items-center gap-3 mb-4">
            <div class="size-10 rounded-lg bg-primary/10 flex items-center justify-center text-primary animate-pulse">
                <span class="material-symbols-outlined">balance</span>
            </div>
            <div>
                <h4 class="font-bold">مطابقة الأنظمة</h4>
                <span class="text-xs text-primary font-bold">جاري التنفيذ...</span>
            </div>
            <div class="mr-auto size-5 border-2 border-primary border-t-transparent rounded-full animate-spin"></div>
        </div>
        <div class="h-2 w-full bg-primary/10 rounded-full overflow-hidden mb-3">
            <div class="h-full bg-primary w-[65%] transition-all"></div>
        </div>
        <p class="text-xs text-slate-500">جاري البحث في قاعدة الأنظمة السعودية</p>
    </div>
    
    {{-- Stage 4: Legal Analysis --}}
    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm opacity-60">
        <div class="flex items-center gap-3 mb-4">
            <div class="size-10 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400">
                <span class="material-symbols-outlined">psychology</span>
            </div>
            <div>
                <h4 class="font-bold text-slate-400">التحليل القانوني</h4>
                <span class="text-xs text-slate-400 font-bold">في الانتظار</span>
            </div>
            <span class="mr-auto text-slate-300 material-symbols-outlined">hourglass_empty</span>
        </div>
        <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden mb-3">
            <div class="h-full bg-slate-300 w-0"></div>
        </div>
        <p class="text-xs text-slate-400">تحليل مدى انطباق الأنظمة على الوقائع</p>
    </div>
    
    {{-- Stage 5: Brief Generation --}}
    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm opacity-60">
        <div class="flex items-center gap-3 mb-4">
            <div class="size-10 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400">
                <span class="material-symbols-outlined">edit_document</span>
            </div>
            <div>
                <h4 class="font-bold text-slate-400">صياغة المذكرة</h4>
                <span class="text-xs text-slate-400 font-bold">في الانتظار</span>
            </div>
            <span class="mr-auto text-slate-300 material-symbols-outlined">hourglass_empty</span>
        </div>
        <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden mb-3">
            <div class="h-full bg-slate-300 w-0"></div>
        </div>
        <p class="text-xs text-slate-400">إنشاء المذكرة القانونية النهائية</p>
    </div>
    
    {{-- Stage 6: Review --}}
    <div class="bg-white p-6 rounded-xl border border-slate-200 shadow-sm opacity-60">
        <div class="flex items-center gap-3 mb-4">
            <div class="size-10 rounded-lg bg-slate-100 flex items-center justify-center text-slate-400">
                <span class="material-symbols-outlined">verified</span>
            </div>
            <div>
                <h4 class="font-bold text-slate-400">المراجعة النهائية</h4>
                <span class="text-xs text-slate-400 font-bold">في الانتظار</span>
            </div>
            <span class="mr-auto text-slate-300 material-symbols-outlined">hourglass_empty</span>
        </div>
        <div class="h-2 w-full bg-slate-100 rounded-full overflow-hidden mb-3">
            <div class="h-full bg-slate-300 w-0"></div>
        </div>
        <p class="text-xs text-slate-400">مراجعة وتدقيق المخرجات</p>
    </div>
</div>

{{-- AI Insights --}}
<div class="mt-8 bg-gradient-to-br from-primary to-emerald-800 p-6 rounded-xl text-white shadow-lg overflow-hidden relative">
    <div class="relative z-10">
        <div class="flex items-center gap-2 mb-4">
            <span class="material-symbols-outlined">lightbulb</span>
            <span class="font-bold">رؤى الذكاء الاصطناعي</span>
        </div>
        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
            <div class="bg-white/10 p-4 rounded-xl">
                <p class="text-3xl font-black">١٢</p>
                <p class="text-sm opacity-80">مستند تم تحليله</p>
            </div>
            <div class="bg-white/10 p-4 rounded-xl">
                <p class="text-3xl font-black">٨</p>
                <p class="text-sm opacity-80">وقائع مستخرجة</p>
            </div>
            <div class="bg-white/10 p-4 rounded-xl">
                <p class="text-3xl font-black">٢٤</p>
                <p class="text-sm opacity-80">نظام مطابق</p>
            </div>
        </div>
    </div>
    <div class="absolute -bottom-8 -left-8 opacity-10">
        <span class="material-symbols-outlined text-[150px]">psychology</span>
    </div>
</div>
@endsection
