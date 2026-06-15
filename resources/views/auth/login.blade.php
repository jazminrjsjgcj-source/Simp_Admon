<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PUNTA — Iniciar sesión</title>
  <link rel="stylesheet" href="{{ asset('css/01-variables.css') }}">
  <link rel="stylesheet" href="{{ asset('css/02-base.css') }}">
  <link rel="stylesheet" href="{{ asset('css/04-components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/09-utilities.css') }}">
  <style>
    body { background: #f3f4f6; display: grid; place-items: center; min-height: 100vh; }
    .login-card { background: white; border-radius: 16px; padding: 40px; max-width: 420px; width: 100%; box-shadow: 0 4px 24px rgba(0,0,0,.08); }
    .login-card h1 { font-size: 24px; margin-bottom: 4px; color: var(--accent); }
    .login-card p { color: #667085; margin-bottom: 24px; font-size: 14px; }
    .login-card label { display: block; margin-bottom: 4px; font-size: 13px; font-weight: 600; color: #344054; }
    .login-card input { width: 100%; padding: 10px 14px; border: 1px solid #d0d5dd; border-radius: 8px; font-size: 14px; margin-bottom: 16px; box-sizing: border-box; }
    .login-card .btn { width: 100%; justify-content: center; margin-top: 8px; }
    .login-error { background: #fef2f2; color: #991b1b; padding: 10px 14px; border-radius: 8px; font-size: 13px; margin-bottom: 16px; }
    .login-footer { text-align: center; margin-top: 20px; font-size: 12px; color: #9ca3af; }
  </style>
</head>
<body>
  <div class="login-card">
    <h1>PUNTA</h1>
    <p>Plataforma Unificada de Normativa, Trámites y Agendas<br>H. Ayuntamiento de La Paz, B.C.S.</p>

    @if($errors->any())
      <div class="login-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
      @csrf
      <label for="email">Correo electrónico</label>
      <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus placeholder="usuario@lapaz.gob.mx">

      <label for="password">Contraseña</label>
      <input type="password" id="password" name="password" required placeholder="••••••••">

      <button type="submit" class="btn">Iniciar sesión</button>
    </form>

    <div class="login-footer">
      Sistema PUNTA &mdash; Plataforma Unificada de Normativa, Trámites y Agendas
    </div>
  </div>
</body>
</html>
