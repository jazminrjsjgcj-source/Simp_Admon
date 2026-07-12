@extends('layouts.app')
@section('title', 'Regulaciones')

@section('content')
@php $verDependencia = auth()->user()->veVariasDependencias(); @endphp
<div class="page-wide">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Catálogo de Regulaciones</h2>
      <p class="nowrap">Leyes y reglamentos vigentes citables desde los wizards.</p>
    </div>
    <div class="head-actions">
      @if(auth()->user()->veVariasDependencias())
        <a href="{{ route('regulaciones.papelera-regulaciones') }}" class="btn btn-outline btn-sm">Papelera</a>
      @endif
      <a href="{{ route('regulaciones.descargar-zip', request()->only(['q', 'estatus', 'dependencia'])) }}" class="btn btn-blanco">Descargar ZIP</a>
      @if(auth()->user()->tienePermiso('regulaciones.crear'))
        <a href="{{ route('regulaciones.create') }}" class="btn btn-blanco">Subir regulación</a>
      @endif
    </div>
  </div>

  {{-- Estante de favoritos del usuario (lomos en fila). Siempre visible; si no
       hay favoritos muestra un mensaje. Los lomos se agregan/quitan en vivo al
       marcar el corazón en el catálogo de abajo (ver JS al final). --}}
  <div class="card" style="margin-bottom:16px; overflow:hidden">
    <div class="card-body-padded" style="padding-bottom:0">
      <h3 style="margin:0 0 4px">Mi estante de favoritos</h3>
      <p class="text-muted-sm" style="margin:0 0 12px">Tus regulaciones marcadas, a la mano.</p>
    </div>
    <div class="reg-estante" id="regEstante" data-ruta-show="{{ url('regulaciones') }}">
      <span class="reg-estante-vacio" id="regEstanteVacio" @if($favoritas->count()) style="display:none" @endif>
        Aún no tienes favoritos. Marca el corazón de una regulación para guardarla aquí.
      </span>
      @foreach($favoritas as $fav)
        <a href="{{ route('regulaciones.show', $fav) }}" class="reg-lomo" data-id="{{ $fav->id }}" title="{{ $fav->nombre }}">
          <span>{{ $fav->nombre }}</span>
        </a>
      @endforeach
    </div>
  </div>

  {{-- Filtros --}}
  <form method="GET" action="{{ route('regulaciones.index') }}" class="card">
    <div class="card-body-padded wizard-fields">
      <div class="field">
        <label class="label-meta">Búsqueda</label>
        <input type="text" name="q" value="{{ request('q') }}" placeholder="Nombre de la regulación">
      </div>
      <div class="field">
        <label class="label-meta">Estatus</label>
        <select name="estatus">
          <option value="">Todos</option>
          @foreach($estatuses as $e)
            <option value="{{ $e }}" {{ request('estatus') === $e ? 'selected' : '' }}>
              {{ ucfirst(str_replace('_', ' ', $e)) }}
            </option>
          @endforeach
        </select>
      </div>
      @if($verDependencia)
      <div class="field">
        <label class="label-meta">Dependencia</label>
        <select name="dependencia">
          <option value="">Todas</option>
          @foreach($dependencias as $d)
            <option value="{{ $d->id }}" {{ request('dependencia') == $d->id ? 'selected' : '' }}>
              {{ $d->nombre }}
            </option>
          @endforeach
        </select>
      </div>
      @endif
      <div class="field" style="display:flex;align-items:flex-end;gap:8px">
        <button type="submit" class="btn btn-outline">Filtrar</button>
        @if(request()->hasAny(['q', 'estatus', 'dependencia']))
          <a href="{{ route('regulaciones.index') }}" class="btn btn-outline">Limpiar</a>
        @endif
      </div>
    </div>
  </form>

  {{-- Catálogo de regulaciones como libros con apertura 3D --}}
  @if($regulaciones->isEmpty())
    <div class="card">
      <div class="text-center u-empty-lg">
        <h3 style="margin:0 0 8px">Aún no hay regulaciones registradas</h3>
        <p class="text-muted-sm">Sube el primer archivo PDF o Word para iniciar el catálogo.</p>
        @if(auth()->user()->tienePermiso('regulaciones.crear'))
          <a href="{{ route('regulaciones.create') }}" class="btn btn-blanco mt-4">Subir primera regulación</a>
        @endif
      </div>
    </div>
  @else
    <div class="reg-catalogo">
      @foreach($regulaciones as $reg)
        @php $esFav = in_array($reg->id, $favoritasIds); @endphp
        <div class="reg-frame">
          <button type="button" class="reg-corazon" aria-pressed="{{ $esFav ? 'true' : 'false' }}"
                  aria-label="Marcar como favorita"
                  data-id="{{ $reg->id }}"
                  data-nombre="{{ $reg->nombre }}"
                  data-url="{{ route('regulaciones.favorita', $reg) }}">
            &#9829;
          </button>
          <div class="reg-book">
            <div class="reg-inside">
              <div>
                @if($verDependencia)<div class="reg-inside-meta">{{ $reg->dependencia->nombre ?? 'Sin dependencia' }}</div>@endif
                <div class="reg-inside-resumen">{{ \Illuminate\Support\Str::limit($reg->resumen ?? 'Sin resumen.', 90) }}</div>
              </div>
              <div class="reg-inside-acciones">
                <a href="{{ route('regulaciones.show', $reg) }}">Ver</a>
              </div>
            </div>
            <a href="{{ route('regulaciones.show', $reg) }}" class="reg-cover" data-tipo="{{ strtolower($reg->tipo ?? '') }}">
              <div class="reg-cover-spine"></div>
              <div>
                <div class="reg-cover-tipo">{{ strtoupper($reg->tipo ?? 'Regulación') }}</div>
                <div class="reg-cover-titulo">{{ $reg->nombre }}</div>
              </div>
              <div class="reg-cover-pie">
                {{-- Bug #29: badge de estatus visible en la portada --}}
                @php $estatusEf = $reg->estatusEfectivo(); @endphp
                <span class="reg-estatus-badge reg-estatus-{{ $estatusEf }}">{{ ucfirst(str_replace('_', ' ', $estatusEf)) }}</span>
                · {{ optional($reg->fecha_publicacion)->format('Y') ?? '' }}

                {{-- ── ESTADO DE LA CONVERSIÓN, EN LA PORTADA ──

                     La conversión ahora ocurre en segundo plano. Sin este indicador, un
                     usuario que sube diez regulaciones no tiene forma de saber cuáles ya
                     están listas y cuáles siguen procesándose, salvo entrando a cada una.

                     Y lo más importante: sin él, una conversión FALLIDA es invisible desde
                     el catálogo. La regulación se ve igual que las demás, y nadie descubre
                     que su texto nunca se extrajo hasta que intenta citarla y no puede.

                     Solo se pinta cuando hay algo que decir: una conversión terminada bien
                     no necesita adorno, y un catálogo lleno de etiquetas verdes es un
                     catálogo donde nadie mira las etiquetas. --}}
                @if($reg->conversion_estatus === \App\Models\Regulacion::CONVERSION_PROCESANDO)
                  <span class="reg-estatus-badge" style="background:#fffbeb;color:#92400e" title="Se está extrayendo el texto del documento">⏳ Convirtiendo</span>
                @elseif($reg->conversion_estatus === \App\Models\Regulacion::CONVERSION_ERROR)
                  <span class="reg-estatus-badge" style="background:var(--chip-red-bg);color:var(--chip-red)" title="{{ $reg->conversion_error }}">⚠ Sin convertir</span>
                @endif
              </div>
            </a>
          </div>
        </div>
      @endforeach
    </div>
    <div class="card-body-padded">{{ $regulaciones->links() }}</div>
  @endif

</div>
@endsection

@push('scripts')
@php
    $regFavJs = public_path('js/regulaciones-favoritas.js');
    $regFavVer = file_exists($regFavJs) ? filemtime($regFavJs) : null;
@endphp
<script src="{{ asset('js/regulaciones-favoritas.js') }}{{ $regFavVer ? '?v=' . $regFavVer : '' }}"></script>
@endpush