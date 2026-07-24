<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PUNTA — Iniciar sesión</title>
  <link rel="stylesheet" href="{{ asset('css/01-variables.css') }}">
  <link rel="stylesheet" href="{{ asset('css/02-base.css') }}">
  <link rel="stylesheet" href="{{ asset('css/04-components.css') }}">
  <link rel="stylesheet" href="{{ asset('css/11-utilities.css') }}">
  <style>
    /* Login PUNTA: fondo guinda con tarjeta blanca centrada.
       Usa exclusivamente las variables del sistema (paleta, radios, sombra). */
    body {
      background: var(--primary-container);
      display: grid;
      place-items: center;
      min-height: 100vh;
      margin: 0;
    }
    .login-card {
      background: var(--surface);
      border-radius: var(--radius-lg);
      padding: 36px 32px;
      max-width: 380px;
      width: calc(100% - 32px);
      box-shadow: var(--shadow);
    }
    .login-brand { text-align: center; margin-bottom: 24px; }
    .login-brand img { width: 72px; height: 72px; object-fit: contain; display: block; margin: 0 auto 10px; }
    .login-brand h1 {
      font-size: 22px;
      font-weight: 800;
      color: var(--primary);
      letter-spacing: .04em;
      margin: 0;
    }
    .login-brand .login-sub {
      font-size: 11px;
      color: var(--muted-light);
      letter-spacing: .18em;
      margin-top: 4px;
    }
    .login-card label {
      display: block;
      margin-bottom: 5px;
      font-size: 12px;
      font-weight: 600;
      color: var(--muted);
    }
    .login-card input {
      width: 100%;
      box-sizing: border-box;
      padding: 10px 14px;
      border: 1px solid var(--border);
      border-radius: var(--radius);
      font-size: 13px;
      color: var(--text);
      background: var(--surface);
      margin-bottom: 16px;
    }
    .login-card input:focus { outline: none; border-color: var(--primary); }
    .login-pass { position: relative; }
    .login-pass input { padding-right: 40px; }
    .login-pass-toggle {
      position: absolute;
      right: 12px;
      top: 20px;
      transform: translateY(-50%);
      background: none;
      border: none;
      cursor: pointer;
      color: var(--muted-light);
      font-size: 16px;
      padding: 0;
      line-height: 1;
    }
    .login-card .btn { width: 100%; justify-content: center; margin-top: 4px; gap: 8px; }
    .login-error {
      background: var(--chip-red-bg);
      color: var(--chip-red);
      padding: 10px 14px;
      border-radius: var(--radius);
      font-size: 13px;
      margin-bottom: 16px;
    }
    .login-aviso {
      border-top: 1px solid var(--border);
      margin-top: 20px;
      padding-top: 14px;
      display: flex;
      gap: 8px;
      align-items: flex-start;
    }
    .login-aviso .punto {
      flex: 0 0 8px;
      width: 8px;
      height: 8px;
      border-radius: var(--radius-pill);
      background: var(--chip-amber);
      margin-top: 4px;
    }
    .login-aviso span { font-size: 11px; color: var(--muted-light); line-height: 1.5; }
  </style>
</head>
<body>
  <div class="login-card">

    <div class="login-brand">
      <img src="{{ asset('img/logo-punta.png') }}" alt="Logo PUNTA">
      <h1>PUNTA</h1>
      <div class="login-sub">GESTIÓN ADMINISTRATIVA</div>
    </div>

    @if($errors->any())
      <div class="login-error">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('login') }}">
      @csrf

      <label for="email">Usuario Institucional</label>
      <input type="email" id="email" name="email" value="{{ old('email') }}" required autofocus
             placeholder="usuario@lapaz.gob.mx">

      <label for="password">Contraseña</label>
      <div class="login-pass">
        <input type="password" id="password" name="password" required placeholder="Contraseña">
        <button type="button" class="login-pass-toggle" id="togglePass" aria-label="Mostrar u ocultar contraseña">
          <svg id="iconOjo" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/>
            <circle cx="12" cy="12" r="3"/>
          </svg>
        </button>
      </div>

      <button type="submit" class="btn">
        Ingresar
        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
          <line x1="5" y1="12" x2="19" y2="12"/>
          <polyline points="12 5 19 12 12 19"/>
        </svg>
      </button>
    </form>

    <div class="login-aviso">
      <span class="punto"></span>
      <span>Acceso restringido a personal autorizado. El uso de esta plataforma está sujeto a las políticas de seguridad institucional.</span>
    </div>

  </div>

  <script>
    (function () {
      var btn = document.getElementById('togglePass');
      var input = document.getElementById('password');
      var icono = document.getElementById('iconOjo');
      if (!btn || !input || !icono) return;

      var ojoAbierto = '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/>';
      var ojoCerrado = '<path d="M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7-10-7-10-7z"/><circle cx="12" cy="12" r="3"/><line x1="3" y1="3" x2="21" y2="21"/>';

      btn.addEventListener('click', function () {
        var oculto = input.type === 'password';
        input.type = oculto ? 'text' : 'password';
        icono.innerHTML = oculto ? ojoCerrado : ojoAbierto;
      });
    })();
  </script>
</body>
</html>
