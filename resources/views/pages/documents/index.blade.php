@extends('layouts.app')

@section('title', 'إدارة المستندات - المستشار القانوني الذكي')

@push('styles')
<style>
    #uploadCaseId,
    #caseSort {
        appearance: none;
        -webkit-appearance: none;
        -moz-appearance: none;
        background-image: none;
    }
    .document-highlight {
        animation: documentHighlight 2s ease-out forwards;
    }
    @keyframes documentHighlight {
        0% { opacity: 0.4; box-shadow: 0 0 0 3px rgba(0, 107, 52, 0.6); }
        70% { opacity: 1; box-shadow: 0 0 0 6px rgba(0, 107, 52, 0.2); }
        100% { opacity: 1; box-shadow: none; }
    }
    #searchResultsDropdown {
        max-height: 20rem;
        overflow-y: auto;
    }
    #allDocsDropdownContent {
        transition: max-height 0.25s ease-out, opacity 0.2s ease-out;
    }
    #allDocsDropdownContent:not(.open) {
        max-height: 0;
        overflow: hidden;
        opacity: 0;
    }
    #allDocsDropdownContent.open {
        max-height: 80vh;
        opacity: 1;
    }
</style>
@endpush

@section('content')
{{-- Header --}}
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-4 rounded-xl shadow-sm border border-primary/5 mb-8">
    <div>
        <h2 class="text-xl font-bold">مستودع الأدلة والمستندات</h2>
        <p class="text-slate-500 text-sm">كل قضية لها مجلدها ومرفقاتها قابلة للتحميل</p>
    </div>
    <button onclick="document.getElementById('uploadModal').classList.remove('hidden')" class="bg-primary hover:bg-primary/90 text-white px-6 py-2.5 rounded-xl font-bold flex items-center gap-2 transition-all shadow-lg shadow-primary/20">
        <span class="material-symbols-outlined">upload_file</span>
        <span>رفع ملف جديد</span>
    </button>
</div>

<div class="flex gap-8">
    {{-- Case folders: wider sidebar + real-time search --}}
    <aside class="w-80 xl:w-96 shrink-0 flex flex-col gap-4">
        <div class="flex-1 min-h-0 flex flex-col">
            <h3 class="text-xs font-bold text-slate-400 uppercase tracking-widest mb-3 pr-2">مجلدات القضايا</h3>
            <div class="relative mb-3" id="searchWrap">
                <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg pointer-events-none">search</span>
                <input type="text" id="caseSearch" placeholder="ابحث عن قضية أو مستند..." autocomplete="off" class="w-full bg-background-light border-none rounded-xl pr-10 pl-4 py-2.5 text-sm focus:ring-2 focus:ring-primary/20 focus:bg-white transition-all" />
                <div id="searchResultsDropdown" class="hidden absolute top-full left-0 right-0 mt-1 bg-white rounded-xl border border-primary/10 shadow-lg z-20 overflow-hidden">
                    <div id="searchResultsContent"></div>
                </div>
            </div>
            <div class="flex items-center gap-2 mb-3">
                <span class="text-xs text-slate-500">ترتيب:</span>
                <div class="relative flex-1">
                    <select id="caseSort" class="w-full text-xs bg-background-light border-none rounded-lg py-2 pl-8 pr-2 focus:ring-2 focus:ring-primary/20 appearance-none">
                        <option value="date_desc" {{ ($sort ?? 'date_desc') === 'date_desc' ? 'selected' : '' }}>الأحدث</option>
                        <option value="date_asc" {{ ($sort ?? '') === 'date_asc' ? 'selected' : '' }}>الأقدم</option>
                        <option value="name_asc" {{ ($sort ?? '') === 'name_asc' ? 'selected' : '' }}>أ-ي</option>
                        <option value="name_desc" {{ ($sort ?? '') === 'name_desc' ? 'selected' : '' }}>ي-أ</option>
                    </select>
                    <span class="material-symbols-outlined absolute left-2 top-1/2 -translate-y-1/2 text-slate-400 text-sm pointer-events-none">expand_more</span>
                </div>
            </div>
            <div class="flex flex-col gap-1 overflow-y-auto min-h-0" id="caseFolderList">
                {{-- All Documents: dropdown with case folders nested under it --}}
                <div class="rounded-xl border {{ !request('case_id') ? 'bg-white shadow-sm border-slate-200' : 'border-transparent' }}">
                    <div class="flex items-center gap-2 px-4 py-3">
                        <button type="button" id="allDocsDropdownToggle" class="flex items-center gap-2 flex-1 min-w-0 text-right rounded-lg hover:bg-slate-50 transition-colors -m-1 p-1" aria-expanded="true" aria-controls="allDocsDropdownContent">
                            <span class="material-symbols-outlined text-primary shrink-0 transition-transform" id="allDocsChevron">expand_more</span>
                            <span class="material-symbols-outlined {{ !request('case_id') ? 'fill-1 text-primary' : 'text-slate-400' }} shrink-0">folder_open</span>
                            <span class="text-sm font-bold truncate {{ !request('case_id') ? 'text-primary' : 'text-slate-600' }}">جميع المستندات</span>
                            <span class="mr-auto text-xs bg-primary/10 text-primary px-2 py-0.5 rounded-full shrink-0">{{ $documents->total() }}</span>
                        </button>
                        <a href="{{ route('documents.index', array_filter(['sort' => $sort ?? 'date_desc'])) }}" class="shrink-0 p-1.5 rounded-lg hover:bg-primary/10 text-slate-500 hover:text-primary transition-colors" title="عرض الكل">
                            <span class="material-symbols-outlined text-lg">open_in_new</span>
                        </a>
                    </div>
                    <div id="allDocsDropdownContent" class="open pl-4 pr-2 pb-2 border-t border-slate-100 mt-0 pt-1" role="region">
                        @forelse($cases as $c)
                            <a href="{{ route('documents.index', ['case_id' => $c->id, 'sort' => $sort ?? 'date_desc']) }}" class="case-folder-item flex flex-col gap-0.5 px-3 py-2.5 rounded-lg transition-all {{ ($selectedCaseId ?? '') == $c->id ? 'bg-primary/10 text-primary' : 'hover:bg-slate-50 text-slate-600' }}" data-search="{{ $c->title }}">
                                <div class="flex items-center gap-2">
                                    <span class="material-symbols-outlined {{ ($selectedCaseId ?? '') == $c->id ? 'fill-1 text-primary' : 'text-slate-400' }} text-lg">folder</span>
                                    <span class="text-sm font-semibold truncate flex-1 min-w-0" title="{{ $c->title }}">{{ $c->title }}</span>
                                    <span class="text-xs bg-slate-100 px-2 py-0.5 rounded-full shrink-0">{{ $c->documents_count }}</span>
                                </div>
                                <p class="text-[11px] text-slate-400 pr-8">{{ $c->created_at->format('Y-m-d') }}</p>
                            </a>
                        @empty
                            <p class="text-xs text-slate-400 px-3 py-2">لا توجد قضايا بعد</p>
                        @endforelse
                    </div>
                </div>
            </div>
            <p id="caseSearchNoResults" class="text-xs text-slate-400 px-4 py-2 hidden">لا توجد نتائج</p>
        </div>
    </aside>
    
    {{-- Documents Grid --}}
    <div class="flex-1">
        @if($selectedCaseId && $cases->firstWhere('id', $selectedCaseId))
            <p class="text-sm text-slate-500 mb-4">مستندات قضية: <strong>{{ $cases->firstWhere('id', $selectedCaseId)->title }}</strong></p>
        @endif
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            @forelse($documents as $document)
                <div id="doc-{{ $document->id }}" class="bg-white p-5 rounded-xl border border-primary/5 shadow-sm hover:border-primary transition-all magnetic-element {{ (isset($highlightDocumentId) && $highlightDocumentId == $document->id) ? 'document-highlight' : '' }}">
                    <div class="flex items-start gap-4">
                        @if($document->isPdf())
                            <div class="size-12 rounded-lg bg-red-50 flex items-center justify-center text-red-600">
                                <span class="material-symbols-outlined">picture_as_pdf</span>
                            </div>
                        @elseif($document->isImage())
                            <div class="size-12 rounded-lg bg-emerald-50 flex items-center justify-center text-emerald-600">
                                <span class="material-symbols-outlined">image</span>
                            </div>
                        @else
                            <div class="size-12 rounded-lg bg-slate-100 flex items-center justify-center text-slate-600">
                                <span class="material-symbols-outlined">description</span>
                            </div>
                        @endif
                        <div class="flex-1 min-w-0">
                            <h4 class="font-bold text-sm truncate" title="{{ $document->filename }}">{{ $document->filename }}</h4>
                            <p class="text-xs text-slate-500">{{ $document->human_readable_size ?? '---' }}</p>
                            <p class="text-xs text-slate-400 mt-1">{{ $document->case?->title ?? '—' }}</p>
                            <p class="text-xs text-slate-400">{{ $document->created_at->format('Y-m-d') }}</p>
                        </div>
                    </div>
                    <div class="flex gap-2 mt-4">
                        <a href="{{ route('documents.preview', $document) }}" target="_blank" rel="noopener" class="flex-1 text-center py-2 bg-primary/10 text-primary rounded-lg text-xs font-bold hover:bg-primary/20 transition-colors" title="معاينة">
                            <span class="material-symbols-outlined text-sm align-middle">visibility</span>
                        </a>
                        <a href="{{ route('documents.download', $document) }}" class="flex-1 text-center py-2 bg-slate-100 text-slate-600 rounded-lg text-xs font-bold hover:bg-slate-200 transition-colors" title="تحميل">
                            <span class="material-symbols-outlined text-sm align-middle">download</span>
                        </a>
                    </div>
                </div>
            @empty
                <div class="col-span-full text-center py-12 text-slate-500">
                    <span class="material-symbols-outlined text-6xl text-slate-300 mb-4 block">folder_open</span>
                    <p class="font-bold">لا توجد مستندات</p>
                    <p class="text-sm">@if($selectedCaseId) لا توجد مرفقات في هذه القضية. @else ابدأ برفع المستندات أو أنشئ قضية مع مرفقات. @endif</p>
                </div>
            @endforelse
        </div>
        
        @if($documents->hasPages())
            <div class="mt-6">
                {{ $documents->links() }}
            </div>
        @endif
    </div>
</div>

{{-- Upload Modal: portal-style UI, multiple files, loading, toast on success --}}
<div id="uploadModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-xl border border-primary/10 shadow-lg max-w-lg w-full overflow-hidden relative">
        <div class="p-6 border-b border-primary/5">
            <h3 class="text-xl font-black text-slate-900 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">upload_file</span>
                رفع ملفات
            </h3>
            <p class="text-slate-500 text-sm mt-1">اختر مجلد القضية وملفاً أو أكثر. حد أقصى 50 ميجابايت لكل ملف، ولا حد لإجمالي الطلب.</p>
        </div>
        <form id="uploadForm" action="{{ route('documents.store') }}" method="POST" enctype="multipart/form-data" class="p-6">
            @csrf
            <div class="mb-5">
                <label class="block text-sm font-semibold text-slate-700 mb-2">مجلد القضية</label>
                <div class="relative">
                    <select name="case_id" id="uploadCaseId" class="upload-case-select w-full pl-10 pr-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary text-slate-800" required>
                        <option value="">اختر القضية</option>
                        @foreach($cases as $c)
                            <option value="{{ $c->id }}" {{ (old('case_id', $selectedCaseId) == $c->id ? 'selected' : '') }}>{{ $c->title }}</option>
                        @endforeach
                    </select>
                    <span class="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                </div>
            </div>
            <div class="mb-5">
                <label class="block text-sm font-semibold text-slate-700 mb-2">الملفات</label>
                <div class="border-2 border-dashed border-slate-200 rounded-xl p-6 bg-background-light/50 hover:border-primary/30 transition-colors">
                    <input type="file" name="files[]" id="uploadFiles" multiple
                        accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.txt,.doc,.docx,.pdf,.ppt,.pptx,image/*,text/plain,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation"
                        class="w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:bg-primary file:text-white file:font-bold file:cursor-pointer">
                    <p class="text-xs text-slate-500 mt-2">صور، TXT، DOC/DOCX، PDF، PPT/PPTX. يمكن اختيار عدة ملفات.</p>
                    <ul id="uploadFileList" class="mt-3 space-y-1 text-sm text-slate-600 hidden"></ul>
                </div>
                @error('files.*')
                    <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                @enderror
            </div>
            <div class="flex gap-3">
                <button type="submit" id="uploadSubmitBtn" class="flex-1 bg-primary text-white py-3.5 rounded-xl font-bold hover:bg-primary/90 transition-all shadow-lg shadow-primary/20 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined">upload</span>
                    رفع الملفات
                </button>
                <button type="button" onclick="document.getElementById('uploadModal').classList.add('hidden')" class="px-6 py-3.5 bg-slate-100 text-slate-700 rounded-xl font-bold hover:bg-slate-200 transition-colors">
                    إلغاء
                </button>
            </div>
        </form>
        {{-- Loading overlay --}}
        <div id="uploadLoading" class="hidden absolute inset-0 bg-white/90 flex flex-col items-center justify-center gap-4 rounded-xl">
            <span class="material-symbols-outlined text-primary text-5xl animate-spin">sync</span>
            <p class="text-slate-600 font-bold">جاري الرفع...</p>
        </div>
    </div>
</div>

@push('scripts')
<script>
(function() {
    var searchInput = document.getElementById('caseSearch');
    var searchWrap = document.getElementById('searchWrap');
    var dropdown = document.getElementById('searchResultsDropdown');
    var dropdownContent = document.getElementById('searchResultsContent');
    var list = document.getElementById('caseFolderList');
    var noResults = document.getElementById('caseSearchNoResults');
    var searchTimeout = null;
    var currentSort = '{{ $sort ?? "date_desc" }}';

    var allDocsToggle = document.getElementById('allDocsDropdownToggle');
    var allDocsContent = document.getElementById('allDocsDropdownContent');
    var allDocsChevron = document.getElementById('allDocsChevron');
    if (allDocsToggle && allDocsContent) {
        allDocsToggle.addEventListener('click', function() {
            var isOpen = allDocsContent.classList.toggle('open');
            allDocsToggle.setAttribute('aria-expanded', isOpen ? 'true' : 'false');
            if (allDocsChevron) allDocsChevron.style.transform = isOpen ? 'rotate(0deg)' : 'rotate(-90deg)';
        });
        if (allDocsChevron) allDocsChevron.style.transition = 'transform 0.2s ease';
    }

    function hideDropdown() {
        if (dropdown) dropdown.classList.add('hidden');
    }

    function showDropdown() {
        if (dropdown) dropdown.classList.remove('hidden');
    }

    if (searchInput && dropdownContent) {
        searchInput.addEventListener('input', function() {
            var q = this.value.trim();
            if (q.length < 1) {
                hideDropdown();
                if (list && noResults) {
                    list.querySelectorAll('.case-folder-item').forEach(function(el) { el.style.display = ''; });
                    noResults.classList.add('hidden');
                }
                return;
            }
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                fetch('{{ route("documents.search") }}?q=' + encodeURIComponent(q))
                    .then(function(r) { return r.json(); })
                    .then(function(data) {
                        var html = '';
                        if (data.cases && data.cases.length) {
                            html += '<div class="px-3 py-2 text-xs font-bold text-slate-400 border-b border-slate-100">قضايا</div>';
                            data.cases.forEach(function(c) {
                                var url = '{{ route("documents.index") }}?case_id=' + encodeURIComponent(c.id) + '&sort=' + currentSort;
                                html += '<a href="' + url + '" class="flex items-center gap-2 px-4 py-2.5 hover:bg-primary/5 text-sm text-slate-700 border-b border-slate-50 last:border-0"><span class="material-symbols-outlined text-slate-400 text-lg">folder</span><span class="truncate">' + (c.title || '') + '</span></a>';
                            });
                        }
                        if (data.documents && data.documents.length) {
                            html += '<div class="px-3 py-2 text-xs font-bold text-slate-400 border-b border-slate-100">مستندات</div>';
                            data.documents.forEach(function(d) {
                                var url = '{{ route("documents.index") }}?case_id=' + encodeURIComponent(d.case_id) + '&highlight_document=' + d.id + '&sort=' + currentSort;
                                html += '<a href="' + url + '" class="flex items-center gap-2 px-4 py-2.5 hover:bg-primary/5 text-sm text-slate-700 border-b border-slate-50 last:border-0"><span class="material-symbols-outlined text-slate-400 text-lg">description</span><span class="truncate flex-1">' + (d.filename || '') + '</span><span class="text-xs text-slate-400 truncate max-w-[8rem]">' + (d.case_title || '') + '</span></a>';
                            });
                        }
                        if (!html) html = '<div class="px-4 py-4 text-sm text-slate-500 text-center">لا توجد نتائج</div>';
                        dropdownContent.innerHTML = html;
                        showDropdown();
                    })
                    .catch(function() {
                        dropdownContent.innerHTML = '<div class="px-4 py-4 text-sm text-slate-500 text-center">حدث خطأ</div>';
                        showDropdown();
                    });
            }, 200);
        });
        searchInput.addEventListener('focus', function() {
            if (this.value.trim() && dropdownContent && dropdownContent.innerHTML.trim()) showDropdown();
        });
    }
    document.addEventListener('click', function(e) {
        if (searchWrap && !searchWrap.contains(e.target)) hideDropdown();
    });
    if (list && noResults && searchInput) {
        searchInput.addEventListener('input', function() {
            var q = this.value.trim().toLowerCase();
            if (q.length < 1) {
                var items = list.querySelectorAll('.case-folder-item');
                items.forEach(function(el) { el.style.display = ''; });
                noResults.classList.add('hidden');
            }
        });
    }
    var caseSort = document.getElementById('caseSort');
    if (caseSort) {
        caseSort.addEventListener('change', function() {
            var params = new URLSearchParams(window.location.search);
            params.set('sort', this.value);
            window.location = '{{ route("documents.index") }}?' + params.toString();
        });
    }
    var uploadFiles = document.getElementById('uploadFiles');
    var uploadFileList = document.getElementById('uploadFileList');
    if (uploadFiles && uploadFileList) {
        uploadFiles.addEventListener('change', function() {
            uploadFileList.innerHTML = '';
            if (this.files.length) {
                uploadFileList.classList.remove('hidden');
                for (var i = 0; i < this.files.length; i++) {
                    var li = document.createElement('li');
                    li.textContent = this.files[i].name + ' (' + (this.files[i].size / 1024 / 1024).toFixed(2) + ' MB)';
                    uploadFileList.appendChild(li);
                }
            } else {
                uploadFileList.classList.add('hidden');
            }
        });
    }
    var uploadForm = document.getElementById('uploadForm');
    var uploadLoading = document.getElementById('uploadLoading');
    var uploadSubmitBtn = document.getElementById('uploadSubmitBtn');
    if (uploadForm && uploadLoading) {
        uploadForm.addEventListener('submit', function(e) {
            e.preventDefault();
            var files = document.getElementById('uploadFiles');
            if (!files || !files.files.length) {
                if (window.showToast) window.showToast('يرجى اختيار ملف واحد على الأقل.', 'error');
                return;
            }
            uploadLoading.classList.remove('hidden');
            if (uploadSubmitBtn) uploadSubmitBtn.disabled = true;
            var formData = new FormData(uploadForm);
            var action = uploadForm.action;
            var token = document.querySelector('input[name="_token"]');
            if (token) formData.set('_token', token.value);
            fetch(action, {
                method: 'POST',
                body: formData,
                headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' }
            }).then(function(res) {
                if (res.status === 413) {
                    if (window.showToast) window.showToast('حجم الطلب يتجاوز الحد المسموح به على الخادم. قلّل حجم الملفات وحاول مرة أخرى.', 'error');
                    uploadLoading.classList.add('hidden');
                    if (uploadSubmitBtn) uploadSubmitBtn.disabled = false;
                    return;
                }
                if (!res.ok) {
                    return res.json().catch(function() { return {}; }).then(function(data) {
                        var msg = (data && data.message) || 'حدث خطأ أثناء الرفع.';
                        if (data && data.errors) {
                            var first = Object.keys(data.errors)[0];
                            if (first && data.errors[first][0]) msg = data.errors[first][0];
                        }
                        if (window.showToast) window.showToast(msg, 'error');
                        uploadLoading.classList.add('hidden');
                        if (uploadSubmitBtn) uploadSubmitBtn.disabled = false;
                    });
                }
                return res.json().then(function(data) {
                    if (data && data.success && data.message) {
                        if (window.showToast) window.showToast(data.message, 'success');
                    }
                    window.location.reload();
                });
            }).catch(function() {
                if (window.showToast) window.showToast('حدث خطأ في الاتصال. حاول مرة أخرى.', 'error');
                uploadLoading.classList.add('hidden');
                if (uploadSubmitBtn) uploadSubmitBtn.disabled = false;
            });
        });
    }
    var highlightId = '{{ $highlightDocumentId ?? "" }}';
    if (highlightId) {
        var el = document.getElementById('doc-' + highlightId);
        if (el) {
            el.scrollIntoView({ behavior: 'smooth', block: 'center' });
        }
    }
})();
</script>
@endpush
@endsection
