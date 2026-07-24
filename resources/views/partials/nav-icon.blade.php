{{--
  Icono de menú en SVG de línea (estilo simple, tipo Material/Google).

  Se dibuja aquí, sin dependencias externas: el proyecto NO carga ninguna
  librería de iconos, así que las clases tipo "ti ti-*" no se ven. Este SVG sí.

  Hereda el color con currentColor y usa trazo medio (1.8) sin relleno, para
  que no se vea saturado.

  Uso:  @include('partials.nav-icon', ['name' => 'search'])

  Todos los iconos viven en este único archivo: si mañana cambias uno, se toca aquí.
--}}
@php
  $paths = match ($name ?? '') {
    'dashboard'      => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/>',
    'search'         => '<circle cx="11" cy="11" r="7"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
    'tramites'       => '<path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2z"/><line x1="9" y1="13" x2="15" y2="13"/><line x1="9" y1="17" x2="13" y2="17"/>',
    'checklist'      => '<path d="M4 6l1.6 1.6L8.5 4.7"/><path d="M4 13l1.6 1.6L8.5 11.7"/><line x1="11" y1="6" x2="20" y2="6"/><line x1="11" y1="13" x2="20" y2="13"/><line x1="4" y1="20" x2="20" y2="20"/>',
    'calendar-check' => '<rect x="4" y="5" width="16" height="16" rx="2"/><line x1="4" y1="9" x2="20" y2="9"/><line x1="8" y1="3" x2="8" y2="6"/><line x1="16" y1="3" x2="16" y2="6"/><path d="M9 15l1.8 1.8L15 13"/>',
    'report'         => '<path d="M14 3v4a1 1 0 0 0 1 1h4"/><path d="M17 21H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h7l5 5v11a2 2 0 0 1-2 2z"/><path d="M9 14l1.8 1.8L15 12"/>',
    'book'           => '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>',
    'library'        => '<rect x="4" y="5" width="3" height="14" rx="1"/><rect x="9" y="5" width="3" height="14" rx="1"/><path d="M15.2 6.2l2.9.8-3 12-2.9-.8z"/>',
    'calendar'       => '<rect x="4" y="5" width="16" height="16" rx="2"/><line x1="4" y1="9" x2="20" y2="9"/><line x1="8" y1="3" x2="8" y2="6"/><line x1="16" y1="3" x2="16" y2="6"/>',
    'signature'      => '<path d="M3 17c2-1 3-4 2-5-1-1-2 1-1 3 1 3 4 2 5-1"/><path d="M9 14c1 .5 2 .5 3-1"/><path d="M12 20h9"/><path d="M16.5 4.5a2.12 2.12 0 0 1 3 3L14 13l-4 1 1-4z"/>',
    'settings'       => '<circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 1 1-2.83 2.83l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-4 0v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 1 1-2.83-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1 0-4h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 1 1 2.83-2.83l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 4 0v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 1 1 2.83 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 0 4h-.09a1.65 1.65 0 0 0-1.51 1z"/>',
    'users'          => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
    'clock'          => '<circle cx="12" cy="12" r="9"/><polyline points="12 7.5 12 12 15 13.5"/>',
    'history'        => '<path d="M3 3v5h5"/><path d="M3.05 13A9 9 0 1 0 6 5.3L3 8"/><polyline points="12 7.5 12 12 15 13.5"/>',
    default          => '',
  };
@endphp
<svg class="nav-ico" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">{!! $paths !!}</svg>
