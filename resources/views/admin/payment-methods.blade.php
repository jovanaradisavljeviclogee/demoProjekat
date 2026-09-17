<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    {{-- React čita token odavde i šalje ga uz svaki zahtev koji menja stanje (PM-N-03). --}}
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Payment methods</title>
    {{-- Mora stajati PRE @vite: `@vitejs/plugin-react` u dev režimu traži React Refresh
         preambulu u stranici, a bez nje prva komponenta pukne sa „can't detect preamble".
         U produkcionom build-u direktiva ne ispisuje ništa, zato `vite build` prolazi
         čist i propust se vidi isključivo preko dev servera. --}}
    @viteReactRefresh
    @vite(['resources/css/app.css', 'resources/js/main.jsx'])
</head>
<body class="bg-gray-50 text-gray-900">
    {{-- Jedina mount tačka. Sve tri rute iz PM-FR-31 iscrtava React (PM-FR-80). --}}
    <div id="app"></div>
</body>
</html>
