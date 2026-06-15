@extends('layouts.app')
@section('title', 'Configuración del Sistema')

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Configuración del Sistema</h2>
      <p class="nowrap">Módulos administrativos para parámetros, umbrales, unidades de valor y control de acceso.</p>
    </div>
  </div>

  <div class="wizard-fields">

    {{-- Parámetros del costo burocrático --}}
    @if(auth()->user()->tienePermiso('parametros.gestionar'))
    <a href="{{ route('admin.parametros.index') }}" class="card u-card-link">
      <div class="card-body-padded">
        <span class="label-meta">Cálculo de costos</span>
        <h3 class="u-card-title">Parámetros del costo burocrático</h3>
        <p class="text-muted-sm">Salario hora, precio de copia, jornada laboral, factor de días hábiles y días por mes.</p>
      </div>
    </a>
    @endif

    {{-- Unidades de valor --}}
    @if(auth()->user()->tienePermiso('unidades_valor.gestionar'))
    <a href="{{ route('admin.unidades-valor.index') }}" class="card u-card-link">
      <div class="card-body-padded">
        <span class="label-meta">Referencias monetarias</span>
        <h3 class="u-card-title">Unidades de valor</h3>
        <p class="text-muted-sm">Valores anuales de UMA, salario mínimo y UDI para conversión de umbrales y reportes.</p>
      </div>
    </a>
    @endif

    {{-- Umbrales configurados --}}
    @if(auth()->user()->tienePermiso('umbrales.gestionar'))
    <a href="{{ route('admin.umbrales.index') }}" class="card u-card-link">
      <div class="card-body-padded">
        <span class="label-meta">Impacto regulatorio</span>
        <h3 class="u-card-title">Umbrales configurados</h3>
        <p class="text-muted-sm">Montos por sector y subsector contra los cuales se clasifica el impacto de cada trámite.</p>
      </div>
    </a>
    @endif

    {{-- Control de acceso (ACL) --}}
    @if(auth()->user()->tienePermiso('acl.gestionar'))
    <a href="{{ route('admin.acl.index') }}" class="card u-card-link">
      <div class="card-body-padded">
        <span class="label-meta">Seguridad</span>
        <h3 class="u-card-title">Control de Acceso</h3>
        <p class="text-muted-sm">Administra roles, permisos y asignaciones del sistema. Consulta la bitácora de cambios ACL.</p>
      </div>
    </a>
    @endif

    {{-- Periodos --}}
    <a href="{{ route('admin.periodos') }}" class="card u-card-link">
      <div class="card-body-padded">
        <span class="label-meta">Gestión temporal</span>
        <h3 class="u-card-title">Periodos de captura</h3>
        <p class="text-muted-sm">Crear, activar y cerrar periodos de revisión. Solo puede haber un periodo activo a la vez.</p>
      </div>
    </a>

    {{-- Catálogos — Fase C --}}
    <a href="{{ route('admin.catalogos.index') }}" class="card u-card-link">
      <div class="card-body-padded">
        <span class="label-meta">Estructura orgánica</span>
        <h3 class="u-card-title">Catálogos</h3>
        <p class="text-muted-sm">Dependencias y unidades administrativas. Activar o desactivar sin eliminar.</p>
      </div>
    </a>

      </div>

</div>
@endsection
