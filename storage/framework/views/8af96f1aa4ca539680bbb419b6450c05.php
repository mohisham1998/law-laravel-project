<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">
    <title><?php echo $__env->yieldContent('title', 'المستشار القانوني الذكي'); ?></title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <?php echo $__env->yieldPushContent('styles'); ?>
    <script>
        tailwind.config = {
            darkMode: "class",
            theme: {
                extend: {
                    colors: {
                        "primary": "#006b34",
                        "secondary-green": "#2FAF74",
                        "background-light": "#f5f8f7",
                        "background-dark": "#0f2319",
                        "border-gray": "#E5E7EB",
                    },
                    fontFamily: {
                        "display": ["Cairo", "sans-serif"]
                    },
                    borderRadius: {
                        "DEFAULT": "0.5rem",
                        "lg": "1rem",
                        "xl": "1.5rem",
                        "full": "9999px"
                    },
                },
            },
        }
    </script>
    <style>
        body {
            font-family: 'Cairo', sans-serif;
            background-color: #f5f8f7;
        }
        /* RTL: dropdown arrow on the left (start) side only – no native right-side arrow */
        select.appearance-none {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
        }
        .notion-card {
            background: white;
            border: 1px solid rgba(0, 107, 52, 0.1);
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
        }
        .magnetic-element {
            transition: transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        .magnetic-element:hover {
            transform: scale(1.02) translateY(-2px);
        }
    </style>
</head>
<body class="bg-background-light text-slate-900 font-display">
    <div>
        
        <aside class="fixed top-0 right-0 w-72 h-screen bg-white border-l border-primary/10 flex flex-col overflow-y-auto z-30">
            <div class="p-6 flex items-center gap-3">
                <div class="w-10 h-10 bg-primary rounded-lg flex items-center justify-center text-white">
                    <span class="material-symbols-outlined">gavel</span>
                </div>
                <div class="flex flex-col">
                    <h1 class="text-primary text-lg font-bold leading-none">المستشار القانوني</h1>
                    <p class="text-slate-500 text-xs mt-1">نظام الذكاء الاصطناعي</p>
                </div>
            </div>
            
            <nav class="flex-1 px-4 space-y-2 mt-4">
                <a class="flex items-center gap-3 px-4 py-3 rounded-xl <?php echo e(request()->routeIs('dashboard') ? 'bg-primary/10 text-primary font-bold' : 'text-slate-600 hover:bg-slate-50'); ?> transition-all" href="<?php echo e(route('dashboard')); ?>">
                    <span class="material-symbols-outlined <?php echo e(request()->routeIs('dashboard') ? 'fill-1' : ''); ?>">dashboard</span>
                    <span>لوحة التحكم</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-xl <?php echo e(request()->routeIs('cases.*') ? 'bg-primary/10 text-primary font-bold' : 'text-slate-600 hover:bg-slate-50'); ?> transition-colors" href="<?php echo e(route('cases.index')); ?>">
                    <span class="material-symbols-outlined">work</span>
                    <span>القضايا</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-xl <?php echo e(request()->routeIs('ai-analysis') ? 'bg-primary/10 text-primary font-bold' : 'text-slate-600 hover:bg-slate-50'); ?> transition-colors" href="<?php echo e(route('ai-analysis')); ?>">
                    <span class="material-symbols-outlined">psychology</span>
                    <span>تحليل الذكاء الاصطناعي</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-xl <?php echo e(request()->routeIs('documents.*') ? 'bg-primary/10 text-primary font-bold' : 'text-slate-600 hover:bg-slate-50'); ?> transition-colors" href="<?php echo e(route('documents.index')); ?>">
                    <span class="material-symbols-outlined">description</span>
                    <span>المستندات</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-xl <?php echo e(request()->routeIs('laws.*') ? 'bg-primary/10 text-primary font-bold' : 'text-slate-600 hover:bg-slate-50'); ?> transition-colors" href="<?php echo e(route('laws.index')); ?>">
                    <span class="material-symbols-outlined">gavel</span>
                    <span>الأنظمة والقوانين</span>
                </a>
            </nav>
            
            <div class="p-4 border-t border-primary/5">
                <a class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-600 hover:bg-slate-50 transition-colors" href="<?php echo e(route('settings')); ?>">
                    <span class="material-symbols-outlined">settings</span>
                    <span>الإعدادات</span>
                </a>
                <div class="mt-4 p-4 bg-primary/5 rounded-xl">
                    <p class="text-xs text-slate-500 mb-2">مساحة التخزين</p>
                    <div class="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden">
                        <div class="bg-primary h-full" style="width: <?php echo e($sidebarStoragePercent ?? 0); ?>%"></div>
                    </div>
                    <p class="text-[10px] text-primary mt-2 font-bold">تم استخدام <?php echo e($sidebarStorageGB ?? 0); ?> جيجابايت من <?php echo e($sidebarStorageCapacityGB ?? 10); ?></p>
                </div>
                <p class="text-[9px] text-slate-400 mt-2 text-center" id="ui-version">UI v2.0.0</p>
            </div>
        </aside>
        
        
        <main class="flex flex-col min-h-screen" style="margin-right: 18rem; max-width: calc(100vw - 18rem); overflow-x: hidden;">
            
            <header class="h-20 bg-white border-b border-primary/5 px-6 sm:px-10 lg:px-12 flex items-center justify-between gap-6 shrink-0">
                <div class="flex-1 min-w-0 max-w-md relative" x-data="globalSearch()" @click.outside="close()">
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors">search</span>
                        <input
                            id="global-search-input"
                            x-model="query"
                            @input.debounce.300ms="search()"
                            @focus="open = true"
                            @keydown.escape="close()"
                            @keydown.arrow-down.prevent="navigate(1)"
                            @keydown.arrow-up.prevent="navigate(-1)"
                            @keydown.enter.prevent="selectHighlighted()"
                            class="w-full bg-slate-100 border-none rounded-xl pr-10 pl-4 py-2.5 text-sm focus:ring-2 focus:ring-primary/20 focus:bg-white transition-all"
                            placeholder="ابحث عن قضايا، مستندات، أو قوانين..."
                            type="text"
                            autocomplete="off"
                        />
                        <span x-show="loading" class="absolute left-3 top-1/2 -translate-y-1/2">
                            <span class="w-3.5 h-3.5 border-2 border-primary border-t-transparent rounded-full animate-spin inline-block"></span>
                        </span>
                    </div>

                    
                    <div
                        x-show="open && (results.length > 0 || (query.length >= 2 && !loading))"
                        x-transition:enter="transition ease-out duration-150"
                        x-transition:enter-start="opacity-0 -translate-y-1"
                        x-transition:enter-end="opacity-100 translate-y-0"
                        x-transition:leave="transition ease-in duration-100"
                        x-transition:leave-start="opacity-100"
                        x-transition:leave-end="opacity-0"
                        class="absolute top-full mt-2 w-full bg-white rounded-2xl shadow-xl border border-slate-200 z-50 overflow-hidden max-h-96 overflow-y-auto"
                    >
                        <template x-if="results.length === 0 && query.length >= 2 && !loading">
                            <div class="px-4 py-8 text-center text-slate-500 text-sm">
                                <span class="material-symbols-outlined text-3xl text-slate-300 block mb-2">search_off</span>
                                لا توجد نتائج لـ "<span x-text="query"></span>"
                            </div>
                        </template>

                        <template x-for="(result, idx) in results" :key="result.type + result.id">
                            <a
                                :href="result.url"
                                class="flex items-center gap-3 px-4 py-3 hover:bg-slate-50 transition-colors border-b border-slate-100 last:border-0 cursor-pointer"
                                :class="idx === highlighted ? 'bg-primary/5' : ''"
                                @mouseenter="highlighted = idx"
                            >
                                <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0"
                                     :class="{
                                         'bg-primary/10 text-primary': result.type === 'case',
                                         'bg-blue-50 text-blue-600': result.type === 'document',
                                         'bg-emerald-50 text-emerald-600': result.type === 'law',
                                     }">
                                    <span class="material-symbols-outlined text-base" x-text="result.icon"></span>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-semibold text-slate-900 truncate" x-text="result.title"></p>
                                    <p class="text-xs text-slate-500 truncate" x-text="result.subtitle"></p>
                                </div>
                                <span class="text-[10px] font-bold px-2 py-0.5 rounded-full shrink-0"
                                      :class="{
                                          'bg-primary/10 text-primary': result.type === 'case',
                                          'bg-blue-50 text-blue-600': result.type === 'document',
                                          'bg-emerald-50 text-emerald-600': result.type === 'law',
                                      }"
                                      x-text="result.type_label">
                                </span>
                            </a>
                        </template>
                    </div>
                </div>
                <div class="flex items-center gap-3 sm:gap-4 shrink-0">
                    <div x-data="notificationCenter()" x-init="init()" class="relative shrink-0" @click.outside="panelOpen = false">
                        <button
                            @click="panelOpen = !panelOpen; if(panelOpen) markRead()"
                            class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600 relative magnetic-element hover:bg-slate-200 transition-colors"
                            :class="enabled ? (panelOpen ? 'bg-slate-200' : '') : 'opacity-60 cursor-not-allowed'"
                            :disabled="!enabled"
                        >
                            <span class="material-symbols-outlined" :class="unreadCount > 0 ? 'fill-1' : ''">notifications</span>
                            <span
                                x-show="unreadCount > 0"
                                x-transition
                                class="absolute -top-0.5 -right-0.5 min-w-[18px] h-[18px] bg-red-500 rounded-full border-2 border-white text-white text-[9px] font-bold flex items-center justify-center px-1"
                                x-text="unreadCount > 9 ? '9+' : unreadCount"
                            ></span>
                        </button>

                        
                        <div
                            x-show="panelOpen"
                            x-transition:enter="transition ease-out duration-200"
                            x-transition:enter-start="opacity-0 scale-95 translate-y-1"
                            x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                            x-transition:leave="transition ease-in duration-150"
                            x-transition:leave-start="opacity-100"
                            x-transition:leave-end="opacity-0"
                            class="absolute top-12 left-0 w-80 bg-white rounded-2xl shadow-xl border border-slate-200 z-50 overflow-hidden"
                            @click.outside="panelOpen = false"
                        >
                            <div class="px-4 py-3 border-b border-slate-100 flex items-center justify-between">
                                <h4 class="font-bold text-sm text-slate-900">الإشعارات</h4>
                                <button @click="clearAll()" x-show="notifications.length > 0" class="text-xs text-slate-400 hover:text-slate-600 transition-colors">مسح الكل</button>
                            </div>

                            <div class="max-h-80 overflow-y-auto">
                                <template x-if="notifications.length === 0">
                                    <div class="px-4 py-10 text-center text-slate-400">
                                        <span class="material-symbols-outlined text-3xl block mb-2 text-slate-300">notifications_off</span>
                                        <p class="text-sm" x-text="enabled ? 'لا توجد إشعارات' : 'الإشعارات معطلة من الإعدادات'"></p>
                                    </div>
                                </template>

                                <template x-for="(n, idx) in notifications" :key="idx">
                                    <a
                                        :href="n.url"
                                        class="flex items-start gap-3 px-4 py-3 hover:bg-slate-50 transition-colors border-b border-slate-50 last:border-0 cursor-pointer"
                                        :class="!n.read ? 'bg-primary/3' : ''"
                                    >
                                        <div class="w-8 h-8 rounded-lg flex items-center justify-center shrink-0 mt-0.5"
                                             :class="{
                                                 'bg-emerald-100 text-emerald-600': n.type === 'agent.completed',
                                                 'bg-red-100 text-red-600': ['agent.failed','pipeline.halted','pipeline.paused'].includes(n.type),
                                                 'bg-blue-100 text-blue-600': n.type === 'agent.started',
                                                 'bg-amber-100 text-amber-700': ['pipeline.timeout_warning','agent.low_confidence'].includes(n.type),
                                                 'bg-indigo-100 text-indigo-600': ['rag.processing.parsed','rag.processing.completed','bulk.action.completed'].includes(n.type),
                                                 'bg-primary/10 text-primary': n.type === 'case.status_changed',
                                             }">
                                            <span class="material-symbols-outlined text-base"
                                                  :class="{
                                                      'fill-1': !n.read
                                                  }"
                                                  x-text="n.icon">
                                            </span>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <p class="text-xs font-semibold text-slate-900 leading-tight truncate" x-text="n.title"></p>
                                            <p class="text-[11px] text-slate-500 mt-0.5 line-clamp-2" x-text="n.body"></p>
                                            <p class="text-[10px] text-slate-400 mt-1" x-text="n.time"></p>
                                        </div>
                                        <span x-show="!n.read" class="w-2 h-2 bg-primary rounded-full mt-2 shrink-0"></span>
                                    </a>
                                </template>
                            </div>

                            <div class="px-4 py-2.5 border-t border-slate-100 bg-slate-50">
                                <div class="flex items-center gap-2 text-[11px]"
                                     :class="sseConnected ? 'text-emerald-600' : 'text-slate-400'">
                                    <span class="w-2 h-2 rounded-full inline-block"
                                          :class="sseConnected ? 'bg-emerald-500 animate-pulse' : 'bg-slate-400'">
                                    </span>
                                    <span x-text="sseConnected ? 'متصل - يتم المراقبة الآن' : 'غير متصل'"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="h-8 w-[1px] bg-slate-200 shrink-0 hidden sm:block"></div>
                    <div class="flex items-center gap-2 sm:gap-3 shrink-0 min-w-0">
                        <div class="text-left min-w-0 hidden sm:block">
                            <p class="text-sm font-bold text-slate-900 leading-none truncate"><?php echo e(auth()->user()->name ?? 'المستخدم'); ?></p>
                            <p class="text-[11px] text-slate-500 mt-1"><?php echo e(auth()->user()->role ?? 'محامي'); ?></p>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-primary/10 overflow-hidden border-2 border-primary/20 flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-primary">person</span>
                        </div>
                        <form method="POST" action="<?php echo e(route('logout')); ?>" class="inline">
                            <?php echo csrf_field(); ?>
                            <button type="submit" class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600 hover:bg-red-50 hover:text-red-600 transition-colors">
                                <span class="material-symbols-outlined">logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </header>
            
            
            <div class="p-6 sm:p-8 flex-1 overflow-visible min-w-0">
                <?php echo $__env->yieldContent('content'); ?>
            </div>
            
            
            <footer class="mt-auto p-8 border-t border-primary/5 text-slate-400 text-xs text-center">
                © <?php echo e(date('Y')); ?> المستشار القانوني الذكي. جميع الحقوق محفوظة. تعمل بواسطة تقنيات الذكاء الاصطناعي المتطورة.
            </footer>
        </main>
    </div>
    
    
    <div id="toast-container" class="fixed bottom-6 left-6 right-6 sm:left-auto sm:right-auto sm:max-w-sm z-[100] flex flex-col gap-2 pointer-events-none" aria-live="polite"></div>
    
    <script>
    (function() {
        function showToast(message, type) {
            type = type || 'success';
            var container = document.getElementById('toast-container');
            if (!container) return;
            var id = 'toast-' + Date.now();
            var bg = type === 'success' ? 'bg-primary' : 'bg-red-600';
            var icon = type === 'success' ? 'check_circle' : 'error';
            var html = '<div id="' + id + '" class="pointer-events-auto flex items-center gap-3 ' + bg + ' text-white px-4 py-3 rounded-xl shadow-lg animate-toast-in" role="alert">' +
                '<span class="material-symbols-outlined">' + icon + '</span>' +
                '<span class="flex-1 text-sm font-medium">' + (message || '') + '</span>' +
                '</div>';
            container.insertAdjacentHTML('beforeend', html);
            var el = document.getElementById(id);
            setTimeout(function() {
                if (el) {
                    el.classList.add('animate-toast-out');
                    setTimeout(function() { if (el && el.parentNode) el.parentNode.removeChild(el); }, 300);
                }
            }, 4000);
        }
        window.showToast = showToast;
        <?php if(session('success')): ?>
        document.addEventListener('DOMContentLoaded', function() { showToast(<?php echo \Illuminate\Support\Js::from(session('success')); ?>, 'success'); });
        <?php endif; ?>
        <?php if(session('error')): ?>
        document.addEventListener('DOMContentLoaded', function() { showToast(<?php echo \Illuminate\Support\Js::from(session('error')); ?>, 'error'); });
        <?php endif; ?>
        <?php if($errors->any()): ?>
        document.addEventListener('DOMContentLoaded', function() { showToast(<?php echo \Illuminate\Support\Js::from($errors->first()); ?>, 'error'); });
        <?php endif; ?>
    })();
    </script>
    <style>
    @keyframes toast-in {
        from { opacity: 0; transform: translateY(1rem); }
        to { opacity: 1; transform: translateY(0); }
    }
    @keyframes toast-out {
        from { opacity: 1; transform: translateY(0); }
        to { opacity: 0; transform: translateY(-0.5rem); }
    }
    .animate-toast-in { animation: toast-in 0.3s ease-out forwards; }
    .animate-toast-out { animation: toast-out 0.3s ease-in forwards; }
    [x-cloak] { display: none !important; }
    </style>
    
    <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(auth()->guard()->check()): ?>
    
    <script>
    (function() {
        var SESSION_KEY = 'or_balance_checked';
        var BALANCE_KEY = 'or_balance_data';

        function applyBalanceToWidget(data) {
            // Update the balance widget in settings page if it's mounted
            var widget = document.getElementById('orBalanceWidget');
            if (!widget) return;
            var remaining = data.remaining != null ? parseFloat(data.remaining) : null;
            var isDepleted = data.is_depleted;
            var isLow = data.is_low;
            var remainingSAR = data.remaining_sar != null ? parseFloat(data.remaining_sar).toFixed(2) : '—';
            var remainingUSD = remaining != null ? Math.max(0, remaining).toFixed(2) : '—';
            var totalSAR = data.total_credits_sar != null ? parseFloat(data.total_credits_sar).toFixed(2) : '—';
            var usageSAR = data.usage_sar != null ? parseFloat(data.usage_sar).toFixed(2) : '—';

            var color = isDepleted ? 'text-red-600' : (isLow ? 'text-amber-600' : 'text-emerald-600');
            var bgColor = isDepleted ? 'bg-red-50 border-red-200' : (isLow ? 'bg-amber-50 border-amber-200' : 'bg-emerald-50 border-emerald-200');
            var icon = isDepleted ? 'money_off' : (isLow ? 'warning' : 'check_circle');

            widget.className = 'mt-4 p-4 rounded-xl border text-sm ' + bgColor;
            widget.innerHTML =
                '<div class="flex items-center justify-between mb-2">' +
                    '<span class="font-bold flex items-center gap-1.5 ' + color + '">' +
                        '<span class="material-symbols-outlined text-base">' + icon + '</span>' +
                        (isDepleted ? 'نفد الرصيد' : (isLow ? 'رصيد منخفض' : 'رصيد كافٍ')) +
                    '</span>' +
                    '<span class="text-xs text-slate-400">تحديث تلقائي كل 5 دقائق</span>' +
                '</div>' +
                '<div class="grid grid-cols-2 gap-2 text-xs mt-1">' +
                    '<div class="bg-white/70 rounded-lg p-2 text-center">' +
                        '<p class="text-slate-500 mb-0.5">الرصيد المتبقي</p>' +
                        '<p class="font-bold text-base ' + color + '">' + remainingSAR + ' ر.س</p>' +
                        '<p class="text-slate-400">(' + remainingUSD + ' USD)</p>' +
                    '</div>' +
                    '<div class="bg-white/70 rounded-lg p-2 text-center">' +
                        '<p class="text-slate-500 mb-0.5">الاستخدام الإجمالي</p>' +
                        '<p class="font-bold text-base text-slate-700">' + usageSAR + ' ر.س</p>' +
                        '<p class="text-slate-400">من ' + totalSAR + ' ر.س</p>' +
                    '</div>' +
                '</div>' +
                (isDepleted ? '<p class="mt-2 text-xs text-red-600 font-medium">أضف رصيداً من <a href="https://openrouter.ai/credits" target="_blank" class="underline">openrouter.ai/credits</a> لتشغيل القضايا.</p>' : '');
        }

        function checkBalance(force) {
            // Use cached result in sessionStorage unless forced
            if (!force) {
                var cached = sessionStorage.getItem(BALANCE_KEY);
                if (cached) {
                    try {
                        var d = JSON.parse(cached);
                        applyBalanceToWidget(d);
                        return;
                    } catch(e) {}
                }
            }

            fetch('<?php echo e(route('settings.openrouter-status')); ?>', {
                headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
            })
            .then(function(r) { return r.json(); })
            .then(function(data) {
                if (!data.ok) return;
                sessionStorage.setItem(BALANCE_KEY, JSON.stringify(data));
                applyBalanceToWidget(data);

                // Show toast once per browser session
                if (!sessionStorage.getItem(SESSION_KEY)) {
                    sessionStorage.setItem(SESSION_KEY, '1');
                    if (data.is_depleted) {
                        showToast('نفد رصيد OpenRouter — لن تتمكن من تشغيل قضايا جديدة.', 'error');
                    } else if (data.is_low) {
                        showToast('تحذير: رصيد OpenRouter منخفض (أقل من $1). يُنصح بإعادة الشحن.', 'error');
                    }
                }
            })
            .catch(function() {});
        }

        // Expose globally so settings page can trigger a forced refresh regardless of stored provider
        window.refreshOpenRouterBalance = function() {
            sessionStorage.removeItem(BALANCE_KEY);
            sessionStorage.removeItem(SESSION_KEY);
            checkBalance(true);
        };
    })();
    </script>
    <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

    
    <script>
    function globalSearch() {
        return {
            query: '',
            results: [],
            loading: false,
            open: false,
            highlighted: -1,
            _timer: null,

            async search() {
                this.highlighted = -1;
                if (this.query.length < 2) {
                    this.results = [];
                    this.open = false;
                    return;
                }
                this.loading = true;
                this.open = true;
                try {
                    const r = await fetch('/search?q=' + encodeURIComponent(this.query), {
                        headers: { 'Accept': 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
                    });
                    const d = await r.json();
                    this.results = d.results ?? [];
                } catch(e) {
                    this.results = [];
                } finally {
                    this.loading = false;
                }
            },

            close() {
                this.open = false;
                this.highlighted = -1;
            },

            navigate(dir) {
                if (!this.open || this.results.length === 0) return;
                this.highlighted = Math.max(0, Math.min(this.results.length - 1, this.highlighted + dir));
            },

            selectHighlighted() {
                if (this.highlighted >= 0 && this.results[this.highlighted]) {
                    window.location.href = this.results[this.highlighted].url;
                }
            },
        };
    }

    // ── Notification Center ──────────────────────────────────────────────
    function notificationCenter() {
        return {
            notifications: [],
            panelOpen: false,
            sseConnected: false,
            enabled: <?php echo json_encode((bool) (auth()->user()->notifications_enabled ?? true), 15, 512) ?>,
            _es: null,
            _retryTimer: null,
            MAX_NOTIFICATIONS: 30,

            get unreadCount() {
                return this.notifications.filter(n => !n.read).length;
            },

            _suppressToasts: true,

            init() {
                if (!this.enabled) {
                    return;
                }
                this.connect();
            },

            connect() {
                if (!this.enabled) return;
                if (this._es) { this._es.close(); }
                this._suppressToasts = true;
                this._es = new EventSource('<?php echo e(route("notifications.stream")); ?>');
                this._es.onopen = () => { this.sseConnected = true; };
                this._es.onmessage = (e) => {
                    try {
                        const event = JSON.parse(e.data);
                        this.handleEvent(event);
                    } catch (_) {}
                };
                this._es.onerror = () => {
                    this.sseConnected = false;
                    this._es.close();
                    this._retryTimer = setTimeout(() => this.connect(), 5000);
                };
            },

            handleEvent(event) {
                if (event.event_type === 'notifications.connected') {
                    if (typeof event.notifications_enabled === 'boolean') {
                        this.enabled = event.notifications_enabled;
                    }
                    this.sseConnected = true;
                    // Allow 2s for historical events to flow in silently, then enable toasts
                    setTimeout(() => { this._suppressToasts = false; }, 2000);
                    return;
                }
                if (event.event_type !== 'notification') return;

                const type = event.notification_type;
                const agentName = event.agent_name;
                const caseTitle = event.case_title || 'القضية';

                let title = '';
                let body = '';
                let icon = 'notifications';

                if (type === 'agent.completed') {
                    title = 'اكتمل الوكيل: ' + (agentName || '#' + event.agent_number);
                    body = 'في قضية: ' + caseTitle;
                    icon = 'check_circle';
                } else if (type === 'agent.started') {
                    title = 'بدأ الوكيل: ' + (agentName || '#' + event.agent_number);
                    body = 'في قضية: ' + caseTitle;
                    icon = 'play_circle';
                } else if (type === 'agent.failed') {
                    title = 'فشل الوكيل: ' + (agentName || '#' + event.agent_number);
                    body = (event.error || '') + ' | ' + caseTitle;
                    icon = 'error';
                } else if (type === 'agent.low_confidence') {
                    title = 'انخفاض مستوى الثقة';
                    body = caseTitle;
                    icon = 'warning';
                } else if (type === 'pipeline.timeout_warning') {
                    title = 'تحذير قرب انتهاء المهلة';
                    body = 'المتبقي: ' + (event.remaining_minutes ?? '?') + ' دقيقة | ' + caseTitle;
                    icon = 'schedule';
                } else if (type === 'pipeline.halted' || type === 'pipeline.paused') {
                    title = type === 'pipeline.halted' ? 'توقفت العملية' : 'تم إيقاف العملية مؤقتاً';
                    body = (event.error || '') + ' | ' + caseTitle;
                    icon = 'warning';
                } else if (type === 'rag.processing.parsed' || type === 'rag.processing.completed' || type === 'rag.processing.failed') {
                    title = event.title || 'تحديث ملف النظام';
                    body = event.body || '';
                    icon = type === 'rag.processing.failed' ? 'error' : 'fact_check';
                } else if (type === 'bulk.action.completed') {
                    title = event.title || 'اكتملت عملية مجمعة';
                    body = event.body || '';
                    icon = 'checklist';
                } else if (type === 'case.status_changed') {
                    const statusLabels = {
                        'phase3_completed': 'اكتملت القضية', 'completed_with_warnings': 'اكتملت مع تحذيرات',
                        'failed': 'فشلت القضية', 'phase2_completed': 'اكتمل التحليل القانوني',
                        'phase2_processing': 'بدأت المرحلة الثانية', 'phase3_pending': 'جاهزة للمرحلة الثالثة',
                        'paused': 'تم إيقاف القضية مؤقتاً', 'phase1_pending': 'أعيدت القضية إلى المرحلة الأولى',
                    };
                    const newStatus = event.new_status;
                    if (!statusLabels[newStatus]) return; // skip minor status changes
                    title = statusLabels[newStatus] || 'تغير حالة القضية';
                    body = caseTitle;
                    icon = newStatus === 'phase3_completed' ? 'celebration' : (newStatus === 'failed' ? 'error' : 'info');
                } else {
                    return;
                }

                const n = {
                    type, title, body, icon,
                    url: event.url || '#',
                    time: new Date().toLocaleTimeString('ar-SA', { hour: '2-digit', minute: '2-digit' }),
                    read: false,
                    id: Date.now(),
                };

                this.notifications.unshift(n);
                if (this.notifications.length > this.MAX_NOTIFICATIONS) {
                    this.notifications = this.notifications.slice(0, this.MAX_NOTIFICATIONS);
                }

                // Show toast only for live (non-historical) important notifications
                const isImportant = ['agent.failed', 'pipeline.halted', 'pipeline.paused', 'case.status_changed', 'rag.processing.failed'].includes(type);
                if (typeof window.showToast === 'function' && isImportant && !this._suppressToasts) {
                    const isError = ['agent.failed', 'pipeline.halted', 'pipeline.paused', 'rag.processing.failed'].includes(type);
                    const toastScope = caseTitle && caseTitle !== 'القضية' ? caseTitle : '';
                    window.showToast(toastScope ? (title + ' - ' + toastScope) : title, isError ? 'error' : 'success');
                }
            },

            markRead() {
                this.notifications = this.notifications.map(n => ({ ...n, read: true }));
            },

            clearAll() {
                this.notifications = [];
                this.panelOpen = false;
            },
        };
    }
    </script>

    <?php echo $__env->yieldPushContent('scripts'); ?>
</body>
</html>
<?php /**PATH /var/www/html/resources/views/layouts/app.blade.php ENDPATH**/ ?>