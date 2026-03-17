@extends('layouts.app')

@section('title', 'تعديل ' . $lawRegistry->name)

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex items-center gap-2 text-sm text-slate-500 mb-6">
        <a href="{{ route('law-library.index') }}" class="hover:text-primary">مكتبة الأنظمة</a>
        <span class="material-symbols-outlined text-xs">chevron_left</span>
        <a href="{{ route('law-library.show', $lawRegistry) }}" class="hover:text-primary">{{ $lawRegistry->name }}</a>
        <span class="material-symbols-outlined text-xs">chevron_left</span>
        <span class="text-slate-900 font-semibold">تعديل</span>
    </div>

    <div class="bg-white p-8 rounded-xl border border-primary/10 shadow-lg">
        <h1 class="text-xl font-black mb-6">تعديل معلومات النظام</h1>

        <form action="{{ route('law-library.update', $lawRegistry) }}" method="POST" class="space-y-6">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">اسم النظام *</label>
                <input name="name" value="{{ old('name', $lawRegistry->name) }}" 
                    class="w-full px-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary @error('name') ring-2 ring-red-500 @enderror" 
                    required>
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">الوصف</label>
                <textarea name="description" rows="3"
                    class="w-full px-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary resize-none">{{ old('description', $lawRegistry->description) }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">التصنيف</label>
                    <select name="category" class="w-full px-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary">
                        <option value="">اختر التصنيف</option>
                        <option value="civil" {{ old('category', $lawRegistry->category) === 'civil' ? 'selected' : '' }}>مدني</option>
                        <option value="criminal" {{ old('category', $lawRegistry->category) === 'criminal' ? 'selected' : '' }}>جزائي</option>
                        <option value="commercial" {{ old('category', $lawRegistry->category) === 'commercial' ? 'selected' : '' }}>تجاري</option>
                        <option value="labor" {{ old('category', $lawRegistry->category) === 'labor' ? 'selected' : '' }}>عمالي</option>
                        <option value="family" {{ old('category', $lawRegistry->category) === 'family' ? 'selected' : '' }}>أحوال شخصية</option>
                        <option value="administrative" {{ old('category', $lawRegistry->category) === 'administrative' ? 'selected' : '' }}>إداري</option>
                        <option value="evidence" {{ old('category', $lawRegistry->category) === 'evidence' ? 'selected' : '' }}>إثبات</option>
                        <option value="procedures" {{ old('category', $lawRegistry->category) === 'procedures' ? 'selected' : '' }}>إجراءات</option>
                    </select>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">سنة الإصدار (هجري)</label>
                    <input name="effective_year" value="{{ old('effective_year', $lawRegistry->effective_year) }}" 
                        class="w-full px-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">الحالة</label>
                <select name="status" class="w-full px-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary" required>
                    <option value="active" {{ old('status', $lawRegistry->status) === 'active' ? 'selected' : '' }}>نافذ</option>
                    <option value="superseded" {{ old('status', $lawRegistry->status) === 'superseded' ? 'selected' : '' }}>منسوخ</option>
                    <option value="draft" {{ old('status', $lawRegistry->status) === 'draft' ? 'selected' : '' }}>مسودة</option>
                </select>
            </div>

            <div class="flex gap-4">
                <button type="submit" class="flex-1 bg-primary text-white font-bold py-4 rounded-xl hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
                    حفظ التعديلات
                </button>
                <a href="{{ route('law-library.show', $lawRegistry) }}" class="px-8 py-4 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition-colors">
                    إلغاء
                </a>
            </div>
        </form>
    </div>
</div>
@endsection
