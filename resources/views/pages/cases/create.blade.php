@extends('layouts.app')

@section('title', 'إنشاء قضية جديدة - المستشار القانوني الذكي')

@section('content')
<div class="max-w-2xl mx-auto">
    <div class="flex justify-between items-center mb-6">
        <a href="{{ route('cases.index') }}" class="flex items-center gap-1.5 text-sm text-slate-600 hover:text-primary transition-colors" title="العودة للقضايا">
            <span class="material-symbols-outlined text-lg">arrow_forward</span>
            <span>العودة</span>
        </a>
        <span class="text-sm text-slate-900 font-semibold">إنشاء قضية جديدة</span>
    </div>
    
    <div class="bg-white p-8 rounded-xl border border-primary/10 shadow-lg">
        <div class="flex items-center gap-3 mb-8">
            <div class="size-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
                <span class="material-symbols-outlined">add_task</span>
            </div>
            <div>
                <h1 class="text-xl font-black">إنشاء قضية جديدة</h1>
                <p class="text-slate-500 text-sm">أدخل تفاصيل القضية لبدء التحليل الذكي</p>
            </div>
        </div>
        
        <form action="{{ route('cases.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">عنوان القضية *</label>
                <input name="title" value="{{ old('title') }}" 
                    class="w-full px-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary @error('title') ring-2 ring-red-500 @enderror" 
                    placeholder="مثال: نزاع ملكية عقارية - الشيخ زايد" required>
                @error('title')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">اسم العميل</label>
                <div class="relative">
                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">person</span>
                    <input name="client_name" value="{{ old('client_name') }}" 
                        class="w-full pr-10 pl-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary" 
                        placeholder="اسم العميل أو الشركة">
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">تصنيف القضية</label>
                <div class="relative">
                    <select name="category" class="w-full pl-10 pr-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary appearance-none">
                        <option value="civil" {{ old('category') === 'civil' ? 'selected' : '' }}>مدني</option>
                        <option value="criminal" {{ old('category') === 'criminal' ? 'selected' : '' }}>جنائي</option>
                        <option value="commercial" {{ old('category') === 'commercial' ? 'selected' : '' }}>تجاري</option>
                        <option value="family" {{ old('category') === 'family' ? 'selected' : '' }}>أحوال شخصية</option>
                        <option value="administrative" {{ old('category') === 'administrative' ? 'selected' : '' }}>إداري</option>
                        <option value="labor" {{ old('category') === 'labor' ? 'selected' : '' }}>عمالي</option>
                    </select>
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                </div>
            </div>
            
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">وصف القضية</label>
                <textarea name="description" rows="5"
                    class="w-full px-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary resize-none" 
                    placeholder="اكتب تفاصيل وملخص القضية هنا...">{{ old('description') }}</textarea>
            </div>
            
            <div>
                <span class="block text-sm font-semibold text-slate-700 mb-2">مرفقات القضية</span>
                <label for="attachments" class="block border-2 border-dashed border-slate-200 rounded-xl p-8 bg-background-light/50 hover:border-primary/40 hover:bg-primary/5 transition-colors cursor-pointer mt-2">
                    <input type="file" name="attachments[]" id="attachments" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.txt,.doc,.docx,.pdf,.ppt,.pptx,image/*,text/plain,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation"
                        class="sr-only">
                    <div class="flex flex-col items-center justify-center gap-2 text-center">
                        <span class="material-symbols-outlined text-4xl text-primary/70">upload_file</span>
                        <span class="text-sm font-medium text-slate-600">اسحب الملفات هنا أو انقر لاختيار الملفات</span>
                        <span class="text-xs text-slate-500">الصور، TXT، DOC/DOCX، PDF، PPT/PPTX — حد أقصى 50 ميجابايت للملف</span>
                    </div>
                    <ul id="fileList" class="mt-4 pt-4 border-t border-slate-200 space-y-1.5 text-sm text-slate-600 hidden"></ul>
                </label>
                @error('attachments.*')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            
            <div class="bg-primary/5 p-4 rounded-xl flex items-start gap-3">
                <span class="material-symbols-outlined text-primary">info</span>
                <p class="text-sm text-slate-600">المرفقات ستُحفظ في مجلد القضية وتظهر في مستندات. بعد إنشاء القضية، سيبدأ الذكاء الاصطناعي بتحليلها تلقائياً.</p>
            </div>
            
            <div class="flex gap-4">
                <button type="submit" class="flex-1 bg-primary text-white font-bold py-4 rounded-xl hover:bg-primary/90 transition-all shadow-lg shadow-primary/20 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">check</span>
                    حفظ وإنشاء القضية
                </button>
                <a href="{{ route('cases.index') }}" class="px-8 py-4 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition-colors">
                    إلغاء
                </a>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script>
document.getElementById('attachments').addEventListener('change', function() {
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
