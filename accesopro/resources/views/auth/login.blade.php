@extends('layouts.app')

@section('title', 'Acceder')

@section('content')
    <style>
        .login-wrap { display: flex; justify-content: center; align-items: center; min-height: 80vh; }
        .login-card { width: 100%; max-width: 380px; }
        .login-card h1 { font-size: 20px; margin-bottom: 4px; }
        .login-card p.sub { color: #67717E; margin-top: 0; margin-bottom: 20px; font-size: 14px; }
        .login-card label { display: block; font-size: 13px; font-weight: 600; margin-bottom: 4px; }
        .login-card input[type=email], .login-card input[type=password] {
            width: 100%; padding: 10px 12px; border: 1px solid #D7DCE1; border-radius: 6px; margin-bottom: 14px; font-size: 14px;
        }
        .login-card button {
            width: 100%; padding: 11px; background: #59C267; color: #111E32; font-weight: 700;
            border: none; border-radius: 6px; cursor: pointer; font-size: 14px;
        }
        .login-card .errors { background: #FDECEC; color: #B3261E; padding: 10px 12px; border-radius: 6px; margin-bottom: 14px; font-size: 13px; }
    </style>
    <div class="login-wrap">
        <div class="card login-card">
            <h1>AccesoPro · Control</h1>
            <p class="sub">Tabla de control del pipeline. Acceso restringido.</p>

            @if ($errors->any())
                <div class="errors">{{ $errors->first() }}</div>
            @endif

            <form method="POST" action="{{ route('login.store') }}">
                @csrf
                <label for="email">Correo</label>
                <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus>

                <label for="password">Contraseña</label>
                <input type="password" id="password" name="password" required>

                <button type="submit">Entrar</button>
            </form>
        </div>
    </div>
@endsection
