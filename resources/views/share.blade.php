<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#1A1A1A">
        <meta name="description" content="{{ $ogDescription }}">

        <title>{{ $ogTitle }}</title>

        {{-- Balises Open Graph / Twitter Card : lues par les robots des
             réseaux sociaux au moment où le lien est collé (jamais après,
             jamais via JS) — cf. ShareController::showPage pour le pourquoi
             de cette vue dédiée plutôt que la coquille SPA générique. --}}
        <meta property="og:type" content="website">
        <meta property="og:site_name" content="PolitiMatch">
        <meta property="og:title" content="{{ $ogTitle }}">
        <meta property="og:description" content="{{ $ogDescription }}">
        <meta property="og:image" content="{{ $ogImage }}">
        <meta property="og:url" content="{{ $ogUrl }}">
        <meta name="twitter:card" content="summary_large_image">
        <meta name="twitter:title" content="{{ $ogTitle }}">
        <meta name="twitter:description" content="{{ $ogDescription }}">
        <meta name="twitter:image" content="{{ $ogImage }}">

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased">
        <div id="app"></div>
    </body>
</html>
