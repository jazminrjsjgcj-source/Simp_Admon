{{--
  Componente reusable de chip de estatus.
  Bug #B15: centraliza el mapeo "estatus técnico → clase CSS de color".

  Uso:
    <x-badge-estatus :estatus="$tramite->estatus" />
    <x-badge-estatus :estatus="$agenda->estatus" mayuscula />
    <x-badge-estatus :estatus="$prop->estatus ?? 'borrador'" />

  Antes era código duplicado tipo:
    <span class="badge {{ match($t->estatus){'completado'=>'success-b',...} }}">
      @estatus($t->estatus)
    </span>

  Ahora esa lógica vive en UN solo lugar (este componente). Si el sistema agrega un
  estado nuevo, basta con extender el mapa aquí.
--}}

@props([
    'estatus' => null,
    'mayuscula' => false,
])

@php
    // Mapeo único de estatus → clase CSS de chip. Cualquier estado que no esté
    // listado cae en 'neutral-b' (gris suave), nunca queda sin color.
    $clasesPorEstatus = [
        // Trámites y Agenda SyD (vocabulario homologado)
        'borrador'         => 'neutral-b',
        'en_observacion'   => 'warning-b',
        'en_correccion'    => 'warning-b',
        'en_firma'         => 'info-b',
        'completado'       => 'success-b',
        // Propuestas regulatorias
        'consulta'         => 'info-b',
        'determinada'      => 'accent-b',
        'dictaminada'      => 'success-b',
        'publicada'        => 'success-b',
        // Regulaciones
        'vigente'          => 'success-b',
        'en_revision'      => 'warning-b',
        'derogada'         => 'neutral-b',
        // Estados satélite frecuentes (AIR, exención, observaciones)
        'pendiente'        => 'warning-b',
        'enviado'          => 'info-b',
        'en_dictamen'      => 'info-b',
        'dictaminado'      => 'success-b',
        'aprobada'         => 'success-b',
        'rechazada'        => 'danger-b',
        'rechazado'        => 'danger-b',
        'condicionado'     => 'warning-b',
        'solicitada'       => 'info-b',
    ];

    $valor = $estatus ?? 'borrador';
    $clase = $clasesPorEstatus[$valor] ?? 'neutral-b';
    $estiloMayuscula = $mayuscula ? 'text-transform:uppercase' : '';
@endphp

<span class="badge {{ $clase }}" @if($mayuscula) style="text-transform:uppercase" @endif>
    @estatus($valor)
</span>
