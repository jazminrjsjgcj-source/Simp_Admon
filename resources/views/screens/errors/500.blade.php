@extends('layouts.app')
@section('title', 'Error del sistema')
@section('content')
<div class="page-default" style="min-height:60vh;display:flex;align-items:center;justify-content:center">
  <div style="text-align:center;max-width:480px">
    <p style="font-size:64px;font-weight:700;color:var(--chip-red);margin:0;line-height:1">500</p>
    <h2 style="margin:12px 0 8px;font-size:20px">Error del sistema</h2>
    <p style="color:var(--muted);line-height:1.6;margin-bottom:24px">
      Algo salió mal al procesar tu solicitud. El equipo técnico ya fue notificado. Intenta de nuevo en unos minutos.
    </p>
    <a href="{{ url('/') }}" class="btn">Ir al inicio</a>
  </div>
</div>
@endsection
