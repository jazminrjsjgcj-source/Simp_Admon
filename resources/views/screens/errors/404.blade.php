@extends('layouts.app')
@section('title', 'Página no encontrada')
@section('content')
<div class="page-default" style="min-height:60vh;display:flex;align-items:center;justify-content:center">
  <div style="text-align:center;max-width:480px">
    <p style="font-size:64px;font-weight:700;color:var(--muted-light);margin:0;line-height:1">404</p>
    <h2 style="margin:12px 0 8px;font-size:20px">Página no encontrada</h2>
    <p style="color:var(--muted);line-height:1.6;margin-bottom:24px">
      La página que buscas no existe o fue movida. Verifica la dirección o vuelve al inicio.
    </p>
    <a href="{{ url('/') }}" class="btn">Ir al inicio</a>
  </div>
</div>
@endsection
