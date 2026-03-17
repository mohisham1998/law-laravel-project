@extends('layouts.app')

@section('title', 'الجدول الزمني - ' . ($case->title ?? 'القضية'))

@section('content')
{{-- Back on the left, current page on the right (no duplicate arrow) --}}
<div class="flex justify-between items-center mb-6">
    <a href="{{ route('cases.show', $case) }}" class="flex items-center gap-1.5 text-sm text-slate-600 hover:text-primary transition-colors" title="العودة للقضية">
        <span class="material-symbols-outlined text-lg">arrow_forward</span>
        <span>العودة</span>
    </a>
    <span class="text-sm text-slate-900 font-semibold">الجدول الزمني</span>
</div>

<div class="max-w-3xl">
    <h1 class="text-2xl font-black mb-2">الجدول الزمني للقضية</h1>
    <p class="text-slate-500 mb-8">متابعة جميع الأحداث والتحديثات</p>
    
    {{-- Timeline --}}
    <div class="relative">
        <div class="absolute right-4 top-0 bottom-0 w-0.5 bg-primary/20"></div>
        
        <div class="space-y-8">
            {{-- Event 1 --}}
            <div class="relative flex gap-6 pr-4">
                <div class="absolute right-2 w-4 h-4 rounded-full bg-primary border-4 border-white shadow"></div>
                <div class="flex-1 bg-white p-5 rounded-xl border border-primary/10 shadow-sm mr-6">
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="font-bold">تم إنشاء القضية</h4>
                        <span class="text-xs text-slate-500">{{ $case->created_at?->format('Y-m-d H:i') ?? 'الآن' }}</span>
                    </div>
                    <p class="text-sm text-slate-600">تم إنشاء القضية وبدء عملية التحليل الذكي.</p>
                </div>
            </div>
            
            {{-- Event 2 --}}
            <div class="relative flex gap-6 pr-4">
                <div class="absolute right-2 w-4 h-4 rounded-full bg-emerald-500 border-4 border-white shadow"></div>
                <div class="flex-1 bg-white p-5 rounded-xl border border-emerald-200 shadow-sm mr-6">
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="font-bold text-emerald-700">اكتمال المرحلة الأولى</h4>
                        <span class="text-xs text-slate-500">منذ ساعتين</span>
                    </div>
                    <p class="text-sm text-slate-600">تم استخراج الوقائع القانونية بنجاح.</p>
                    <div class="mt-3 flex gap-2">
                        <span class="px-2 py-1 bg-emerald-100 text-emerald-700 rounded text-xs font-bold">٨ وقائع</span>
                    </div>
                </div>
            </div>
            
            {{-- Event 3 --}}
            <div class="relative flex gap-6 pr-4">
                <div class="absolute right-2 w-4 h-4 rounded-full bg-primary border-4 border-white shadow animate-pulse"></div>
                <div class="flex-1 bg-white p-5 rounded-xl border border-primary shadow-sm mr-6 ring-2 ring-primary/20">
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="font-bold text-primary">جاري - المرحلة الثانية</h4>
                        <span class="text-xs text-primary font-bold">الآن</span>
                    </div>
                    <p class="text-sm text-slate-600">جاري مطابقة الأنظمة والقوانين السعودية.</p>
                    <div class="mt-3">
                        <div class="flex items-center gap-2">
                            <div class="flex-1 h-2 bg-primary/10 rounded-full overflow-hidden">
                                <div class="h-full bg-primary w-[65%]"></div>
                            </div>
                            <span class="text-xs font-bold text-primary">٦٥%</span>
                        </div>
                    </div>
                </div>
            </div>
            
            {{-- Event 4 (Pending) --}}
            <div class="relative flex gap-6 pr-4 opacity-50">
                <div class="absolute right-2 w-4 h-4 rounded-full bg-slate-300 border-4 border-white shadow"></div>
                <div class="flex-1 bg-slate-50 p-5 rounded-xl border border-slate-200 mr-6">
                    <div class="flex justify-between items-start mb-2">
                        <h4 class="font-bold text-slate-400">المرحلة الثالثة - صياغة المذكرة</h4>
                        <span class="text-xs text-slate-400">قريباً</span>
                    </div>
                    <p class="text-sm text-slate-400">سيتم إنشاء المذكرة القانونية تلقائياً.</p>
                </div>
            </div>
        </div>
    </div>
</div>
@endsection
