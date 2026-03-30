@extends('layouts.app')

@section('title', 'الأنظمة والقوانين - المستشار القانوني الذكي')

@section('content')
<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-8">
    <div>
        <h2 class="text-2xl font-black tracking-tight">الأنظمة والقوانين</h2>
        <p class="text-slate-500">قاعدة بيانات الأنظمة السعودية مع البحث الذكي</p>
    </div>
    <div class="flex items-center gap-3">
        <button id="bulkDeleteBtn" onclick="confirmBulkDelete()" class="hidden bg-red-600 text-white px-6 py-3 rounded-xl font-bold hover:bg-red-700 transition-all shadow-lg shadow-red-600/20 items-center gap-2">
            <span class="material-symbols-outlined">delete</span>
            <span>حذف المحدد (<span id="selectedCount">0</span>)</span>
        </button>
        <button onclick="document.getElementById('uploadModal').classList.remove('hidden')" class="bg-primary text-white px-6 py-3 rounded-xl font-bold hover:bg-primary/90 transition-all shadow-lg shadow-primary/20 flex items-center gap-2">
            <span class="material-symbols-outlined">add</span>
            إضافة قانون جديد
        </button>
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

<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
    @forelse($laws as $law)
        <div class="bg-white p-5 rounded-xl border border-primary/5 shadow-sm hover:border-primary transition-all magnetic-element law-card" data-law-id="{{ $law->id }}">
            <div class="flex items-start justify-between gap-3 mb-3">
                <div class="flex items-start gap-3 flex-1 min-w-0">
                    <label class="flex items-center shrink-0 cursor-pointer" onclick="event.stopPropagation()">
                        <input type="checkbox" class="law-checkbox size-5 rounded border-slate-300 text-primary focus:ring-primary focus:ring-offset-0 cursor-pointer" value="{{ $law->id }}" onchange="updateBulkDeleteButton()">
                    </label>
                    <a href="{{ route('laws.show', $law) }}" class="flex items-start gap-3 flex-1 min-w-0 hover:opacity-80 transition-opacity">
                        <div class="size-12 rounded-lg bg-primary/10 flex items-center justify-center text-primary shrink-0">
                            <span class="material-symbols-outlined">gavel</span>
                        </div>
                        <div class="flex-1 min-w-0">
                            <h4 class="font-bold text-sm">{{ $law->name }}</h4>
                            @if($law->description)
                                <p class="text-xs text-slate-500 mt-1 line-clamp-2">{{ $law->description }}</p>
                            @endif
                        </div>
                    </a>
                </div>
                <div class="flex items-center gap-1" onclick="event.stopPropagation()">
                    <button onclick="openEditModal({{ $law->id }}, '{{ addslashes($law->name) }}', '{{ addslashes($law->description ?? '') }}', '{{ $law->category ?? '' }}')" class="size-8 flex items-center justify-center text-slate-400 hover:text-primary hover:bg-primary/10 rounded-lg transition-colors" title="تعديل">
                        <span class="material-symbols-outlined text-lg">edit</span>
                    </button>
                    <button onclick="confirmDelete({{ $law->id }}, '{{ addslashes($law->name) }}')" class="size-8 flex items-center justify-center text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-colors" title="حذف">
                        <span class="material-symbols-outlined text-lg">delete</span>
                    </button>
                </div>
            </div>
            <div class="flex items-center gap-3 text-xs text-slate-600 mt-3 pt-3 border-t border-slate-100">
                <div class="flex items-center gap-1">
                    <span class="material-symbols-outlined text-sm">description</span>
                    <span>{{ $law->files_count }} ملف</span>
                </div>
                @if($law->isProcessed())
                    <span class="mr-auto px-2 py-0.5 bg-emerald-100 text-emerald-700 rounded-full text-xs font-bold">معالج</span>
                @else
                    <span class="mr-auto px-2 py-0.5 bg-amber-100 text-amber-700 rounded-full text-xs font-bold">قيد المعالجة</span>
                @endif
            </div>
        </div>
    @empty
        <div class="col-span-full bg-white rounded-xl border border-primary/10 shadow-sm p-12 text-center">
            <span class="material-symbols-outlined text-6xl text-slate-300 mb-4 block">gavel</span>
            <h3 class="font-bold text-lg mb-2">لا توجد قوانين في المكتبة</h3>
            <p class="text-slate-500 mb-6">ابدأ ببناء قاعدة المعرفة القانونية بإضافة الأنظمة والقوانين السعودية</p>
            <button onclick="document.getElementById('uploadModal').classList.remove('hidden')" class="inline-flex items-center gap-2 bg-primary text-white px-6 py-3 rounded-xl font-bold hover:bg-primary/90 transition-all">
                <span class="material-symbols-outlined">add</span>
                إضافة أول قانون
            </button>
        </div>
    @endforelse
</div>

@if($laws->hasPages())
    <div class="mt-8">
        {{ $laws->links() }}
    </div>
@endif

{{-- Upload Modal --}}
<div id="uploadModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-slate-100 sticky top-0 bg-white">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-black flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">gavel</span>
                    إضافة قانون جديد
                </h3>
                <button onclick="document.getElementById('uploadModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
            <p class="text-slate-500 text-sm mt-1">سيتم معالجة الملفات تلقائياً وإنشاء فهرس قابل للبحث</p>
        </div>
        
        <form id="uploadLawForm" action="{{ route('laws.store') }}" method="POST" enctype="multipart/form-data" class="p-6 space-y-5">
            @csrf

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">اسم القانون *</label>
                <input name="name" value="{{ old('name') }}" 
                    class="w-full px-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary" 
                    placeholder="مثال: قانون الإثبات" required>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">الوصف</label>
                <textarea name="description" rows="3"
                    class="w-full px-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary resize-none" 
                    placeholder="وصف موجز للقانون ومجال تطبيقه">{{ old('description') }}</textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">التصنيف</label>
                <div class="relative">
                    <select name="category" class="w-full pr-10 pl-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary appearance-none">
                        <option value="">اختر التصنيف</option>
                        <option value="civil">مدني</option>
                        <option value="criminal">جزائي</option>
                        <option value="commercial">تجاري</option>
                        <option value="labor">عمالي</option>
                        <option value="family">أحوال شخصية</option>
                        <option value="administrative">إداري</option>
                        <option value="evidence">إثبات</option>
                        <option value="procedures">إجراءات</option>
                    </select>
                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                </div>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">ملفات القانون *</label>
                <div class="border-2 border-dashed border-slate-200 rounded-xl p-6 bg-background-light/50 hover:border-primary/30 transition-colors">
                    <input type="file" name="files[]" id="lawFiles" multiple
                        accept=".txt,.pdf,.doc,.docx"
                        class="w-full text-sm text-slate-500 file:mr-4 file:py-2.5 file:px-4 file:rounded-xl file:border-0 file:bg-primary file:text-white file:font-bold file:cursor-pointer" required>
                    <p class="text-xs text-slate-500 mt-2">TXT, PDF, DOC, DOCX. حد أقصى 50 ميجابايت للملف. يمكن رفع عدة ملفات.</p>
                    <ul id="fileList" class="mt-3 space-y-1 text-sm text-slate-600 hidden"></ul>
                </div>
            </div>

            <div class="bg-primary/5 p-4 rounded-xl flex items-start gap-3">
                <span class="material-symbols-outlined text-primary">info</span>
                <div class="text-sm text-slate-600">
                    <p class="font-semibold mb-1">سيتم تلقائياً:</p>
                    <ul class="list-disc list-inside space-y-1">
                        <li>قراءة وتحليل جميع المواد القانونية</li>
                        <li>تنظيم المواد وترتيبها</li>
                        <li>جعل القانون قابل للبحث بالذكاء الاصطناعي</li>
                        <li>ربط القانون بالمحلل القانوني الذكي</li>
                    </ul>
                </div>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="submit" id="submitBtn" class="flex-1 bg-primary text-white font-bold py-4 rounded-xl hover:bg-primary/90 transition-all shadow-lg shadow-primary/20 flex items-center justify-center gap-2">
                    <span class="material-symbols-outlined" id="submitIcon">check</span>
                    <span id="submitText">حفظ ومعالجة</span>
                </button>
                <button type="button" onclick="document.getElementById('uploadModal').classList.add('hidden')" class="px-8 py-4 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition-colors">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Edit Modal --}}
<div id="editModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-50 p-4">
    <div class="bg-white rounded-2xl max-w-2xl w-full shadow-2xl max-h-[90vh] overflow-y-auto">
        <div class="p-6 border-b border-slate-100 sticky top-0 bg-white">
            <div class="flex items-center justify-between">
                <h3 class="text-xl font-black flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">edit</span>
                    تعديل القانون
                </h3>
                <button onclick="document.getElementById('editModal').classList.add('hidden')" class="text-slate-400 hover:text-slate-600">
                    <span class="material-symbols-outlined">close</span>
                </button>
            </div>
        </div>
        
        <form id="editLawForm" method="POST" class="p-6 space-y-5">
            @csrf
            @method('PUT')

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">اسم القانون *</label>
                <input id="editName" name="name" 
                    class="w-full px-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary" 
                    required>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">الوصف</label>
                <textarea id="editDescription" name="description" rows="3"
                    class="w-full px-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary resize-none"></textarea>
            </div>

            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">التصنيف</label>
                <div class="relative">
                    <select id="editCategory" name="category" class="w-full pr-10 pl-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary appearance-none">
                        <option value="">اختر التصنيف</option>
                        <option value="civil">مدني</option>
                        <option value="criminal">جزائي</option>
                        <option value="commercial">تجاري</option>
                        <option value="labor">عمالي</option>
                        <option value="family">أحوال شخصية</option>
                        <option value="administrative">إداري</option>
                        <option value="evidence">إثبات</option>
                        <option value="procedures">إجراءات</option>
                    </select>
                    <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">expand_more</span>
                </div>
            </div>

            <div class="flex gap-3 pt-4">
                <button type="submit" class="flex-1 bg-primary text-white font-bold py-4 rounded-xl hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
                    حفظ التعديلات
                </button>
                <button type="button" onclick="document.getElementById('editModal').classList.add('hidden')" class="px-8 py-4 bg-slate-100 text-slate-700 font-bold rounded-xl hover:bg-slate-200 transition-colors">
                    إلغاء
                </button>
            </div>
        </form>
    </div>
</div>

{{-- Loading Overlay --}}
<div id="loadingOverlay" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-[60]">
    <div class="bg-white rounded-2xl p-8 shadow-2xl text-center max-w-md">
        <div class="size-16 mx-auto mb-4 relative">
            <div class="absolute inset-0 border-4 border-primary/20 rounded-full"></div>
            <div class="absolute inset-0 border-4 border-primary border-t-transparent rounded-full animate-spin"></div>
        </div>
        <h3 class="text-lg font-bold mb-2">جاري رفع القانون...</h3>
        <p class="text-sm text-slate-500">الرجاء الانتظار، جاري معالجة الملفات</p>
        <div class="mt-4 space-y-2 text-sm text-slate-600">
            <div class="flex items-center justify-center gap-2">
                <div class="size-2 bg-primary rounded-full animate-pulse"></div>
                <p>قراءة وتحليل المواد القانونية</p>
            </div>
            <div class="flex items-center justify-center gap-2">
                <div class="size-2 bg-primary rounded-full animate-pulse" style="animation-delay: 0.2s"></div>
                <p>تنظيم وفهرسة المحتوى</p>
            </div>
            <div class="flex items-center justify-center gap-2">
                <div class="size-2 bg-primary rounded-full animate-pulse" style="animation-delay: 0.4s"></div>
                <p>تجهيز النظام للبحث الذكي</p>
            </div>
        </div>
    </div>
</div>

{{-- Delete Confirmation Modal --}}
<div id="deleteModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-[70] p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="size-16 mx-auto mb-4 rounded-full bg-red-100 flex items-center justify-center">
                <span class="material-symbols-outlined text-red-600 text-4xl">warning</span>
            </div>
            <h3 class="text-xl font-black text-center mb-2">تأكيد الحذف</h3>
            <p class="text-slate-600 text-center mb-1">هل أنت متأكد من حذف القانون:</p>
            <p class="text-slate-900 font-bold text-center mb-4" id="deleteLawName"></p>
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                <div class="flex items-start gap-2 text-sm text-red-800">
                    <span class="material-symbols-outlined text-red-600 shrink-0">info</span>
                    <div>
                        <p class="font-semibold mb-1">تحذير:</p>
                        <p>سيتم حذف جميع الملفات والمواد المرتبطة بهذا القانون بشكل نهائي ولا يمكن التراجع عن هذا الإجراء.</p>
                    </div>
                </div>
            </div>
            <form id="deleteForm" method="POST">
                @csrf
                @method('DELETE')
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-red-600 text-white font-bold py-3 rounded-xl hover:bg-red-700 transition-all">
                        نعم، احذف القانون
                    </button>
                    <button type="button" onclick="closeDeleteModal()" class="flex-1 bg-slate-100 text-slate-700 font-bold py-3 rounded-xl hover:bg-slate-200 transition-colors">
                        إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

{{-- Bulk Delete Confirmation Modal --}}
<div id="bulkDeleteModal" class="hidden fixed inset-0 bg-black/50 flex items-center justify-center z-[70] p-4">
    <div class="bg-white rounded-2xl max-w-md w-full shadow-2xl">
        <div class="p-6">
            <div class="size-16 mx-auto mb-4 rounded-full bg-red-100 flex items-center justify-center">
                <span class="material-symbols-outlined text-red-600 text-4xl">warning</span>
            </div>
            <h3 class="text-xl font-black text-center mb-2">تأكيد الحذف الجماعي</h3>
            <p class="text-slate-600 text-center mb-4">هل أنت متأكد من حذف <span class="font-bold text-slate-900" id="bulkDeleteCount"></span> قانون؟</p>
            <div class="bg-red-50 border border-red-200 rounded-xl p-4 mb-6">
                <div class="flex items-start gap-2 text-sm text-red-800">
                    <span class="material-symbols-outlined text-red-600 shrink-0">info</span>
                    <div>
                        <p class="font-semibold mb-1">تحذير:</p>
                        <p>سيتم حذف جميع القوانين المحددة مع كافة الملفات والمواد المرتبطة بها بشكل نهائي ولا يمكن التراجع عن هذا الإجراء.</p>
                    </div>
                </div>
            </div>
            <form id="bulkDeleteForm" method="POST" action="{{ route('laws.bulk-delete') }}">
                @csrf
                @method('DELETE')
                <input type="hidden" name="law_ids" id="bulkDeleteIds">
                <div class="flex gap-3">
                    <button type="submit" class="flex-1 bg-red-600 text-white font-bold py-3 rounded-xl hover:bg-red-700 transition-all">
                        نعم، احذف الكل
                    </button>
                    <button type="button" onclick="closeBulkDeleteModal()" class="flex-1 bg-slate-100 text-slate-700 font-bold py-3 rounded-xl hover:bg-slate-200 transition-colors">
                        إلغاء
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
document.getElementById('lawFiles')?.addEventListener('change', function() {
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

// Loading animation on form submit
document.getElementById('uploadLawForm')?.addEventListener('submit', function(e) {
    var submitBtn = document.getElementById('submitBtn');
    var submitIcon = document.getElementById('submitIcon');
    var submitText = document.getElementById('submitText');
    var loadingOverlay = document.getElementById('loadingOverlay');
    
    // Disable button
    submitBtn.disabled = true;
    submitBtn.classList.add('opacity-50', 'cursor-not-allowed');
    
    // Change button text
    submitIcon.textContent = 'hourglass_empty';
    submitIcon.classList.add('animate-spin');
    submitText.textContent = 'جاري الرفع...';
    
    // Show loading overlay
    loadingOverlay.classList.remove('hidden');
});

// Open edit modal
function openEditModal(id, name, description, category) {
    var modal = document.getElementById('editModal');
    var form = document.getElementById('editLawForm');
    
    // Set form action
    form.action = '/laws/' + id;
    
    // Fill form fields
    document.getElementById('editName').value = name;
    document.getElementById('editDescription').value = description;
    document.getElementById('editCategory').value = category;
    
    // Show modal
    modal.classList.remove('hidden');
}

// Update bulk delete button visibility
function updateBulkDeleteButton() {
    const checkboxes = document.querySelectorAll('.law-checkbox:checked');
    const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
    const selectedCount = document.getElementById('selectedCount');
    
    if (checkboxes.length > 0) {
        bulkDeleteBtn.classList.remove('hidden');
        bulkDeleteBtn.classList.add('flex');
        selectedCount.textContent = checkboxes.length;
    } else {
        bulkDeleteBtn.classList.add('hidden');
        bulkDeleteBtn.classList.remove('flex');
    }
}

// Confirm single delete
function confirmDelete(lawId, lawName) {
    const modal = document.getElementById('deleteModal');
    const form = document.getElementById('deleteForm');
    const nameElement = document.getElementById('deleteLawName');
    
    // Set form action
    form.action = '/laws/' + lawId;
    
    // Set law name
    nameElement.textContent = lawName;
    
    // Show modal
    modal.classList.remove('hidden');
}

// Close delete modal
function closeDeleteModal() {
    document.getElementById('deleteModal').classList.add('hidden');
}

// Confirm bulk delete
function confirmBulkDelete() {
    const checkboxes = document.querySelectorAll('.law-checkbox:checked');
    const lawIds = Array.from(checkboxes).map(cb => cb.value);
    
    if (lawIds.length === 0) return;
    
    const modal = document.getElementById('bulkDeleteModal');
    const countElement = document.getElementById('bulkDeleteCount');
    const idsInput = document.getElementById('bulkDeleteIds');
    
    // Set count and IDs
    countElement.textContent = lawIds.length;
    idsInput.value = JSON.stringify(lawIds);
    
    // Show modal
    modal.classList.remove('hidden');
}

// Close bulk delete modal
function closeBulkDeleteModal() {
    document.getElementById('bulkDeleteModal').classList.add('hidden');
}
</script>
@endpush
@endsection
