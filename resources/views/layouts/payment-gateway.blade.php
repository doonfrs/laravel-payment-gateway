<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>@yield('title', __('payment-gateway::messages.default_title'))</title>

    <!-- Scripts and Styles -->
    @vite(['resources/css/app.css', 'resources/js/app.js'])

    <!-- Additional Styles -->
    @stack('styles')
</head>

<body class="bg-gray-50 min-h-screen">
    <div class="min-h-screen">
        <main>
            @yield('content')
        </main>
    </div>

    <!-- Additional Scripts -->
    @stack('scripts')
</body>

</html> 