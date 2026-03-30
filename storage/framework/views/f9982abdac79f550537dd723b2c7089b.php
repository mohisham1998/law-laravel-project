<?php $__env->startSection('title', 'الإعدادات - المستشار القانوني الذكي'); ?>

<?php
$llmProvider = $llmProvider ?? 'openrouter';
$puterModel = $puterModel ?? 'gpt-5-nano';
$puterDisclosureAcknowledged = $puterDisclosureAcknowledged ?? false;
$notificationsEnabled = $notificationsEnabled ?? true;
?>

<?php $__env->startPush('styles'); ?>
<link href="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/css/select2.min.css" rel="stylesheet" />
<style>
    /* Sample output animation */
    @keyframes fadeSlideIn {
        from { opacity: 0; transform: translateY(6px); }
        to   { opacity: 1; transform: translateY(0); }
    }
    .sample-char {
        display: inline;
        animation: fadeSlideIn 0.25s ease both;
    }
    .sample-cursor {
        display: inline-block;
        width: 2px;
        height: 1em;
        background: #006b34;
        margin-right: 1px;
        vertical-align: text-bottom;
        animation: blink 0.8s step-end infinite;
    }
    @keyframes blink { 50% { opacity: 0; } }
    .sample-output-box {
        background: linear-gradient(135deg, #f0faf5 0%, #f8fffe 100%);
        border: 1px solid rgba(0,107,52,0.15);
        border-radius: 1rem;
        padding: 1.25rem 1.5rem;
        margin-top: 1rem;
        font-size: 0.9375rem;
        line-height: 1.75;
        color: #1e293b;
        min-height: 4rem;
        position: relative;
        overflow: hidden;
    }
    .sample-output-box::before {
        content: '';
        position: absolute;
        top: 0; right: 0;
        width: 3px; height: 100%;
        background: linear-gradient(180deg, #006b34, #00a854);
        border-radius: 0 1rem 1rem 0;
    }
    .sample-output-box.is-error {
        background: linear-gradient(135deg, #fff5f5 0%, #fff8f8 100%);
        border-color: rgba(220, 38, 38, 0.2);
    }
    .sample-output-box.is-error::before {
        background: linear-gradient(180deg, #ef4444, #f87171);
    }
    .sample-output-shell {
        opacity: 0;
        transform: translateY(6px);
        transition: opacity 220ms ease, transform 220ms ease;
    }
    .sample-output-shell.is-visible {
        opacity: 1;
        transform: translateY(0);
    }
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
    /* Arrow on the left (start side in RTL) only */
    .select2-container--default .select2-selection--single .select2-selection__arrow {
        height: 54px;
        left: auto;
        right: 12px;
    }
    .select2-results__options {
        max-height: 22rem !important;
    }
    .select2-results__option {
        padding: 12px 14px !important;
        font-size: 0.9375rem;
    }
    .settings-toast-container {
        position: fixed;
        top: 1.25rem;
        left: 1.25rem;
        z-index: 120;
        display: flex;
        flex-direction: column;
        gap: 0.5rem;
        pointer-events: none;
    }
    .settings-toast {
        pointer-events: auto;
        min-width: 18rem;
        max-width: 28rem;
        border-radius: 0.75rem;
        padding: 0.75rem 1rem;
        font-size: 0.875rem;
        line-height: 1.5;
        border: 1px solid transparent;
        box-shadow: 0 8px 20px rgba(15, 23, 42, 0.08);
        animation: fadeSlideIn 0.2s ease both;
    }
    .settings-toast.info {
        background: #ecfeff;
        border-color: #a5f3fc;
        color: #0f766e;
    }
    .settings-toast.warn {
        background: #fffbeb;
        border-color: #fde68a;
        color: #92400e;
    }
    .settings-toast.error {
        background: #fef2f2;
        border-color: #fecaca;
        color: #b91c1c;
    }
</style>
<?php $__env->stopPush(); ?>

<?php $__env->startSection('content'); ?>

<h2 class="text-2xl font-black tracking-tight mb-2">الإعدادات</h2>
<p class="text-slate-500 mb-10">إدارة حسابك وتفضيلات النظام</p>

<form action="<?php echo e(route('settings.update')); ?>" method="POST" id="settingsForm" class="min-w-0"
    x-data="settingsForm()"
    @submit.prevent="handleSubmit"
>
    <?php echo csrf_field(); ?>
    
    <input type="hidden" name="selected_model" id="selectedModelInput" value="<?php echo e($selectedModel); ?>">
    <input type="hidden" name="llm_provider" :value="provider">
    <input type="hidden" name="puter_model" :value="selectedPuterModel">
    <input type="hidden" name="puter_disclosure_acknowledged" value="1">
    <input type="hidden" name="notifications_enabled" :value="notificationsEnabled ? 1 : 0">
    
    <input type="hidden" id="puterTokenField" value="">

    
    <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm mb-6">
        <h3 class="font-bold mb-4 flex items-center gap-2">
            <span class="material-symbols-outlined text-primary">hub</span>
            مزود الذكاء الاصطناعي
        </h3>
        <div class="flex gap-4 flex-wrap">
            <label class="flex items-center gap-3 cursor-pointer p-4 rounded-xl border-2 transition-colors flex-1 min-w-[160px]"
                   :class="provider === 'openrouter' ? 'border-primary bg-primary/5' : 'border-slate-200 hover:border-primary/40'">
                <input type="radio" name="_provider_radio" value="openrouter" x-model="provider" class="sr-only">
                <span class="material-symbols-outlined text-2xl" :class="provider === 'openrouter' ? 'text-primary' : 'text-slate-400'">router</span>
                <div>
                    <p class="font-bold text-sm" :class="provider === 'openrouter' ? 'text-primary' : 'text-slate-700'">OpenRouter</p>
                    <p class="text-xs text-slate-500">مفتاح API شخصي</p>
                </div>
                <span x-show="provider === 'openrouter'" class="material-symbols-outlined text-primary mr-auto">check_circle</span>
            </label>
            <label class="flex items-center gap-3 cursor-pointer p-4 rounded-xl border-2 transition-colors flex-1 min-w-[160px]"
                   :class="provider === 'puter' ? 'border-primary bg-primary/5' : 'border-slate-200 hover:border-primary/40'">
                <input type="radio" name="_provider_radio" value="puter" x-model="provider" class="sr-only">
                <span class="material-symbols-outlined text-2xl" :class="provider === 'puter' ? 'text-primary' : 'text-slate-400'">cloud</span>
                <div>
                    <p class="font-bold text-sm" :class="provider === 'puter' ? 'text-primary' : 'text-slate-700'">Puter</p>
                    <p class="text-xs text-slate-500">حساب Puter المجاني</p>
                </div>
                <span x-show="provider === 'puter'" class="material-symbols-outlined text-primary mr-auto">check_circle</span>
            </label>
        </div>
    </div>

    
    <div x-show="provider === 'puter'" x-transition class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm mb-6 space-y-5">

        
        <div class="flex items-center gap-4 flex-wrap">
            <div class="flex items-center gap-2">
                <span class="material-symbols-outlined text-lg" :class="puterStatus === 'connected' ? 'text-emerald-600' : 'text-red-500'">
                    <template x-if="puterStatus === 'connected'">circle</template>
                    <template x-if="puterStatus !== 'connected'">radio_button_unchecked</template>
                    <?php echo e(''); ?>

                </span>
                <span class="font-semibold text-sm"
                    :class="puterStatus === 'connected' ? 'text-emerald-700' : 'text-red-600'"
                    x-text="puterStatus === 'connected' ? 'متصل بـ Puter' : (puterStatus === 'connecting' ? 'جارٍ الاتصال...' : 'غير متصل')">
                </span>
            </div>
            <button type="button"
                @click="reconnectPuter()"
                x-show="puterStatus !== 'connecting'"
                class="flex items-center gap-2 text-sm font-bold text-primary border border-primary/20 bg-primary/5 px-3 py-2 rounded-xl hover:bg-primary/10 transition mr-auto">
                <span class="material-symbols-outlined text-sm">refresh</span>
                <span>إعادة الربط</span>
            </button>
            <button type="button" x-show="puterStatus !== 'connected'"
                @click="connectPuter()"
                :disabled="puterStatus === 'connecting'"
                class="flex items-center gap-2 bg-primary text-white text-sm font-bold px-4 py-2 rounded-xl hover:bg-primary/90 transition disabled:opacity-60">
                <span class="material-symbols-outlined text-sm">link</span>
                <span x-text="puterStatus === 'connecting' ? 'جارٍ الاتصال...' : 'ربط حساب Puter'"></span>
            </button>
            <p x-show="puterError" x-text="puterError" class="text-sm text-red-600 w-full"></p>
        </div>

        
        <div>
            <div class="flex items-center justify-between mb-2">
                <label class="text-sm font-semibold text-slate-700">نموذج Puter المستخدم</label>
                <label class="flex items-center gap-2 cursor-pointer select-none">
                    <span class="text-xs text-slate-500">المجاني فقط</span>
                    <div class="relative">
                        <input type="checkbox" id="puterFreeFilter" class="sr-only peer">
                        <div class="w-9 h-5 bg-slate-200 rounded-full peer-checked:bg-primary transition-colors"></div>
                        <div class="absolute top-0.5 right-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-[-16px]"></div>
                    </div>
                </label>
            </div>
            <div x-show="puterModelsLoading" class="h-14 bg-slate-100 rounded-xl animate-pulse flex items-center justify-center text-slate-400 text-sm">
                جارٍ تحميل النماذج...
            </div>
            <div x-show="puterModelsError && !puterModelsLoading" class="flex items-center gap-3 p-4 bg-red-50 border border-red-200 rounded-xl">
                <span class="material-symbols-outlined text-red-500">error</span>
                <span class="text-sm text-red-700">تعذّر تحميل قائمة النماذج.</span>
                <button type="button" @click="loadPuterModels()" class="mr-auto text-sm text-primary font-bold hover:underline">إعادة المحاولة</button>
            </div>
            <select id="puterModelSelect" x-show="!puterModelsLoading && !puterModelsError"
                x-model="selectedPuterModel"
                class="w-full">
            </select>
            <p class="text-xs text-slate-500 mt-2">النماذج المحملة من Puter — النماذج المجانية لا تستهلك رصيداً</p>

            
            <div id="puterSampleOutput" class="hidden sample-output-shell">
                <div class="flex items-center gap-2 mt-4 mb-1">
                    <span class="material-symbols-outlined text-primary text-base">smart_toy</span>
                    <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">استجابة تجريبية</span>
                    <span id="puterSampleStatus" class="mr-auto"></span>
                </div>
                <div class="sample-output-box" id="puterSampleText" dir="rtl"></div>
            </div>
        </div>
    </div>

    
    <div class="flex flex-col lg:flex-row items-stretch gap-6 lg:gap-8 xl:gap-10">
        
        <div class="flex-1 min-w-0 w-full lg:min-w-[18rem]">
            <div class="space-y-6 min-w-0">
                
                <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm min-w-0">
                    <h3 class="font-bold mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">person</span>
                        معلومات الحساب
                    </h3>
                    
                    <div class="space-y-4">
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">الاسم</label>
                            <input type="text" name="name" value="<?php echo e(auth()->user()->name); ?>" 
                                class="w-full px-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary">
                        </div>
                        
                        <div>
                            <label class="block text-sm font-semibold text-slate-700 mb-2">البريد الإلكتروني</label>
                            <input type="email" name="email" value="<?php echo e(auth()->user()->email); ?>" 
                                class="w-full px-4 py-3 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary">
                        </div>
                    </div>
                </div>
                
                
                <div x-show="provider === 'openrouter'" x-transition class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm min-w-0">
                    <h3 class="font-bold mb-1 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">account_balance_wallet</span>
                        رصيد OpenRouter
                    </h3>
                    <p class="text-slate-500 text-xs mb-3">يتحدث تلقائياً عند تحميل الصفحة (مخزن مؤقتاً 5 دقائق).</p>

                    
                    <div id="orBalanceWidget" class="mt-1 p-4 rounded-xl border text-sm bg-slate-50 border-slate-200 text-slate-500 text-center">
                        <span class="material-symbols-outlined text-2xl block mb-1 animate-spin text-slate-300">sync</span>
                        جارٍ التحقق من الرصيد...
                    </div>

                    
                    <button type="button" id="checkOpenRouterBtn"
                        class="mt-3 w-full flex items-center justify-center gap-2 bg-primary/10 text-primary font-bold py-2.5 rounded-xl hover:bg-primary/20 transition-colors text-sm">
                        <span class="material-symbols-outlined text-base" id="checkOpenRouterIcon">refresh</span>
                        <span id="checkOpenRouterText">تحديث الرصيد</span>
                    </button>
                    <div id="openRouterResult" class="mt-3 p-4 rounded-xl hidden text-sm"></div>
                </div>

                
                <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm min-w-0">
                    <h3 class="font-bold mb-4 flex items-center gap-2">
                        <span class="material-symbols-outlined text-primary">notifications</span>
                        إعدادات الإشعارات
                    </h3>
                    
                    <label class="flex items-start gap-3 cursor-pointer">
                        <input type="checkbox"
                            x-model="notificationsEnabled"
                            class="w-5 h-5 mt-0.5 text-primary bg-slate-100 border-gray-300 rounded focus:ring-primary">
                        <div>
                            <p class="text-sm font-semibold text-slate-800">تفعيل إشعارات البوابة الفورية</p>
                            <p class="text-xs text-slate-500 mt-1">
                                عند الإيقاف، لن تظهر إشعارات مباشرة داخل البوابة. يمكن إعادة تفعيلها في أي وقت.
                            </p>
                        </div>
                    </label>
                </div>
            </div>
        </div>
        
        
        <div class="w-full lg:w-auto lg:min-w-[20rem] lg:max-w-[28rem] flex shrink-0" x-show="provider === 'openrouter'" x-transition>
            <div class="bg-white p-6 sm:p-8 rounded-xl border border-primary/10 shadow-sm lg:sticky lg:top-24 min-h-[28rem] w-full flex flex-col">
                <h3 class="font-bold mb-6 flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">psychology</span>
                    إعدادات نموذج الذكاء الاصطناعي
                </h3>
                
                <div class="space-y-8 flex-1 flex flex-col">
                    <div>
                        <div class="flex items-center justify-between mb-3">
                            <label class="block text-sm font-semibold text-slate-700">النموذج المستخدم</label>
                            <label class="flex items-center gap-2 cursor-pointer select-none">
                                <span class="text-xs text-slate-500">المجاني فقط</span>
                                <div class="relative">
                                    <input type="checkbox" id="orFreeFilter" class="sr-only peer">
                                    <div class="w-9 h-5 bg-slate-200 rounded-full peer-checked:bg-primary transition-colors"></div>
                                    <div class="absolute top-0.5 right-0.5 w-4 h-4 bg-white rounded-full shadow transition-transform peer-checked:translate-x-[-16px]"></div>
                                </div>
                            </label>
                        </div>
                        <select id="modelSelect" class="w-full">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $models; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $model): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                                <option value="<?php echo e($model['id']); ?>"
                                    data-prompt="<?php echo e($model['pricing']['prompt']); ?>"
                                    data-completion="<?php echo e($model['pricing']['completion']); ?>"
                                    data-tier="<?php echo e((isset($model['pricing']['prompt']) && (float)$model['pricing']['prompt'] == 0 && (float)$model['pricing']['completion'] == 0) ? 'free' : 'paid'); ?>"
                                    <?php echo e($selectedModel == $model['id'] ? 'selected' : ''); ?>>
                                    <?php echo e($model['name']); ?>

                                </option>
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                        </select>
                        <p class="text-xs text-slate-500 mt-3">اختر النموذج المناسب لتحليل القضايا القانونية</p>

                        
                        <div id="orSampleOutput" class="hidden sample-output-shell">
                            <div class="flex items-center gap-2 mt-4 mb-1">
                                <span class="material-symbols-outlined text-primary text-base">smart_toy</span>
                                <span class="text-xs font-semibold text-slate-500 uppercase tracking-wide">استجابة تجريبية</span>
                                <span id="orSampleStatus" class="mr-auto"></span>
                            </div>
                            <div class="sample-output-box" id="orSampleText" dir="rtl"></div>
                        </div>
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

    
    <div class="mt-10">
        <button type="submit"
            :disabled="!canSave"
            :class="canSave ? 'bg-primary hover:bg-primary/90 shadow-lg shadow-primary/20' : 'bg-slate-300 cursor-not-allowed'"
            class="w-full max-w-2xl text-white font-bold py-4 rounded-xl transition-all">
            حفظ التغييرات
        </button>
        <p x-show="provider === 'puter' && puterStatus !== 'connected'" class="text-sm text-amber-600 mt-2 text-center">
            يجب الاتصال بحساب Puter قبل الحفظ
        </p>
    </div>
</form>
<?php $__env->stopSection(); ?>

<?php $__env->startPush('scripts'); ?>
<script src="https://js.puter.com/v2/"></script>
<script>
// Inject Blade values into JS before Alpine initialises
var _settingsInitialProvider = '<?php echo e($llmProvider); ?>';
var _settingsInitialPuterModel = '<?php echo e($puterModel); ?>';
var _settingsDisclosureAcknowledged = <?php echo e($puterDisclosureAcknowledged ? 'true' : 'false'); ?>;
var _settingsNotificationsEnabled = <?php echo e($notificationsEnabled ? 'true' : 'false'); ?>;
var _puterModelsUrl = '<?php echo e(route('api.puter-models')); ?>';

document.addEventListener('alpine:init', function () {
    Alpine.data('settingsForm', function () {
        return {
            provider: _settingsInitialProvider,
            puterStatus: 'checking',
            puterError: '',
            disclosureAcknowledged: _settingsDisclosureAcknowledged,
            disclosureCheckbox: false,
            puterModels: [],
            puterModelsLoading: false,
            puterModelsError: false,
            selectedPuterModel: _settingsInitialPuterModel,
            notificationsEnabled: _settingsNotificationsEnabled,
            get canSave() {
                if (this.provider !== 'puter') return true;
                if (this.puterStatus !== 'connected') return false;
                return true;
            },
            init() {
                this.checkPuterConnection();
                if (this.provider === 'puter') {
                    this.loadPuterModels();
                } else if (this.provider === 'openrouter') {
                    window.dispatchEvent(new CustomEvent('settings:openrouter-check'));
                }
                this.$watch('provider', (val) => {
                    if (val === 'puter') {
                        this.loadPuterModels();
                    } else if (val === 'openrouter') {
                        window.dispatchEvent(new CustomEvent('settings:openrouter-check'));
                    }
                });
            },
            async checkPuterConnection() {
                try {
                    if (typeof puter === 'undefined') { this.puterStatus = 'not_connected'; return; }
                    const ok = puter.auth.isSignedIn();
                    this.puterStatus = ok ? 'connected' : 'not_connected';
                } catch(e) {
                    this.puterStatus = 'not_connected';
                }
            },
            async connectPuter() {
                this.puterStatus = 'connecting';
                try {
                    await puter.auth.signIn();
                    this.puterStatus = 'connected';
                } catch(e) {
                    this.puterStatus = 'not_connected';
                    this.puterError = 'فشل الاتصال بحساب Puter. حاول مرة أخرى.';
                }
            },
            async reconnectPuter() {
                this.puterStatus = 'connecting';
                this.puterError = '';
                try {
                    if (typeof puter === 'undefined' || !puter.auth) {
                        throw new Error('puter unavailable');
                    }
                    if (typeof puter.auth.signOut === 'function') {
                        try { await puter.auth.signOut(); } catch (_e) {}
                    }
                    await puter.auth.signIn();
                    this.puterStatus = 'connected';
                } catch (e) {
                    this.puterStatus = 'not_connected';
                    this.puterError = 'تعذر إعادة ربط حساب Puter. حاول مرة أخرى.';
                }
            },
            async loadPuterModels() {
                this.puterModelsLoading = true;
                this.puterModelsError = false;
                try {
                    const resp = await fetch(_puterModelsUrl);
                    const data = await resp.json();
                    if (data.ok && data.models && data.models.length > 0) {
                        this.puterModels = data.models;
                        this.$nextTick(() => { initPuterSelect2(this.puterModels, this.selectedPuterModel, (id) => { this.selectedPuterModel = id; }); });
                    } else {
                        this.puterModelsError = true;
                    }
                } catch(e) {
                    this.puterModelsError = true;
                } finally {
                    this.puterModelsLoading = false;
                }
            },
            getPuterToken() {
                try {
                    if (typeof puter === 'undefined') return '';
                    var normalize = function(raw) {
                        var token = String(raw || '').trim();
                        if (/^bearer\s+/i.test(token)) token = token.replace(/^bearer\s+/i, '').trim();
                        return token;
                    };

                    if (puter.authToken) {
                        if (puter.authToken && typeof puter.authToken.then === 'function') {
                            return puter.authToken.then(function(t) { return normalize(t); }).catch(function() { return ''; });
                        }
                        var directToken = normalize(puter.authToken);
                        if (directToken) return directToken;
                    }

                    if (puter.auth && typeof puter.auth.getAuthToken === 'function') {
                        var authToken = puter.auth.getAuthToken();
                        if (authToken && typeof authToken.then === 'function') {
                            return authToken.then(function(t) { return normalize(t); }).catch(function() { return ''; });
                        }
                        return normalize(authToken);
                    }

                    return '';
                } catch(e) { return ''; }
            },
            handleSubmit() {
                if (this.provider === 'puter') {
                    document.getElementById('puterTokenField').value = this.getPuterToken();
                }
                this.$el.submit();
            }
        };
    });
});
</script>
<script src="https://code.jquery.com/jquery-3.7.1.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<script>
// ─── Sample Preview ───────────────────────────────────────────────
var _modelPreviewUrl = '<?php echo e(route('api.model-preview')); ?>';
var _previewXsrf = '<?php echo e(csrf_token()); ?>';
var _previewSeq = {};
var _puterAutoFallbackUsed = {};

function getSettingsToastContainer() {
    var el = document.getElementById('settingsToastContainer');
    if (el) return el;
    el = document.createElement('div');
    el.id = 'settingsToastContainer';
    el.className = 'settings-toast-container';
    document.body.appendChild(el);
    return el;
}

function showSettingsToast(type, message) {
    var container = getSettingsToastContainer();
    var toast = document.createElement('div');
    toast.className = 'settings-toast ' + (type || 'info');
    toast.textContent = message;
    container.appendChild(toast);
    setTimeout(function() {
        if (toast && toast.parentNode) {
            toast.parentNode.removeChild(toast);
        }
    }, 4200);
}

function getSettingsFormAlpineData() {
    var formEl = document.getElementById('settingsForm');
    if (!formEl) return null;
    if (formEl._x_dataStack && formEl._x_dataStack[0]) {
        return formEl._x_dataStack[0];
    }
    if (typeof Alpine === 'undefined') return null;
    try {
        return Alpine.$data(formEl);
    } catch (_e) {
        return null;
    }
}

function syncPuterModelState(modelId) {
    if (!modelId) return;
    var alpData = getSettingsFormAlpineData();
    if (alpData) {
        alpData.selectedPuterModel = modelId;
    }

    var formEl = document.getElementById('settingsForm');
    if (formEl) {
        var hidden = formEl.querySelector('input[name="puter_model"]');
        if (hidden) hidden.value = modelId;
    }
}

function puterModelDisplayName(modelId) {
    if (!modelId) return '';
    var found = (_allPuterModels || []).find(function(m) { return m && m.id === modelId; });
    if (found && found.name) return found.name;

    var $opt = $('#puterModelSelect option[value="' + modelId.replace(/"/g, '\\"') + '"]');
    if ($opt.length) {
        var raw = String($opt.text() || '');
        return raw.split('  —  ')[0] || modelId;
    }

    return modelId;
}

function ensurePuterSelectedModel(modelId) {
    if (!modelId || !$('#puterModelSelect').length) return;
    var $sel = $('#puterModelSelect');
    var escaped = modelId.replace(/([ #;?%&,.+*~\':"!^$\[\]()=>|\/\\@])/g,'\\$1');
    var hasOption = $sel.find('option[value="' + escaped + '"]').length > 0;

    if (!hasOption) {
        var label = puterModelDisplayName(modelId) || modelId;
        var opt = new Option(label + '  —  مجاني', modelId, true, true);
        $(opt).attr('data-tier', 'free').attr('data-label', 'مجاني');
        $sel.append(opt);
    }

    // Trigger the actual change handler so Select2 UI and Alpine model stay in sync.
    $sel.val(modelId).trigger('change');

    syncPuterModelState(modelId);
}

window.reconnectPuterFromPreview = async function () {
    var formEl = document.getElementById('settingsForm');
    if (!formEl || typeof Alpine === 'undefined') return;
    var data = Alpine.$data(formEl);
    if (!data || typeof data.reconnectPuter !== 'function') return;

    await data.reconnectPuter();

    if (data.puterStatus === 'connected') {
        var selectedId = $('#puterModelSelect').val();
        if (selectedId) {
            triggerSamplePreview('puter', selectedId, 'puterSampleOutput', 'puterSampleStatus', 'puterSampleText', function () {
                return data.getPuterToken();
            });
        }
    }
};

function triggerSamplePreview(provider, modelId, panelId, statusId, textId, getToken) {
    if (!modelId) return;
    var $panel  = $('#' + panelId);
    var $status = $('#' + statusId);
    var $text   = $('#' + textId);
    var requestId = Date.now().toString() + '-' + Math.random().toString(16).slice(2);
    _previewSeq[panelId] = requestId;

    $panel.removeClass('hidden');
    requestAnimationFrame(function() {
        $panel.addClass('is-visible');
    });
    $status.html('<span class="text-xs text-slate-400 animate-pulse">جارٍ الاختبار...</span>');
    $text.removeClass('is-error');
    $text.html('<span class="sample-cursor"></span>');

    // Puter preview works more reliably through the browser SDK with user session auth.
    if (provider === 'puter' && typeof puter !== 'undefined' && puter.ai && typeof puter.ai.chat === 'function') {
        var previewPrompt = 'اكتب جملة عربية واحدة قصيرة كنموذج معاينة.';
        var sdkFallbackModels = ['gpt-5-nano', 'gpt-4o-mini', 'claude-sonnet-4-5', 'gemini-2.5-flash'];

        var extractPuterContent = function(res) {
            if (typeof res === 'string') return res;
            if (res && res.message && typeof res.message.content === 'string') return res.message.content;
            if (res && typeof res.text === 'string') return res.text;
            return '';
        };

        var trySdkModels = function(models, idx) {
            if (idx >= models.length) {
                throw new Error('تعذر اختبار Puter من المتصفح.');
            }

            var candidate = models[idx];
            return puter.ai.chat(previewPrompt, { model: candidate })
                .then(function(res) {
                    var content = String(extractPuterContent(res) || '').trim();
                    if (!content) {
                        return trySdkModels(models, idx + 1);
                    }
                    return { content: content, model: candidate };
                })
                .catch(function() {
                    return trySdkModels(models, idx + 1);
                });
        };

        var orderedModels = [modelId].concat(sdkFallbackModels.filter(function(m) { return m !== modelId; }));

        trySdkModels(orderedModels, 0)
            .then(function(result) {
                if (_previewSeq[panelId] !== requestId) return;
                $text.removeClass('is-error');
                if (result.model && result.model !== modelId) {
                    var fromName = puterModelDisplayName(modelId);
                    var toName = puterModelDisplayName(result.model);
                    $status.html('<span class="text-xs text-emerald-600 font-semibold flex items-center gap-1"><span class="material-symbols-outlined text-sm">check_circle</span>نجح (نموذج بديل)</span>');
                    ensurePuterSelectedModel(result.model);
                    showSettingsToast('warn', 'تعذر اختبار نموذج "' + fromName + '". تم التحويل إلى "' + toName + '" تلقائياً.');
                } else {
                    $status.html('<span class="text-xs text-emerald-600 font-semibold flex items-center gap-1"><span class="material-symbols-outlined text-sm">check_circle</span>نجح</span>');
                }
                typewriteText($text[0], result.content);
            })
            .catch(function(err) {
                if (_previewSeq[panelId] !== requestId) return;
                var msg = (err && err.message) ? String(err.message) : 'تعذر اختبار Puter من المتصفح.';
                var needsPuterConnection = /auth|token|sign.?in|unauthorized|401|403/i.test(msg);
                if (needsPuterConnection) {
                    $status.html('<span class="text-xs text-amber-600 font-semibold flex items-center gap-1"><span class="material-symbols-outlined text-sm">link_off</span>يتطلب ربط</span>');
                    $text.removeClass('is-error');
                    $text.html('<span class="text-amber-700 text-sm">يجب ربط حساب Puter أولاً من الإعدادات.</span>');
                } else {
                    $status.html('<span class="text-xs text-red-500 font-semibold flex items-center gap-1"><span class="material-symbols-outlined text-sm">error</span>خطأ</span>');
                    $text.addClass('is-error');
                    $text.html('<span class="text-red-500 text-sm">' + $('<span>').text(msg).html() + '</span>');
                }
            });
        return;
    }

    var headers = {
        'Content-Type': 'application/json',
        'Accept':        'application/json',
        'X-CSRF-TOKEN':  _previewXsrf,
    };
    var body = { provider: provider, model: modelId };

    function sendRequest() {
        fetch(_modelPreviewUrl, {
        method: 'POST',
        headers: headers,
        body: JSON.stringify(body),
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (_previewSeq[panelId] !== requestId) return;
        if (data.ok && data.content) {
            $text.removeClass('is-error');
            $status.html('<span class="text-xs text-emerald-600 font-semibold flex items-center gap-1"><span class="material-symbols-outlined text-sm">check_circle</span>نجح</span>');
            typewriteText($text[0], data.content);
        } else {
            var msg = data.error || 'فشل الحصول على استجابة';
            var needsPuterConnection = provider === 'puter' && /ربط حساب Puter|جلسة Puter غير صالحة/i.test(msg);
            if (provider === 'puter' && /403/.test(msg)) {
                var fallback = (_allPuterModels || []).find(function(m) { return m && m.tier === 'free' && m.id && m.id !== modelId; });
                if (fallback && !_puterAutoFallbackUsed[modelId]) {
                    _puterAutoFallbackUsed[modelId] = true;
                    $status.html('<span class="text-xs text-amber-600 font-semibold flex items-center gap-1"><span class="material-symbols-outlined text-sm">autorenew</span>تجربة نموذج مجاني...</span>');
                    triggerSamplePreview('puter', fallback.id, panelId, statusId, textId, getToken);
                    return;
                }
            }
            if (needsPuterConnection) {
                $status.html('<span class="text-xs text-amber-600 font-semibold flex items-center gap-1"><span class="material-symbols-outlined text-sm">link_off</span>يتطلب ربط</span>');
                $text.removeClass('is-error');
                $text.html('<span class="text-amber-700 text-sm">' + $('<span>').text(msg).html() + '</span>');
            } else {
                $status.html('<span class="text-xs text-red-500 font-semibold flex items-center gap-1"><span class="material-symbols-outlined text-sm">error</span>خطأ</span>');
                $text.addClass('is-error');
                $text.html('<span class="text-red-500 text-sm">' + $('<span>').text(msg).html() + '</span>');
            }
        }
    })
    .catch(function(err) {
        if (_previewSeq[panelId] !== requestId) return;
        $status.html('<span class="text-xs text-red-500 font-semibold">تعذّر الاتصال</span>');
        $text.addClass('is-error');
        $text.html('<span class="text-red-500 text-sm">تعذّر الاتصال بالخادم</span>');
    });
    }

    if (provider === 'puter') {
        var tokenCandidate = (typeof getToken === 'function') ? getToken() : '';

        if (tokenCandidate && typeof tokenCandidate.then === 'function') {
            tokenCandidate.then(function(token) {
                token = String(token || '').trim();
                if (/^bearer\s+/i.test(token)) token = token.replace(/^bearer\s+/i, '').trim();
                if (token && token !== '[object Promise]') {
                    headers['X-Puter-Token'] = token;
                } else {
                    body.puter_token = '';
                }
                sendRequest();
            }).catch(function() {
                body.puter_token = '';
                sendRequest();
            });
            return;
        }

        var token = String(tokenCandidate || '').trim();
        if (/^bearer\s+/i.test(token)) token = token.replace(/^bearer\s+/i, '').trim();
        if (token && token !== '[object Promise]') {
            headers['X-Puter-Token'] = token;
        } else {
            body.puter_token = '';
        }
    }

    sendRequest();
}

function typewriteText(container, text) {
    container.innerHTML = '';
    var delay = 0;
    var stepMs = Math.min(60, Math.max(18, Math.round(1200 / text.length)));
    for (var i = 0; i < text.length; i++) {
        (function(ch, d) {
            setTimeout(function() {
                var span = document.createElement('span');
                span.className = 'sample-char';
                span.style.animationDelay = '0ms';
                span.textContent = ch;
                // Remove cursor if present, append char, re-add cursor
                var cur = container.querySelector('.sample-cursor');
                if (cur) container.removeChild(cur);
                container.appendChild(span);
                var cursor = document.createElement('span');
                cursor.className = 'sample-cursor';
                container.appendChild(cursor);
            }, d);
        })(text[i], delay);
        delay += stepMs;
    }
    // Remove cursor after animation finishes
    setTimeout(function() {
        var cur = container.querySelector('.sample-cursor');
        if (cur) cur.remove();
    }, delay + 800);
}

// ─── Puter Select2 ────────────────────────────────────────────────
var _allPuterModels = [];

function initPuterSelect2(models, selectedId, onChange) {
    _allPuterModels = models;
    renderPuterSelect2(models, selectedId, onChange);
}

function renderPuterSelect2(models, selectedId, onChange) {
    var $sel = $('#puterModelSelect');
    if ($sel.data('select2')) { $sel.select2('destroy'); }
    $sel.empty();

    var currentSelected = selectedId || $sel.val();

    models.forEach(function(m) {
        var label = m.pricing_label || '';
        var text = m.name + (label ? '  —  ' + label : '');
        var isSel = m.id === currentSelected;
        var opt = new Option(text, m.id, isSel, isSel);
        $(opt).attr('data-tier', m.tier).attr('data-label', label);
        $sel.append(opt);
    });

    $sel.select2({
        placeholder: 'ابحث عن نموذج...',
        dir: 'rtl',
        width: '100%',
        allowClear: false,
        templateResult: function(item) {
            if (!item.id) return item.text;
            var tier = $(item.element).data('tier');
            var lbl = $(item.element).data('label');
            var $row = $('<div class="flex items-center justify-between gap-3 py-1"></div>');
            $row.append($('<span class="text-slate-800 text-sm"></span>').text(item.text.split('  —  ')[0]));
            if (lbl) {
                var cls = tier === 'free'
                    ? 'text-xs px-2 py-0.5 rounded-full bg-emerald-100 text-emerald-700 font-semibold shrink-0'
                    : 'text-xs px-2 py-0.5 rounded-full bg-amber-100 text-amber-700 font-semibold shrink-0';
                $row.append($('<span></span>').addClass(cls).text(lbl));
            }
            return $row;
        },
        templateSelection: function(item) {
            if (!item.id) return item.text;
            var parts = item.text.split('  —  ');
            return parts[0] + (parts[1] ? '  —  ' + parts[1] : '');
        }
    });

    $sel.off('change.preview').on('change.preview', function() {
        var id = $(this).val();
        onChange(id);
        syncPuterModelState(id);
        var getPuterToken = function() {
            try {
                if (typeof puter === 'undefined') return '';
                var token = puter.authToken ? String(puter.authToken) : '';
                if (!token && puter.auth && typeof puter.auth.getAuthToken === 'function') {
                    var authToken = puter.auth.getAuthToken();
                    if (authToken && typeof authToken.then === 'function') {
                        return authToken.then(function(t) { return String(t || ''); }).catch(function() { return ''; });
                    }
                    token = String(authToken || '');
                }
                token = token.trim();
                if (/^bearer\s+/i.test(token)) token = token.replace(/^bearer\s+/i, '').trim();
                return token;
            } catch(e) { return ''; }
        };
        triggerSamplePreview('puter', id, 'puterSampleOutput', 'puterSampleStatus', 'puterSampleText', getPuterToken);
    });
    $sel.trigger('change.select2');

    var initialId = $sel.val();
    if (initialId) {
        var getPuterToken = function() {
            try {
                if (typeof puter === 'undefined') return '';
                var token = puter.authToken ? String(puter.authToken) : '';
                if (!token && puter.auth && typeof puter.auth.getAuthToken === 'function') {
                    var authToken = puter.auth.getAuthToken();
                    if (authToken && typeof authToken.then === 'function') {
                        return authToken.then(function(t) { return String(t || ''); }).catch(function() { return ''; });
                    }
                    token = String(authToken || '');
                }
                token = token.trim();
                if (/^bearer\s+/i.test(token)) token = token.replace(/^bearer\s+/i, '').trim();
                return token;
            } catch(e) { return ''; }
        };
        triggerSamplePreview('puter', initialId, 'puterSampleOutput', 'puterSampleStatus', 'puterSampleText', getPuterToken);
    }
}

// Free-only toggle for Puter
$(document).on('change', '#puterFreeFilter', function() {
    var freeOnly = $(this).is(':checked');
    var filtered = freeOnly ? _allPuterModels.filter(function(m) { return m.tier === 'free'; }) : _allPuterModels;
    var currentId = $('#puterModelSelect').val();
    renderPuterSelect2(filtered, currentId, function(id) {
        syncPuterModelState(id);
    });
});

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
    
    // Store all OpenRouter options for filter reset
    var _allOrOptions = [];
    $('#modelSelect option').each(function() {
        _allOrOptions.push({
            value: $(this).val(),
            text:  $(this).text(),
            prompt: $(this).data('prompt'),
            completion: $(this).data('completion'),
            tier: $(this).data('tier'),
            selected: $(this).prop('selected'),
        });
    });

    // OpenRouter free-only toggle
    $('#orFreeFilter').on('change', function() {
        var freeOnly = $(this).is(':checked');
        var currentVal = $('#modelSelect').val();
        var $sel = $('#modelSelect');
        if ($sel.data('select2')) { $sel.select2('destroy'); }
        $sel.empty();

        var toShow = freeOnly ? _allOrOptions.filter(function(o) { return o.tier === 'free'; }) : _allOrOptions;
        var hasCurrentVal = toShow.some(function(o) { return o.value === currentVal; });

        toShow.forEach(function(o) {
            var isSel = hasCurrentVal ? (o.value === currentVal) : false;
            var opt = new Option(o.text, o.value, isSel, isSel);
            $(opt).data('prompt', o.prompt).data('completion', o.completion).attr('data-prompt', o.prompt).attr('data-completion', o.completion).attr('data-tier', o.tier);
            $sel.append(opt);
        });

        if (!hasCurrentVal && toShow.length) {
            $sel.val(toShow[0].value);
        }

        $sel.select2({
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
        }).on('change', function() {
            updateAverageCase();
            triggerSamplePreview('openrouter', $(this).val(), 'orSampleOutput', 'orSampleStatus', 'orSampleText', null);
        });

        $sel.trigger('change');
    });

    $('#modelSelect').on('change', function() {
        updateAverageCase();
        var modelId = $(this).val();
        triggerSamplePreview('openrouter', modelId, 'orSampleOutput', 'orSampleStatus', 'orSampleText', null);
    });
    updateAverageCase();
    $('#modelSelect').trigger('change');

    function runOpenRouterCheck() {
        var btn = $('#checkOpenRouterBtn');
        var icon = $('#checkOpenRouterIcon');
        var text = $('#checkOpenRouterText');
        var result = $('#openRouterResult');
        if (!btn.length || !icon.length || !text.length || !result.length) return;
        btn.prop('disabled', true);
        icon.text('hourglass_empty');
        text.text('جاري التحقق...');
        result.addClass('hidden').empty();

        $.get('<?php echo e(route('settings.check-openrouter')); ?>')
            .done(function(data) {
                result.removeClass('hidden');
                if (data.ok) {
                    result.addClass('bg-emerald-50 border border-emerald-200 text-emerald-800');
                    
                    var usageUSD = data.usage != null ? parseFloat(data.usage).toFixed(2) : '—';
                    var usageSAR = data.usage_sar != null ? parseFloat(data.usage_sar).toFixed(2) : '—';
                    var limitDisplay = data.limit_remaining_display || '—';
                    var limitRemaining = data.limit_remaining;
                    
                    var usageText = '';
                    if (limitRemaining !== null && limitRemaining !== undefined) {
                        var limitUSD = parseFloat(limitRemaining).toFixed(2);
                        var limitSAR = data.limit_remaining_sar != null ? parseFloat(data.limit_remaining_sar).toFixed(2) : (parseFloat(limitRemaining) * USD_TO_SAR).toFixed(2);
                        var totalLimit = parseFloat(limitRemaining) + parseFloat(data.usage || 0);
                        var totalLimitUSD = totalLimit.toFixed(2);
                        var totalLimitSAR = (totalLimit * USD_TO_SAR).toFixed(2);
                        usageText = '<p class="text-emerald-600 mt-2"><span class="text-xs text-emerald-500">الاستخدام من الحد الأقصى:</span><br><strong class="text-lg">' + usageSAR + ' ر.س</strong> من <strong class="text-lg">' + totalLimitSAR + ' ر.س</strong></p>' +
                                    '<p class="text-emerald-500 text-xs mt-1">(' + usageUSD + ' USD من ' + totalLimitUSD + ' USD)</p>';
                    } else {
                        usageText = '<p class="text-emerald-600 mt-2"><span class="text-xs text-emerald-500">الاستخدام (الإجمالي):</span><br><strong class="text-lg">' + usageSAR + ' ر.س</strong></p>' +
                                    '<p class="text-emerald-500 text-xs mt-1">(' + usageUSD + ' USD)</p>';
                    }
                    
                    result.html(
                        '<p class="font-semibold flex items-center gap-2"><span class="material-symbols-outlined">check_circle</span>' + (data.message || '') + '</p>' +
                        '<p class="mt-3 text-emerald-700 text-sm">الرصيد المتبقي: <strong>' + limitDisplay + '</strong></p>' +
                        usageText
                    );
                } else {
                    result.addClass('bg-red-50 border border-red-200 text-red-800');
                    result.html('<p class="font-semibold flex items-center gap-2"><span class="material-symbols-outlined">error</span>' + (data.message || 'فشل التحقق') + '</p>');
                }
            })
            .fail(function() {
                result.removeClass('hidden').addClass('bg-red-50 border border-red-200 text-red-800');
                result.html('<p class="font-semibold flex items-center gap-2"><span class="material-symbols-outlined">error</span>فشل الاتصال بالخادم. جرّب من الطرفية: php artisan openrouter:check</p>');
            })
            .always(function() {
                btn.prop('disabled', false);
                icon.text('refresh');
                text.text('تحديث الرصيد');
                // Refresh the auto widget with fresh data
                if (typeof window.refreshOpenRouterBalance === 'function') {
                    window.refreshOpenRouterBalance();
                }
            });
    }

    $('#checkOpenRouterBtn').on('click', function() {
        runOpenRouterCheck();
    });

    window.addEventListener('settings:openrouter-check', function () {
        runOpenRouterCheck();
    });

    // Auto-run balance check when settings page loads (force fresh data, bust session cache)
    $(document).ready(function() {
        if (typeof window.refreshOpenRouterBalance === 'function') {
            window.refreshOpenRouterBalance();
        }
    });
});
</script>
<?php $__env->stopPush(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/pages/settings.blade.php ENDPATH**/ ?>