@extends('layouts.app')
@section('title', 'Acceso denegado')
@section('content')
<div class="page-default" style="min-height:60vh;display:flex;align-items:center;justify-content:center">
  <div style="text-align:center;max-width:480px">
    <p style="font-size:64px;font-weight:700;color:var(--primary);margin:0;line-height:1">403</p>
    <h2 style="margin:12px 0 8px;font-size:20px">Acceso denegado</h2>
    <p style="color:var(--muted);line-height:1.6;margin-bottom:24px">
      {{ $exception->getMessage() ?: 'No tienes permiso para acceder a esta sección. Si crees que es un error, contacta al administrador del sistema.' }}
    </p>
    <a href="{{ url('/') }}" class="btn">Ir al inicio</a>
  </div>
</div>
@endsection
