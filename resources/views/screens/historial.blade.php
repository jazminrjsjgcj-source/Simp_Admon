@extends('layouts.app')
@section('title', 'Historial del registro')

@section('content')
<div class="page-wide">

  <div class="screen-head">
    <div>
      <h2 class="nowrap">Historial del registro</h2>
      <p class="nowrap">Todas las acciones realizadas sobre este registro: quién, cuándo y qué cambió.</p>
    </div>
    <div class="head-actions">
      <a href="{{ url()->previous() }}" class="btn btn-outline">← Volver</a>
    </div>
  </div>

  <div class="card">
    @if($movimientos->isEmpty())
      <div class="text-center u-empty-lg">
        <h3>Sin movimientos registrados</h3>
        <p class="text-muted-sm">Aún no hay acciones registradas para este registro.</p>
      </div>
    @else
      <div class="table-wrap">
        <table class="data-table">
          <thead>
            <tr>
              <th>Fecha y hora</th>
              <th>Acción</th>
              <th>Realizada por</th>
              <th>Cambios</th>
              <th>IP</th>
            </tr>
          </thead>
          <tbody>
            @foreach($movimientos as $m)
              <tr>
                <td class="u-nowrap">{{ \Carbon\Carbon::parse($m->created_at)->format('d/m/Y H:i') }}</td>
                <td>
                  <span class="badge {{ match($m->tipo) {
                    'created' => 'success-b',
                    'deleted' => 'danger-b',
                    default   => '',
                  } }}">
                    {{ $m->accion }}
                  </span>
                </td>
                <td><strong>{{ $m->usuario_nombre ?? 'Sistema' }}</strong></td>
                <td>
                  @if($m->detalle)
                    {{-- Cada cambio viene como "campo: [viejo] -> [nuevo]" separado por | --}}
                    <div class="u-text-sm">
                      @foreach(explode(' | ', $m->detalle) as $cambio)
                        <div class="historial-cambio">{{ $cambio }}</div>
                      @endforeach
                    </div>
                  @else
                    <span class="text-muted-sm">—</span>
                  @endif
                </td>
                <td class="help-small">{{ $m->ip_address ?? '—' }}</td>
              </tr>
            @endforeach
          </tbody>
        </table>
      </div>
      <div class="card-body-padded">{{ $movimientos->appends(request()->query())->links() }}</div>
    @endif
  </div>

</div>
@endsection
