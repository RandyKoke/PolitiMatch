<!DOCTYPE html>
<html lang="fr">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="theme-color" content="#1A1A1A">
        <meta name="description" content="PolitiMatch aide les jeunes Belges francophones à comprendre leurs valeurs politiques et à trouver les partis qui leur correspondent, simplement et sans jargon.">

        <title>{{ config('app.name', 'PolitiMatch') }}</title>

        @fonts

        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="antialiased">
        <div id="app"></div>
    </body>
</html>
