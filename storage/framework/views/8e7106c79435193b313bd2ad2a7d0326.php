<?php $__env->startSection('title', $case->title ?? 'تفاصيل القضية'); ?>

<?php $__env->startSection('content'); ?>
<?php $statusVal = $case->status->value ?? $case->status; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('success')): ?>
    <div class="mb-6 p-4 rounded-xl bg-emerald-50 border border-emerald-200 flex items-center gap-3">
        <span class="material-symbols-outlined text-emerald-600">check_circle</span>
        <p class="font-medium text-emerald-800"><?php echo e(session('success')); ?></p>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(session('error')): ?>
    <div class="mb-6 p-4 rounded-xl bg-red-50 border border-red-200 flex items-center gap-3">
        <span class="material-symbols-outlined text-red-600">error</span>
        <p class="font-medium text-red-800"><?php echo e(session('error')); ?></p>
    </div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<div class="flex justify-between items-center mb-6">
    <a href="<?php echo e(route('cases.index')); ?>" class="flex items-center gap-1.5 text-sm text-slate-600 hover:text-primary transition-colors" title="العودة للقضايا">
        <span class="material-symbols-outlined text-lg">arrow_forward</span>
        <span>العودة</span>
    </a>
    <span class="text-sm text-slate-900 font-semibold"><?php echo e($case->title ?? 'تفاصيل القضية'); ?></span>
</div>


<?php echo $__env->make('components.pipeline-tracker', ['case' => $case, 'statusVal' => $statusVal], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($statusVal, ['phase2_completed', 'completed_with_warnings']) && $case->phase < 3): ?>
<div id="phase3GateBanner" class="bg-gradient-to-r from-indigo-50 to-indigo-100 p-6 rounded-xl border-2 border-indigo-300 shadow-sm mb-6">
    <div class="flex items-center gap-4 flex-wrap">
        <div class="flex-shrink-0 w-12 h-12 bg-indigo-200 rounded-full flex items-center justify-center">
            <span class="material-symbols-outlined text-indigo-700 text-2xl">balance</span>
        </div>
        <div class="flex-1 min-w-0">
            <h3 class="font-bold text-lg text-slate-900 mb-1">المرحلة الثالثة — التحكيم القضائي</h3>
            <p class="text-sm text-slate-600">
                اكتملت المرحلة الثانية بنجاح. يمكنك الآن تشغيل المرحلة الثالثة: القاضي → محامي الخصم → وكيل التحصين.
            </p>
        </div>
        <form action="<?php echo e(route('cases.start-phase3', $case)); ?>" method="POST" class="flex-shrink-0 puter-form">
            <?php echo csrf_field(); ?>
            <input type="hidden" name="puter_token" class="puter-token-input" value="">
            <button type="submit" class="flex items-center gap-2 px-6 py-3 bg-indigo-600 text-white font-bold rounded-xl hover:bg-indigo-700 active:scale-95 transition-all shadow-lg text-base">
                <span class="material-symbols-outlined text-xl">gavel</span>
                بدء التحكيم القضائي
            </button>
        </form>
    </div>
</div>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-8">
    
    <div class="lg:col-span-2 space-y-6">
        
        <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
            <div class="flex justify-between items-start mb-4">
                <div>
                    <h1 class="text-2xl font-black mb-2"><?php echo e($case->title ?? 'عنوان القضية'); ?></h1>
                    <div class="flex items-center gap-4 text-sm text-slate-500">
                        <span class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">calendar_today</span>
                            <?php echo e($case->created_at?->format('Y-m-d') ?? '---'); ?>

                        </span>
                        <span class="flex items-center gap-1">
                            <span class="material-symbols-outlined text-sm">person</span>
                            <?php echo e($case->client_name ?? 'غير محدد'); ?>

                        </span>
                    </div>
                </div>
                <span class="px-4 py-2 rounded-full text-sm font-bold
                    <?php if($statusVal === 'phase3_completed' || $statusVal === 'completed_with_warnings'): ?> bg-emerald-100 text-emerald-700
                    <?php elseif(in_array($statusVal, ['phase1_pending', 'phase1_processing', 'phase2_pending', 'phase2_processing', 'phase3_pending', 'phase3_processing'], true)): ?> bg-amber-100 text-amber-700
                    <?php elseif($statusVal === 'awaiting_laws'): ?> bg-blue-100 text-blue-700
                    <?php elseif($statusVal === 'failed' || $statusVal === 'paused'): ?> bg-red-100 text-red-700
                    <?php elseif($statusVal === 'halted' || $statusVal === 'timed_out'): ?> bg-orange-100 text-orange-700
                    <?php else: ?> bg-blue-100 text-blue-700
                    <?php endif; ?>
                ">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($statusVal === 'completed_with_warnings'): ?> مكتملة بتحذيرات
                    <?php elseif($statusVal === 'phase3_completed'): ?> مكتملة
                    <?php elseif($statusVal === 'phase1_pending' || $statusVal === 'phase1_processing'): ?> جاري التحليل...
                    <?php elseif($statusVal === 'phase2_pending' || $statusVal === 'phase2_processing'): ?> قيد التحليل (المرحلة ٢)
                    <?php elseif($statusVal === 'awaiting_laws'): ?> بانتظار الموافقة
                    <?php elseif($statusVal === 'phase2_completed'): ?> المرحلة ٢ مكتملة
                    <?php elseif($statusVal === 'phase3_pending' || $statusVal === 'phase3_processing'): ?> قيد التحكيم (المرحلة ٣)
                    <?php elseif($statusVal === 'failed'): ?> فشل
                    <?php elseif($statusVal === 'paused'): ?> متوقف
                    <?php elseif($statusVal === 'halted'): ?> توقف المعالجة
                    <?php elseif($statusVal === 'timed_out'): ?> انتهت المهلة
                    <?php else: ?> جديدة
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </span>
            </div>
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($case->model_used ?? null): ?>
                <p class="text-sm text-slate-500 mt-1">النموذج: <?php echo e($case->model_used); ?></p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($statusVal, ['phase1_pending', 'phase1_processing', 'phase2_pending', 'phase2_processing', 'phase3_pending', 'phase3_processing'], true)): ?>
                <div class="mt-4 p-4 rounded-xl bg-amber-50 border border-amber-200 flex items-center gap-3">
                    <span class="material-symbols-outlined text-amber-600 animate-pulse">progress_activity</span>
                    <div>
                        <p class="font-semibold text-amber-800">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($statusVal, ['phase3_pending', 'phase3_processing'])): ?>
                                جاري التحكيم القضائي (المرحلة الثالثة)
                            <?php else: ?>
                                جاري تحليل القضية
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                        </p>
                        <p class="text-sm text-amber-700">يتم عرض المخرجات بشكل مباشر أدناه.</p>
                    </div>
                </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

            <?php echo $__env->make('pages.cases.show-retry-section', ['case' => $case, 'statusVal' => $statusVal], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

            <div class="prose prose-slate max-w-none">
                <h4 class="font-bold text-slate-900">وصف القضية</h4>
                <p class="text-slate-600"><?php echo e($case->intake_text ?? 'لا يوجد وصف متاح'); ?></p>
            </div>
        </div>
        
        
        <?php echo $__env->make('components.phase2-approval-modal', ['case' => $case], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

        

        
        <?php echo $__env->make('components.agent-timeline-live', ['case' => $case, 'statusVal' => $statusVal], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        
        
        

        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($case->status->value ?? $case->status, ['phase2_completed', 'phase3_completed', 'completed_with_warnings'], true)): ?>
            <?php echo $__env->make('components.case-insights', ['case' => $case], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($case->requiredLaws && $case->requiredLaws->count()): ?>
        <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
            <h3 class="font-bold mb-2 flex items-center gap-2">
                <span class="material-symbols-outlined text-primary">gavel</span>
                الأنظمة المطلوبة للتحليل
            </h3>
            <p class="text-sm text-slate-500 mb-3">التحليل يستخدم مكتبة الأنظمة والقوانين (الأنظمة المعرّفة في النظام)، وليس مرفقات خاصة بهذه القضية.</p>
            <ul class="flex flex-wrap gap-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $case->requiredLaws; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $rl): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                    <li class="px-3 py-1.5 bg-slate-100 rounded-lg text-sm font-medium text-slate-700"><?php echo e($rl->law_name); ?></li>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
            </ul>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

        
        <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-bold flex items-center gap-2">
                    <span class="material-symbols-outlined text-primary">description</span>
                    المستندات المرفقة
                </h3>
                <a href="<?php echo e(route('documents.index', ['case_id' => $case->id])); ?>" class="text-primary text-sm font-bold flex items-center gap-1 hover:underline">
                    <span class="material-symbols-outlined text-sm">folder_open</span>
                    عرض في المستندات
                </a>
            </div>
            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($case->documents && $case->documents->count()): ?>
                <div class="space-y-2">
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::openLoop(); ?><?php endif; ?><?php $__currentLoopData = $case->documents; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $doc): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::startLoop($loop->index); ?><?php endif; ?>
                        <div class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl">
                            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($doc->isPdf()): ?>
                                <span class="material-symbols-outlined text-red-500">picture_as_pdf</span>
                            <?php elseif($doc->isImage()): ?>
                                <span class="material-symbols-outlined text-emerald-600">image</span>
                            <?php else: ?>
                                <span class="material-symbols-outlined text-slate-500">description</span>
                            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            <span class="text-sm font-semibold flex-1 truncate"><?php echo e($doc->filename); ?></span>
                            <a href="<?php echo e(route('documents.preview', $doc)); ?>" target="_blank" rel="noopener" class="text-primary" title="معاينة">
                                <span class="material-symbols-outlined">visibility</span>
                            </a>
                            <a href="<?php echo e(route('documents.download', $doc)); ?>" class="text-primary" title="تحميل">
                                <span class="material-symbols-outlined">download</span>
                            </a>
                        </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::endLoop(); ?><?php endif; ?><?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php \Livewire\Features\SupportCompiledWireKeys\SupportCompiledWireKeys::closeLoop(); ?><?php endif; ?>
                </div>
            <?php else: ?>
                <p class="text-center text-slate-400 py-8">لا توجد مستندات مرفقة. يمكنك رفع مرفقات من صفحة المستندات.</p>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        </div>
    </div>
    
    
    <div class="space-y-6">
        
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($statusVal, ['failed', 'paused', 'halted', 'timed_out'], true)): ?>
        <?php
            $haltedAtAgent = $case->halted_at_agent ?? $case->current_agent ?? null;
            $canResume = $haltedAtAgent && $haltedAtAgent > 1;
        ?>
        <div class="rounded-xl shadow-lg overflow-hidden border border-red-200">
            
            <div class="bg-gradient-to-br from-red-500 to-red-600 p-5 text-white">
                <div class="flex items-center gap-2 mb-1">
                    <span class="material-symbols-outlined text-2xl">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array($statusVal, ['halted', 'timed_out'])): ?> stop_circle <?php else: ?> error <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </span>
                    <span class="font-bold text-base">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($statusVal === 'halted'): ?> توقف عند الوكيل <?php echo e($haltedAtAgent); ?>

                        <?php elseif($statusVal === 'timed_out'): ?> انتهت مهلة المعالجة
                        <?php else: ?> القضية فشلت
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </span>
                </div>
                <p class="text-xs opacity-80">المخرجات السابقة محفوظة</p>
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($case->last_error_message): ?>
                <p class="text-xs mt-1 bg-white/20 rounded px-2 py-1"><?php echo e($case->last_error_message); ?></p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
            </div>

            
            <div class="bg-white p-4 space-y-2">
                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($canResume): ?>
                
                <form action="<?php echo e(route('cases.resume', $case)); ?>" method="POST" class="puter-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="puter_token" class="puter-token-input" value="">
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-2 bg-emerald-600 text-white font-bold py-3 rounded-xl hover:bg-emerald-700 active:scale-95 transition-all shadow-md">
                        <span class="material-symbols-outlined">play_arrow</span>
                        <span>استئناف من الوكيل <?php echo e($haltedAtAgent); ?></span>
                    </button>
                </form>
                <p class="text-xs text-slate-500 text-center px-1">يستأنف من حيث توقف — مخرجات الوكلاء السابقين محفوظة</p>
                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

                
                <form action="<?php echo e(route('cases.retry-agent', $case)); ?>" method="POST" class="puter-form">
                    <?php echo csrf_field(); ?>
                    <input type="hidden" name="puter_token" class="puter-token-input" value="">
                    <button type="submit"
                            class="w-full flex items-center justify-center gap-2 <?php echo e($canResume ? 'bg-slate-100 text-slate-600 hover:bg-slate-200 text-sm' : 'bg-white border-2 border-red-500 text-red-600 hover:bg-red-50 font-bold'); ?> py-2.5 rounded-xl active:scale-95 transition-all">
                        <span class="material-symbols-outlined <?php echo e($canResume ? 'text-sm' : ''); ?>">refresh</span>
                        <span>إعادة من البداية</span>
                    </button>
                </form>
            </div>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        
        
        <div class="bg-white p-6 rounded-xl border border-primary/10 shadow-sm">
            <h3 class="font-bold mb-4">إجراءات سريعة</h3>
            <div class="space-y-2">
                <?php echo $__env->make('components.pdf-export-button', ['case' => $case], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>

                
                <button onclick="openModelConfig()"
                        class="w-full flex items-center gap-3 p-3 bg-slate-50 rounded-xl hover:bg-primary/10 transition-colors cursor-pointer group">
                    <span class="material-symbols-outlined text-primary">memory</span>
                    <div class="flex-1 text-right">
                        <span class="text-sm font-semibold block">إعداد نماذج الوكلاء</span>
                        <span class="text-xs text-slate-400"><?php echo e($case->model_used ?? config('openrouter.default_model')); ?></span>
                    </div>
                    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(!empty($case->agent_model_overrides)): ?>
                        <span class="text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-md font-bold flex-shrink-0">
                            <?php echo e(count($case->agent_model_overrides)); ?> مخصص
                        </span>
                    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                </button>

                <a href="<?php echo e(route('cases.timeline', $case)); ?>" class="flex items-center gap-3 p-3 bg-slate-50 rounded-xl hover:bg-primary/10 transition-colors">
                    <span class="material-symbols-outlined text-primary">timeline</span>
                    <span class="text-sm font-semibold">عرض الجدول الزمني</span>
                </a>
                <button class="w-full flex items-center gap-3 p-3 bg-slate-50 rounded-xl hover:bg-primary/10 transition-colors">
                    <span class="material-symbols-outlined text-primary">edit</span>
                    <span class="text-sm font-semibold">تعديل القضية</span>
                </button>
                <button class="w-full flex items-center gap-3 p-3 bg-red-50 rounded-xl hover:bg-red-100 transition-colors text-red-600">
                    <span class="material-symbols-outlined">delete</span>
                    <span class="text-sm font-semibold">حذف القضية</span>
                </button>
            </div>
        </div>
        
        
        <?php
            $aiRecommendation = null;
            // Try QA summary first (agent 9)
            $qaOutput = $case->outputs->where('agent_number', 9)->whereIn('content_type', ['markdown', 'md'])->sortByDesc('id')->first();
            if ($qaOutput && !empty(trim($qaOutput->content ?? ''))) {
                $aiRecommendation = \Illuminate\Support\Str::limit(strip_tags($qaOutput->content), 200);
            }
            // Fallback to lead counsel plan (agent 1)
            if (!$aiRecommendation) {
                $leadOutput = $case->outputs->where('agent_number', 1)->where('content_type', 'markdown')->first();
                if ($leadOutput && !empty(trim($leadOutput->content ?? ''))) {
                    $aiRecommendation = \Illuminate\Support\Str::limit(strip_tags($leadOutput->content), 200);
                }
            }
            // Final fallback: case analysis (agent 0)
            if (!$aiRecommendation) {
                $analysisOutput = $case->outputs->where('agent_number', 0)->where('content_type', 'markdown')->first();
                if ($analysisOutput && !empty(trim($analysisOutput->content ?? ''))) {
                    $aiRecommendation = \Illuminate\Support\Str::limit(strip_tags($analysisOutput->content), 200);
                }
            }
        ?>
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($aiRecommendation): ?>
        <div class="bg-gradient-to-br from-primary to-emerald-800 p-6 rounded-xl text-white shadow-lg">
            <div class="flex items-center gap-2 mb-3">
                <span class="material-symbols-outlined">auto_awesome</span>
                <span class="font-bold">توصيات الذكاء الاصطناعي</span>
            </div>
            <p class="text-sm opacity-90 leading-relaxed"><?php echo e($aiRecommendation); ?></p>
        </div>
        <?php elseif(in_array($statusVal, ['phase1_pending','phase1_processing','phase2_pending','phase2_processing','phase3_pending','phase3_processing'])): ?>
        <div class="bg-gradient-to-br from-slate-600 to-slate-700 p-6 rounded-xl text-white shadow-lg">
            <div class="flex items-center gap-2 mb-3">
                <span class="material-symbols-outlined animate-pulse">auto_awesome</span>
                <span class="font-bold">توصيات الذكاء الاصطناعي</span>
            </div>
            <p class="text-sm opacity-90">جارٍ تحليل القضية... ستظهر التوصيات بعد اكتمال المعالجة.</p>
        </div>
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
    </div>
</div>


<?php echo $__env->make('components.agent-model-config', ['case' => $case], array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>


<?php $__env->startPush('scripts'); ?>
<script>
// Inject Puter token into retry/resume form submits
document.querySelectorAll('form.puter-form').forEach(function(form) {
    form.addEventListener('submit', function() {
        try {
            if (typeof puter !== 'undefined' && puter.authToken) {
                form.querySelector('.puter-token-input').value = puter.authToken;
            }
        } catch(e) {}
    });
});
</script>
<?php $__env->stopPush(); ?>
<?php $__env->stopSection(); ?>

<?php echo $__env->make('layouts.app', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?><?php /**PATH /var/www/html/resources/views/pages/cases/show.blade.php ENDPATH**/ ?>