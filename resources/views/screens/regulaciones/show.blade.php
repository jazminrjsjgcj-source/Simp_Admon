@extends('layouts.app')
@section('title', $regulacion->nombre)

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">{{ $regulacion->nombre }}</h2>
      <p class="nowrap">
        {{ $regulacion->tipo ?? 'Regulación' }}
        @if($regulacion->dependencia) — {{ $regulacion->dependencia->nombre }} @endif
      </p>
    </div>
    <div class="head-actions">
      <a href="{{ route('regulaciones.index') }}" class="btn btn-outline">Volver</a>
      @if(auth()->user()->puedeEditarRegulacion($regulacion))
        <a href="{{ route('regulaciones.edit', $regulacion) }}" class="btn btn-outline">Editar</a>
      @endif
      @if($regulacion->archivo_original)
        <a href="{{ route('regulaciones.descargar', $regulacion) }}" class="btn btn-outline">Descargar original</a>
      @endif
    </div>
  </div>

  <div class="detalle-con-timeline">
    <div class="detalle-main">

  {{-- Metadatos --}}
  <div class="card">
    <div class="card-body-padded">
      <div class="wizard-fields">
        <div>
          <span class="label-meta">Estatus</span>
          <strong>{{ ucfirst(str_replace('_', ' ', $regulacion->estatus)) }}</strong>
        </div>
        <div>
          <span class="label-meta">Conversión</span>
          <strong>{{ ucfirst($regulacion->conversion_estatus) }}</strong>
        </div>
        <div>
          <span class="label-meta">Publicación</span>
          <strong>{{ $regulacion->fecha_publicacion?->format('d/m/Y') ?? '—' }}</strong>
        </div>
        <div>
          <span class="label-meta">Vigencia</span>
          <strong>{{ $regulacion->fecha_vigencia?->format('d/m/Y') ?? '—' }}</strong>
        </div>
      </div>

      @if($regulacion->resumen)
        <div class="section-divided">
          <span class="label-meta">Resumen ciudadano</span>
          <p class="text-muted-sm">{{ $regulacion->resumen }}</p>
        </div>
      @endif
    </div>
  </div>

  {{-- Archivo original para todos los usuarios --}}
  @if($regulacion->archivo_original)
  <div class="card">
    <div class="panel-head">
      <div>
        <h3>Archivo de la regulación</h3>
        <p>Documento oficial en su formato original.</p>
      </div>
      <a href="{{ route('regulaciones.descargar', $regulacion) }}" class="btn btn-outline btn-sm">Descargar</a>
    </div>
    <div class="card-body-padded">
      <div class="assist-box">
        El archivo está disponible en formato {{ strtoupper($regulacion->extension_original ?? 'original') }}.
        Usa el botón de descarga para consultarlo.
      </div>
    </div>
  </div>
  @endif

  {{-- Visor Markdown — solo para admin (uso interno del sistema) --}}
  @if(auth()->user()->rol === 'admin')
  <div class="card">
    <div class="panel-head">
      <div>
        <h3>Contenido interno (Markdown)</h3>
        <p>Texto extraído para uso interno del sistema — citable desde los wizards. Solo visible para administradores.</p>
      </div>
    </div>
    <div class="card-body-padded">
      @if($regulacion->conversion_estatus === 'listo' && $contenidoMd)
        <pre style="white-space:pre-wrap;font-family:inherit;font-size:13px;line-height:1.6;color:var(--text);background:#fbfcfe;border:1px solid var(--surface-high);border-radius:8px;padding:20px;max-height:560px;overflow:auto">{{ $contenidoMd }}</pre>

      @elseif($regulacion->conversion_estatus === 'procesando')
        <div class="text-center u-empty-md">
          <h3>Procesando conversión</h3>
          <p class="text-muted-sm">El archivo se está convirtiendo a Markdown. Recargue la página en unos momentos.</p>
        </div>

      @elseif($regulacion->conversion_estatus === 'error')
        <div class="text-center u-empty-md">
          <h3>Error al convertir</h3>
          <p class="text-muted-sm">{{ $regulacion->conversion_error ?? 'No se pudo extraer el contenido del archivo.' }}</p>
          <form method="POST" action="{{ route('regulaciones.reintentar', $regulacion) }}" class="mt-4">
            @csrf
            <button type="submit" class="btn">Reintentar conversión</button>
          </form>
        </div>

      @else
        <div class="text-center u-empty-md">
          <h3>Conversión pendiente</h3>
          <p class="text-muted-sm">La conversión a Markdown está encolada y se ejecutará en breve.</p>
        </div>
      @endif
    </div>
  </div>
  @endif

    </div>{{-- /detalle-main --}}

    <aside class="detalle-aside">
      @include('partials.timeline', ['tipo' => 'regulacion', 'id' => $regulacion->id])
    </aside>
  </div>{{-- /detalle-con-timeline --}}

</div>
@endsection
