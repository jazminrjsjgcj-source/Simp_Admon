{{--
  Timeline lateral de un registro.

  Uso: @include('partials.timeline', ['tipo' => 'propuesta', 'id' => $propuesta->id])

  Consulta la bitácora del registro y la pinta como línea de tiempo
  vertical (el más reciente arriba). Cada evento muestra fecha, acción,
  quién la hizo y qué cambió. Es reutilizable en propuesta, trámite,
  agenda y regulación.
--}}
@php
  $mapaClases = [
    'tramite'    => \App\Models\Tramite::class,
    'agenda'     => \App\Models\AccionAgenda::class,
    'propuesta'  => \App\Models\PropuestaRegulatoria::class,
    'regulacion' => \App\Models\Regulacion::class,
    'air'        => \App\Models\AnalisisImpactoRegulatorio::class,
  ];
  $claseModelo = $mapaClases[$tipo] ?? null;

  $eventos = collect();
  if ($claseModelo) {
      $eventos = \Illuminate\Support\Facades\DB::table('bitacora')
          ->leftJoin('users', 'bitacora.usuario_id', '=', 'users.id')
          ->where('bitacora.auditable_type', $claseModelo)
          ->where('bitacora.auditable_id', $id)
          ->orderByDesc('bitacora.created_at')
          ->select(
              'bitacora.accion',
              'bitacora.tipo',
              'bitacora.detalle',
              'bitacora.created_at',
              'users.name as usuario_nombre'
          )
          ->get();
  }
@endphp

<div class="card card-pad">
  <h3 class="timeline-titulo">Historial</h3>
  <p class="timeline-subtitulo">Todo lo que ha pasado con este registro.</p>

  @if($eventos->isEmpty())
    <p class="timeline-vacio">Aún no hay movimientos registrados.</p>
  @else
    <div class="timeline">
      @foreach($eventos as $ev)
        <div class="timeline-evento {{ $ev->tipo === 'created' ? 'es-creacion' : ($ev->tipo === 'deleted' ? 'es-eliminacion' : '') }}">
          <div class="timeline-fecha">{{ \Carbon\Carbon::parse($ev->created_at)->format('d/m/Y H:i') }}</div>
          <div class="timeline-accion">{{ $ev->accion }}</div>
          <div class="timeline-usuario">{{ $ev->usuario_nombre ?? 'Sistema' }}</div>
          @if($ev->detalle)
            <div class="timeline-cambios">
              @foreach(explode(' | ', $ev->detalle) as $cambio)
                <div class="timeline-cambio-linea">{{ $cambio }}</div>
              @endforeach
            </div>
          @endif
        </div>
      @endforeach
    </div>
  @endif
</div>
