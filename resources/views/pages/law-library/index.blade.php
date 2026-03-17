@extends('layouts.app')

@section('title', 'مكتبة الأنظمة والقوانين')

@section('content')
<div class="flex justify-between items-center mb-8">
    <div>
        <h1 class="text-2xl font-black">مكتبة الأنظمة والقوانين</h1>
        <p class="text-slate-500 mt-1">إدارة قاعدة المعرفة القانونية مع البحث الدلالي (RAG)</p>
    </div>
    <a href="{{ route('law-library.create') }}" class="bg-primary text-white px-6 py-3 rounded-xl font-bold hover:bg-primary/90 transition-all shadow-lg shadow-primary/20 flex items-center gap-2">
        <span class="material-symbols-outlined">add</span>
        إضافة نظام جديد
    </a>
</div>

@if($laws->isEmpty())
    <div class="bg-white rounded-xl border border-primary/10 shadow-sm p-12 text-center">
        <span class="material-symbols-outlined text-6xl text-slate-300 mb-4 block">gavel</span>
        <h3 class="font-bold text-lg mb-2">لا توجد أنظمة في المكتبة</h3>
        <p class="text-slate-500 mb-6">ابدأ ببناء قاعدة المعرفة القانونية بإضافة الأنظمة والقوانين السعودية</p>
        <a href="{{ route('law-library.create') }}" class="inline-flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-xl font-bold hover:bg-primary/90 transition-all">
            <span class="material-symbols-outlined">add</span>
            إضافة أول نظام
        </a>
    </div>
@else
    <div class="grid grid-cols-1 gap-6">
        @foreach($laws as $law)
            <div class="bg-white rounded-xl border border-primary/10 shadow-sm hover:shadow-md transition-shadow">
                <div class="p-6">
                    <div class="flex items-start justify-between mb-4">
                        <div class="flex-1">
                            <div class="flex items-center gap-3 mb-2">
                                <h3 class="text-lg font-bold">{{ $law->name }}</h3>
                                @if($law->effective_year)
                                    <span class="px-3 py-1 bg-slate-100 text-slate-600 text-xs font-bold rounded-full">{{ $law->effective_year }} هـ</span>
                                @endif
                                @if($law->category)
                                    <span class="px-3 py-1 bg-primary/10 text-primary text-xs font-bold rounded-full">{{ $law->category }}</span>
                                @endif
                            </div>
                            @if($law->description)
                                <p class="text-slate-600 text-sm">{{ Str::limit($law->description, 200) }}</p>
                            @endif
                        </div>
                        <a href="{{ route('law-library.show', $law) }}" class="text-primary hover:text-primary/80">
                            <span class="material-symbols-outlined">arrow_back</span>
                        </a>
                    </div>

                    <div class="flex items-center gap-6 text-sm">
                        <div class="flex items-center gap-2 text-slate-600">
                            <span class="material-symbols-outlined text-lg">description</span>
                            <span>{{ $law->files_count }} ملف</span>
                        </div>
                        <div class="flex items-center gap-2 text-slate-600">
                            <span class="material-symbols-outlined text-lg">article</span>
                            <span>{{ $law->articles_count }} مادة</span>
                        </div>
                        @if($law->isProcessed())
                            <div class="flex items-center gap-2 text-emerald-600">
                                <span class="material-symbols-outlined text-lg">check_circle</span>
                                <span>معالج</span>
                            </div>
                        @else
                            <div class="flex items-center gap-2 text-amber-600">
                                <span class="material-symbols-outlined text-lg">pending</span>
                                <span>قيد المعالجة</span>
                            </div>
                        @endif
                        <div class="mr-auto text-xs text-slate-400">
                            {{ $law->created_at->diffForHumans() }}
                        </div>
                    </div>
                </div>
            </div>
        @endforeach
    </div>

    <div class="mt-8">
        {{ $laws->links() }}
    </div>
@endif
@endsection
