@extends('layouts.app')

@section('title', $law->name . ' - المستشار القانوني الذكي')

@section('content')
{{-- Header --}}
<div class="flex items-center gap-4 mb-8">
    <a href="{{ route('laws.index') }}" class="size-10 flex items-center justify-center rounded-xl bg-white border border-slate-200 hover:border-primary transition-colors">
        <span class="material-symbols-outlined">arrow_forward</span>
    </a>
    <div class="flex-1">
        <h2 class="text-2xl font-black tracking-tight">{{ $law->name }}</h2>
        <p class="text-slate-500">إدارة ملفات القانون والمواد المرتبطة</p>
    </div>
</div>

@if(session('success'))
    <div class="bg-emerald-50 border border-emerald-200 text-emerald-800 px-4 py-3 rounded-xl mb-6 flex items-center gap-2">
        <span class="material-symbols-outlined">check_circle</span>
        <span>{{ session('success') }}</span>
    </div>
@endif

@if($errors->any())
    <div class="bg-red-50 border border-red-200 text-red-800 px-4 py-3 rounded-xl mb-6">
        <ul class="list-disc list-inside">
            @foreach($errors->all() as $error)
                <li>{{ $error }}</li>
            @endforeach
        </ul>
    </div>
@endif

{{-- Law Info Card --}}
<div class="bg-white rounded-xl border border-primary/10 shadow-sm p-6 mb-6">
    <div class="flex items-start justify-between mb-4">
        <div class="flex items-start gap-4">
            <div class="size-16 rounded-xl bg-primary/10 flex items-center justify-center text-primary">
                <span class="material-symbols-outlined text-3xl">gavel</span>
            </div>
            <div>
                <h3 class="text-xl font-bold mb-1">{{ $law->name }}</h3>
                @if($law->description)
                    <p class="text-slate-600">{{ $law->description }}</p>
                @endif
                @if($law->category)
                    <div class="mt-2">
                        <span class="inline-flex items-center gap-1 px-3 py-1 bg-primary/10 text-primary rounded-full text-sm font-bold">
                            <span class="material-symbols-outlined text-sm">category</span>
                            {{ $law->category }}
                        </span>
                    </div>
                @endif
            </div>
        </div>
    </div>

    <div class="grid grid-cols-3 gap-4 pt-4 border-t border-slate-100">
        <div class="text-center">
            <div class="text-2xl font-black text-primary">{{ $law->files->count() }}</div>
            <div class="text-sm text-slate-500">ملف</div>
        </div>
        <div class="text-center">
            <div class="text-2xl font-black text-primary">{{ $law->articles->count() }}</div>
            <div class="text-sm text-slate-500">مادة قانونية</div>
        </div>
        <div class="text-center">
            @if($law->isProcessed())
                <span class="inline-flex items-center gap-1 px-3 py-1.5 bg-emerald-100 text-emerald-700 rounded-full text-sm font-bold">
                    <span class="material-symbols-outlined text-sm">check_circle</span>
                    معالج
                </span>
            @else
                <span class="inline-flex items-center gap-1 px-3 py-1.5 bg-amber-100 text-amber-700 rounded-full text-sm font-bold">
                    <span class="material-symbols-outlined text-sm">hourglass_empty</span>
                    قيد المعالجة
                </span>
            @endif
        </div>
    </div>
</div>

{{-- Files Section --}}
<div class="bg-white rounded-xl border border-primary/10 shadow-sm p-6">
    <div class="flex items-center justify-between mb-6">
        <h3 class="text-lg font-bold flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">folder</span>
            ملفات القانون
        </h3>
        <button onclick="document.getElementById('uploadFilesModal').classList.remove('hidden')" class="bg-primary text-white px-4 py-2 rounded-xl font-bold hover:bg-primary/90 transition-all flex items-center gap-2">
            <span class="material-symbols-outlined text-sm">add</span>
            إضافة ملفات
        </button>
    </div>

    @if($law->files->isEmpty())
        <div class="text-center py-12">
            <span class="material-symbols-outlined text-6xl text-slate-300 mb-4 block">description</span>
            <h4 class="font-bold text-lg mb-2">لا توجد ملفات</h4>
            <p class="text-slate-500 mb-4">ابدأ بإضافة ملفات القانون</p>
            <button onclick="document.getElementById('uploadFilesModal').classList.remove('hidden')" class="inline-flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-xl font-bold hover:bg-primary/90 transition-all">
                <span class="material-symbols-outlined">add</span>
                إضافة ملفات
            </button>
        </div>
    @else
        <div class="space-y-3">
            @foreach($law->files as $file)
                <div class="flex items-center gap-4 p-4 bg-slate-50 rounded-xl hover:bg-slate-100 transition-colors">
                    <div class="size-12 rounded-lg bg-white flex items-center justify-center text-primary shrink-0">
                        <span class="material-symbols-outlined">description</span>
                    </div>
                    <div class="flex-1 min-w-0">
                        <h4 class="font-bold text-sm truncate">{{ $file->filename }}</h4>
                        <div class="flex items-center gap-3 text-xs text-slate-500 mt-1">
                            <span>{{ $file->human_readable_size }}</span>
                            <span>•</span>
                            <span>{{ $file->created_at->diffForHumans() }}</span>
                            @if($file->processing_status === 'completed')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-full font-bold">
                                    <span class="material-symbols-outlined" style="font-size: 12px;">check</span>
                                    معالج
                                </span>
                            @elseif($file->processing_status === 'processing')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full font-bold">
                                    <span class="material-symbols-outlined animate-spin" style="font-size: 12px;">hourglass_empty</span>
                                    جاري المعالجة
                                </span>
                            @elseif($file->processing_status === 'failed')
                                <span class="inline-flex items-center gap-1 px-2 py-0.5 bg-red-100 text-red-700 rounded-full font-bold" @if($file->processing_error) title="{{ $file->processing_error }}" @endif>
                                    <span class="material-symbols-outlined" style="font-size: 12px;">error</span>
                                    فشل
                                </span>
                                @if($file->processing_error)
                                    <span class="text-red-600 text-xs">({{ Str::limit($file->processing_error, 40) }})</span>
                                @endif
                            @endif
                        </div>
                    </div>
                    <div class="flex items-center gap-1">
                        <a href="{{ route('laws.download-file', [$law, $file]) }}" class="size-9 flex items-center justify-center text-slate-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="تحميل">
                            <span class="material-symbols-outlined text-lg">download</span>
                        </a>
                        <button onclick="openReplaceModal({{ $file->id }}, '{{ addslashes($file->filename) }}')" class="size-9 flex items-center justify-center text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-colors" title="استبدال">
                            <span class="material-symbols-outlined text-lg">sync</span>
                        </button>
                        <button onclick="confirmDeleteFile({{ $file->id }}, '{{ addslashes($file->filename) }}')" class="size-9 flex items-center justify-center text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="حذف">
                            <span class="material-symbols-outlined text-lg">delete</span>
                        </button>
                    </div>
                </div>
            @endforeach
        </div>
    @endif
</div>

{{-- Upload Files Modal --}}
<div id="uploadFilesModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl">
        <div class="p-6 border-b border-slate-100">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-black flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">add</span>
                    إضافة ملفات جديدة
                </h3>
                <button onclick="document.getElementById('uploadFilesModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        </div>
        
        <form action="{{ route('laws.upload-files', $law) }}" method="POST" enctype="multipart/form-data" class="p-6">
            @csrf
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">اختر الملفات *</label>
                <div class="border-2 border-dashed border-slate-200 rounded-xl p-6 bg-background-light/50 hover:border-primary/30 transition-colors">
                    <input type="file" name="files[]" id="newFiles" multiple
                        accept=".txt,.pdf,.doc,.docx"
                        class="w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:bg-primary file:text-white file:font-bold file:cursor-pointer" required>
                    <p class="text-xs text-slate-500 mt-2">TXT, PDF, DOC, DOCX. حد أقصى 50 ميجابايت للملف. يمكن رفع عدة ملفات.</p>
                </div>
            </div>

            <div class="flex gap-3 pt-6">
                <button type="submit" class="flex-1 bg-primary text-white font-bold py-4 rounded-xl hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
                    رفع الملفات
                </button>
                <button type="button" onclick="document.getElementById('uploadFilesModal').classList.add('hidden')" class="px-8 py-4 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition-colors">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Replace File Modal --}}
<div id="replaceFileModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl">
        <div class="p-6 border-b border-slate-100">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-black flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">sync</span>
                    استبدال الملف
                </h3>
                <button onclick="closeReplaceModal()" class="text-slate-400 hover:text-slate-600">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <p class="text-sm text-slate-500 mt-1">استبدال: <span id="replaceFileName" class="font-bold"></span></p>
        </div>
        
        <form id="replaceFileForm" method="POST" enctype="multipart/form-data" class="p-6">
            @csrf
            <div class="bg-amber-50 border border-amber-200 rounded-xl p-4 mb-4">
                <div class="flex items-start gap-2 text-sm text-amber-800">
                    <span class="material-symbols-outlined text-amber-600 shrink-0">info</span>
                    <div>
                        <p class="font-semibold mb-1">تنبيه:</p>
                        <p>سيتم حذف الملف القديم وجميع المواد المستخرجة منه واستبدالها بالملف الجديد.</p>
                    </div>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">اختر الملف الجديد *</label>
                <input type="file" name="file"
                    accept=".txt,.pdf,.doc,.docx"
                    class="w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:bg-primary file:text-white file:font-bold file:cursor-pointer" required>
                <p class="text-xs text-slate-500 mt-2">TXT, PDF, DOC, DOCX. حد أقصى 50 ميجابايت.</p>
            </div>

            <div class="flex gap-3 pt-6">
                <button type="submit" class="flex-1 bg-blue-600 text-white font-bold py-3 rounded-xl hover:bg-blue-700 transition-all">
                    استبدال الملف
                </button>
                <button type="button" onclick="closeReplaceModal()" class="flex-1 bg-slate-100 text-slate-700 font-bold py-3 rounded-xl hover:bg-slate-200 transition-colors">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Delete File Confirmation Modal --}}
<div id="deleteFileModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-[70] p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="size-16 mx-auto mb-4 rounded-full bg-red-100 flex items-center justify-center">
                <span class="material-symbols-outlined text-red-600 text-4xl">warning</span>
            </div>
            <h3 class="text-xl font-black text-center mb-2">تأكيد حذف الملف</h3>
            <p class="text-slate-600 text-center mb-1">هل أنت متأكد من حذف الملف:</p>
            <p class="text-slate-900 font-bold text-center mb-4" id="deleteFileName"></p>
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                <div class="flex items-start gap-2 text-sm text-red-800">
                    <span class="material-symbols-outlined text-red-600 shrink-0">info</span>
                    <div>
                        <p class="font-semibold mb-1">تحذير:</p>
                        <p>سيتم حذف الملف وجميع المواد المستخرجة منه بشكل نهائي ولا يمكن التراجع عن هذا الإجراء.</p>
                    </div>
                </div>
            </div>
            <form id="deleteFileForm" method="POST">
                @csrf
                @method('DELETE')
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-red-600 text-white font-bold py-3 rounded-xl hover:bg-red-700 transition-all">
                        نعم، احذف الملف
                    </button>
                    <button type="button" onclick="closeDeleteFileModal()" class="flex-1 bg-slate-100 text-slate-700 font-bold py-3 rounded-xl hover:bg-slate-200 transition-colors">
                        إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function openReplaceModal(fileId, fileName) {
    const modal = document.getElementById('replaceFileModal');
    const form = document.getElementById('replaceFileForm');
    const fileNameElement = document.getElementById('replaceFileName');
    
    form.action = '/laws/{{ $law->id }}/files/' + fileId + '/replace';
    fileNameElement.textContent = fileName;
    
    modal.classList.remove('hidden');
}

function closeReplaceModal() {
    document.getElementById('replaceFileModal').classList.add('hidden');
}

function confirmDeleteFile(fileId, fileName) {
    const modal = document.getElementById('deleteFileModal');
    const form = document.getElementById('deleteFileForm');
    const fileNameElement = document.getElementById('deleteFileName');
    
    form.action = '/laws/{{ $law->id }}/files/' + fileId;
    fileNameElement.textContent = fileName;
    
    modal.classList.remove('hidden');
}

function closeDeleteFileModal() {
    document.getElementById('deleteFileModal').classList.add('hidden');
}
</script>
@endpush
@endsection
