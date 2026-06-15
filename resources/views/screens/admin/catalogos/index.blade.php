@extends('layouts.app')
@section('title', 'Catálogos')

@section('content')
<div class="page-default">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Catálogos</h2>
      <p class="nowrap">Gestión de dependencias y unidades administrativas del sistema.</p>
    </div>
  </div>

  <div class="wizard-fields">
    <a href="{{ route('admin.catalogos.dependencias') }}" class="card u-card-link">
      <div class="card-body-padded">
        <span class="label-meta">Estructura orgánica</span>
        <h3 class="u-card-title">Dependencias</h3>
        <p class="text-muted-sm">Alta, edición y activación/desactivación de dependencias. Históricas o activas.</p>
      </div>
    </a>

    <a href="{{ route('admin.catalogos.unidades') }}" class="card u-card-link">
      <div class="card-body-padded">
        <span class="label-meta">Áreas de servicio</span>
        <h3 class="u-card-title">Unidades administrativas</h3>
        <p class="text-muted-sm">Unidades que atienden trámites dentro de cada dependencia.</p>
      </div>
    </a>

    <a href="{{ route('admin.catalogos.sujetos-obligados') }}" class="card u-card-link">
      <div class="card-body-padded">
        <span class="label-meta">Titulares</span>
        <h3 class="u-card-title">Sujetos obligados</h3>
        <p class="text-muted-sm">Persona titular o responsable de cada dependencia.</p>
      </div>
    </a>
    <a href="{{ route('admin.catalogos.tipos-regulacion') }}" class="card u-card-link">
      <div class="card-body-padded">
        <span class="label-meta">Agenda regulatoria</span>
        <h3 class="u-card-title">Tipos de regulación</h3>
        <p class="text-muted-sm">Reglamento, Acuerdo, Lineamiento, Circular… Alimentan propuestas y regulaciones.</p>
      </div>
    </a>

    <a href="{{ route('admin.catalogos.tipos-tramite') }}" class="card u-card-link">
      <div class="card-body-padded">
        <span class="label-meta">Trámites</span>
        <h3 class="u-card-title">Tipos de trámite</h3>
        <p class="text-muted-sm">Licencia, Permiso, Registro, Certificado… Clasifican cada trámite registrado.</p>
      </div>
    </a>

    <a href="{{ route('admin.catalogos.sectores') }}" class="card u-card-link">
      <div class="card-body-padded">
        <span class="label-meta">SCIAN México 2018</span>
        <h3 class="u-card-title">Sectores económicos SCIAN</h3>
        <p class="text-muted-sm">23 sectores y 94 subsectores para clasificar el impacto económico de trámites y propuestas.</p>
      </div>
    </a>

  </div>

</div>
@endsection
