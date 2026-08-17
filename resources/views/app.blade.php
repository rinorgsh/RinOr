<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    {{-- viewport-fit=cover : le contenu passe sous l'encoche, les retraits
         sont ensuite gérés par env(safe-area-inset-*) dans le CSS. --}}
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title inertia>{{ config('app.name', 'RinOr') }}</title>

    {{-- ============ Installation sur l'écran d'accueil ============ --}}
    <link rel="manifest" href="/manifest.webmanifest">

    {{-- iOS ignore les icônes du manifeste : il lit celle-ci. --}}
    <link rel="apple-touch-icon" href="/icons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="/icons/favicon-32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="/icons/favicon-16.png">

    <meta name="mobile-web-app-capable" content="yes">
    {{-- Toujours nécessaire pour les iOS antérieurs à 15.4. --}}
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-title" content="RinOr">
    <meta name="application-name" content="RinOr">

    {{-- Pas de apple-mobile-web-app-status-bar-style : depuis iOS 15, la barre
         d'état suit theme-color, ce qui donne le bon ton en clair comme en
         sombre au lieu d'un blanc figé. --}}
    <meta name="color-scheme" content="light dark">
    <meta name="theme-color" content="#f7f6f2" media="(prefers-color-scheme: light)">
    <meta name="theme-color" content="#0f0f0e" media="(prefers-color-scheme: dark)">

    {{--
        Stampe data-theme avant le premier paint : une seule source de vérité
        pour le CSS et pour la variante `dark:` de Tailwind, et aucun flash.
    --}}
    <script>
        (function () {
            try {
                var stored = localStorage.getItem('theme');
                var theme = (stored === 'light' || stored === 'dark')
                    ? stored
                    : (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
                document.documentElement.dataset.theme = theme;
            } catch (e) {
                document.documentElement.dataset.theme = 'light';
            }
        })();
    </script>

    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @inertiaHead
</head>
<body>
    @inertia
</body>
</html>
