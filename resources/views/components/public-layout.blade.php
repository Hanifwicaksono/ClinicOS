@props(['title' => 'ClinicOS'])
<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>{{ $title }}</title>
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&family=plus-jakarta-sans:600,700,800" rel="stylesheet" />
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#f6f8f7] font-sans antialiased">
        <header class="border-b border-slate-200 bg-white">
            <div class="mx-auto flex h-20 max-w-6xl items-center justify-between px-5 sm:px-8">
                <a href="/" class="flex items-center gap-3"><x-application-logo class="h-10 w-10" /><span class="font-display text-xl font-extrabold text-clinic-950">Clinic<span class="text-clinic-500">OS</span></span></a>
                @auth<a href="{{ route('dashboard') }}" class="clinic-button-secondary">Dashboard</a>@else<a href="{{ route('login') }}" class="text-sm font-bold text-slate-600 hover:text-clinic-600">Masuk</a>@endauth
            </div>
        </header>
        <main>{{ $slot }}</main>
        <footer class="mt-16 border-t border-slate-200 bg-white"><div class="mx-auto max-w-6xl px-5 py-8 text-sm text-slate-500 sm:px-8">© {{ date('Y') }} ClinicOS</div></footer>
    </body>
</html>
