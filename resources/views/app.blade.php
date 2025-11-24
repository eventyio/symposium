@php
    $is_home = request()->route()->getName() === 'home';
    $is_conferences = request()->route()->getName() === 'conferences.index';
@endphp

<!DOCTYPE html>
<html class="h-full">
<head>
    <title>Symposium for Speakers</title>
    <meta name="viewport" content="width=device-width, initial-scale=1.0">

    <meta property="og:title" content="{{ config('app.name') }}">
    <meta property="og:type" content="website">
    <meta property="og:url" content="https://symposiumapp.com/">
    <meta property="og:image" content="{{ asset('img/symposium-banner.png') }}">

    <meta property="twitter:card" content="summary_large_image">
    <meta property="twitter:url" content="https://symposiumapp.com/">
    <meta property="twitter:title" content="{{ config('app.name') }}">
    <meta property="twitter:description" content="A web app for conference speakers to track talks, bios, and conferences.">
    <meta property="twitter:image" content="{{ asset('img/symposium-banner.png') }}">

    <link rel="stylesheet" href="https://rsms.me/inter/inter.css">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Open+Sans:400,400italic,600,600italic,800,800italic">
    <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Oswald:400,300,700">
    @vite('resources/css/app.css')
    <link rel="stylesheet" href="//cdnjs.cloudflare.com/ajax/libs/fullcalendar/2.2.7/fullcalendar.min.css"/>
    <link href="https://fonts.googleapis.com/css?family=Work+Sans&display=swap" rel="stylesheet">
    <link rel="preconnect" href="https://fonts.gstatic.com">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}">
    @livewireStyles

    <script>
        var Symposium = {
            token: "{{ csrf_token() }}"
        };
    </script>
    @yield('headerScripts')
    @livewireScripts
</head>
<body class="h-full">
    @php
        $app_bg_color = $is_home ? 'bg-white' : 'bg-indigo-100';
    @endphp
    <div id="app" class="min-h-full flex flex-col justify-between {{ $app_bg_color }}">
        <div class="flex-1">
            @include('partials.header', ['title' => $title ?? null])
            @if ($is_home)
                @yield('content')
            @else
                <div class="p-4 bg-indigo-100 mx-auto sm:max-w-7xl">
                    @yield('content')
                </div>
            @endif
        </div>
        @include('partials.footer', ['is_home' => $is_home])
    </div>

    <script src="https://maps.googleapis.com/maps/api/js?key={{ config('services.google.maps.frontend_key') }}&libraries=places"></script>
    @vite('resources/js/app.js')
    @stack('scripts')

    @production
    <!-- Fathom - beautiful, simple website analytics -->
    <script src="https://cdn.usefathom.com/script.js" data-site="IUITLCGM" defer></script>
    <!-- / Fathom -->
    @endproduction
</body>
</html>
