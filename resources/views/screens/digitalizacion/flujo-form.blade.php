@extends('layouts.app')
@section('title', 'Flujo del proceso')
@section('content')
<div class="page-wide">

  <div class="screen-head">
    <div>
      <h2>Flujo del proceso</h2>
      <p>{{ $tramite->nombre_oficial }} — reingeniería v{{ $reingenieria->version }}</p>
    </div>
    <div class="head-actions">
      <button type="button" class="btn btn-outline btn-sm" onclick="ejemploFlujo()"
        title="Llena el formulario con un proceso de ejemplo para ver cómo se captura">
        Ejemplo de llenado
      </button>
      <button type="button" class="btn btn-outline btn-sm" onclick="vaciarFlujo()"
        title="Vacía el formulario">
        Limpiar
      </button>
      <a href="{{ route('digitalizacion.show', $tramite) }}" class="btn btn-outline">Volver</a>
    </div>
  </div>

  @if($errors->any())
    <div class="card card-pad" style="border-left:4px solid #dc2626;margin-bottom:18px">
      <strong>Revise lo siguiente antes de guardar:</strong>
      <ul style="margin:8px 0 0;padding-left:18px">
        @foreach($errors->all() as $error)
          <li style="font-size:13px">{{ $error }}</li>
        @endforeach
      </ul>
    </div>
  @endif

  <form method="POST" action="{{ route('digitalizacion.flujo.guardar', $reingenieria) }}" id="flujoForm">
    @csrf
    @method('PUT')

    {{-- ── 1. Datos generales ───────────────────────────────────────── --}}
    <div class="card card-pad" style="margin-bottom:18px">
      <h3 class="flujo-titulo">1. Datos generales</h3>

      <div class="field">
        <label>Nombre del proceso *</label>
        <input type="text" name="proceso_nombre" maxlength="300" required
          value="{{ old('proceso_nombre', $reingenieria->proceso_nombre) }}">
      </div>

      <div class="flujo-grid-2">
        <div class="field">
          <label>¿Genera un documento o resolutivo?</label>
          <select name="resolutivo_tipo">
            <option value="">No genera</option>
            @foreach(config('punta.flujo.tipos_resolutivo') as $clave => $texto)
              <option value="{{ $clave }}" @selected(old('resolutivo_tipo', $reingenieria->resolutivo_tipo) === $clave)>{{ $texto }}</option>
            @endforeach
          </select>
        </div>

        <div class="field">
          <label>Nombre del documento</label>
          <input type="text" name="resolutivo_nombre" maxlength="300"
            placeholder="Ej. Permiso para vendedor ambulante"
            value="{{ old('resolutivo_nombre', $reingenieria->resolutivo_nombre) }}">
        </div>
      </div>

      <div class="flujo-grid-2">
        <div class="field">
          <label>¿Cómo inicia el proceso?</label>
          <input type="text" name="inicia_con" maxlength="500"
            value="{{ old('inicia_con', $reingenieria->inicia_con) }}">
        </div>
        <div class="field">
          <label>¿Cómo termina?</label>
          <input type="text" name="termina_con" maxlength="500"
            value="{{ old('termina_con', $reingenieria->termina_con) }}">
        </div>
      </div>
    </div>

    {{-- ── 2. Participantes ─────────────────────────────────────────── --}}
    <div class="card card-pad" style="margin-bottom:18px">
      <h3 class="flujo-titulo">2. Participantes</h3>
      <p class="flujo-ayuda">Quién interviene en el proceso. El color de cada uno en el
        diagrama sale de su tipo, no se elige.</p>

      <div id="participantes"></div>
      <button type="button" class="flujo-agregar" onclick="agregarParticipante()">+ Agregar participante</button>
    </div>

    {{-- ── 3. Resultados finales ────────────────────────────────────── --}}
    <div class="card card-pad" style="margin-bottom:18px">
      <h3 class="flujo-titulo">3. Formas en que puede terminar</h3>
      <p class="flujo-ayuda">No todos los finales son el documento emitido: también se
        termina porque la solicitud no procede o porque se cancela.</p>

      <div id="resultados"></div>
      <button type="button" class="flujo-agregar" onclick="agregarResultado()">+ Agregar resultado</button>
    </div>

    {{-- ── 4. Fases y actividades ───────────────────────────────────── --}}
    <div class="card card-pad" style="margin-bottom:18px">
      <h3 class="flujo-titulo">4. Fases y actividades</h3>
      <p class="flujo-ayuda">Las actividades se numeran solas de corrido. Una actividad que
        revisa algo necesita decir qué pasa cuando sale bien y cuando sale mal.</p>

      <div id="fases"></div>
      <button type="button" class="flujo-agregar" onclick="agregarFase()">+ Agregar fase</button>
    </div>

    <div class="form-actions">
      <a href="{{ route('digitalizacion.show', $tramite) }}" class="btn btn-outline">Cancelar</a>
      <button type="submit" class="btn">Guardar flujo</button>
    </div>
  </form>
</div>

@push('scripts')
<script>
// ── Datos que el formulario necesita, desde PHP ────────────────────────
//
// Los arma el controlador y aquí solo se reciben ya listos: transformar datos no es
// trabajo de la plantilla, y además una directiva con arreglos anidados escritos a
// lo largo de varias líneas no compila.
//
// Ojo al escribir en este archivo: Blade no distingue un comentario de JavaScript
// del resto del texto, así que una arroba seguida del nombre de una directiva se
// compila aunque esté dentro de //. Para nombrarla en un comentario, se dobla la
// arroba.
const CAT = {
  participantes: @json(config('punta.flujo.tipos_participante')),
  destinos:      @json(config('punta.flujo.destinos_ruta')),
  pagos:         @json(config('punta.flujo.acciones_pago')),
  derechos:      @json($derechos),
};

// El flujo ya capturado, para reabrir el formulario con todo puesto.
const GUARDADO = @json($guardado);

// ── Contadores ────────────────────────────────────────────────────────
//
// Cada elemento lleva una CLAVE estable (p1, f2, a7) con la que lo referencian los
// demás: una ruta apunta a "a7" aunque esa actividad todavía no exista en la base.
// El índice del name[] solo crece y nunca se reutiliza, así que borrar una fila no
// obliga a renumerar las demás — PHP recibe el arreglo con huecos y los ignora.
let n = { p: 0, r: 0, f: 0, a: 0, ruta: 0 };
const nuevaClave = (t) => t + (++n[t]);

function opciones(pares, sel) {
  return Object.entries(pares)
    .map(([v, t]) => `<option value="${v}" ${v === sel ? 'selected' : ''}>${typeof t === 'object' ? t.label : t}</option>`)
    .join('');
}

// ── Participantes ─────────────────────────────────────────────────────
function agregarParticipante(datos) {
  const clave = datos?.clave || nuevaClave('p');
  const i = n.p;
  const fila = document.createElement('div');
  fila.className = 'flujo-fila';
  fila.dataset.clave = clave;
  fila.innerHTML = `
    <input type="hidden" name="participantes[${i}][clave]" value="${clave}">
    <div class="field" style="flex:2">
      <input type="text" name="participantes[${i}][nombre]" maxlength="200" required
        placeholder="Ej. Dirección de Comercio" value="${datos?.nombre || ''}">
    </div>
    <div class="field" style="flex:1">
      <select name="participantes[${i}][tipo]">${opciones(CAT.participantes, datos?.tipo)}</select>
    </div>
    <button type="button" class="flujo-quitar" onclick="quitar(this)">Quitar</button>`;
  document.getElementById('participantes').appendChild(fila);
  refrescarReferencias();
}

// ── Resultados ────────────────────────────────────────────────────────
function agregarResultado(datos) {
  const clave = datos?.clave || nuevaClave('r');
  const i = n.r;
  const fila = document.createElement('div');
  fila.className = 'flujo-fila';
  fila.dataset.clave = clave;
  fila.innerHTML = `
    <input type="hidden" name="resultados[${i}][clave]" value="${clave}">
    <div class="field" style="flex:1">
      <input type="text" name="resultados[${i}][nombre]" maxlength="200" required
        placeholder="Ej. Permiso emitido" value="${datos?.nombre || ''}">
    </div>
    <button type="button" class="flujo-quitar" onclick="quitar(this)">Quitar</button>`;
  document.getElementById('resultados').appendChild(fila);
  refrescarReferencias();
}

// ── Fases ─────────────────────────────────────────────────────────────
function agregarFase(datos) {
  const clave = datos?.clave || nuevaClave('f');
  const i = n.f;
  const caja = document.createElement('details');
  caja.className = 'flujo-fase';
  caja.dataset.indice = i;
  caja.open = true;   // recién creada se abre; las cargadas se cierran al terminar
  caja.innerHTML = `
    <summary>
      <span class="flujo-fase-titulo">${datos?.nombre || 'Fase sin nombre'}</span>
      <span class="flujo-cuenta">0 actividades</span>
    </summary>
    <div class="flujo-fase-cuerpo">
      <input type="hidden" name="fases[${i}][clave]" value="${clave}">
      <div class="flujo-grid-2">
        <div class="field">
          <label>Nombre de la fase</label>
          <input type="text" name="fases[${i}][nombre]" maxlength="200" required
            placeholder="Ej. Captura y expediente" value="${datos?.nombre || ''}">
        </div>
        <div class="field">
          <label>Nota de la fase (opcional)</label>
          <input type="text" name="fases[${i}][nota]" maxlength="2000" value="${datos?.nota || ''}">
        </div>
      </div>
      <div class="flujo-actividades"></div>
      <div style="display:flex;gap:8px;margin-top:8px">
        <button type="button" class="flujo-agregar" onclick="agregarActividad(this.closest('.flujo-fase'))">+ Agregar actividad</button>
        <button type="button" class="flujo-quitar" onclick="quitar(this, '.flujo-fase')">Quitar fase</button>
      </div>
    </div>`;
  document.getElementById('fases').appendChild(caja);
  (datos?.actividades || []).forEach(a => agregarActividad(caja, a));
  return caja;
}

// ── Actividades ───────────────────────────────────────────────────────
function agregarActividad(caja, datos) {
  const f = caja.dataset.indice;
  const clave = datos?.clave || nuevaClave('a');
  const j = n.a;
  const item = document.createElement('details');
  item.className = 'flujo-actividad';
  item.dataset.clave = clave;
  item.open = ! datos;   // la nueva se abre para capturarla; las cargadas, cerradas
  const p = `fases[${f}][actividades][${j}]`;

  item.innerHTML = `
    <summary>
      <span class="flujo-act-num"></span>
      <span class="flujo-act-titulo">${datos?.descripcion || 'Actividad nueva'}</span>
      ${datos?.tiene_decision ? '<span class="flujo-marca-decision">revisa</span>' : ''}
    </summary>
    <div class="flujo-actividad-cuerpo">
    <input type="hidden" name="${p}[clave]" value="${clave}">

    <div class="flujo-grid-2">
      <div class="field">
        <label>¿Quién la realiza?</label>
        <select name="${p}[participante]" class="ref-participante" required></select>
      </div>
      <div class="field">
        <label>¿Qué hace?</label>
        <input type="text" name="${p}[descripcion]" maxlength="500" required value="${datos?.descripcion || ''}">
      </div>
    </div>

    <label class="flujo-check">
      <input type="checkbox" name="${p}[tiene_decision]" value="1"
        ${datos?.tiene_decision ? 'checked' : ''} onchange="alternarDecision(this)">
      Esta actividad revisa algo y el proceso puede seguir por dos caminos
    </label>

    <div class="flujo-bloque decision" hidden>
      <div class="field">
        <label>¿Qué se revisa?</label>
        <input type="text" name="${p}[que_revisa]" maxlength="500"
          placeholder="Ej. ¿La solicitud está completa?" value="${datos?.que_revisa || ''}">
      </div>
      <div class="rutas-decision"></div>
    </div>

    <div class="flujo-bloque simple">
      <div class="rutas-simple"></div>
    </div>

    <details class="flujo-extra">
      <summary>Pago, nota y estado</summary>

      <div class="field">
        <label>¿Interviene un pago?</label>
        <div class="flujo-checks">${
          Object.entries(CAT.pagos).map(([v, t]) => `
            <label class="flujo-check"><input type="checkbox" name="${p}[pago][acciones][]" value="${v}"
              ${(datos?.pago?.acciones || []).includes(v) ? 'checked' : ''}> ${t}</label>`).join('')
        }</div>
      </div>

      <div class="flujo-grid-2">
        <div class="field">
          <label>Concepto de cobro</label>
          <select name="${p}[pago][derecho_id]">
            <option value="">— sin concepto —</option>
            ${CAT.derechos.map(d => `<option value="${d.id}" ${datos?.pago?.derecho_id == d.id ? 'selected' : ''}>${d.texto}</option>`).join('')}
          </select>
          <small class="flujo-ayuda">Sale del catálogo de derechos del trámite: el importe no se captura aquí.</small>
        </div>
        <div class="field">
          <label>¿Quién valida el pago?</label>
          <select name="${p}[pago][participante]" class="ref-participante"></select>
        </div>
      </div>

      <div class="flujo-grid-2">
        <div class="field">
          <label>Título de la nota</label>
          <input type="text" name="${p}[nota][titulo]" maxlength="200" value="${datos?.nota?.titulo || ''}">
        </div>
        <div class="field">
          <label>Nuevo estado tras esta actividad</label>
          <input type="text" name="${p}[estado]" maxlength="60"
            placeholder="Ej. Pendiente de pago" value="${datos?.estado || ''}">
        </div>
      </div>
      <div class="field">
        <label>Aclaración</label>
        <textarea name="${p}[nota][texto]" rows="2" maxlength="2000">${datos?.nota?.texto || ''}</textarea>
      </div>
    </details>

    <button type="button" class="flujo-quitar" onclick="quitar(this, '.flujo-actividad')">Quitar actividad</button>
    </div>`;

  caja.querySelector('.flujo-actividades').appendChild(item);

  // Rutas: una sola si no decide, dos si decide.
  const rutas = datos?.rutas || [];
  agregarRuta(item, 'simple', 'siempre', rutas.find(r => r.condicion === 'siempre'));
  agregarRuta(item, 'decision', 'correcto', rutas.find(r => r.condicion === 'correcto'));
  agregarRuta(item, 'decision', 'incorrecto', rutas.find(r => r.condicion === 'incorrecto'));

  alternarDecision(item.querySelector('[type=checkbox]'));
  refrescarReferencias();
}

function agregarRuta(item, zona, condicion, datos) {
  const p = item.querySelector('input[type=hidden]').name.replace('[clave]', '');
  const k = n.ruta++;
  const etiqueta = { siempre: '¿Qué sucede después?', correcto: 'Si está correcto', incorrecto: 'Si NO está correcto' }[condicion];

  const fila = document.createElement('div');
  fila.className = 'flujo-ruta';
  fila.innerHTML = `
    <input type="hidden" name="${p}[rutas][${k}][condicion]" value="${condicion}">
    <div class="field">
      <label>${etiqueta}</label>
      <select name="${p}[rutas][${k}][destino_tipo]" onchange="alternarDestino(this)">
        ${opciones(CAT.destinos, datos?.destino_tipo || 'siguiente')}
      </select>
    </div>
    <div class="field destino-actividad" hidden>
      <label>¿A qué actividad?</label>
      <select name="${p}[rutas][${k}][destino_actividad]" class="ref-actividad" data-sel="${datos?.destino_actividad || ''}"></select>
    </div>
    <div class="field destino-resultado" hidden>
      <label>¿Con qué resultado?</label>
      <select name="${p}[rutas][${k}][resultado]" class="ref-resultado" data-sel="${datos?.resultado || ''}"></select>
    </div>`;

  item.querySelector('.' + (zona === 'simple' ? 'rutas-simple' : 'rutas-decision')).appendChild(fila);
  alternarDestino(fila.querySelector('select'));
}

// ── Mostrar y ocultar según lo elegido ────────────────────────────────
function alternarDecision(check) {
  const item = check.closest('.flujo-actividad');
  item.querySelector('.flujo-bloque.decision').hidden = ! check.checked;
  item.querySelector('.flujo-bloque.simple').hidden   = check.checked;

  // Las rutas que no aplican se desactivan para que no viajen en el envío: si
  // fueran, una actividad tendría a la vez ruta única y rutas de decisión.
  item.querySelectorAll('.rutas-simple select, .rutas-simple input').forEach(e => e.disabled = check.checked);
  item.querySelectorAll('.rutas-decision select, .rutas-decision input, .flujo-bloque.decision input[type=text]')
      .forEach(e => e.disabled = ! check.checked);
}

function alternarDestino(select) {
  const fila = select.closest('.flujo-ruta');
  fila.querySelector('.destino-actividad').hidden = select.value !== 'actividad';
  fila.querySelector('.destino-resultado').hidden = select.value !== 'fin';
}

// ── Mantener al día los selects que apuntan a otros elementos ─────────
//
// Al añadir un participante o una actividad, todos los selects que los referencian
// tienen que enterarse. Se reconstruyen conservando lo ya elegido.
function refrescarReferencias() {
  const listas = {
    'ref-participante': [...document.querySelectorAll('#participantes .flujo-fila')]
      .map(f => [f.dataset.clave, f.querySelector('input[type=text]').value || '(sin nombre)']),
    'ref-resultado': [...document.querySelectorAll('#resultados .flujo-fila')]
      .map(f => [f.dataset.clave, f.querySelector('input[type=text]').value || '(sin nombre)']),
    'ref-actividad': [...document.querySelectorAll('.flujo-actividad')]
      .map((a, i) => [a.dataset.clave, (i + 1) + '. ' + (a.querySelector('input[name*="[descripcion]"]').value || '(sin descripción)')]),
  };

  Object.entries(listas).forEach(([clase, pares]) => {
    document.querySelectorAll('.' + clase).forEach(sel => {
      const elegido = sel.value || sel.dataset.sel || '';
      sel.innerHTML = '<option value="">— seleccionar —</option>' +
        pares.map(([v, t]) => `<option value="${v}" ${v === elegido ? 'selected' : ''}>${t}</option>`).join('');
    });
  });
}

/**
 * Refresca lo que se lee en los resúmenes plegados.
 *
 * Es lo que permite trabajar con todo cerrado: el resumen de cada actividad muestra
 * su número y su descripción, y el de la fase cuántas actividades tiene. Sin esto
 * habría que abrirlas una por una para saber cuál es cuál.
 */
function refrescarResumenes() {
  let numero = 0;

  document.querySelectorAll('.flujo-fase').forEach(fase => {
    const nombre = fase.querySelector('input[name*="[nombre]"]');
    fase.querySelector('.flujo-fase-titulo').textContent = nombre?.value || 'Fase sin nombre';

    const acts = fase.querySelectorAll('.flujo-actividad');
    fase.querySelector('.flujo-cuenta').textContent =
      acts.length + (acts.length === 1 ? ' actividad' : ' actividades');

    acts.forEach(act => {
      numero++;
      const desc   = act.querySelector('input[name*="[descripcion]"]');
      const decide = act.querySelector('input[type=checkbox][name*="[tiene_decision]"]');
      const quien  = act.querySelector('.ref-participante');

      act.querySelector('.flujo-act-num').textContent = numero + '.';
      act.querySelector('.flujo-act-titulo').textContent =
        (quien?.selectedOptions[0]?.text && quien.value ? quien.selectedOptions[0].text + ' — ' : '')
        + (desc?.value || 'Actividad sin descripción');

      let marca = act.querySelector('.flujo-marca-decision');
      if (decide?.checked && ! marca) {
        marca = document.createElement('span');
        marca.className = 'flujo-marca-decision';
        marca.textContent = 'revisa';
        act.querySelector('summary').appendChild(marca);
      } else if (! decide?.checked && marca) {
        marca.remove();
      }
    });
  });
}

function quitar(boton, selector) {
  const fila = boton.closest(selector || '.flujo-fila');
  fila.remove();
  refrescarReferencias();
  refrescarResumenes();
}

// Los nombres cambian mientras se escriben, y los selects que los muestran también.
document.addEventListener('input', e => {
  if (e.target.matches('#participantes input, #resultados input, input[name*="[descripcion]"]')) {
    refrescarReferencias();
  }
  refrescarResumenes();
});

document.addEventListener('change', e => {
  if (e.target.matches('.ref-participante, [name*="[tiene_decision]"]')) {
    refrescarResumenes();
  }
});

// ── Arranque ──────────────────────────────────────────────────────────
if (GUARDADO.fases.length) {
  GUARDADO.participantes.forEach(p => { n.p = Math.max(n.p, +p.clave.slice(1) || 0); agregarParticipante(p); });
  GUARDADO.resultados.forEach(r => { n.r = Math.max(n.r, +r.clave.slice(1) || 0); agregarResultado(r); });
  GUARDADO.fases.forEach(f => agregarFase(f));
} else {
  agregarParticipante({ nombre: 'Persona solicitante', tipo: 'solicitante' });
  agregarParticipante({ nombre: 'Sistema', tipo: 'sistema' });
  agregarResultado({ nombre: 'Documento emitido' });
  agregarResultado({ nombre: 'Solicitud no procedente' });
  agregarFase({ nombre: 'Inicio' });
}
/**
 * Un campo obligatorio dentro de una sección plegada no se puede enfocar, así que
 * el navegador cancela el envío sin decir dónde está el problema —solo deja un
 * aviso en la consola—. Al detectar el primer campo inválido se abren todas las
 * secciones que lo contienen, para que el mensaje se vea donde toca.
 */
document.addEventListener('invalid', e => {
  let caja = e.target.closest('details');
  while (caja) {
    caja.open = true;
    caja = caja.parentElement ? caja.parentElement.closest('details') : null;
  }
}, true);

/** Vacía el formulario y lo deja como recién abierto. */
function vaciarFlujo() {
  ['participantes', 'resultados', 'fases'].forEach(id => document.getElementById(id).innerHTML = '');
  document.querySelectorAll('#flujoForm input[type=text], #flujoForm select').forEach(c => {
    if (! c.closest('#participantes, #resultados, #fases')) c.value = '';
  });
  n = { p: 0, r: 0, f: 0, a: 0, ruta: 0 };
  refrescarReferencias();
  refrescarResumenes();
}

/**
 * Llena el formulario con un proceso de ejemplo.
 *
 * Se arma con las mismas funciones que usa la captura normal, así que muestra
 * exactamente lo que hay que hacer: una fase con una revisión que puede devolver,
 * un pago que referencia el catálogo del trámite, un turno a dos áreas a la vez y
 * dos finales distintos.
 */
function ejemploFlujo() {
  vaciarFlujo();

  document.querySelector('[name=proceso_nombre]').value    = 'Permiso para vendedor ambulante';
  document.querySelector('[name=resolutivo_tipo]').value   = 'permiso';
  document.querySelector('[name=resolutivo_nombre]').value = 'Permiso para vendedor ambulante';
  document.querySelector('[name=inicia_con]').value        = 'La persona solicita el permiso en línea';
  document.querySelector('[name=termina_con]').value       = 'Se entrega el permiso firmado con código QR';

  [['Persona solicitante', 'solicitante'], ['Sistema', 'sistema'],
   ['Dirección de Comercio', 'revisora'], ['Protección Civil', 'tecnica'],
   ['Tesorería Municipal', 'tesoreria']].forEach(([nombre, tipo]) => agregarParticipante({ nombre, tipo }));

  ['Permiso emitido', 'Solicitud no procedente'].forEach(nombre => agregarResultado({ nombre }));

  // Las claves se leen de lo ya creado, para que las rutas apunten a algo real.
  const claveP = i => document.querySelectorAll('#participantes .flujo-fila')[i].dataset.clave;
  const claveR = i => document.querySelectorAll('#resultados .flujo-fila')[i].dataset.clave;

  agregarFase({
    nombre: 'Captura y expediente',
    nota: 'Se precargan identidad, domicilio y contacto del expediente digital.',
    actividades: [
      { descripcion: 'Inicia la solicitud y captura sus datos', participante: claveP(0),
        rutas: [{ condicion: 'siempre', destino_tipo: 'siguiente' }] },
      { descripcion: 'Valida la integridad de la solicitud', participante: claveP(1),
        tiene_decision: true, que_revisa: '¿La solicitud está completa?',
        rutas: [{ condicion: 'correcto', destino_tipo: 'siguiente' },
                { condicion: 'incorrecto', destino_tipo: 'inicio_fase' }] },
    ],
  });

  agregarFase({
    nombre: 'Cálculo y pago',
    actividades: [
      { descripcion: 'Realiza el pago de los derechos', participante: claveP(0),
        estado: 'Pendiente de pago',
        pago: { acciones: ['calcula_monto', 'genera_referencia', 'realiza_pago'] },
        rutas: [{ condicion: 'siempre', destino_tipo: 'siguiente' }] },
      { descripcion: 'Valida el pago recibido', participante: claveP(4),
        tiene_decision: true, que_revisa: '¿El pago está completo y validado?',
        rutas: [{ condicion: 'correcto', destino_tipo: 'siguiente' },
                { condicion: 'incorrecto', destino_tipo: 'inicio_fase' }] },
    ],
  });

  const fase3 = agregarFase({
    nombre: 'Revisión institucional',
    actividades: [
      { descripcion: 'Turna el expediente a las áreas que deben revisarlo', participante: claveP(1),
        nota: { titulo: 'Revisión simultánea',
                texto: 'La revisión de Protección Civil no detiene la de Comercio.' } },
      { descripcion: 'Revisa las medidas de seguridad', participante: claveP(3),
        rutas: [{ condicion: 'siempre', destino_tipo: 'siguiente' }] },
      { descripcion: 'Revisa el expediente administrativo', participante: claveP(2),
        tiene_decision: true, que_revisa: '¿La solicitud es procedente?',
        rutas: [{ condicion: 'correcto', destino_tipo: 'fin', resultado: claveR(0) },
                { condicion: 'incorrecto', destino_tipo: 'fin', resultado: claveR(1) }] },
    ],
  });

  // El turno a dos áreas a la vez: dos rutas desde la misma actividad.
  const acts = fase3.querySelectorAll('.flujo-actividad');
  const primera = acts[0];
  primera.querySelectorAll('.rutas-simple .flujo-ruta').forEach(r => r.remove());
  agregarRuta(primera, 'simple', 'siempre',
    { destino_tipo: 'actividad', destino_actividad: acts[1].dataset.clave });
  agregarRuta(primera, 'simple', 'siempre',
    { destino_tipo: 'actividad', destino_actividad: acts[2].dataset.clave });

  refrescarReferencias();
  refrescarResumenes();
  document.querySelectorAll('.flujo-fase, .flujo-actividad').forEach(d => d.open = false);
}

refrescarReferencias();
refrescarResumenes();

// Con un flujo ya capturado se arranca todo plegado: se ve el esqueleto completo
// del proceso de un vistazo y se abre solo lo que se vaya a tocar.
if (GUARDADO.fases.length) {
  document.querySelectorAll('.flujo-fase, .flujo-actividad').forEach(d => d.open = false);
}
</script>
@endpush
@endsection
