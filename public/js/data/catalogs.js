// ===============================
// PUNTA â€” Catálogos y listas reutilizables
// ===============================
// Fase 2: Separado desde main.js original

const sectorCatalog = [
  {
    sector: "1 Agricultura, cría y explotación de animales, aprovechamiento forestal, pesca y caza",
    subsectores: [
      "111 Agricultura",
      "112 Cría y explotación de animales",
      "113 Aprovechamiento forestal",
      "114 Pesca, caza y captura",
      "115 Servicios relacionados con las actividades agropecuarias y forestales",
    ],
  },
  {
    sector: "2 Minería",
    subsectores: [
      "211 Extracción de petróleo y gas",
      "212 Minería de minerales metálicos y no metálicos, excepto petróleo y gas",
      "213 Servicios relacionados con la minería",
    ],
  },
  {
    sector: "3 Generación, transmisión, distribución y comercialización de energía eléctrica, suministro de agua y de gas natural",
    subsectores: [
      "221 Generación, transmisión, distribución y comercialización de energía eléctrica, suministro de agua y de gas natural",
    ],
  },
  {
    sector: "4 Construcción",
    subsectores: [
      "236 Edificación",
      "237 Construcción de obras de ingeniería civil",
      "238 Trabajos especializados para la construcción",
    ],
  },
  {
    sector: "5 Industrias manufactureras",
    subsectores: [
      "311 Industria alimentaria",
      "312 Industria de las bebidas y del tabaco",
      "313 Fabricación de insumos textiles y acabado de textiles",
      "314 Fabricación de productos textiles, excepto prendas de vestir",
      "315 Fabricación de prendas de vestir",
      "316 Curtido y acabado de cuero y piel, y fabricación de productos de cuero, piel y materiales sucedáneos",
      "321 Industria de la madera",
      "322 Industria del papel",
      "323 Impresión e industrias conexas",
      "324 Fabricación de productos derivados del petróleo y del carbón",
      "325 Industria química",
      "326 Industria del plástico y del hule",
      "327 Fabricación de productos a base de minerales no metálicos",
      "331 Industrias metálicas básicas",
      "332 Fabricación de productos metálicos",
      "333 Fabricación de maquinaria y equipo",
      "334 Fabricación de equipo de computación, comunicación, medición y otros equipos, componentes y accesorios electrónicos",
      "335 Fabricación de accesorios, aparatos eléctricos y equipo de generación de energía eléctrica",
      "336 Fabricación de equipo de transporte",
      "337 Fabricación de muebles, colchones y persianas",
      "339 Otras industrias manufactureras",
    ],
  },
  {
    sector: "6 Comercio al por mayor",
    subsectores: [
      "431 Comercio al por mayor de abarrotes, alimentos, bebidas, hielo y tabaco",
      "432 Comercio al por mayor de productos textiles y calzado",
      "433 Comercio al por mayor de productos farmacéuticos, de perfumería, artículos para el esparcimiento, electrodomésticos menores y aparatos de línea blanca",
      "434 Comercio al por mayor de materias primas agropecuarias y forestales, para la industria, y materiales de desecho",
      "435 Comercio al por mayor de maquinaria, equipo y mobiliario para actividades agropecuarias, industriales, de servicios y comerciales",
      "436 Comercio al por mayor de camiones y de partes y refacciones nuevas para automóviles, camionetas y camiones",
      "437 Intermediación de comercio al por mayor",
    ],
  },
  {
    sector: "7 Comercio al por menor",
    subsectores: [
      "461 Comercio al por menor de abarrotes, alimentos, bebidas, hielo y tabaco",
      "462 Comercio al por menor en tiendas de autoservicio y departamentales",
      "463 Comercio al por menor de productos textiles, bisutería, accesorios de vestir y calzado",
      "464 Comercio al por menor de artículos para el cuidado de la salud",
      "465 Comercio al por menor de artículos de papelería, para el esparcimiento y otros artículos de uso personal",
      "466 Comercio al por menor de enseres domésticos, computadoras, artículos para la decoración de interiores y artículos usados",
      "467 Comercio al por menor de artículos de ferretería, tlapalería y vidrios",
      "468 Comercio al por menor de vehículos de motor, refacciones, combustibles y lubricantes",
      "469 Comercio al por menor exclusivamente a través de internet, y catálogos impresos, televisión y similares",
    ],
  },
  {
    sector: "8 Transportes, correos y almacenamiento",
    subsectores: [
      "481 Transporte aéreo",
      "482 Transporte por ferrocarril",
      "483 Transporte por agua",
      "484 Autotransporte de carga",
      "485 Transporte terrestre de pasajeros, excepto por ferrocarril",
      "486 Transporte por ductos",
      "487 Transporte turístico",
      "488 Servicios relacionados con el transporte",
      "491 Servicios postales",
      "492 Servicios de mensajería y paquetería",
      "493 Servicios de almacenamiento",
    ],
  },
  {
    sector: "9 Información en medios masivos",
    subsectores: [
      "511 Edición de periódicos, revistas, libros, software y otros materiales, y edición de estas publicaciones integrada con la impresión",
      "512 Industria fílmica y del video, e industria del sonido",
      "515 Radio y televisión",
      "517 Telecomunicaciones",
      "518 Procesamiento electrónico de información, hospedaje y otros servicios relacionados",
      "519 Otros servicios de información",
    ],
  },
  {
    sector: "10 Servicios financieros y de seguros",
    subsectores: [
      "521 Banca central",
      "522 Instituciones de intermediación crediticia y financiera no bursátil",
      "523 Actividades bursátiles, cambiarias y de inversión financiera",
      "524 Compañías de seguros, fianzas, y administración de fondos para el retiro",
      "525 Sociedades de inversión especializadas en fondos para el retiro y fondos de inversión",
    ],
  },
  {
    sector: "11 Servicios inmobiliarios y de alquiler de bienes muebles e intangibles",
    subsectores: [
      "531 Servicios inmobiliarios",
      "532 Servicios de alquiler de bienes muebles",
      "533 Servicios de alquiler de marcas registradas, patentes y franquicias",
    ],
  },
  {
    sector: "12 Servicios profesionales, científicos y técnicos",
    subsectores: ["541 Servicios profesionales, científicos y técnicos"],
  },
  {
    sector: "13 Corporativos",
    subsectores: ["551 Corporativos"],
  },
  {
    sector: "14 Servicios de apoyo a los negocios y manejo de residuos, y servicios de remediación",
    subsectores: [
      "561 Servicios de apoyo a los negocios",
      "562 Manejo de residuos y servicios de remediación",
    ],
  },
  {
    sector: "15 Servicios educativos",
    subsectores: ["611 Servicios educativos"],
  },
  {
    sector: "16 Servicios de salud y de asistencia social",
    subsectores: [
      "621 Servicios médicos de consulta externa y servicios relacionados",
      "622 Hospitales",
      "623 Residencias de asistencia social y para el cuidado de la salud",
      "624 Otros servicios de asistencia social",
    ],
  },
  {
    sector: "17 Servicios de esparcimiento culturales y deportivos, y otros servicios recreativos",
    subsectores: [
      "711 Servicios artísticos, culturales y deportivos, y otros servicios relacionados",
      "712 Museos, sitios históricos, zoológicos y similares",
      "713 Servicios de entretenimiento en instalaciones recreativas y otros servicios recreativos",
    ],
  },
  {
    sector: "18 Servicios de alojamiento temporal y de preparación de alimentos y bebidas",
    subsectores: [
      "721 Servicios de alojamiento temporal",
      "722 Servicios de preparación de alimentos y bebidas",
    ],
  },
  {
    sector: "19 Otros servicios excepto actividades gubernamentales",
    subsectores: [
      "811 Servicios de reparación y mantenimiento",
      "812 Servicios personales",
      "813 Asociaciones y organizaciones",
      "814 Hogares con empleados domésticos",
    ],
  },
  {
    sector: "20 Actividades legislativas, gubernamentales, de impartición de justicia y de organismos internacionales y extraterritoriales",
    subsectores: [
      "931 Actividades legislativas, gubernamentales y de impartición de justicia",
      "932 Organismos internacionales y extraterritoriales",
    ],
  },
];

// ===============================
// Catálogo de tipos de documento para codificación de homoclave
// Fuente: Lineamientos IV.II H. Ayuntamiento de La Paz
// ===============================

const codigoTiposDocumento = [
  { clave: "MP", nombre: "Manual de procedimientos" },
  { clave: "P",  nombre: "Procedimiento" },
  { clave: "G",  nombre: "Guía" },
  { clave: "I",  nombre: "Instructivo" },
  { clave: "F",  nombre: "Formato" },
  { clave: "IT", nombre: "Instrucción de Trabajo" },
  { clave: "C",  nombre: "Catálogo" },
  { clave: "T",  nombre: "Trámite" },
  { clave: "S",  nombre: "Servicio" },            // CREADO: no existe en lineamientos originales
];

// ===============================
// Catálogo de dependencias con clave orgánica
// Estructura: XXX (3 dígitos) = primeros 3 de clave orgánica
// NOTA: Códigos placeholder basados en estructura municipal típica.
//       Deben ajustarse a la clave orgánica real del H. Ayuntamiento de La Paz.
// ===============================

const codigoDependencias = [
  { codigo: "100", nombre: "Secretaría Técnica", areas: [
    { codigo: "00", nombre: "Despacho" },
    { codigo: "01", nombre: "Unidad de Planeación y Seguimiento" },
    { codigo: "02", nombre: "Unidad de Análisis y Estadística" },
    { codigo: "03", nombre: "Unidad de Evaluación y Resultados" },
  ]},
  { codigo: "101", nombre: "Secretaría General Municipal", areas: [
    { codigo: "00", nombre: "Despacho" },
    { codigo: "01", nombre: "Dirección Técnica de Cabildo" },
    { codigo: "02", nombre: "Dirección de Protección Civil" },
    { codigo: "03", nombre: "Coordinación Administrativa" },
    { codigo: "04", nombre: "Comandancia Gral. Heroico Cuerpo de Bomberos" },
    { codigo: "05", nombre: "Junta Municipal de Reclutamiento" },
    { codigo: "06", nombre: "Departamento de Archivo General" },
    { codigo: "07", nombre: "Departamento de Enlace Jurídico y Certificaciones" },
  ]},
  { codigo: "102", nombre: "Tesorería Municipal", areas: [
    { codigo: "00", nombre: "Despacho" },
    { codigo: "01", nombre: "Dirección de Ingresos" },
    { codigo: "02", nombre: "Dirección de Egresos" },
    { codigo: "03", nombre: "Dirección de Programación y Presupuesto" },
    { codigo: "04", nombre: "Dirección de Comercio" },
    { codigo: "05", nombre: "Dirección de la Zona Federal Marítimo Terrestre" },
    { codigo: "06", nombre: "Dirección de Panteones Municipales" },
    { codigo: "07", nombre: "Coordinación Administrativa" },
    { codigo: "08", nombre: "Coordinación de Caja General" },
    { codigo: "09", nombre: "Departamento de Enlace Jurídico" },
  ]},
  { codigo: "103", nombre: "Oficialía Mayor", areas: [
    { codigo: "00", nombre: "Despacho" },
    { codigo: "01", nombre: "Dirección de Recursos Humanos" },
    { codigo: "02", nombre: "Dirección de Adquisiciones y Servicios Generales" },
    { codigo: "03", nombre: "Coordinación Administrativa" },
    { codigo: "04", nombre: "Departamento de Enlace Jurídico" },
    { codigo: "05", nombre: "Departamento de Desarrollo Organizacional" },
    { codigo: "06", nombre: "Departamento de Auditorías y Certificaciones" },
  ]},
  { codigo: "104", nombre: "Contraloría Municipal", areas: [
    { codigo: "00", nombre: "Despacho" },
    { codigo: "01", nombre: "Dirección de Auditoría y Supervisión Gubernamental" },
    { codigo: "02", nombre: "Dirección Anticorrupción" },
    { codigo: "03", nombre: "Dirección de la Unidad de Transparencia" },
    { codigo: "04", nombre: "Coordinación Administrativa" },
    { codigo: "05", nombre: "Departamento de Enlace Jurídico" },
  ]},
  { codigo: "105", nombre: "Dirección General de Gestión Integral de la Ciudad", areas: [
    { codigo: "00", nombre: "Despacho" },
    { codigo: "01", nombre: "Dirección de Ordenamiento del Territorio" },
    { codigo: "02", nombre: "Dirección de Obras Públicas" },
    { codigo: "03", nombre: "Dirección de Medio Ambiente" },
    { codigo: "04", nombre: "Dirección de Movilidad y Espacio Público" },
    { codigo: "05", nombre: "Dirección de Enlace Administrativo" },
    { codigo: "06", nombre: "Subdirección Técnica Jurídica" },
  ]},
  { codigo: "106", nombre: "Dirección General de Servicios Públicos", areas: [
    { codigo: "00", nombre: "Despacho" },
    { codigo: "01", nombre: "Dirección de Operaciones" },
    { codigo: "02", nombre: "Dirección de Conservación de Espacios Públicos" },
    { codigo: "03", nombre: "Subdirección Administrativa" },
    { codigo: "04", nombre: "Departamento de Vinculación y Participación Ciudadana" },
  ]},
  { codigo: "107", nombre: "Dirección General de Bienestar y Desarrollo Económico", areas: [
    { codigo: "00", nombre: "Despacho" },
    { codigo: "01", nombre: "Dirección de Planeación" },
    { codigo: "02", nombre: "Dirección de Fomento Económico" },
    { codigo: "03", nombre: "Dirección de Turismo" },
    { codigo: "04", nombre: "Dirección de Proyectos e Inversión" },
    { codigo: "05", nombre: "Dirección de Desarrollo Delegacional Sustentable" },
    { codigo: "06", nombre: "Coordinación Administrativa" },
    { codigo: "07", nombre: "Departamento de Enlace Jurídico" },
  ]},
  { codigo: "108", nombre: "Dirección General de Seguridad Vial y Transporte", areas: [
    { codigo: "00", nombre: "Despacho" },
    { codigo: "01", nombre: "Dirección de Movilidad y Seguridad Vial" },
    { codigo: "02", nombre: "Dirección de Transporte" },
    { codigo: "03", nombre: "Dirección del Sistema Municipal de Transporte" },
    { codigo: "04", nombre: "Coordinación Administrativa" },
    { codigo: "05", nombre: "Coordinación Jurídica" },
  ]},
  { codigo: "109", nombre: "Dirección General de Seguridad Pública y Policía Preventiva", areas: [
    { codigo: "00", nombre: "Despacho" },
    { codigo: "01", nombre: "Dirección de Proximidad Social y Seguridad Pública" },
    { codigo: "02", nombre: "Subdirección Administrativa" },
    { codigo: "03", nombre: "Subdirección Jurídica" },
    { codigo: "04", nombre: "Coordinación de Fortalecimiento Institucional" },
  ]},
  { codigo: "110", nombre: "Dirección General de Catastro", areas: [
    { codigo: "00", nombre: "Despacho" },
    { codigo: "01", nombre: "Subdirección Técnica" },
    { codigo: "02", nombre: "Subdirección de Administración Catastral" },
    { codigo: "03", nombre: "Coordinación de Apoyo Administrativo" },
    { codigo: "04", nombre: "Departamento de Enlace Jurídico" },
  ]},
  { codigo: "111", nombre: "Dirección General de Inclusión y Diversidad", areas: [
    { codigo: "00", nombre: "Despacho" },
    { codigo: "01", nombre: "Dirección Municipal de Inclusión" },
    { codigo: "02", nombre: "Dirección Municipal del Deporte" },
    { codigo: "03", nombre: "Dirección Municipal de la Juventud" },
    { codigo: "04", nombre: "Dirección Municipal de Asuntos Indígenas y Afromexicanas" },
    { codigo: "05", nombre: "Dirección Municipal de Cultura" },
    { codigo: "06", nombre: "Subdirección de Planeación y Evaluación de Políticas Públicas" },
    { codigo: "07", nombre: "Coordinación de Vinculación Interinstitucional" },
    { codigo: "08", nombre: "Coordinación de Apoyo Administrativo" },
    { codigo: "09", nombre: "Departamento de Enlace Jurídico" },
  ]},
  { codigo: "112", nombre: "Dirección General de Gobierno Digital", areas: [
    { codigo: "00", nombre: "Despacho" },
    { codigo: "01", nombre: "Dirección de Tecnologías de la Información y Digitalización" },
    { codigo: "02", nombre: "Dirección de Infraestructura Tecnológica y Operaciones" },
    { codigo: "03", nombre: "Dirección de Simplificación Administrativa" },
    { codigo: "04", nombre: "Coordinación de Ciberseguridad" },
    { codigo: "05", nombre: "Coordinación Administrativa" },
    { codigo: "06", nombre: "Departamento de Enlace de Asuntos Jurídicos" },
  ]},
  { codigo: "113", nombre: "Dirección General de Sustentabilidad y Manejo de Residuos", areas: [
    { codigo: "00", nombre: "Despacho" },
    { codigo: "01", nombre: "Dirección de Saneamiento Ambiental" },
    { codigo: "02", nombre: "Dirección Administrativa" },
    { codigo: "03", nombre: "Coordinación de Participación Ciudadana" },
    { codigo: "04", nombre: "Departamento de Enlace Jurídico" },
  ]},
  { codigo: "114", nombre: "Instituto Municipal de Planeación de La Paz", areas: [
    { codigo: "00", nombre: "Despacho" },
  ]},
  { codigo: "115", nombre: "Desarrollo Integral de la Familia", areas: [
    { codigo: "00", nombre: "Despacho" },
  ]},
  { codigo: "116", nombre: "Instituto Municipal de las Mujeres", areas: [
    { codigo: "00", nombre: "Despacho" },
  ]},
  { codigo: "117", nombre: "Dirección de la Policía Auxiliar", areas: [
    { codigo: "00", nombre: "Despacho" },
    { codigo: "01", nombre: "Comandancia Operativa" },
    { codigo: "02", nombre: "Departamento de Enlace Jurídico" },
    { codigo: "03", nombre: "Departamento de Relaciones Institucionales y Contratos" },
  ]},
  { codigo: "118", nombre: "SIPINNA Municipal", areas: [
    { codigo: "00", nombre: "Secretaría Ejecutiva" },
    { codigo: "01", nombre: "Departamento de Políticas Públicas y Vinculación" },
    { codigo: "02", nombre: "Departamento de Información, Difusión y Primer Contacto" },
  ]},
  { codigo: "119", nombre: "OOMSAPAS La Paz", areas: [
    { codigo: "00", nombre: "Despacho" },
  ]},
  { codigo: "120", nombre: "Tiburón Urbano de La Paz", areas: [
    { codigo: "00", nombre: "Despacho" },
  ]},
];

// Consecutivo simulado para el prototipo (en producción viene de la BD)
let homoclaveConsecutivo = 1;


