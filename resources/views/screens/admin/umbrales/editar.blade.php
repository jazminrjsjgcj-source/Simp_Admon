@extends('layouts.app')
@section('title', 'Editar umbral')

@section('content')
<div class="page-narrow">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Editar umbral</h2>
      <p class="nowrap">{{ $umbral->sector->nombre ?? 'Todos los sectores' }} {{ $umbral->subsector ? ' / ' . $umbral->subsector->nombre : '' }}</p>
    </div>
    <div class="head-actions">
      <a href="{{ route('admin.umbrales.index') }}" class="btn btn-outline">Cancelar</a>
    </div>
  </div>

  @if(session('error'))
    <div class="card-body-padded u-error-box">
      <p style="margin:0;color:#991B1B">{{ session('error') }}</p>
    </div>
  @endif

  @if($errors->any())
    <div class="card-body-padded u-error-box">
      <ul class="error-list">@foreach($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
    </div>
  @endif

  <form method="POST" action="{{ route('admin.umbrales.actualizar', $umbral) }}" id="formUmbral">
    @csrf @method('PUT')
    <div class="card">
      <div class="card-body-padded wizard-fields">

        <x-selector-scian
          :sector="old('sector_id', $umbral->sector_id)"
          :subsector="old('subsector_id', $umbral->subsector_id)" />

        <div class="field">
          <label>Año *</label>
          <input required type="number" name="anio" value="{{ old('anio', $umbral->anio) }}">
        </div>

        <div class="field">
          <label>Estado *</label>
          <select required name="estatus">
            <option value="activo"   {{ old('estatus', $umbral->estatus) === 'activo'   ? 'selected' : '' }}>Activo</option>
            <option value="inactivo" {{ old('estatus', $umbral->estatus) === 'inactivo' ? 'selected' : '' }}>Inactivo</option>
          </select>
        </div>

        <div class="field">
          <label>Monto base *</label>
          <input required type="number" step="0.0001" min="0" name="monto_base" value="{{ old('monto_base', $umbral->monto_base) }}">
        </div>

        <div class="field">
          <label>Unidad base *</label>
          <select required name="unidad_base">
            @foreach(['pesos','UMA','salario_minimo','UDI'] as $u)
              <option value="{{ $u }}" {{ old('unidad_base', $umbral->unidad_base) === $u ? 'selected' : '' }}>{{ $u }}</option>
            @endforeach
          </select>
        </div>

        <div class="field">
          <label>Vigencia desde</label>
          <input type="date" name="vigencia_inicio" value="{{ old('vigencia_inicio', optional($umbral->vigencia_inicio)->format('Y-m-d')) }}">
        </div>

        <div class="field">
          <label>Vigencia hasta</label>
          <input type="date" name="vigencia_fin" value="{{ old('vigencia_fin', optional($umbral->vigencia_fin)->format('Y-m-d')) }}">
        </div>

        <div class="field span-2">
          <label>Fuente</label>
          <input type="text" name="fuente" value="{{ old('fuente', $umbral->fuente) }}">
        </div>

        <div class="field">
          <label>Fecha del documento fuente</label>
          <input type="date" name="fecha_fuente" value="{{ old('fecha_fuente', optional($umbral->fecha_fuente)->format('Y-m-d')) }}">
        </div>

        <div class="field span-2">
          <label>Observaciones</label>
          <textarea name="observaciones" rows="3">{{ old('observaciones', $umbral->observaciones) }}</textarea>
        </div>

      </div>

      <div class="card-body-padded">
        <span class="label-meta">Equivalencias actuales</span>
        <div class="modal-grid mt-2">
          <div class="modal-data-item"><span>Pesos</span><strong>${{ number_format($umbral->monto_pesos, 2) }}</strong></div>
          <div class="modal-data-item"><span>UMA</span><strong>{{ $umbral->monto_uma ? number_format($umbral->monto_uma, 2) : '' }}</strong></div>
          <div class="modal-data-item"><span>Salario mínimo</span><strong>{{ $umbral->monto_salario_minimo ? number_format($umbral->monto_salario_minimo, 2) : '' }}</strong></div>
        </div>
        <small class="help-small mt-2 d-flex">Se recalcularán automáticamente al guardar.</small>
      </div>

      <div class="card-actions card-actions-end">
        <a href="{{ route('admin.umbrales.index') }}" class="btn btn-outline">Cancelar</a>
        <button type="button" class="btn" onclick="document.getElementById('confirmModal').classList.add('open')">Guardar cambios</button>
      </div>
    </div>
  </form>

  <div class="confirm-modal-backdrop" id="confirmModal">
    <div class="confirm-modal">
      <h3>¿Confirmar cambio del umbral?</h3>
      <p>Este umbral afecta la clasificación de impacto de todos los trámites del sector. Los trámites mantendrán su impacto registrado hasta que se vuelvan a calcular.</p>
      <div class="confirm-modal-actions">
        <button type="button" class="btn btn-outline" onclick="document.getElementById('confirmModal').classList.remove('open')">Revisar</button>
        <button type="button" class="btn" onclick="document.getElementById('formUmbral').submit()">Sí, guardar</button>
      </div>
    </div>
  </div>

</div>
@endsection
