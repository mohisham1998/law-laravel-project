@extends('layouts.app')

@section('title', 'الإعدادات - المستشار القانوني الذكي')

@push('styles')
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    .select2-container--default .select2-selection--single {
        background-color: #f5f8f7;
        border: none;
        border-radius: 0.75rem;
        padding-left: 0;
    }
    .select2-dropdown {
        border-radius: 0.75rem;
        border: 1px solid rgba(0, 107, 52, 0.1);
    }
    .select2-container--default .select2-results__option--highlighted[aria-selected] {
        background-color: rgba(0, 107, 52, 0.1);
        color: #006b34;
    }
    .select2-search--dropdown .select2-search__field {
        border-radius: 0.5rem;
        border: 1px solid #E5E7EB;
        padding: 10px 14px;
        font-size: 1rem;
    }
    /* Taller dropdown for smoother search: more options visible */
    .select2-container--default .select2-selection--single {
        height: 56px;
        padding: 10px 18px;
    }
    .select2-container--default .select2-selection--single .select2-selection__rendered {
        line-height: 36px;
    }
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 54px;
    }
    .select2-results__options {
        max-height: 22rem !important;
    }
    .select2-results__option {
        padding: 12px 14px !important;
        font-size: 0.9375rem;
    }
</style>
@endpush

@section('content')
{{-- Page title above both columns so cards align in parallel --}}
<h2 class="text-2xl font-black tracking-tight mb-2">الإعدادات</h2>
<p class="text-slate-500 mb-10">إدارة حسابك وتفضيلات النظام</p>

<form action="{{ route('settings.update') }}" method="POST" id="settingsForm">
    @csrf
    {{-- Hidden field so selected model is submitted (the select lives in the left column) --}}
    <input type="hidden" name="selected_model" id="selectedModelInput" value="{{ $selectedModel }}">

    {{-- Two columns: responsive gap (~30rem on xl), parallel cards, stretch to equal height --}}
    <div class="flex flex-wrap items-stretch gap-8 sm:gap-12 md:gap-16 lg:gap-20 xl:gap-[28rem] 2xl:gap-[30rem]">
        {{-- Right column (first in RTL): account + notifications --}}
        <div class="flex-1 min-w-0 max-w-2xl">
            <div class="space-y-6">
                {{-- Account Information --}}
                <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
                    <h3 class="font-bold mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">person</span>
                        معلومات الحساب
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">الاسم</label>
                            <input type="text" name="name" value="{{ auth()->user()->name }}" 
                                class="w-full px-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">البريد الإلكتروني</label>
                            <input type="email" name="email" value="{{ auth()->user()->email }}" 
                                class="w-full px-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
                </div>
                
                {{-- Notification Settings --}}
                <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
                    <h3 class="font-bold mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">notifications</span>
                        إعدادات الإشعارات
                    </h3>
                    
                    <div class="space-y-3">
                        <label class="flex items-center gap-3">
                            <input type="checkbox" checked class="w-5 h-5 text-primary bg-slate-100 border-gray-300 rounded focus:ring-primary">
                            <span class="text-sm">إشعارات البريد الإلكتروني عند اكتمال التحليل</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input type="checkbox" checked class="w-5 h-5 text-primary bg-slate-100 border-gray-300 rounded focus:ring-primary">
                            <span class="text-sm">إشعارات القضايا الجديدة</span>
                        </label>
                        <label class="flex items-center gap-3">
                            <input type="checkbox" class="w-5 h-5 text-primary bg-slate-100 border-gray-300 rounded focus:ring-primary">
                            <span class="text-sm">التقارير الأسبوعية</span>
                        </label>
                    </div>
                </div>
            </div>
        </div>
        
        {{-- Left column (second in RTL): إعدادات نموذج الذكاء الاصطناعي --}}
        <div class="w-full sm:w-[32rem] xl:w-[38rem] shrink-0 flex">
            <div class="bg-white p-8 rounded-xl border border-primary/10 shadow-sm sticky top-24 min-h-[32rem] w-full flex flex-col">
                <h3 class="font-bold mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">psychology</span>
                    إعدادات نموذج الذكاء الاصطناعي
                </h3>
                
                <div class="space-y-8 flex-1 flex flex-col">
                    <div>
                        <label class="block text-sm font-semibold text-slate-700 mb-3">النموذج المستخدم</label>
                        <select id="modelSelect" class="w-full">
                            @foreach($models as $model)
                                <option value="{{ $model['id'] }}" 
                                    data-prompt="{{ $model['pricing']['prompt'] }}"
                                    data-completion="{{ $model['pricing']['completion'] }}"
                                    {{ $selectedModel == $model['id'] ? 'selected' : '' }}>
                                    {{ $model['name'] }}
                                </option>
                            @endforeach
                        </select>
                        <p class="text-xs text-slate-500 mt-3">اختر النموذج المناسب لتحليل القضايا القانونية</p>
                    </div>
                    
                    <div class="bg-primary/5 p-6 rounded-xl flex-1 flex flex-col min-h-[14rem]">
                        <h4 class="font-bold text-sm mb-3 flex items-center gap-2">
                            <span class="material-symbols-outlined text-primary text-base">calculate</span>
                            التكلفة التقديرية
                        </h4>
                        <p class="text-slate-600 text-sm mb-5">تقدير تكلفة تحليل حالة قانونية متوسطة (نص وملخص ومراجع) بالنموذج المختار:</p>
                        <div class="bg-white p-6 rounded-lg border border-primary/10 mt-auto">
                            <p class="text-slate-500 text-sm mb-1">حالة متوسطة</p>
                            <p class="text-2xl font-black text-primary"><span id="averageCaseSAR">0.00</span> ر.س</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    {{-- Save button below both cards, full width --}}
    <div class="mt-10">
        <button type="submit" class="w-full max-w-2xl bg-primary text-white font-bold py-4 rounded-xl hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
            حفظ التغييرات
        </button>
    </div>
</form>
@endsection

@push('scripts')
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
$(document).ready(function() {
    var AVG_PROMPT = 15000;
    var AVG_COMPLETION = 6000;
    var USD_TO_SAR = 3.75;

    $('#modelSelect').select2({
        placeholder: 'اختر النموذج',
        dir: 'rtl',
        width: '100%',
        templateResult: function(model) {
            if (!model.id) return model.text;
            var $c = $('<div class="py-2"></div>');
            $c.append($('<div class="font-bold text-slate-900"></div>').text(model.text));
            return $c;
        },
        templateSelection: function(model) { return model.text; }
    });
    
    function updateAverageCase() {
        var selected = $('#modelSelect option:selected');
        var promptPrice = parseFloat(selected.data('prompt')) || 0;
        var completionPrice = parseFloat(selected.data('completion')) || 0;
        var totalUSD = (promptPrice * AVG_PROMPT) + (completionPrice * AVG_COMPLETION);
        $('#averageCaseSAR').text((totalUSD * USD_TO_SAR).toFixed(2));
        $('#selectedModelInput').val(selected.val());
    }
    
    $('#modelSelect').on('change', updateAverageCase);
    updateAverageCase();
});
</script>
@endpush
