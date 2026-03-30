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
        
        @if($errors->any())
            <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200">
                <p class="font-semibold text-red-800 mb-2">يرجى تصحيح الأخطاء أدناه:</p>
                <ul class="list-disc list-inside text-sm text-red-700 space-y-1">
                    @foreach($errors->all() as $err)
                        <li>{{ $err }}</li>
                    @endforeach
                </ul>
                <p class="text-xs text-red-600 mt-2">المرفقات لا تُعاد بعد الخطأ؛ يرجى اختيارها مرة أخرى.</p>
            </div>
        @endif

        <form id="createCaseForm" action="{{ route('cases.store') }}" method="POST" enctype="multipart/form-data" class="space-y-6">
            @csrf
            <input type="hidden" name="puter_token" id="puterTokenInput" value="">
            
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
                    <select name="category" class="w-full pr-10 pl-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary appearance-none">
                        <option value="civil" {{ old('category') === 'civil' ? 'selected' : '' }}>مدني</option>
                        <option value="criminal" {{ old('category') === 'criminal' ? 'selected' : '' }}>جنائي</option>
                        <option value="commercial" {{ old('category') === 'commercial' ? 'selected' : '' }}>تجاري</option>
                        <option value="family" {{ old('category') === 'family' ? 'selected' : '' }}>أحوال شخصية</option>
                        <option value="administrative" {{ old('category') === 'administrative' ? 'selected' : '' }}>إداري</option>
                        <option value="labor" {{ old('category') === 'labor' ? 'selected' : '' }}>عمالي</option>
                    </select>
                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
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
                <button type="submit" id="submitCaseBtn" class="flex-1 bg-primary text-white font-bold py-4 rounded-xl hover:bg-primary/90 transition-all shadow-lg shadow-primary/20 flex items-center justify-center gap-2 disabled:opacity-70 disabled:cursor-not-allowed relative overflow-hidden">
                    <span class="submit-icon material-symbols-outlined">check</span>
                    <span class="submit-text">حفظ وإنشاء القضية</span>
                    <div id="loadingOverlay" class="absolute inset-0 bg-primary flex items-center justify-center opacity-0 transition-opacity duration-300">
                        <div class="flex items-center gap-2">
                            <div class="relative">
                                <div class="w-6 h-6 border-2 border-white/30 border-t-white rounded-full animate-spin"></div>
                            </div>
                            <span id="loadingText" class="text-white font-bold whitespace-nowrap">جاري إنشاء القضية</span>
                        </div>
                    </div>
                </button>
                <a href="{{ route('cases.index') }}" class="px-8 py-4 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition-colors">
                    إلغاء
                </a>
            </div>
        </form>
    </div>
</div>
@push('scripts')
<script src="https://js.puter.com/v2/"></script>
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

document.getElementById('createCaseForm').addEventListener('submit', async function(e) {
    // Inject Puter token if Puter provider is active
    try {
        if (typeof puter !== 'undefined') {
            var token = '';

            if (puter.authToken) {
                if (typeof puter.authToken.then === 'function') {
                    token = await puter.authToken;
                } else {
                    token = puter.authToken;
                }
            }

            if (!token && puter.auth && typeof puter.auth.getAuthToken === 'function') {
                var authToken = puter.auth.getAuthToken();
                if (authToken && typeof authToken.then === 'function') {
                    token = await authToken;
                } else {
                    token = authToken;
                }
            }

            token = String(token || '').trim();
            if (/^bearer\s+/i.test(token)) {
                token = token.replace(/^bearer\s+/i, '').trim();
            }

            document.getElementById('puterTokenInput').value = token;
        }
    } catch (err) {
        // puter.js not loaded or not connected — fine, token stays empty
    }

    var btn = document.getElementById('submitCaseBtn');
    if (btn.disabled) return;
    
    // Disable button
    btn.disabled = true;
    
    // Hide original content
    var icon = btn.querySelector('.submit-icon');
    var text = btn.querySelector('.submit-text');
    if (icon) icon.style.opacity = '0';
    if (text) text.style.opacity = '0';
    
    // Show loading overlay with animation
    var overlay = document.getElementById('loadingOverlay');
    overlay.classList.remove('opacity-0');
    
    // Animate loading text cycling
    var loadingTexts = [
        'جاري إنشاء القضية',
        'جارٍ تحليل القضية',
        'جاري معالجة المستندات',
        'قارب الاكتمال'
    ];
    var loadingTextEl = document.getElementById('loadingText');
    var textIndex = 0;
    
    // Function to cycle through loading texts with fade
    function cycleLoadingText() {
        loadingTextEl.style.opacity = '0';
        setTimeout(function() {
            textIndex = (textIndex + 1) % loadingTexts.length;
            loadingTextEl.textContent = loadingTexts[textIndex];
            loadingTextEl.style.opacity = '1';
        }, 300);
    }
    
    // Start cycling text every 2 seconds
    var textInterval = setInterval(cycleLoadingText, 2000);
    
    // Initial text
    loadingTextEl.style.transition = 'opacity 0.3s ease';
    
    // Store interval on button to clear it later if needed
    btn.dataset.textInterval = textInterval;
});
</script>
@endpush
@endsection
