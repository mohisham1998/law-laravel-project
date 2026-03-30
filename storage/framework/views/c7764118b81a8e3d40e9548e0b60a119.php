<?php $__env->startSection('title', 'لوحة التحكم - المستشار القانوني الذكي'); ?>

<?php $__env->startSection('content'); ?>
<div class="mb-8">
    <h2 class="text-2xl font-extrabold text-slate-900">مرحباً بك مجدداً</h2>
    <p class="text-slate-500">إليك نظرة عامة على نشاطك القانوني والتحليلات الذكية اليوم.</p>
</div>


<div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
    <div class="notion-card p-6 rounded-2xl magnetic-element">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-primary/10 rounded-xl flex items-center justify-center text-primary">
                <span class="material-symbols-outlined">folder_open</span>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($activeTrend !== null): ?>
                <span class="text-<?php echo e($activeTrend >= 0 ? 'green' : 'red'); ?>-600 text-xs font-bold bg-<?php echo e($activeTrend >= 0 ? 'green' : 'red'); ?>-50 px-2 py-1 rounded-full"><?php echo e($activeTrend >= 0 ? '+' : ''); ?><?php echo e($activeTrend); ?>%</span>
            <?php else: ?>
                <span class="text-slate-400 text-xs bg-slate-100 px-2 py-1 rounded-full">--</span>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
        <p class="text-slate-500 text-sm mb-1">عدد القضايا النشطة</p>
        <h3 class="text-3xl font-black text-slate-900"><?php echo e($stats['active_cases']); ?></h3>
    </div>
    
    <div class="notion-card p-6 rounded-2xl magnetic-element">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-blue-500/10 rounded-xl flex items-center justify-center text-blue-600">
                <span class="material-symbols-outlined">analytics</span>
            </div>
            <span class="text-blue-600 text-xs font-bold bg-blue-50 px-2 py-1 rounded-full">جارٍ</span>
        </div>
        <p class="text-slate-500 text-sm mb-1">القضايا قيد التحليل</p>
        <h3 class="text-3xl font-black text-slate-900"><?php echo e($stats['analyzing_cases']); ?></h3>
    </div>
    
    <div class="notion-card p-6 rounded-2xl magnetic-element">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-orange-500/10 rounded-xl flex items-center justify-center text-orange-600">
                <span class="material-symbols-outlined">task_alt</span>
            </div>
            <span class="text-orange-600 text-xs font-bold bg-orange-50 px-2 py-1 rounded-full">مكتمل</span>
        </div>
        <p class="text-slate-500 text-sm mb-1">المذكرات القانونية المكتملة</p>
        <h3 class="text-3xl font-black text-slate-900"><?php echo e($stats['completed_briefs']); ?></h3>
    </div>
    
    <div class="notion-card p-6 rounded-2xl magnetic-element">
        <div class="flex items-center justify-between mb-4">
            <div class="w-12 h-12 bg-purple-500/10 rounded-xl flex items-center justify-center text-purple-600">
                <span class="material-symbols-outlined">library_books</span>
            </div>
            <span class="text-slate-500 text-xs">إجمالي</span>
        </div>
        <p class="text-slate-500 text-sm mb-1">عدد المستندات</p>
        <h3 class="text-3xl font-black text-slate-900"><?php echo e($stats['total_documents']); ?></h3>
    </div>
</div>


<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    
    <div class="lg:col-span-2 notion-card p-6 rounded-2xl">
        <div class="flex items-center justify-between mb-6">
            <h4 class="font-bold text-slate-900">عدد القضايا شهرياً</h4>
            <div class="relative">
                <select class="w-full pr-8 pl-3 py-2 bg-slate-50 border-none text-xs rounded-lg focus:ring-primary/20 appearance-none">
                    <option>آخر ٦ أشهر</option>
                    <option>السنة الحالية</option>
                </select>
                <span class="material-symbols-outlined absolute right-2 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none text-sm">expand_more</span>
            </div>
        </div>
        <div class="h-64 flex items-end gap-4 px-2">
            <?php $lastIdx = count($monthlyData) - 1; ?>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $monthlyData; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $month): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                <div class="flex-1 flex flex-col items-center gap-2 group">
                    <div class="w-full <?php echo e($index == $lastIdx ? 'bg-primary/20' : 'bg-primary/10'); ?> rounded-t-lg group-hover:bg-primary transition-colors relative" style="height: <?php echo e($month['height']); ?>%">
                        <span class="absolute -top-6 left-1/2 -translate-x-1/2 text-[10px] font-bold opacity-0 group-hover:opacity-100 transition-opacity"><?php echo e($month['value']); ?></span>
                    </div>
                    <span class="text-xs text-slate-500 <?php echo e($index == $lastIdx ? 'font-bold' : ''); ?>"><?php echo e($month['name']); ?></span>
                </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        </div>
    </div>
    
    
    <div class="notion-card p-6 rounded-2xl flex flex-col items-center justify-center">
        <h4 class="font-bold text-slate-900 w-full mb-6 text-right">نسبة اكتمال التحليل</h4>
        <div class="relative w-48 h-48 mb-6">
            <svg class="w-full h-full -rotate-90" viewBox="0 0 36 36">
                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#e2e8f0" stroke-width="3"></path>
                <path d="M18 2.0845 a 15.9155 15.9155 0 0 1 0 31.831 a 15.9155 15.9155 0 0 1 0 -31.831" fill="none" stroke="#006b34" stroke-dasharray="<?php echo e($stats['completion_rate']); ?>, 100" stroke-width="3"></path>
            </svg>
            <div class="absolute inset-0 flex flex-col items-center justify-center">
                <span class="text-3xl font-black text-slate-900"><?php echo e($stats['completion_rate']); ?>%</span>
                <span class="text-xs text-slate-500">مكتمل</span>
            </div>
        </div>
        <div class="w-full space-y-2">
            <div class="flex items-center justify-between text-xs">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-primary"></span>
                    <span class="text-slate-600">قضايا منتهية</span>
                </div>
                <span class="font-bold"><?php echo e($stats['completion_rate']); ?>%</span>
            </div>
            <div class="flex items-center justify-between text-xs">
                <div class="flex items-center gap-2">
                    <span class="w-3 h-3 rounded-full bg-slate-200"></span>
                    <span class="text-slate-600">قضايا معلقة</span>
                </div>
                <span class="font-bold"><?php echo e($stats['pending_rate']); ?>%</span>
            </div>
        </div>
    </div>
</div>


<div class="notion-card p-6 rounded-2xl">
    <div class="flex items-center justify-between mb-8">
        <div>
            <h4 class="font-bold text-slate-900 text-lg">مراحل المعالجة الذكية</h4>
            <p class="text-slate-500 text-sm">حالة وكلاء الذكاء الاصطناعي في تحليل القضايا الحالية</p>
        </div>
        <a href="<?php echo e(route('ai-analysis')); ?>" class="text-primary text-sm font-bold flex items-center gap-1 hover:underline">
            عرض التفاصيل
            <span class="material-symbols-outlined text-sm">arrow_left</span>
        </a>
    </div>
    
    <div class="space-y-6">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $agentProgress; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $index => $agent): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
        <?php
            $colors = ['primary', 'blue-600', 'purple-600'];
            $icons = ['spellcheck', 'auto_awesome', 'edit_note'];
            $statusText = match($agent['status']) {
                'completed' => 'مكتمل',
                'failed' => 'فشل',
                'processing' => 'جاري المعالجة',
                default => 'في الانتظار',
            };
            $color = $colors[$index] ?? 'primary';
            $icon = $icons[$index] ?? 'psychology';
        ?>
        <div class="relative">
            <div class="flex items-center justify-between mb-2">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-<?php echo e($color); ?>/10 flex items-center justify-center text-<?php echo e($color); ?>">
                        <span class="material-symbols-outlined text-base"><?php echo e($icon); ?></span>
                    </div>
                    <div>
                        <p class="text-sm font-bold text-slate-900"><?php echo e($agent['name']); ?></p>
                        <p class="text-[11px] text-slate-500"><?php echo e($statusText); ?></p>
                    </div>
                </div>
                <span class="text-xs font-bold text-<?php echo e($color); ?>"><?php echo e($agent['progress']); ?>%</span>
            </div>
            <div class="w-full bg-slate-100 h-2 rounded-full overflow-hidden">
                <div class="bg-<?php echo e($color); ?> h-full transition-all" style="width: <?php echo e($agent['progress']); ?>%"></div>
            </div>
        </div>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
        <div class="text-center py-4 text-slate-500">
            <p>لا توجد عمليات قيد المعالجة حالياً</p>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/pages/dashboard.blade.php ENDPATH**/ ?>