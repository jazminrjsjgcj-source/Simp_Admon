{{--
  Componente: lector-regulacion (panel lateral / drawer)

  Se incluye UNA sola vez en el layout principal para que esté disponible
  en cualquier pantalla del sistema sin duplicar HTML. Se abre llamando a
  la función global:

      window.abrirLectorRegulacion(id, nombre)

  desde cualquier botón o enlace, pasando el ID de la regulación y su
  nombre (para el encabezado del panel).

  Por qué es un panel lateral y no un modal de pantalla completa:
  la persona necesita leer el texto de la regulación MIENTRAS sigue viendo
  y usando el formulario que tiene detrás (por ejemplo, citar un artículo
  en un trámite sin perder lo que ya llenó). Un modal que cubre toda la
  pantalla se lo impediría.

  Por qué usa position:fixed en vez de vivir dentro del flujo de la página:
  al estar fijo al viewport, el panel nunca compite por scroll con la
  página que tiene detrás — la única barra de scroll que existe dentro del
  panel es la del iframe. Antes, el visor anterior vivía dentro de una
  tarjeta que se desplazaba con la página, y el usuario terminaba con dos
  barras de scroll superpuestas (la de la página y la del iframe), lo cual
  era confuso y es exactamente el problema que este panel resuelve.
--}}

<div id="lectorRegulacionOverlay" class="lector-reg-overlay" aria-hidden="true">
  <div id="lectorRegulacionPanel" class="lector-reg-panel" role="dialog" aria-modal="false" aria-labelledby="lectorRegulacionTitulo">

    <div class="lector-reg-header">
      <div class="lector-reg-header-texto">
        <span class="lector-reg-eyebrow">Consultando regulación</span>
        <h3 id="lectorRegulacionTitulo">Cargando…</h3>
      </div>
      <div class="lector-reg-header-acciones">
        <a id="lectorRegulacionAbrirCompleto" href="#" target="_blank" class="btn btn-outline btn-sm" title="Abrir la ficha completa de la regulación en una pestaña nueva">
          Ficha completa
        </a>
        <button type="button" class="lector-reg-cerrar" id="lectorRegulacionCerrar" aria-label="Cerrar panel de lectura">
          &times;
        </button>
      </div>
    </div>

    <div class="lector-reg-cuerpo">
      <div id="lectorRegulacionCargando" class="lector-reg-loader">
        <div class="lector-reg-spinner"></div>
        <p>Cargando documento…</p>
      </div>
      <iframe id="lectorRegulacionIframe" class="lector-reg-iframe hidden"
              title="Vista previa de la regulación"></iframe>
    </div>

  </div>
</div>

@once
@push('scripts')
<script>
  // Mismo patrón que window.EDITOR_REG en regulacion-editor.js: Blade no puede
  // generar rutas dentro de un archivo .js estático, así que se las pasamos
  // como configuración global. El JS arma la URL final con el ID recibido.
  window.LECTOR_REG_CFG = {
    previewBase: "{{ url('regulaciones') }}",
    showBase:    "{{ url('regulaciones') }}",
  };
</script>
<script src="{{ asset('js/lector-regulacion.js') }}?v={{ filemtime(public_path('js/lector-regulacion.js')) }}"></script>
@endpush
@endonce
