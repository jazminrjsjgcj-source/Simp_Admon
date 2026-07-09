{{--
  Componente: <x-selector-scian :sector="$sector_id" :subsector="$subsector_id" />

  Renderiza dos selects dependientes (sector → subsector) usando el catálogo
  oficial SCIAN México. Al cambiar el sector se filtra el subsector.

  Atributos:
    sector      → ID del sector preseleccionado (opcional)
    subsector   → ID del subsector preseleccionado (opcional)
    name_sector → name del campo sector (por defecto 'sector_id')
    name_sub    → name del campo subsector (por defecto 'subsector_id')
    required    → true/false
--}}
@props([
    'sector'      => null,
    'subsector'   => null,
    'name_sector' => 'sector_id',
    'name_sub'    => 'subsector_id',
    'required'    => false,
])

@php
    $sectores    = \App\Models\SectorScian::orderBy('codigo')->get(['id', 'codigo', 'nombre']);
    $subsectores = \App\Models\SubsectorScian::orderBy('codigo')->get(['id', 'codigo', 'nombre', 'sector_id']);
    $uid         = 'scian_' . uniqid();
@endphp

<div class="field">
    <label>Sector económico (SCIAN) @if($required)*@endif</label>
    <select name="{{ $name_sector }}" {{ $required ? 'required' : '' }} id="{{ $uid }}_sector">
        <option value="">Seleccione una opción</option>
        @foreach($sectores as $s)
            <option value="{{ $s->id }}" {{ (string) $sector === (string) $s->id ? 'selected' : '' }}>
                {{ $s->codigo }} — {{ $s->nombre }}
            </option>
        @endforeach
    </select>
</div>

<div class="field">
    <label>Subsector económico</label>
    <select name="{{ $name_sub }}" id="{{ $uid }}_subsector" {{ $sector ? '' : 'disabled' }}>
        <option value="">Seleccione primero un sector</option>
        @foreach($subsectores as $sub)
            <option value="{{ $sub->id }}"
                    data-sector="{{ $sub->sector_id }}"
                    {{ (string) $subsector === (string) $sub->id ? 'selected' : '' }}>
                {{ $sub->codigo }} — {{ $sub->nombre }}
            </option>
        @endforeach
    </select>
</div>

<script>
(function () {
    var sectorSel    = document.getElementById('{{ $uid }}_sector');
    var subsectorSel = document.getElementById('{{ $uid }}_subsector');
    if (!sectorSel || !subsectorSel) return;

    var todasOpciones = Array.from(subsectorSel.querySelectorAll('option[data-sector]'));

    function filtrarSubsectores() {
        var sectorId = sectorSel.value;
        subsectorSel.innerHTML = '<option value="">Seleccione una opción</option>';
        todasOpciones.forEach(function (opt) {
            if (opt.dataset.sector === sectorId) {
                subsectorSel.appendChild(opt.cloneNode(true));
            }
        });
        // Ítem B: el subsector queda bloqueado mientras no haya sector elegido.
        subsectorSel.disabled = !sectorId;
    }

    sectorSel.addEventListener('change', filtrarSubsectores);

    // Si hay preselección, aplica el filtro inicial respetando el subsector seleccionado
    if (sectorSel.value) {
        var preservar = subsectorSel.value;
        filtrarSubsectores();
        if (preservar) {
            var opcion = subsectorSel.querySelector('option[value="' + preservar + '"]');
            if (opcion) opcion.selected = true;
        }
    }
})();
</script>