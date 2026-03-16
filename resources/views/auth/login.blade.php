<!doctype html>
<html lang="pt-BR">

<head>
    <meta charset="utf-8">
    <meta name="viewport"
        content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/@mdi/font@5.x/css/materialdesignicons.min.css" rel="stylesheet">
    <link rel="shortcut icon" type="image/png" href="{{ asset('img/favicons.png') }}" />
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <title>{{ config('app.name') }} — {{ config('app.descricao', 'Gestão de Pessoas') }}</title>
</head>

<body>
    <div id="app">
        <login app-name="{{ config('app.name') }} — {{ config('app.descricao', 'Gestão de Pessoas') }}"
            ambiente="{{ env('AMBIENTE', 'desenv') }}"
            color="{{ env('AMBIENTE') === 'producao' || env('AMBIENTE') === 'desenv' ? 'primary' : 'orange darken-4' }}">
        </login>
    </div>
    <script src="{{ asset('js/app.js') }}"></script>
</body>

</html>