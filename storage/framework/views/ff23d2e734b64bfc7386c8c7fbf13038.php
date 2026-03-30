<?php $__env->startSection('title', 'إدارة القضايا - المستشار القانوني الذكي'); ?>

<?php use Illuminate\Support\Str; ?>

<?php $__env->startSection('content'); ?>

<div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 bg-white p-4 rounded-xl shadow-sm border border-primary/5 mb-8">
    <div class="flex flex-col">
        <h2 class="text-2xl font-black tracking-tight">إدارة القضايا القانونية</h2>
        <p class="text-slate-500">مرحباً بك في لوحة تحكم القضايا الخاصة بك</p>
    </div>
    <div class="flex items-center gap-3 w-full md:w-auto">
        <div class="relative flex-1 md:w-64">
            <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400">search</span>
            <input class="w-full pr-10 pl-4 py-2 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary" placeholder="بحث عن قضية..." type="text"/>
        </div>
        <a href="<?php echo e(route('cases.create')); ?>" class="flex items-center gap-2 bg-primary text-white px-6 py-2.5 rounded-xl font-bold hover:bg-primary/90 transition-all shadow-lg shadow-primary/20">
            <span class="material-symbols-outlined">add</span>
            <span>قضية جديدة</span>
        </a>
    </div>
</div>


<div class="grid grid-cols-2 md:grid-cols-5 gap-4 mb-8">
    <div class="bg-white p-6 rounded-xl border border-primary/5 shadow-sm magnetic-element">
        <p class="text-slate-500 text-sm mb-1">قضايا جديدة</p>
        <div class="flex justify-between items-end">
            <p class="text-3xl font-bold"><?php echo e($stats['new']); ?></p>
            <span class="text-blue-500 material-symbols-outlined text-4xl opacity-20">fiber_new</span>
        </div>
    </div>
    <div class="bg-white p-6 rounded-xl border border-primary/5 shadow-sm magnetic-element">
        <p class="text-slate-500 text-sm mb-1">قيد التحليل</p>
        <div class="flex justify-between items-end">
            <p class="text-3xl font-bold"><?php echo e($stats['analyzing']); ?></p>
            <span class="text-amber-500 material-symbols-outlined text-4xl opacity-20">analytics</span>
        </div>
    </div>
    <div class="bg-white p-6 rounded-xl border border-primary/5 shadow-sm magnetic-element">
        <p class="text-slate-500 text-sm mb-1">قيد الصياغة</p>
        <div class="flex justify-between items-end">
            <p class="text-3xl font-bold"><?php echo e($stats['drafting']); ?></p>
            <span class="text-primary material-symbols-outlined text-4xl opacity-20">edit_note</span>
        </div>
    </div>
    <div class="bg-white p-6 rounded-xl border border-primary/5 shadow-sm magnetic-element">
        <p class="text-slate-500 text-sm mb-1">مكتملة</p>
        <div class="flex justify-between items-end">
            <p class="text-3xl font-bold"><?php echo e($stats['completed']); ?></p>
            <span class="text-emerald-500 material-symbols-outlined text-4xl opacity-20">check_circle</span>
        </div>
    </div>
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(($stats['failed'] ?? 0) > 0): ?>
    <div class="bg-red-50 p-6 rounded-xl border border-red-200 shadow-sm magnetic-element">
        <p class="text-red-600 text-sm mb-1">فشلت</p>
        <div class="flex justify-between items-end">
            <p class="text-3xl font-bold text-red-600"><?php echo e($stats['failed']); ?></p>
            <span class="text-red-500 material-symbols-outlined text-4xl opacity-30">error</span>
        </div>
    </div>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
</div>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <div class="lg:col-span-2 flex flex-col gap-4">
        <div class="flex items-center justify-between mb-2">
            <h3 class="text-lg font-bold flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">list_alt</span>
                قائمة القضايا الحالية
            </h3>
            <div class="flex items-center gap-3">
                <button x-data="{ selected: [] }" @click="const checkboxes = document.querySelectorAll('.case-checkbox'); const allChecked = Array.from(checkboxes).every(c => c.checked); checkboxes.forEach(c => c.checked = !allChecked)" class="text-primary text-sm font-semibold hover:underline">تحديد الكل</button>
                <a href="#" class="text-primary text-sm font-semibold hover:underline">عرض الكل</a>
            </div>
        </div>
        
        
        <div x-data="{
            checkedCount: 0,
            hasPausedCases: false,
            hasProcessingCases: false,
            hasFailedCases: false,
            hasCompletedCases: false,
            updateStatus() {
                const checkboxes = document.querySelectorAll('.case-checkbox:checked');
                const ids = Array.from(checkboxes).map(cb => cb.value);
                this.checkedCount = ids.length;
                
                // Check case statuses from data attributes
                this.hasPausedCases = false;
                this.hasProcessingCases = false;
                this.hasFailedCases = false;
                this.hasCompletedCases = false;
                
                checkboxes.forEach(cb => {
                    const status = cb.dataset.status;
                    if (status === 'paused') this.hasPausedCases = true;
                    if (status === 'phase1_processing' || status === 'phase2_processing' || status === 'phase3_processing') this.hasProcessingCases = true;
                    if (status === 'failed' || status === 'halted' || status === 'timed_out') this.hasFailedCases = true;
                    if (status === 'phase2_completed' || status === 'phase3_completed' || status === 'completed_with_warnings') this.hasCompletedCases = true;
                });
            }
        }" 
             @change="updateStatus()"
             @bulk-selection-changed.window="updateStatus()"
             x-show="checkedCount > 0"
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="mb-4 bg-primary/5 border border-primary/20 rounded-xl p-4">
            <div class="flex flex-wrap items-center justify-between gap-3">
                <div class="flex items-center gap-2">
                    <span class="text-sm font-bold text-primary">
                        <span x-text="checkedCount">0</span> قضية محددة
                    </span>
                </div>
                <div class="flex flex-wrap items-center gap-2">
                    
                    <span x-show="hasPausedCases" x-cloak>
                        <form action="<?php echo e(route('cases.bulk.resume')); ?>" method="POST" onsubmit="return confirm('هل تريد استكمال معالجة القضايا المحددة؟');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="case_ids" class="bulk-case-ids">
                            <button type="submit" class="flex items-center gap-2 px-3 py-2 text-sm bg-primary text-white rounded-lg hover:bg-primary/90 transition-colors">
                                <span class="material-symbols-outlined text-base">play_circle</span>
                                استكمال المعالجة
                            </button>
                        </form>
                    </span>
                    
                    
                    <span x-show="hasProcessingCases" x-cloak>
                        <form action="<?php echo e(route('cases.bulk.pause')); ?>" method="POST" onsubmit="return confirm('هل تريد إيقاف معالجة القضايا المحددة مؤقتاً؟');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="case_ids" class="bulk-case-ids">
                            <button type="submit" class="flex items-center gap-2 px-3 py-2 text-sm bg-amber-500 text-white rounded-lg hover:bg-amber-600 transition-colors">
                                <span class="material-symbols-outlined text-base">pause_circle</span>
                                إيقاف مؤقت
                            </button>
                        </form>
                    </span>
                    
                    
                    <span x-show="hasFailedCases" x-cloak>
                        <form action="<?php echo e(route('cases.bulk.retry')); ?>" method="POST" onsubmit="return confirm('هل تريد إعادة محاولة معالجة القضايا المحددة؟');">
                            <?php echo csrf_field(); ?>
                            <input type="hidden" name="case_ids" class="bulk-case-ids">
                            <button type="submit" class="flex items-center gap-2 px-3 py-2 text-sm bg-red-500 text-white rounded-lg hover:bg-red-600 transition-colors">
                                <span class="material-symbols-outlined text-base">refresh</span>
                                إعادة المحاولة
                            </button>
                        </form>
                    </span>
                    
                    
                    <span x-show="hasCompletedCases" x-cloak>
                        <form action="<?php echo e(route('cases.bulk.delete')); ?>" method="POST" onsubmit="return confirm('هل أنت متأكد من حذف القضايا المحددة نهائياً؟ لا يمكن التراجع.');">
                            <?php echo csrf_field(); ?>
                            <?php echo method_field('DELETE'); ?>
                            <input type="hidden" name="case_ids" class="bulk-case-ids">
                            <button type="submit" class="flex items-center gap-2 px-3 py-2 text-sm bg-red-600 text-white rounded-lg hover:bg-red-700 transition-colors">
                                <span class="material-symbols-outlined text-base">delete</span>
                                حذف
                            </button>
                        </form>
                    </span>
                </div>
            </div>
        </div>
        
        <script>
            document.addEventListener('change', function(e) {
                if (e.target.classList.contains('case-checkbox')) {
                    const checkedBoxes = document.querySelectorAll('.case-checkbox:checked');
                    const ids = Array.from(checkedBoxes).map(cb => cb.value);
                    document.querySelectorAll('.bulk-case-ids').forEach(input => {
                        input.value = ids.join(',');
                    });
                    
                    // Dispatch custom event for Alpine components
                    window.dispatchEvent(new CustomEvent('bulk-selection-changed', {
                        detail: { count: ids.length }
                    }));
                }
            });
        </script>
        
        <div class="flex flex-col gap-3">
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $cases ?? []; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $case): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <?php 
                    $statusVal = $case->status->value ?? $case->status;
                    $isFailed = $statusVal === 'failed';
                    $isPaused = $statusVal === 'paused';
                    $isHalted = $statusVal === 'halted';
                    $isTimedOut = $statusVal === 'timed_out';
                    $isProcessing = in_array($statusVal, ['phase1_pending', 'phase1_processing', 'phase2_pending', 'phase2_processing', 'phase3_pending', 'phase3_processing']);
                    $isCompleted = in_array($statusVal, ['phase2_completed', 'phase3_completed', 'completed_with_warnings']);
                    $isAwaiting = $statusVal === 'awaiting_laws';
                ?>
                <div class="bg-white p-5 rounded-xl border <?php echo e($isFailed || $isHalted || $isTimedOut ? 'border-red-200 bg-red-50/30' : 'border-primary/5'); ?> shadow-sm hover:border-primary transition-all group">
                    <div class="flex justify-between items-start">
                        <div class="flex items-start gap-4">
                            <input type="checkbox" class="case-checkbox w-5 h-5 rounded border-slate-300 text-primary focus:ring-primary mt-1" value="<?php echo e($case->id); ?>" data-status="<?php echo e($statusVal); ?>">
                            <div class="size-12 rounded-lg 
                                <?php if($isFailed): ?> bg-red-100 text-red-600
                                <?php elseif($isCompleted): ?> bg-emerald-50 text-emerald-600
                                <?php elseif($isProcessing): ?> bg-amber-50 text-amber-600
                                <?php elseif($isAwaiting): ?> bg-blue-50 text-blue-600
                                <?php else: ?> bg-slate-100 text-slate-600
                                <?php endif; ?>
                                flex items-center justify-center">
                                <span class="material-symbols-outlined">
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isFailed): ?> error
                                    <?php elseif($isCompleted): ?> verified
                                    <?php elseif($isProcessing): ?> sync
                                    <?php elseif($isAwaiting): ?> hourglass_top
                                    <?php else: ?> balance
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                </span>
                            </div>
                            <div>
                                <h4 class="font-bold text-lg group-hover:text-primary transition-colors">
                                    <a href="<?php echo e(route('cases.show', $case->id)); ?>"><?php echo e($case->title); ?></a>
                                </h4>
                                <p class="text-sm text-slate-500 mb-2">المرحلة: <?php echo e($case->phase ?? '1'); ?></p>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isFailed && $case->last_error_message): ?>
                                    <p class="text-xs text-red-600 mb-2 truncate max-w-xs" title="<?php echo e($case->last_error_message); ?>">
                                        <span class="material-symbols-outlined text-xs align-middle">warning</span>
                                        <?php echo e(Str::limit($case->last_error_message, 50)); ?>

                                    </p>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                <div class="flex gap-4">
                                    <span class="flex items-center gap-1 text-xs text-slate-400">
                                        <span class="material-symbols-outlined text-xs">calendar_today</span>
                                        <?php echo e($case->created_at->format('Y-m-d')); ?>

                                    </span>
                                    <span class="flex items-center gap-1 text-xs text-slate-400">
                                        <span class="material-symbols-outlined text-xs">folder</span>
                                        قضية
                                    </span>
                                </div>
                            </div>
                        </div>
                        <div class="flex flex-col items-end gap-2">
                            <span class="px-3 py-1 rounded-full text-xs font-bold
                                <?php if($isFailed): ?> bg-red-100 text-red-700
                                <?php elseif($isPaused): ?> bg-orange-100 text-orange-700
                                <?php elseif($isCompleted): ?> bg-emerald-100 text-emerald-700
                                <?php elseif($isProcessing): ?> bg-amber-100 text-amber-700
                                <?php elseif($isAwaiting): ?> bg-blue-100 text-blue-700
                                <?php else: ?> bg-slate-100 text-slate-700
                                <?php endif; ?>
                            ">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isFailed): ?> فشلت
                                <?php elseif($isPaused): ?> متوقفة
                                <?php elseif($isCompleted): ?> مكتملة
                                <?php elseif(in_array($statusVal, ['phase3_pending', 'phase3_processing'])): ?> قيد التحكيم
                                <?php elseif($isProcessing): ?> قيد التحليل
                                <?php elseif($isAwaiting): ?> بانتظار الموافقة
                                <?php else: ?> جديدة
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </span>

                            
                            <div x-data="{ open: false }" class="relative" @click.outside="open = false">
                                <button @click.stop="open = !open"
                                    class="flex items-center gap-1 px-3 py-1.5 text-sm text-slate-600 bg-slate-100 hover:bg-slate-200 rounded-lg transition-colors cursor-pointer"
                                    title="الإجراءات">
                                    <span class="material-symbols-outlined text-base">more_horiz</span>
                                    <span class="font-medium">إجراءات</span>
                                </button>

                                <div x-show="open"
                                    x-transition:enter="transition ease-out duration-150"
                                    x-transition:enter-start="opacity-0 scale-95"
                                    x-transition:enter-end="opacity-100 scale-100"
                                    x-transition:leave="transition ease-in duration-100"
                                    x-transition:leave-start="opacity-100"
                                    x-transition:leave-end="opacity-0"
                                    class="absolute right-0 top-full mt-1 w-52 bg-white rounded-xl shadow-xl border border-slate-200 z-[100] overflow-hidden py-1"
                                    style="position: absolute;"
                                >
                                    
                                    <a href="<?php echo e(route('cases.show', $case->id)); ?>"
                                       class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors cursor-pointer">
                                        <span class="material-symbols-outlined text-base text-slate-400">open_in_new</span>
                                        عرض القضية
                                    </a>

                                    
                                    <a href="<?php echo e(route('ai-analysis', $case->id)); ?>"
                                       class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors cursor-pointer">
                                        <span class="material-symbols-outlined text-base text-blue-500">psychology</span>
                                        تحليل الذكاء الاصطناعي
                                    </a>

                                    
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isCompleted): ?>
                                    <a href="<?php echo e(route('cases.pdf', $case->id)); ?>"
                                       download="legal-brief-<?php echo e(now()->format('Y-m-d')); ?>.pdf"
                                       class="flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors cursor-pointer">
                                        <span class="material-symbols-outlined text-base text-emerald-600">picture_as_pdf</span>
                                        تصدير PDF
                                    </a>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isProcessing): ?>
                                    <form action="<?php echo e(route('cases.pause', $case->id)); ?>" method="POST"
                                          onsubmit="return confirm('هل تريد إيقاف معالجة هذه القضية مؤقتاً؟');">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit"
                                            class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors cursor-pointer text-right">
                                            <span class="material-symbols-outlined text-base text-amber-500">pause_circle</span>
                                            إيقاف مؤقت
                                        </button>
                                    </form>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isPaused): ?>
                                    <form action="<?php echo e(route('cases.resume', $case->id)); ?>" method="POST">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit"
                                            class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors cursor-pointer text-right">
                                            <span class="material-symbols-outlined text-base text-primary">play_circle</span>
                                            استكمال المعالجة
                                        </button>
                                    </form>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isFailed || $isHalted || $isTimedOut): ?>
                                    <form action="<?php echo e(route('cases.retry-agent', $case->id)); ?>" method="POST"
                                          onsubmit="return confirm('هل تريد إعادة محاولة معالجة هذه القضية؟');">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit"
                                            class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors cursor-pointer text-right">
                                            <span class="material-symbols-outlined text-base text-red-500">refresh</span>
                                            إعادة المحاولة
                                        </button>
                                    </form>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    
                                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($isAwaiting): ?>
                                    <form action="<?php echo e(route('cases.start-phase2', $case->id)); ?>" method="POST">
                                        <?php echo csrf_field(); ?>
                                        <button type="submit"
                                            class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-slate-700 hover:bg-slate-50 transition-colors cursor-pointer text-right">
                                            <span class="material-symbols-outlined text-base text-primary">rocket_launch</span>
                                            بدء المرحلة الثانية
                                        </button>
                                    </form>
                                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                                    <div class="border-t border-slate-100 my-1"></div>

                                    
                                    <form action="<?php echo e(route('cases.destroy', $case->id)); ?>" method="POST"
                                          onsubmit="return confirm('هل أنت متأكد من حذف هذه القضية نهائياً؟ لا يمكن التراجع.');">
                                        <?php echo csrf_field(); ?>
                                        <?php echo method_field('DELETE'); ?>
                                        <button type="submit"
                                            class="w-full flex items-center gap-3 px-4 py-2.5 text-sm text-red-600 hover:bg-red-50 transition-colors cursor-pointer text-right">
                                            <span class="material-symbols-outlined text-base">delete</span>
                                            حذف القضية
                                        </button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                <div class="bg-white p-10 rounded-xl border border-primary/5 shadow-sm text-center">
                    <span class="material-symbols-outlined text-5xl text-slate-300 mb-4 block">folder_open</span>
                    <h4 class="font-bold text-lg text-slate-700 mb-2">لا توجد قضايا بعد</h4>
                    <p class="text-sm text-slate-500 mb-4">ابدأ بإنشاء قضيتك الأولى لتبدأ عملية التحليل الذكي.</p>
                    <a href="<?php echo e(route('cases.create')); ?>" class="inline-flex items-center gap-2 bg-primary text-white px-5 py-2.5 rounded-xl font-bold hover:bg-primary/90 transition-all">
                        <span class="material-symbols-outlined text-sm">add</span>
                        قضية جديدة
                    </a>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
    
    
    <div class="flex flex-col gap-4">
        <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-lg">
            <div class="flex items-center gap-2 mb-6">
                <div class="size-8 bg-primary/20 rounded-lg flex items-center justify-center text-primary">
                    <span class="material-symbols-outlined">add_task</span>
                </div>
                <h3 class="text-lg font-bold">إنشاء قضية جديدة</h3>
            </div>
            
            <form action="<?php echo e(route('cases.store')); ?>" method="POST" enctype="multipart/form-data" class="flex flex-col gap-5">
                <?php echo csrf_field(); ?>
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-slate-700">عنوان القضية</label>
                    <input name="title" class="w-full px-4 py-2.5 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary" placeholder="مثال: مراجعة عقد عقاري" type="text" required/>
                </div>
                
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-slate-700">اسم العميل</label>
                    <div class="relative">
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 text-lg">person</span>
                        <input name="client_name" class="w-full pr-10 pl-4 py-2.5 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary" placeholder="ابحث عن عميل أو أضف جديداً" type="text"/>
                    </div>
                </div>
                
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-slate-700">وصف القضية</label>
                    <textarea name="description" class="w-full px-4 py-2.5 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary resize-none" placeholder="اكتب تفاصيل وملخص القضية هنا..." rows="4"></textarea>
                </div>
                
                <div class="flex flex-col gap-1.5">
                    <label class="text-sm font-semibold text-slate-700">تصنيف القضية</label>
                    <div class="relative">
                        <select name="category" class="w-full pr-10 pl-4 py-2.5 bg-background-light border-none rounded-xl focus:ring-2 focus:ring-primary appearance-none">
                            <option value="civil">مدني</option>
                            <option value="criminal">جنائي</option>
                            <option value="commercial">تجاري</option>
                            <option value="family">أحوال شخصية</option>
                            <option value="administrative">إداري</option>
                        </select>
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-lg">expand_more</span>
                    </div>
                </div>

                <div class="flex flex-col gap-1.5">
                    <span class="text-sm font-semibold text-slate-700">مرفقات القضية (اختياري)</span>
                    <label for="index-attachments" class="block border-2 border-dashed border-primary/30 rounded-xl p-5 bg-primary/5 hover:border-primary/50 hover:bg-primary/10 transition-colors cursor-pointer mt-1 min-h-[120px]">
                        <input type="file" name="attachments[]" id="index-attachments" multiple accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.txt,.doc,.docx,.pdf,.ppt,.pptx,image/*,text/plain,application/pdf,application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document,application/vnd.ms-powerpoint,application/vnd.openxmlformats-officedocument.presentationml.presentation" class="sr-only">
                        <div class="flex flex-col items-center justify-center gap-2 text-center">
                            <span class="material-symbols-outlined text-4xl text-primary">upload_file</span>
                            <span class="text-sm font-medium text-slate-700">انقر لاختيار الملفات أو اسحبها هنا</span>
                            <span class="text-xs text-slate-500">صور، TXT، DOC، PDF، PPT — حد أقصى 50 م.ب. تظهر في مستندات القضية.</span>
                        </div>
                        <ul id="indexFileList" class="mt-3 pt-3 border-t border-primary/20 space-y-1 text-xs text-slate-600 hidden"></ul>
                    </label>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__errorArgs = ['attachments.*'];
$__bag = $errors->getBag($__errorArgs[1] ?? 'default');
if ($__bag->has($__errorArgs[0])) :
if (isset($message)) { $__messageOriginal = $message; }
$message = $__bag->first($__errorArgs[0]); ?>
                        <p class="mt-1 text-sm text-red-600"><?php echo e($message); ?></p>
                    <?php unset($message);
if (isset($__messageOriginal)) { $message = $__messageOriginal; }
endif;
unset($__errorArgs, $__bag); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </div>
                
                <button class="mt-2 w-full bg-primary text-white font-bold py-3 rounded-xl hover:bg-primary/90 transition-all shadow-md" type="submit">
                    حفظ وإنشاء القضية
                </button>
            </form>
        </div>
        
        
        <div class="bg-gradient-to-br from-primary to-emerald-800 p-6 rounded-xl text-white shadow-lg overflow-hidden relative">
            <div class="relative z-10">
                <div class="flex items-center gap-2 mb-2">
                    <span class="material-symbols-outlined">auto_awesome</span>
                    <span class="text-sm font-bold uppercase tracking-wider">تحليل المستشار الذكي</span>
                </div>
                <?php
                    $needsReview = ($stats['analyzing'] ?? 0) + ($stats['drafting'] ?? 0);
                ?>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($needsReview > 0): ?>
                    <p class="text-sm opacity-90 leading-relaxed">بناءً على نشاطك الأخير، هناك <?php echo e($needsReview); ?> <?php echo e($needsReview === 1 ? 'قضية تتطلب' : 'قضايا تتطلب'); ?> مراجعة فورية للمستندات القانونية لضمان الامتثال.</p>
                <?php else: ?>
                    <p class="text-sm opacity-90 leading-relaxed">لا توجد قضايا قيد التحليل حالياً. قم بإنشاء قضية جديدة لبدء التحليل الذكي.</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>
            <div class="absolute -bottom-4 -left-4 opacity-10">
                <span class="material-symbols-outlined text-[100px]">psychology</span>
            </div>
        </div>
    </div>
</div>
<?php $__env->startPush('scripts'); ?>
<style>
[x-cloak] { display: none !important; }
</style>
<script>
document.getElementById('index-attachments') && document.getElementById('index-attachments').addEventListener('change', function() {
    var list = document.getElementById('indexFileList');
    if (!list) return;
    list.innerHTML = '';
    if (this.files.length) {
        list.classList.remove('hidden');
        for (var i = 0; i < this.files.length; i++) {
            var li = document.createElement('li');
            li.textContent = this.files[i].name + ' (' + (this.files[i].size / 1024 / 1024).toFixed(2) + ' م.ب)';
            list.appendChild(li);
        }
    } else {
        list.classList.add('hidden');
    }
});
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/pages/cases/index.blade.php ENDPATH**/ ?>