@extends('layouts.app')

@section('title', $lawRegistry->name)

@section('content')
<div class="flex items-center gap-2 text-sm text-slate-500 mb-6">
    <a href="{{ route('law-library.index') }}" class="hover:text-primary">مكتبة الأنظمة</a>
    <span class="material-symbols-outlined text-xs">chevron_left</span>
    <span class="text-slate-900 font-semibold">{{ $lawRegistry->name }}</span>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
    <div class="lg:col-span-2 space-y-6">
        {{-- Law Info --}}
        <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
            <div class="flex justify-between items-start mb-4">
                <div class="flex-1">
                    <h1 class="text-2xl font-black mb-2">{{ $lawRegistry->name }}</h1>
                    <div class="flex items-center gap-3 text-sm">
                        @if($lawRegistry->effective_year)
                            <span class="px-3 py-1.5 bg-slate-100 text-slate-600 font-bold rounded-full">{{ $lawRegistry->effective_year }} هـ</span>
                        @endif
                        @if($lawRegistry->category)
                            <span class="px-3 py-1.5 bg-primary/10 text-primary font-bold rounded-full">{{ $lawRegistry->category }}</span>
                        @endif
                        <span class="px-3 py-1.5 {{ $lawRegistry->status === 'active' ? 'bg-emerald-100 text-emerald-700' : 'bg-slate-100 text-slate-600' }} font-bold rounded-full">
                            {{ $lawRegistry->status === 'active' ? 'نافذ' : ($lawRegistry->status === 'superseded' ? 'منسوخ' : 'مسودة') }}
                        </span>
                    </div>
                </div>
                <a href="{{ route('law-library.edit', $lawRegistry) }}" class="text-primary hover:text-primary/80">
                    <span class="material-symbols-outlined">edit</span>
                </a>
            </div>

            @if($lawRegistry->description)
                <p class="text-slate-600">{{ $lawRegistry->description }}</p>
            @endif
        </div>

        {{-- Stats --}}
        <div class="grid grid-cols-4 gap-4">
            <div class="bg-white p-4 rounded-xl border border-primary/10 text-center">
                <div class="text-3xl font-black text-primary mb-1">{{ $stats['total_files'] }}</div>
                <div class="text-xs text-slate-500">ملفات</div>
            </div>
            <div class="bg-white p-4 rounded-xl border border-primary/10 text-center">
                <div class="text-3xl font-black text-emerald-600 mb-1">{{ $stats['processed_files'] }}</div>
                <div class="text-xs text-slate-500">معالج</div>
            </div>
            <div class="bg-white p-4 rounded-xl border border-primary/10 text-center">
                <div class="text-3xl font-black text-blue-600 mb-1">{{ $stats['total_articles'] }}</div>
                <div class="text-xs text-slate-500">مادة</div>
            </div>
            <div class="bg-white p-4 rounded-xl border border-primary/10 text-center">
                <div class="text-3xl font-black text-purple-600 mb-1">{{ $stats['embedded_articles'] }}</div>
                <div class="text-xs text-slate-500">مفهرس</div>
            </div>
        </div>

        {{-- Files --}}
        <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">description</span>
                    الملفات المرفقة
                </h3>
                <button type="button" onclick="document.getElementById('uploadModal').classList.remove('hidden')" class="text-primary text-sm font-bold flex items-center gap-1 hover:underline">
                    <span class="material-symbols-outlined text-sm">add</span>
                    إضافة ملفات
                </button>
            </div>

            @if($lawRegistry->files->isEmpty())
                <p class="text-center text-slate-400 py-8">لا توجد ملفات</p>
            @else
                <div class="space-y-3">
                    @foreach($lawRegistry->files as $file)
                        <div class="flex items-center gap-4 p-4 bg-slate-50 rounded-xl">
                            <div class="size-10 bg-primary/10 rounded-lg flex items-center justify-center text-primary">
                                <span class="material-symbols-outlined">description</span>
                            </div>
                            <div class="flex-1 min-w-0">
                                <h4 class="font-bold text-sm truncate">{{ $file->filename }}</h4>
                                <p class="text-xs text-slate-500">{{ $file->human_readable_size }} • {{ $file->total_articles }} مادة</p>
                            </div>
                            @if($file->is_processed)
                                <span class="px-3 py-1 bg-emerald-100 text-emerald-700 text-xs font-bold rounded-full">معالج</span>
                            @else
                                <span class="px-3 py-1 bg-amber-100 text-amber-700 text-xs font-bold rounded-full">قيد المعالجة</span>
                            @endif
                        </div>
                    @endforeach
                </div>
            @endif
        </div>

        {{-- Articles --}}
        <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
            <h3 class="font-bold mb-4 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">article</span>
                المواد المستخرجة ({{ $lawRegistry->articles->count() }})
            </h3>

            @if($lawRegistry->articles->isEmpty())
                <p class="text-center text-slate-400 py-8">لا توجد مواد بعد. سيتم استخراجها تلقائياً بعد رفع الملفات.</p>
            @else
                <div class="space-y-2 max-h-96 overflow-y-auto">
                    @foreach($lawRegistry->articles->take(50) as $article)
                        <div class="p-3 bg-slate-50 rounded-lg hover:bg-slate-100 transition-colors">
                            <div class="flex items-start justify-between gap-3">
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 mb-1">
                                        <span class="font-bold text-sm text-primary">المادة {{ $article->article_number }}</span>
                                        @if($article->hasEmbedding())
                                            <span class="text-xs text-emerald-600 flex items-center gap-1">
                                                <span class="material-symbols-outlined text-xs">check_circle</span>
                                                مفهرس
                                            </span>
                                        @endif
                                    </div>
                                    <p class="text-xs text-slate-600 line-clamp-2">{{ Str::limit($article->article_text, 150) }}</p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                    @if($lawRegistry->articles->count() > 50)
                        <p class="text-center text-xs text-slate-400 pt-2">عرض 50 من {{ $lawRegistry->articles->count() }} مادة</p>
                    @endif
                </div>
            @endif
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="space-y-6">
        <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
            <h3 class="font-bold mb-4">إجراءات</h3>
            <div class="space-y-2">
                <form action="{{ route('law-library.reprocess', $lawRegistry) }}" method="POST">
                    @csrf
                    <button type="submit" class="w-full flex items-center gap-3 p-3 bg-slate-50 rounded-xl hover:bg-primary/10 transition-colors">
                        <span class="material-symbols-outlined text-primary">refresh</span>
                        <span class="text-sm font-semibold">إعادة المعالجة</span>
                    </button>
                </form>
                <form action="{{ route('law-library.destroy', $lawRegistry) }}" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف هذا النظام؟')">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="w-full flex items-center gap-3 p-3 bg-red-50 rounded-xl hover:bg-red-100 transition-colors text-red-600">
                        <span class="material-symbols-outlined">delete</span>
                        <span class="text-sm font-semibold">حذف النظام</span>
                    </button>
                </form>
            </div>
        </div>

        <div class="bg-gradient-to-br from-primary to-emerald-800 p-6 rounded-xl text-white shadow-lg">
            <div class="flex items-center gap-2 mb-3">
                <span class="material-symbols-outlined">psychology</span>
                <span class="font-bold">البحث الدلالي (RAG)</span>
            </div>
            <p class="text-sm opacity-90">هذا النظام متاح للبحث الدلالي. الوكلاء الأذكياء يمكنهم العثور على المواد ذات الصلة تلقائياً.</p>
        </div>
    </div>
</div>

{{-- Upload Modal --}}
<div id="uploadModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-lg w-full shadow-2xl">
        <div class="p-6 border-b border-slate-100">
            <h3 class="text-lg font-black flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">upload_file</span>
                إضافة ملفات إلى {{ $lawRegistry->name }}
            </h3>
        </div>
        <form action="{{ route('law-library.upload-files', $lawRegistry) }}" method="POST" enctype="multipart/form-data" class="p-6">
            @csrf
            <div class="mb-5">
                <label class="block text-sm font-semibold text-slate-700 mb-2">الملفات</label>
                <div class="border-2 border-dashed border-slate-200 rounded-xl p-6 bg-background-light/50 hover:border-primary/30 transition-colors">
                    <input type="file" name="files[]" id="uploadFiles" multiple
                        accept=".txt,.pdf,.doc,.docx"
                        class="w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:bg-primary file:text-white file:font-bold file:cursor-pointer">
                    <p class="text-xs text-slate-500 mt-2">TXT, PDF, DOC, DOCX. حد أقصى 50 ميجابايت للملف.</p>
                    <ul id="uploadFileList" class="mt-3 space-y-1 text-sm text-slate-600 hidden"></ul>
                </div>
            </div>
            <div class="flex gap-3">
                <button type="submit" class="flex-1 bg-primary text-white py-3.5 rounded-xl font-bold hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
                    رفع ومعالجة
                </button>
                <button type="button" onclick="document.getElementById('uploadModal').classList.add('hidden')" class="px-6 py-3.5 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition-colors">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('uploadFiles')?.addEventListener('change', function() {
    var list = document.getElementById('uploadFileList');
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
