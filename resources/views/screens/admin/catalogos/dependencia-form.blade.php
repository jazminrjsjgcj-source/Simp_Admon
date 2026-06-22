@extends('layouts.app')
@section('title', $dependencia ? 'Editar dependencia' : 'Nueva dependencia')

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">{{ $dependencia ? 'Editar dependencia' : 'Nueva dependencia' }}</h2>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.catalogos.dependencias') }}" class="btn btn-outline">Cancelar</a>
    </div>
  </div>

  <form method="POST"
    action="{{ $dependencia
      ? route('admin.catalogos.dependencias.actualizar', $dependencia)
      : route('admin.catalogos.dependencias.guardar') }}">
    @csrf
    @if($dependencia) @method('PUT') @endif

    <div class="card">
      <div class="card-body-padded">
        <div class="wizard-fields">

          <div class="field">
            <label for="codigo">Código *</label>
            <input id="codigo" name="codigo" type="text" maxlength="10" required
              placeholder="Ej. 000"
              value="{{ old('codigo', $dependencia?->codigo) }}">
            @error('codigo')<span class="field-error">{{ $message }}</span>@enderror
            <small class="help-small">Identificador corto único (máx. 10 caracteres).</small>
          </div>

          <div class="field">
            <label for="nombre">Nombre oficial *</label>
            <input id="nombre" name="nombre" type="text" maxlength="255" required
              placeholder="Ej. H. Ayuntamiento de La Paz"
              value="{{ old('nombre', $dependencia?->nombre) }}">
            @error('nombre')<span class="field-error">{{ $message }}</span>@enderror
          </div>

        </div>
      </div>
      <div class="card-foot">
        <a href="{{ route('admin.catalogos.dependencias') }}" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-success">
          {{ $dependencia ? 'Guardar cambios' : 'Crear dependencia' }}
        </button>
      </div>
    </div>
  </form>

  {{-- Unidades administrativas ligadas: solo al editar una dependencia que ya existe --}}
  @if($dependencia)
  <div class="card" style="margin-top:24px">
    <div class="panel-head">
      <div>
        <h3>Unidades administrativas</h3>
        <p>Unidades ligadas a esta dependencia.</p>
      </div>
    </div>
    <div class="card-body-padded">

      {{-- Lista de unidades existentes --}}
      @if($unidades->isNotEmpty())
        <div class="table-wrap">
          <table class="data-table">
            <thead>
              <tr><th>Código</th><th>Nombre</th><th>Estado</th><th class="table-action-cell">Acciones</th></tr>
            </thead>
            <tbody>
              @foreach($unidades as $uni)
                <tr>
                  <td>{{ $uni->codigo }}</td>
                  <td>{{ $uni->nombre }}</td>
                  <td>
                    @if($uni->activo ?? true)
                      <span class="badge chip-green-b">Activa</span>
                    @else
                      <span class="badge">Inactiva</span>
                    @endif
                  </td>
                  <td class="table-action-cell">
                    <div class="table-actions" style="display:flex;gap:6px">
                      {{-- Activar / desactivar --}}
                      <form method="POST" action="{{ route('admin.catalogos.unidades.toggle', $uni) }}" class="u-inline">
                        @csrf
                        <input type="hidden" name="volver_a_dependencia" value="{{ $dependencia->id }}">
                        <button type="submit" class="btn btn-outline btn-sm">
                          {{ ($uni->activo ?? true) ? 'Desactivar' : 'Activar' }}
                        </button>
                      </form>
                      {{-- Eliminar (solo borra si no tiene nada ligado; el controlador valida) --}}
                      <form method="POST" action="{{ route('admin.catalogos.unidades.eliminar', $uni) }}" class="u-inline"
                        onsubmit="return confirmarAccion(this, '¿Eliminar la unidad {{ $uni->nombre }}? Solo se eliminará si no tiene trámites ni usuarios ligados.')">
                        @csrf
                        @method('DELETE')
                        <input type="hidden" name="volver_a_dependencia" value="{{ $dependencia->id }}">
                        <button type="submit" class="btn btn-outline btn-sm">Eliminar</button>
                      </form>
                    </div>
                  </td>
                </tr>
              @endforeach
            </tbody>
          </table>
        </div>
      @else
        <div class="assist-box">Esta dependencia aún no tiene unidades administrativas.</div>
      @endif

      {{-- Formulario para agregar una unidad nueva, ya ligada a esta dependencia --}}
      <div style="margin-top:18px;border-top:1px solid var(--surface-high);padding-top:18px">
        <h4 style="margin:0 0 12px;font-size:14px;font-weight:700;color:var(--text)">Agregar unidad</h4>
        <form method="POST" action="{{ route('admin.catalogos.unidades.guardar') }}">
          @csrf
          <input type="hidden" name="dependencia_id" value="{{ $dependencia->id }}">
          <input type="hidden" name="volver_a_dependencia" value="{{ $dependencia->id }}">
          <div style="display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
            <div class="field" style="flex:0 0 140px">
              <label for="uni_codigo">Código *</label>
              <input id="uni_codigo" name="codigo" type="text" maxlength="10" required placeholder="Ej. DEJ">
              @error('codigo')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field" style="flex:1;min-width:200px">
              <label for="uni_nombre">Nombre *</label>
              <input id="uni_nombre" name="nombre" type="text" maxlength="255" required placeholder="Ej. Departamento de Enlace Jurídico">
              @error('nombre')<span class="field-error">{{ $message }}</span>@enderror
            </div>
            <div class="field" style="flex:0 0 auto">
              <button type="submit" class="btn btn-sm">Agregar unidad</button>
            </div>
          </div>
        </form>
      </div>

    </div>
  </div>
  @endif

</div>
@endsection
