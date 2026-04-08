<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" dir="{{ app()->getLocale() === 'ar' ? 'rtl' : 'ltr' }}" data-theme="{{ function_exists('app_color_theme') ? app_color_theme() : 'light' }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', __('default_title'))</title>

    @php
        $fontFamily = config('payment-gateway.font_family', 'Almarai');
        $fontSlug = strtolower($fontFamily);
    @endphp
    <link href="https://fonts.bunny.net/css?family={{ $fontSlug }}:300,400,700&display=swap" rel="stylesheet">

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>

<body class="bg-base-200 min-h-screen text-base-content" style="font-family: '{{ $fontFamily }}', sans-serif;">
    <div class="min-h-screen">
        <main>
            @yield('content')
        </main>
    </div>

    <!-- Additional Scripts -->
    @stack('scripts')
</body>

</html>
