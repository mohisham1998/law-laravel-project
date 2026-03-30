@extends('layouts.app')

@section('title', 'إضافة نظام جديد')

@section('content')
<div class="max-w-3xl mx-auto">
    <div class="flex items-center gap-2 text-sm text-slate-500 mb-6">
        <a href="{{ route('law-library.index') }}" class="hover:text-primary">مكتبة الأنظمة</a>
        <span class="material-symbols-outlined text-xs">chevron_left</span>
        <span class="text-slate-900 font-semibold">إضافة نظام جديد</span>
    </div>

    <div class="bg-white p-8 rounded-xl border border-primary/10 shadow-lg">
        <div class="flex items-center gap-3 mb-8">
            <div class="size-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
                <span class="material-symbols-outlined">gavel</span>
            </div>
            <div>
                <h1 class="text-xl font-black">إضافة نظام قانوني جديد</h1>
                <p class="text-slate-500 text-sm">سيتم معالجة الملفات تلقائياً وإنشاء فهرس قابل للبحث</p>
            </div>
        </div>

        <form action="{{ route('law-library.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">اسم النظام *</label>
                <input name="name" value="{{ old('name') }}" 
                    class="w-full px-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary @error('name') ring-2 ring-red-500 @enderror" 
                    placeholder="مثال: نظام الإثبات" required>
                @error('name')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">الوصف</label>
                <textarea name="description" rows="3"
                    class="w-full px-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary resize-none" 
                    placeholder="وصف موجز للنظام ومجال تطبيقه">{{ old('description') }}</textarea>
            </div>

            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">التصنيف</label>
                    <div class="relative">
                        <select name="category" class="w-full pr-10 pl-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary appearance-none">
                            <option value="">اختر التصنيف</option>
                            <option value="civil" {{ old('category') === 'civil' ? 'selected' : '' }}>مدني</option>
                            <option value="criminal" {{ old('category') === 'criminal' ? 'selected' : '' }}>جزائي</option>
                            <option value="commercial" {{ old('category') === 'commercial' ? 'selected' : '' }}>تجاري</option>
                            <option value="labor" {{ old('category') === 'labor' ? 'selected' : '' }}>عمالي</option>
                            <option value="family" {{ old('category') === 'family' ? 'selected' : '' }}>أحوال شخصية</option>
                            <option value="administrative" {{ old('category') === 'administrative' ? 'selected' : '' }}>إداري</option>
                            <option value="evidence" {{ old('category') === 'evidence' ? 'selected' : '' }}>إثبات</option>
                            <option value="procedures" {{ old('category') === 'procedures' ? 'selected' : '' }}>إجراءات</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                    </div>
                </div>

                <div>
                    <label class="block text-sm font-semibold text-slate-700 mb-2">سنة الإصدار (هجري)</label>
                    <input name="effective_year" value="{{ old('effective_year') }}" 
                        class="w-full px-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary" 
                        placeholder="مثال: 1443">
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">ملفات النظام *</label>
                <div class="border-2 border-dashed border-slate-200 rounded-xl p-6 bg-background-light/50 hover:border-primary/30 transition-colors">
                    <input type="file" name="files[]" id="lawFiles" multiple
                        accept=".txt,.pdf,.doc,.docx,text/plain,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document"
                        class="w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:bg-primary file:text-white file:font-bold file:cursor-pointer" required>
                    <p class="text-xs text-slate-500 mt-2">TXT, PDF, DOC, DOCX. حد أقصى 50 ميجابايت للملف. يمكن رفع عدة ملفات.</p>
                    <ul id="fileList" class="mt-3 space-y-1 text-sm text-slate-600 hidden"></ul>
                </div>
                @error('files.*')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>

            <div class="bg-primary/5 p-4 rounded-xl flex items-start gap-3">
                <span class="material-symbols-outlined text-primary">info</span>
                <div class="text-sm text-slate-600">
                    <p class="font-semibold mb-1">سيتم تلقائياً:</p>
                    <ul class="list-disc list-inside space-y-1">
                        <li>استخراج جميع المواد من الملفات</li>
                        <li>إنشاء فهرس قابل للبحث</li>
                        <li>توليد embeddings للبحث الدلالي (RAG)</li>
                        <li>ربط النظام بنظام الوكلاء الذكي</li>
                    </ul>
                </div>
            </div>

            <div class="flex gap-4">
                <button type="submit" class="flex-1 bg-primary text-white font-bold py-4 rounded-xl hover:bg-primary/90 transition-all shadow-lg shadow-primary/20 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">check</span>
                    حفظ ومعالجة
                </button>
                <a href="{{ route('law-library.index') }}" class="px-8 py-4 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition-colors">
                    إلغاء
                </a>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('lawFiles').addEventListener('change', function() {
    var list = document.getElementById('fileList');
    list.innerHTML = '';
    if (this.files.length) {
        list.classList.remove('hidden');
        for (var i = 0; i < this.files.length; i++) {
            var li = document.createElement('li');
            li.textContent = this.files[i].name + ' (' + (this.files[i].size / 1024 / 1024).toFixed(2) + ' MB)';
            list.appendChild(li);
        }
    } else {
        list.classList.add('hidden');
    }
});
</script>
@endpush
@endsection
