@extends('layouts.app')

@section('title', 'مكتبة الأنظمة والقوانين')

@section('content')

{{-- Page Header --}}
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-2xl font-black tracking-tight">مكتبة الأنظمة والقوانين</h1>
        <p class="text-slate-500 mt-1 text-sm">قاعدة المعرفة القانونية السعودية مع البحث الدلالي (RAG)</p>
    </div>
    <a href="{{ route('law-library.create') }}"
       class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-bold hover:bg-primary/90 transition-all shadow-lg shadow-primary/20 text-sm cursor-pointer shrink-0">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4 h-4"><path d="M12 5v14M5 12h14"/></svg>
        إضافة نظام جديد
    </a>
</div>

{{-- Stats Row --}}
<div class="grid grid-cols-3 gap-4 mb-6">
    <div class="bg-white rounded-xl border border-slate-200 p-4 text-center shadow-sm">
        <div class="text-3xl font-black text-primary mb-1">{{ number_format($stats['total']) }}</div>
        <div class="text-xs text-slate-500 font-medium">إجمالي الأنظمة</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4 text-center shadow-sm">
        <div class="text-3xl font-black text-emerald-600 mb-1">{{ number_format($stats['active']) }}</div>
        <div class="text-xs text-slate-500 font-medium">نظام ساري</div>
    </div>
    <div class="bg-white rounded-xl border border-slate-200 p-4 text-center shadow-sm">
        <div class="text-3xl font-black text-blue-600 mb-1">{{ number_format($stats['articles']) }}</div>
        <div class="text-xs text-slate-500 font-medium">مادة مفهرسة</div>
    </div>
</div>

{{-- Search & Filters --}}
<form method="GET" action="{{ route('law-library.index') }}" class="bg-white rounded-xl border border-slate-200 shadow-sm p-4 mb-4">
    <div class="flex flex-col gap-3">
        {{-- Row 1: Search --}}
        <div class="relative">
            <div class="absolute inset-y-0 right-3 flex items-center pointer-events-none">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4 text-slate-400"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
            </div>
            <input type="text" name="search" value="{{ request('search') }}"
                   placeholder="ابحث باسم النظام… (مثال: نظام العمل، نظام الإثبات)"
                   class="w-full pr-9 pl-4 py-2.5 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary transition-colors bg-slate-50">
        </div>

        {{-- Row 2: Filters + Sort + Actions --}}
        <div class="flex flex-wrap gap-2 items-center">
            {{-- Category --}}
            <select name="category"
                    class="px-3 py-2 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary bg-white cursor-pointer">
                <option value="">كل التصنيفات</option>
                @foreach($categories as $cat)
                    <option value="{{ $cat }}" @selected(request('category') === $cat)>{{ $cat }}</option>
                @endforeach
            </select>

            {{-- Status --}}
            <select name="status"
                    class="px-3 py-2 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary bg-white cursor-pointer">
                <option value="">كل الحالات</option>
                <option value="active"    @selected(request('status') === 'active')>ساري</option>
                <option value="abrogated" @selected(request('status') === 'abrogated')>ملغى</option>
                <option value="draft"     @selected(request('status') === 'draft')>مسودة</option>
            </select>

            {{-- Sort --}}
            <select name="sort"
                    class="px-3 py-2 rounded-lg border border-slate-200 text-sm focus:outline-none focus:ring-2 focus:ring-primary/30 focus:border-primary bg-white cursor-pointer">
                <option value="newest"   @selected(request('sort','newest') === 'newest')>الأحدث إضافةً</option>
                <option value="oldest"   @selected(request('sort') === 'oldest')>الأقدم إضافةً</option>
                <option value="articles" @selected(request('sort') === 'articles')>الأكثر موادًا</option>
                <option value="name"     @selected(request('sort') === 'name')>الاسم أبجديًا</option>
            </select>

            <button type="submit"
                    class="px-5 py-2 bg-primary text-white rounded-lg text-sm font-bold hover:bg-primary/90 transition-colors cursor-pointer">
                تطبيق
            </button>

            @if(request()->hasAny(['search', 'category', 'status', 'sort']))
                <a href="{{ route('law-library.index') }}"
                   class="px-3 py-2 bg-slate-100 text-slate-500 rounded-lg text-sm hover:bg-slate-200 transition-colors cursor-pointer flex items-center gap-1">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3.5 h-3.5"><path d="M18 6L6 18M6 6l12 12"/></svg>
                    مسح
                </a>
            @endif

            {{-- Active filter chips --}}
            @if(request('search'))
                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-primary/10 text-primary rounded-full text-xs font-medium">
                    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-3 h-3"><circle cx="11" cy="11" r="8"/><path d="m21 21-4.35-4.35"/></svg>
                    {{ request('search') }}
                </span>
            @endif
            @if(request('category'))
                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-blue-50 text-blue-700 rounded-full text-xs font-medium">{{ request('category') }}</span>
            @endif
            @if(request('status'))
                <span class="inline-flex items-center gap-1 px-2.5 py-1 bg-emerald-50 text-emerald-700 rounded-full text-xs font-medium">
                    {{ ['active'=>'ساري','abrogated'=>'ملغى','draft'=>'مسودة'][request('status')] ?? request('status') }}
                </span>
            @endif
        </div>
    </div>
</form>

{{-- Table --}}
@if($laws->isEmpty())
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm p-16 text-center">
        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" class="w-16 h-16 text-slate-300 mx-auto mb-4"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>
        @if(request()->hasAny(['search', 'category', 'status']))
            <h3 class="font-bold text-lg mb-2">لا توجد نتائج</h3>
            <p class="text-slate-500 mb-4">جرّب تغيير معايير البحث</p>
            <a href="{{ route('law-library.index') }}" class="inline-flex items-center gap-2 text-primary hover:underline text-sm font-bold">مسح الفلاتر</a>
        @else
            <h3 class="font-bold text-lg mb-2">لا توجد أنظمة في المكتبة</h3>
            <p class="text-slate-500 mb-6 text-sm">ابدأ ببناء قاعدة المعرفة القانونية بإضافة الأنظمة والقوانين السعودية</p>
            <a href="{{ route('law-library.create') }}"
               class="inline-flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-xl font-bold hover:bg-primary/90 transition-all">
                <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" class="w-4 h-4"><path d="M12 5v14M5 12h14"/></svg>
                إضافة أول نظام
            </a>
        @endif
    </div>
@else
    <div class="bg-white rounded-xl border border-slate-200 shadow-sm overflow-hidden">
        {{-- Table header --}}
        <div class="overflow-x-auto">
            <table class="w-full text-sm" dir="rtl">
                <thead>
                    <tr class="border-b border-slate-100 bg-slate-50">
                        <th class="text-right px-5 py-3 font-semibold text-slate-500 text-xs w-10">#</th>
                        <th class="text-right px-5 py-3 font-semibold text-slate-500 text-xs">اسم النظام</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-500 text-xs hidden md:table-cell">التصنيف</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-500 text-xs hidden lg:table-cell">السنة</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-500 text-xs">المواد</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-500 text-xs">الحالة</th>
                        <th class="text-right px-4 py-3 font-semibold text-slate-500 text-xs hidden xl:table-cell">تاريخ الإضافة</th>
                        <th class="text-center px-4 py-3 font-semibold text-slate-500 text-xs w-24">إجراءات</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($laws as $index => $law)
                        <tr class="hover:bg-slate-50/60 transition-colors duration-150 group cursor-pointer border-b border-slate-100 last:border-0"
                            onclick="window.location='{{ route('law-library.show', $law) }}'">

                            {{-- # --}}
                            <td class="px-5 py-3 text-slate-400 text-xs font-mono">
                                {{ $laws->firstItem() + $loop->index }}
                            </td>

                            {{-- Name + description --}}
                            <td class="px-5 py-3">
                                <div class="font-semibold text-slate-900 group-hover:text-primary transition-colors leading-snug text-sm">
                                    {{ $law->name }}
                                </div>
                                @if($law->description)
                                    <div class="text-xs text-slate-400 mt-0.5 line-clamp-1 hidden sm:block max-w-xs">
                                        {{ $law->description }}
                                    </div>
                                @endif
                            </td>

                            {{-- Category --}}
                            <td class="px-4 py-3 hidden md:table-cell">
                                @if($law->category)
                                    <span class="inline-flex items-center px-2 py-0.5 rounded-md text-xs font-medium bg-slate-100 text-slate-600">
                                        {{ $law->category }}
                                    </span>
                                @else
                                    <span class="text-slate-300 text-xs">—</span>
                                @endif
                            </td>

                            {{-- Effective year --}}
                            <td class="px-4 py-3 text-center hidden lg:table-cell">
                                <span class="text-xs text-slate-500 font-medium tabular-nums">
                                    {{ $law->effective_year ? $law->effective_year . ' هـ' : '—' }}
                                </span>
                            </td>

                            {{-- Articles count — most important data point --}}
                            <td class="px-4 py-3 text-center">
                                @if($law->articles_count > 0)
                                    <div class="inline-flex flex-col items-center">
                                        <span class="text-base font-black text-blue-700 tabular-nums leading-none">
                                            {{ number_format($law->articles_count) }}
                                        </span>
                                        <span class="text-[10px] text-slate-400 leading-none mt-0.5">مادة</span>
                                    </div>
                                @else
                                    <span class="text-xs text-slate-300">—</span>
                                @endif
                            </td>

                            {{-- Status --}}
                            <td class="px-4 py-3 text-center">
                                @if($law->status === 'active')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-emerald-50 text-emerald-700 border border-emerald-200 whitespace-nowrap">
                                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 shrink-0"></span>
                                        ساري
                                    </span>
                                @elseif($law->status === 'abrogated')
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-red-50 text-red-700 border border-red-200 whitespace-nowrap">
                                        <span class="w-1.5 h-1.5 rounded-full bg-red-500 shrink-0"></span>
                                        ملغى
                                    </span>
                                @else
                                    <span class="inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-bold bg-slate-100 text-slate-600 border border-slate-200 whitespace-nowrap">
                                        <span class="w-1.5 h-1.5 rounded-full bg-slate-400 shrink-0"></span>
                                        مسودة
                                    </span>
                                @endif
                            </td>

                            {{-- Date added --}}
                            <td class="px-4 py-3 hidden xl:table-cell">
                                <div class="text-xs text-slate-500 whitespace-nowrap"
                                     title="{{ $law->created_at->format('Y-m-d H:i') }}">
                                    {{ $law->created_at->diffForHumans() }}
                                </div>
                                <div class="text-[10px] text-slate-400 mt-0.5 tabular-nums">
                                    {{ $law->created_at->format('Y/m/d') }}
                                </div>
                            </td>

                            {{-- Actions --}}
                            <td class="px-4 py-3 text-center" onclick="event.stopPropagation()">
                                <div class="flex items-center justify-center gap-0.5">
                                    <a href="{{ route('law-library.show', $law) }}"
                                       class="p-1.5 rounded-lg text-slate-400 hover:text-primary hover:bg-primary/10 transition-colors cursor-pointer"
                                       title="عرض">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/></svg>
                                    </a>
                                    <a href="{{ route('law-library.edit', $law) }}"
                                       class="p-1.5 rounded-lg text-slate-400 hover:text-blue-600 hover:bg-blue-50 transition-colors cursor-pointer"
                                       title="تعديل">
                                        <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/></svg>
                                    </a>
                                    <form action="{{ route('law-library.destroy', $law) }}" method="POST"
                                          onsubmit="return confirm('هل أنت متأكد من حذف {{ addslashes($law->name) }}؟')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit"
                                                class="p-1.5 rounded-lg text-slate-400 hover:text-red-600 hover:bg-red-50 transition-colors cursor-pointer"
                                                title="حذف">
                                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" class="w-4 h-4"><polyline points="3 6 5 6 21 6"/><path d="M19 6l-1 14a2 2 0 0 1-2 2H8a2 2 0 0 1-2-2L5 6"/><path d="M10 11v6M14 11v6"/><path d="M9 6V4a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2"/></svg>
                                        </button>
                                    </form>
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- Table footer / Pagination --}}
        @if($laws->hasPages())
        <div class="px-5 py-4 border-t border-slate-100 bg-slate-50/30">
            {{ $laws->links() }}
        </div>
        @else
        <div class="px-5 py-3 border-t border-slate-100 bg-slate-50/30 text-right">
            <p class="text-sm text-slate-500">
                إجمالي <span class="font-semibold text-slate-700">{{ number_format($laws->total()) }}</span> نظام
            </p>
        </div>
        @endif
    </div>
@endif

@endsection
