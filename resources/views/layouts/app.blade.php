<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'المستشار القانوني الذكي')</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    @stack('styles')
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
    <div class="flex h-screen overflow-hidden">
        {{-- Sidebar --}}
        <aside class="w-72 bg-white border-l border-primary/10 flex flex-col h-full">
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
                <a class="flex items-center gap-3 px-4 py-3 rounded-xl {{ request()->routeIs('dashboard') ? 'bg-primary/10 text-primary font-bold' : 'text-slate-600 hover:bg-slate-50' }} transition-all" href="{{ route('dashboard') }}">
                    <span class="material-symbols-outlined {{ request()->routeIs('dashboard') ? 'fill-1' : '' }}">dashboard</span>
                    <span>لوحة التحكم</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-xl {{ request()->routeIs('cases.*') ? 'bg-primary/10 text-primary font-bold' : 'text-slate-600 hover:bg-slate-50' }} transition-colors" href="{{ route('cases.index') }}">
                    <span class="material-symbols-outlined">work</span>
                    <span>القضايا</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-xl {{ request()->routeIs('ai-analysis') ? 'bg-primary/10 text-primary font-bold' : 'text-slate-600 hover:bg-slate-50' }} transition-colors" href="{{ route('ai-analysis') }}">
                    <span class="material-symbols-outlined">psychology</span>
                    <span>تحليل الذكاء الاصطناعي</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-xl {{ request()->routeIs('documents.*') ? 'bg-primary/10 text-primary font-bold' : 'text-slate-600 hover:bg-slate-50' }} transition-colors" href="{{ route('documents.index') }}">
                    <span class="material-symbols-outlined">description</span>
                    <span>المستندات</span>
                </a>
                <a class="flex items-center gap-3 px-4 py-3 rounded-xl {{ request()->routeIs('laws.*') ? 'bg-primary/10 text-primary font-bold' : 'text-slate-600 hover:bg-slate-50' }} transition-colors" href="{{ route('laws.index') }}">
                    <span class="material-symbols-outlined">gavel</span>
                    <span>الأنظمة والقوانين</span>
                </a>
            </nav>
            
            <div class="p-4 border-t border-primary/5">
                <a class="flex items-center gap-3 px-4 py-3 rounded-xl text-slate-600 hover:bg-slate-50 transition-colors" href="{{ route('settings') }}">
                    <span class="material-symbols-outlined">settings</span>
                    <span>الإعدادات</span>
                </a>
                <div class="mt-4 p-4 bg-primary/5 rounded-xl">
                    <p class="text-xs text-slate-500 mb-2">مساحة التخزين</p>
                    <div class="w-full bg-slate-200 h-1.5 rounded-full overflow-hidden">
                        <div class="bg-primary h-full w-[65%]"></div>
                    </div>
                    <p class="text-[10px] text-primary mt-2 font-bold">تم استخدام ٦.٥ جيجابايت من ١٠</p>
                </div>
            </div>
        </aside>
        
        {{-- Main Content --}}
        <main class="flex-1 flex flex-col overflow-y-auto">
            {{-- Header: extra padding/margins to avoid truncation --}}
            <header class="h-20 bg-white/80 backdrop-blur-md border-b border-primary/5 px-6 sm:px-10 lg:px-12 flex items-center justify-between gap-6 sticky top-0 z-10 shrink-0">
                <div class="flex-1 min-w-0 max-w-md">
                    <div class="relative group">
                        <span class="material-symbols-outlined absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 group-focus-within:text-primary transition-colors">search</span>
                        <input class="w-full bg-slate-100 border-none rounded-xl pr-10 pl-4 py-2.5 text-sm focus:ring-2 focus:ring-primary/20 focus:bg-white transition-all" placeholder="ابحث عن قضايا، مستندات، أو قوانين..." type="text"/>
                    </div>
                </div>
                <div class="flex items-center gap-3 sm:gap-4 shrink-0">
                    <button class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600 relative magnetic-element shrink-0">
                        <span class="material-symbols-outlined">notifications</span>
                        <span class="absolute top-2 right-2 w-2 h-2 bg-red-500 rounded-full border-2 border-white"></span>
                    </button>
                    <div class="h-8 w-[1px] bg-slate-200 shrink-0 hidden sm:block"></div>
                    <div class="flex items-center gap-2 sm:gap-3 shrink-0 min-w-0">
                        <div class="text-left min-w-0 hidden sm:block">
                            <p class="text-sm font-bold text-slate-900 leading-none truncate">{{ auth()->user()->name ?? 'المستخدم' }}</p>
                            <p class="text-[11px] text-slate-500 mt-1">{{ auth()->user()->role ?? 'محامي' }}</p>
                        </div>
                        <div class="w-10 h-10 rounded-full bg-primary/10 overflow-hidden border-2 border-primary/20 flex items-center justify-center shrink-0">
                            <span class="material-symbols-outlined text-primary">person</span>
                        </div>
                        <form method="POST" action="{{ route('logout') }}" class="inline">
                            @csrf
                            <button type="submit" class="w-10 h-10 rounded-xl bg-slate-100 flex items-center justify-center text-slate-600 hover:bg-red-50 hover:text-red-600 transition-colors">
                                <span class="material-symbols-outlined">logout</span>
                            </button>
                        </form>
                    </div>
                </div>
            </header>
            
            {{-- Page Content --}}
            <div class="p-8 flex-1">
                @yield('content')
            </div>
            
            {{-- Footer --}}
            <footer class="mt-auto p-8 border-t border-primary/5 text-slate-400 text-xs text-center">
                © {{ date('Y') }} المستشار القانوني الذكي. جميع الحقوق محفوظة. تعمل بواسطة تقنيات الذكاء الاصطناعي المتطورة.
            </footer>
        </main>
    </div>
    
    {{-- Toast container (fixed, above everything) --}}
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
        @if (session('success'))
        document.addEventListener('DOMContentLoaded', function() { showToast({{ json_encode(session('success')) }}, 'success'); });
        @endif
        @if (session('error'))
        document.addEventListener('DOMContentLoaded', function() { showToast({{ json_encode(session('error')) }}, 'error'); });
        @endif
        @if ($errors->any())
        document.addEventListener('DOMContentLoaded', function() { showToast({{ json_encode($errors->first()) }}, 'error'); });
        @endif
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
    </style>
    
    @stack('scripts')
</body>
</html>
