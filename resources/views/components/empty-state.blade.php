{{--
  Componente: estado vacío (x-empty-state)

  Muestra un mensaje centrado cuando una sección no tiene datos.
  Siguiendo Refactoring UI §46: título claro, explicación breve,
  acción principal, icono sencillo.

  Uso:
    <x-empty-state
      titulo="Sin regulaciones"
      mensaje="Aún no se han registrado regulaciones en el catálogo."
      icono="ti-file-text"
      :accion-url="route('regulaciones.create')"
      accion-texto="Subir regulación"
    />

  Solo el atributo 'mensaje' es obligatorio.
--}}
@props([
    'titulo'      => '',
    'mensaje',
    'icono'       => 'ti-inbox',
    'accionUrl'   => '',
    'accionTexto' => '',
])

<div style="text-align:center;padding:48px 24px;color:var(--muted)">
  <i class="ti {{ $icono }}" style="font-size:40px;opacity:.4;display:block;margin-bottom:12px"></i>
  @if($titulo)
    <p style="font-size:16px;font-weight:600;color:var(--text);margin:0 0 6px">{{ $titulo }}</p>
  @endif
  <p style="font-size:14px;line-height:1.5;margin:0 auto;max-width:400px">{{ $mensaje }}</p>
  @if($accionUrl)
    <a href="{{ $accionUrl }}" class="btn btn-sm" style="margin-top:16px">{{ $accionTexto }}</a>
  @endif
</div>
