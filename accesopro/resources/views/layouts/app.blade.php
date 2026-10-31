<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>@yield('title', 'AccesoPro') · Control</title>
    <style>
        :root {
            --dark-blue: #111E32;
            --primary-green: #59C267;
            --light-green: #DDF2E8;
            --soft-blue: #25394F;
            --light-blue: #67717E;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            font-family: -apple-system, "Segoe UI", Roboto, Inter, Arial, sans-serif;
            background: #F4F6F8;
            color: var(--dark-blue);
        }
        header.topbar {
            background: var(--dark-blue);
            color: #fff;
            padding: 14px 24px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        header.topbar .brand {
            font-weight: 700;
            font-size: 18px;
        }
        header.topbar .brand span { color: var(--primary-green); }
        header.topbar form button {
            background: transparent;
            border: 1px solid var(--light-blue);
            color: #fff;
            padding: 6px 14px;
            border-radius: 6px;
            cursor: pointer;
        }
        main { padding: 24px; max-width: 1200px; margin: 0 auto; }
        .card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 1px 3px rgba(0,0,0,.08);
        }
        a { color: var(--primary-green); }
    </style>
</head>
<body>
@auth
    <header class="topbar">
        <div class="brand">Acceso<span>Pro</span> · Control</div>
        <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit">Cerrar sesión ({{ auth()->user()->email }})</button>
        </form>
    </header>
@endauth
<main>
    @yield('content')
</main>
</body>
</html>
