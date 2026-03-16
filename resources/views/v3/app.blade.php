<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">

<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    <title>GENTE 2.0 - Gestão de Pessoas</title>

    <!-- Usando script direto do Vite Dev Server -->
    <!-- Em produção precisaria ler o manifest.json gerado no public/build-v3 -->
    @if(app()->environment('local'))
        <script type="module" src="http://localhost:5173/@vite/client"></script>
        <script type="module" src="http://localhost:5173/src/main.js"></script>
    @else
        @php
            $manifest = json_decode(file_get_contents(public_path('build-v3/.vite/manifest.json')), true);
            $mainFile = $manifest['src/main.js']['file'];
            $cssFile = $manifest['src/main.js']['css'][0] ?? null;
        @endphp
        @if($cssFile)
            <link rel="stylesheet" href="{{ asset('build-v3/' . $cssFile) }}">
        @endif
        <script type="module" src="{{ asset('build-v3/' . $mainFile) }}"></script>
    @endif
</head>

<body class="antialiased">
    <!-- Ponto de Montagem do Vue 3 -->
    <div id="app"></div>

    <script>
        // Exportando variáveis globais pro Vue 3 se necessário
        window.__env = {
            baseUrl: '{{ url("") }}',
            ambiente: '{{ env("APP_ENV") }}'
        };
    </script>
</body>

</html>