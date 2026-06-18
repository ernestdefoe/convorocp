<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title inertia>{{ \App\Support\Branding::data()['name'] }}</title>
    <style>:root{ --cp-ind: {{ \App\Support\Branding::accent() }}; --cp-vio: {{ \App\Support\Branding::accent() }}; }</style>
    <script>
        (function () {
            try {
                if (localStorage.getItem('cp-theme') === 'light') {
                    document.documentElement.classList.add('light');
                }
            } catch (e) {}
        })();
    </script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3/dist/tabler-icons.min.css">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
