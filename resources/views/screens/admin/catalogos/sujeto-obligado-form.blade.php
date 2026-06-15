@extends('layouts.app')
@section('title', $sujeto ? 'Editar sujeto obligado' : 'Nuevo sujeto obligado')

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">{{ $sujeto ? 'Editar sujeto obligado' : 'Nuevo sujeto obligado' }}</h2>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.catalogos.sujetos-obligados') }}" class="btn btn-outline">Cancelar</a>
    </div>
  </div>

  <form method="POST"
    action="{{ $sujeto
      ? route('admin.catalogos.sujetos-obligados.actualizar', $sujeto)
      : route('admin.catalogos.sujetos-obligados.guardar') }}">
    @csrf
    @if($sujeto) @method('PUT') @endif

    <div class="card">
      <div class="card-body-padded">
        <div class="wizard-fields">

          <div class="field span-2">
            <label for="dependencia_id">Dependencia *</label>
            <select id="dependencia_id" name="dependencia_id" required>
              <option value="">— Seleccione dependencia —</option>
              @foreach($dependencias as $dep)
                <option value="{{ $dep->id }}"
                  {{ old('dependencia_id', $sujeto?->dependencia_id) == $dep->id ? 'selected' : '' }}>
                  {{ $dep->nombre }}
                </option>
              @endforeach
            </select>
            @error('dependencia_id')<span class="field-error">{{ $message }}</span>@enderror
            <small class="help-small">Cada dependencia tiene un titular vigente. Si registras uno nuevo, recuerda desactivar el anterior.</small>
          </div>

          <div class="field span-2">
            <label for="nombre">Nombre *</label>
            <input id="nombre" name="nombre" type="text" maxlength="255" required
              placeholder="Ej. Lic. Ana Torres Gómez"
              value="{{ old('nombre', $sujeto?->nombre) }}">
            @error('nombre')<span class="field-error">{{ $message }}</span>@enderror
          </div>

          <div class="field span-2">
            <label for="cargo">Cargo</label>
            <input id="cargo" name="cargo" type="text" maxlength="255"
              placeholder="Ej. Directora General"
              value="{{ old('cargo', $sujeto?->cargo) }}">
            @error('cargo')<span class="field-error">{{ $message }}</span>@enderror
          </div>

        </div>
      </div>
      <div class="card-foot">
        <a href="{{ route('admin.catalogos.sujetos-obligados') }}" class="btn btn-outline">Cancelar</a>
        <button type="submit" class="btn btn-success">
          {{ $sujeto ? 'Guardar cambios' : 'Crear sujeto obligado' }}
        </button>
      </div>
    </div>
  </form>

</div>
@endsection
