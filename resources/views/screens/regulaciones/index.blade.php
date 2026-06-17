@extends('layouts.app')
@section('title', 'Regulaciones')

@section('content')
<div class="page-wide">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Catálogo de Regulaciones</h2>
      <p class="nowrap">Leyes y reglamentos vigentes citables desde los wizards.</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('regulaciones.descargar-zip', request()->only(['q', 'estatus', 'dependencia'])) }}" class="btn btn-outline">Descargar ZIP</a>
      <a href="{{ route('regulaciones.create') }}" class="btn">Subir regulación</a>
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
      <div class="field" style="display:flex;align-items:flex-end;gap:8px">
        <button type="submit" class="btn btn-outline">Filtrar</button>
        <a href="{{ route('regulaciones.index') }}" class="btn btn-outline">Limpiar</a>
      </div>
    </div>
  </form>

  {{-- Tabla de regulaciones --}}
  <div class="card">
    @if($regulaciones->isEmpty())
      <div class="text-center u-empty-lg">
        <h3 style="margin:0 0 8px">Aún no hay regulaciones registradas</h3>
        <p class="text-muted-sm">Sube el primer archivo PDF o Word para iniciar el catálogo.</p>
        <a href="{{ route('regulaciones.create') }}" class="btn mt-4">Subir primera regulación</a>
      </div>
    @else
      <table class="data-table">
        <thead>
          <tr>
            <th>Nombre</th>
            <th>Tipo</th>
            <th>Dependencia</th>
            <th>Estatus</th>
            <th>Conversión</th>
            <th>Acciones</th>
          </tr>
        </thead>
        <tbody>
          @foreach($regulaciones as $reg)
            <tr>
              <td><strong>{{ $reg->nombre }}</strong></td>
              <td>{{ $reg->tipo ?? '—' }}</td>
              <td>{{ $reg->dependencia->nombre ?? '—' }}</td>
              <td>
                <span class="chip chip-{{ $reg->estaVigente() ? 'success' : 'gray' }}">
                  {{ ucfirst(str_replace('_', ' ', $reg->estatus)) }}
                </span>
              </td>
              <td>
                @switch($reg->conversion_estatus)
                  @case('listo')      <span class="chip chip-success">Lista</span> @break
                  @case('procesando') <span class="chip chip-amber">Procesando</span> @break
                  @case('error')      <span class="chip chip-red">Error</span> @break
                  @default            <span class="chip chip-gray">Pendiente</span>
                @endswitch
              </td>
              <td>
                <a href="{{ route('regulaciones.show', $reg) }}" class="btn btn-outline btn-sm">Ver</a>
                @if(auth()->user()->puedeEditarRegulacion($reg))
                  <a href="{{ route('regulaciones.edit', $reg) }}" class="btn btn-outline btn-sm">Editar</a>
                @endif
              </td>
            </tr>
          @endforeach
        </tbody>
      </table>
      <div class="card-body-padded">{{ $regulaciones->links() }}</div>
    @endif
  </div>

</div>
@endsection
