<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover, padding-top: env(safe-area-inset-top), padding-bottom: env(safe-area-inset-bottom)">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <!-- Favicon (généré par realfavicongenerator) -->
    <link rel="icon" type="image/png" href="/favicon-96x96.png" sizes="96x96">
    <link rel="icon" type="image/svg+xml" href="/favicon.svg">
    <link rel="shortcut icon" href="/favicon.ico">
    <link rel="apple-touch-icon" sizes="180x180" href="/apple-touch-icon.png">
    <link rel="manifest" href="/site.webmanifest" />

    <!-- PWA -->
    <link rel="manifest" href="/site.webmanifest">
    <meta name="theme-color" content="#2D6A4F">
    <meta name="mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="TemaCoach">

    <title inertia>TemaCoach</title>

    @routes
    @vite(['resources/css/app.css', 'resources/js/app.js', "resources/js/Pages/{$page['component']}.vue"])
    @inertiaHead
</head>
<body class="font-sans antialiased">
    @inertia

    <!-- Enregistrement du Service Worker -->
    <script>
        if ('serviceWorker' in navigator) {
            window.addEventListener('load', () => {
                navigator.serviceWorker.register('/sw.js')
                    .then(reg => console.log('SW enregistré:', reg.scope))
                    .catch(err => console.log('SW erreur:', err));
            });
        }
    </script>
</body>
</html>