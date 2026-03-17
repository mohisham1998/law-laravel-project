<!DOCTYPE html>
<html dir="rtl" lang="ar">
<head>
    <meta charset="utf-8"/>
    <meta content="width=device-width, initial-scale=1.0" name="viewport"/>
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>إنشاء حساب - المستشار القانوني الذكي</title>
    <script src="https://cdn.tailwindcss.com?plugins=forms,container-queries"></script>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&display=swap" rel="stylesheet"/>
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet"/>
    <script>
        tailwind.config = {
            theme: {
                extend: {
                    colors: {
                        "primary": "#006b34",
                        "secondary-green": "#2FAF74",
                        "background-light": "#f5f8f7",
                    },
                },
            },
        }
    </script>
    <style>body { font-family: 'Cairo', sans-serif; }</style>
</head>
<body class="bg-background-light min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-xl shadow-2xl border border-slate-200 max-w-[550px] w-full p-8 sm:p-16">
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
            <h2 class="text-3xl font-bold text-slate-900 mb-2">إنشاء حساب جديد</h2>
            <p class="text-slate-500">أدخل بياناتك لإنشاء حساب جديد</p>
        </div>
        
        @if ($errors->any())
            <div class="mb-6 p-4 bg-red-50 border border-red-200 text-red-800 rounded-xl">
                @foreach ($errors->all() as $error)
                    <p class="flex items-center gap-2"><span class="material-symbols-outlined text-sm">error</span>{{ $error }}</p>
                @endforeach
            </div>
        @endif
        
        <form action="{{ route('register') }}" method="POST" class="space-y-6">
            @csrf
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">الاسم الكامل</label>
                <input name="name" value="{{ old('name') }}" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="أدخل اسمك الكامل" required type="text"/>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">البريد الإلكتروني</label>
                <input name="email" value="{{ old('email') }}" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="example@law.sa" required type="email"/>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">كلمة المرور</label>
                <input name="password" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="••••••••" required type="password"/>
            </div>
            <div>
                <label class="block text-sm font-semibold text-slate-700 mb-2">تأكيد كلمة المرور</label>
                <input name="password_confirmation" class="w-full px-4 py-3.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-primary/20 focus:border-primary" placeholder="••••••••" required type="password"/>
            </div>
            <button class="w-full bg-primary hover:bg-primary/90 text-white font-bold py-4 rounded-xl shadow-lg shadow-primary/20 transition-all" type="submit">
                إنشاء الحساب
            </button>
        </form>
        
        <p class="mt-10 text-center text-slate-600">
            لديك حساب بالفعل؟
            <a class="font-bold text-primary hover:text-secondary-green mr-1" href="{{ route('login') }}">تسجيل الدخول</a>
        </p>
    </div>
</body>
</html>
