<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>تسجيل الدخول - المستشار القانوني الذكي</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&family=Public+Sans:wght@400;500;600;700&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
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
                        "display": ["Cairo", "Public Sans", "sans-serif"]
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
        }
    </style>
</head>
<body class="bg-background-light dark:bg-background-dark min-h-screen flex items-center justify-center p-4">
    <div class="flex w-full h-full bg-white dark:bg-slate-900 rounded-xl overflow-hidden shadow-2xl border border-border-gray dark:border-primary/20 max-w-[550px]">
        <div class="w-full flex flex-col justify-center px-8 sm:px-16 py-12 lg:px-16">
            <div class="flex items-center gap-3 text-primary mb-12">
                <div class="bg-primary p-2 rounded-lg">
                    <svg class="w-6 h-6 text-white" fill="none" viewBox="0 0 48 48" xmlns="http://www.w3.org/2000/svg">
                        <path clip-rule="evenodd" d="M24 18.4228L42 11.475V34.3663C42 34.7796 41.7457 35.1504 41.3601 35.2992L24 42V18.4228Z" fill="currentColor" fill-rule="evenodd"></path>
                        <path clip-rule="evenodd" d="M24 8.18819L33.4123 11.574L24 15.2071L14.5877 11.574L24 8.18819ZM9 15.8487L21 20.4805V37.6263L9 32.9945V15.8487ZM27 37.6263V20.4805L39 15.8487V32.9945L27 37.6263ZM25.354 2.29885C24.4788 1.98402 23.5212 1.98402 22.646 2.29885L4.98454 8.65208C3.7939 9.08038 3 10.2097 3 11.475V34.3663C3 36.0196 4.01719 37.5026 5.55962 38.098L22.9197 44.7987C23.6149 45.0671 24.3851 45.0671 25.0803 44.7987L42.4404 38.098C43.9828 37.5026 45 36.0196 45 34.3663V11.475C45 10.2097 44.2061 9.08038 43.0155 8.65208L25.354 2.29885Z" fill="currentColor" fill-rule="evenodd"></path>
                    </svg>
                </div>
                <h1 class="text-xl font-bold">المستشار القانوني الذكي</h1>
            </div>
            
            <div class="mb-10">
                <h2 class="text-3xl font-bold text-slate-900 dark:text-slate-100 mb-2">تسجيل الدخول</h2>
                <p class="text-slate-500 dark:text-slate-400">مرحباً بك مجدداً! يرجى إدخال بياناتك للدخول إلى حسابك</p>
            </div>
            
            @if ($errors->any())
                <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-xl">
                    @foreach ($errors->all() as $error)
                        <p class="flex items-center gap-2">
                            <span class="material-symbols-outlined text-sm">error</span>
                            {{ $error }}
                        </p>
                    @endforeach
                </div>
            @endif
            
            <form action="{{ route('login') }}" class="space-y-6" method="POST">
                @csrf
                <div>
                    <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300 mb-2" for="email">البريد الإلكتروني</label>
                    <div class="relative">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400">
                            <span class="material-symbols-outlined text-[20px]">mail</span>
                        </span>
                        <input class="w-full pr-12 pl-4 py-3.5 bg-slate-50 dark:bg-slate-800 border border-border-gray dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-slate-400 @error('email') border-red-500 @enderror" id="email" name="email" placeholder="example@law.sa" required type="email" value="{{ old('email') }}"/>
                    </div>
                </div>
                
                <div>
                    <div class="flex justify-between items-center mb-2">
                        <label class="block text-sm font-semibold text-slate-700 dark:text-slate-300" for="password">كلمة المرور</label>
                        <a class="text-sm font-medium text-secondary-green hover:underline" href="#">نسيت كلمة المرور؟</a>
                    </div>
                    <div class="relative">
                        <span class="absolute inset-y-0 right-0 flex items-center pr-4 text-slate-400">
                            <span class="material-symbols-outlined text-[20px]">lock</span>
                        </span>
                        <input class="w-full pr-12 pl-12 py-3.5 bg-slate-50 dark:bg-slate-800 border border-border-gray dark:border-slate-700 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary outline-none transition-all placeholder:text-slate-400" id="password" name="password" placeholder="••••••••" required type="password"/>
                        <button class="absolute inset-y-0 left-0 flex items-center pl-4 text-slate-400 hover:text-primary transition-colors" type="button" onclick="togglePassword()">
                            <span class="material-symbols-outlined text-[20px]" id="passwordIcon">visibility</span>
                        </button>
                    </div>
                </div>
                
                <div class="flex items-center">
                    <input class="w-4 h-4 text-primary bg-slate-100 border-gray-300 rounded focus:ring-primary dark:focus:ring-primary dark:ring-offset-gray-800 focus:ring-2 dark:bg-gray-700 dark:border-gray-600" id="remember" name="remember" type="checkbox"/>
                    <label class="mr-2 text-sm text-slate-600 dark:text-slate-400" for="remember">تذكرني على هذا الجهاز</label>
                </div>
                
                <button class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-4 px-6 rounded-xl shadow-lg shadow-primary/20 transform active:scale-[0.98] transition-all duration-150 flex items-center justify-center gap-2" type="submit">
                    <span>دخول</span>
                    <span class="material-symbols-outlined text-[20px]">login</span>
                </button>
            </form>
            
            <div class="relative my-8">
                <div class="absolute inset-0 flex items-center">
                    <div class="w-full border-t border-border-gray dark:border-slate-700"></div>
                </div>
                <div class="relative flex justify-center text-sm">
                    <span class="px-4 bg-white dark:bg-slate-900 text-slate-500">أو من خلال</span>
                </div>
            </div>
            
            <div class="grid grid-cols-1 gap-4">
                <button class="flex items-center justify-center gap-3 px-4 py-3 border border-border-gray dark:border-slate-700 rounded-xl hover:bg-slate-50 dark:hover:bg-slate-800 transition-colors">
                    <svg class="w-5 h-5" viewBox="0 0 24 24">
                        <path d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z" fill="#4285F4"/>
                        <path d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z" fill="#34A853"/>
                        <path d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.07H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.93l2.85-2.22.81-.62z" fill="#FBBC05"/>
                        <path d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.07l3.66 2.84c.87-2.6 3.3-4.53 6.16-4.53z" fill="#EA4335"/>
                    </svg>
                    <span class="text-sm font-semibold text-slate-700 dark:text-slate-300">تسجيل عبر جوجل</span>
                </button>
            </div>
            
            <p class="mt-10 text-center text-slate-600 dark:text-slate-400">
                ليس لديك حساب؟
                <a class="font-bold text-primary hover:text-secondary-green transition-colors mr-1" href="{{ route('register') }}">إنشاء حساب جديد</a>
            </p>
        </div>
    </div>
    
    <script>
        function togglePassword() {
            const input = document.getElementById('password');
            const icon = document.getElementById('passwordIcon');
            if (input.type === 'password') {
                input.type = 'text';
                icon.textContent = 'visibility_off';
            } else {
                input.type = 'password';
                icon.textContent = 'visibility';
            }
        }
    </script>
</body>
</html>
