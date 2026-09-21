/*!
 * lamosquita-slider · editor (escritorio de WordPress)
 * -----------------------------------------------------------------
 * Pinta la caja de edición a partir del JSON de #lmq-slider-datos y lo
 * mantiene al día con cada cambio. Al guardar la entrada, WordPress manda
 * ese JSON y PHP lo sanea entero (wordpress/datos.php).
 *
 * Sólo se vuelve a pintar todo cuando cambia la estructura (pestaña,
 * slides, imágenes, foco). Al escribir en un campo sólo se actualiza el
 * dato, para no perder el cursor.
 */
(function (w, d) {
  'use strict';

  var E = w.LMQ_EDITOR;
  var campo = d.getElementById('lmq-slider-datos');
  var raiz = d.getElementById('lmq-editor');
  if (!E || !campo || !raiz) return;

  var datos = JSON.parse(campo.value || '{}');
  var actual = -1;   // -1 = por defecto; 0, 1… = programación

  // ─── utilidades ────────────────────────────────────────────────────

  /** h('div', {class: 'x', onclick: f}, [hijos]) */
  function h(etiqueta, atr, hijos) {
    var el = d.createElement(etiqueta);
    Object.keys(atr || {}).forEach(function (k) {
      var v = atr[k];
      if (v === null || v === undefined || v === false) return;
      if (k.slice(0, 2) === 'on') el.addEventListener(k.slice(2), v);
      else if (k === 'value') el.value = v;
      else if (k === 'checked') el.checked = !!v;
      else el.setAttribute(k, v === true ? '' : v);
    });
    [].concat(hijos || []).forEach(function (c) {
      if (c === null || c === undefined || c === false) return;
      el.appendChild(typeof c === 'string' ? d.createTextNode(c) : c);
    });
    return el;
  }
  function copia(o) { return JSON.parse(JSON.stringify(o)); }
  function guardar() { campo.value = JSON.stringify(datos); }

  /** PHP convierte los objetos vacíos en listas: se arreglan aquí. */
  function normalizar(v) {
    var n = copia(E.nueva);
    v = v || {};
    Object.keys(n).forEach(function (k) { if (v[k] === undefined) v[k] = n[k]; });
    if (Array.isArray(v.proporciones)) v.proporciones = {};
    if (Array.isArray(v.titulo)) v.titulo = {};
    Object.keys(n.proporciones).forEach(function (f) { if (!v.proporciones[f]) v.proporciones[f] = n.proporciones[f]; });
    Object.keys(n.titulo).forEach(function (k) { if (v.titulo[k] === undefined) v.titulo[k] = n.titulo[k]; });
    v.slides = (v.slides || []).map(function (s) {
      s.imagenes = (!s.imagenes || Array.isArray(s.imagenes)) ? {} : s.imagenes;
      s.titulo = s.titulo || ''; s.url = s.url || ''; s.color = s.color || '';
      s.posiciones = (!s.posiciones || Array.isArray(s.posiciones)) ? {} : s.posiciones;
      return s;
    });
    return v;
  }
  datos.defecto = normalizar(datos.defecto);
  datos.programaciones = (datos.programaciones || []).map(normalizar);

  function version() { return actual < 0 ? datos.defecto : datos.programaciones[actual]; }

  // En pantalla completa el hueco depende de cada pantalla: para la vista
  // previa se usa una típica de cada formato.
  var PANTALLA_TIPICA = { 'escritorio': [16, 9], 'tableta-horizontal': [4, 3], 'tableta-vertical': [3, 4], 'movil': [9, 19.5] };

  /** [ancho, alto] de la proporción del formato en esta versión. */
  function proporcion(v, f) {
    if (v.modo === 'pantalla') return PANTALLA_TIPICA[f];
    var m = String(v.proporciones[f] || '').match(/^\s*(\d+(?:\.\d+)?)\s*[:\/]\s*(\d+(?:\.\d+)?)\s*$/);
    if (m && +m[1] > 0 && +m[2] > 0) return [+m[1], +m[2]];
    var n = String(E.nueva.proporciones[f]).split(':');
    return [+n[0], +n[1]];
  }

  /**
   * Coloca sobre la imagen entera el rectángulo que se verá en un hueco de
   * proporción «prop», con object-fit: cover y el foco como object-position:
   * el punto del foco de la imagen cae en el mismo punto del hueco.
   */
  function marcar(img, marco, prop, foco) {
    if (!img.naturalWidth) return;
    var ri = img.naturalWidth / img.naturalHeight, rc = prop[0] / prop[1];
    var fw = ri > rc ? rc / ri : 1, fh = ri > rc ? 1 : ri / rc;
    var xy = foco.split(' ').map(function (n) { return parseFloat(n) / 100; });
    marco.style.left = (xy[0] * (1 - fw) * 100) + '%';
    marco.style.top = (xy[1] * (1 - fh) * 100) + '%';
    marco.style.width = (fw * 100) + '%';
    marco.style.height = (fh * 100) + '%';
    escalar();
  }

  // ─── el título en las vistas previas ─────────────────────────────
  // Pantalla típica de cada formato, para pasar el cuerpo del título (rem,
  // vw…) a píxeles y reducirlo a la escala de la vista previa. Orientativo:
  // si el theme pone el slider más estrecho que la pantalla, en la web el
  // título ocupará algo más.
  var PANTALLA = { 'escritorio': [1280, 800], 'tableta-horizontal': [1024, 768], 'tableta-vertical': [768, 1024], 'movil': [390, 844] };

  function pxReales(valor, f) {
    var m = String(valor || '').match(/^(\d+(?:\.\d+)?)(px|rem|em|vw|vh|svh)$/);
    if (!m) return 40;
    var n = +m[1];
    if (m[2] === 'px') return n;
    if (m[2] === 'rem' || m[2] === 'em') return n * 16;
    if (m[2] === 'vw') return n * PANTALLA[f][0] / 100;
    return n * PANTALLA[f][1] / 100;
  }

  /** Posición que se verá en un formato: la suya → la de escritorio del slide → la del slider. */
  function posicionDe(v, s, f) { var p = s.posiciones || {}; return p[f] || p.escritorio || v.titulo.posicion; }
  function origenDe(s, f) {
    var p = s.posiciones || {};
    if (p[f]) return 'propia';
    return (f !== 'escritorio' && p.escritorio) ? 'la de escritorio' : 'la del slider';
  }
  function alineacion(pos) {
    var partes = (pos === 'centro' ? 'centro-centro' : pos).split('-');
    var eje = { arriba: 'flex-start', centro: 'center', abajo: 'flex-end', izquierda: 'flex-start', derecha: 'flex-end' };
    var txt = { izquierda: 'left', centro: 'center', derecha: 'right' };
    return 'align-items:' + eje[partes[0]] + ';justify-content:' + eje[partes[1]] + ';text-align:' + txt[partes[1]];
  }

  /** La capa del título que va encima de una vista previa. El tamaño lo pone escalar(). */
  function capaTitulo(v, s, f) {
    if (!s.titulo) return null;
    return h('div', {
      class: 'lmq-ed-capa', style: alineacion(posicionDe(v, s, f)),
      'data-formato': f, 'data-cuerpo': f === 'movil' ? v.titulo.tamano_movil : v.titulo.tamano
    }, h('span', { class: 'lmq-ed-titulo', style: 'font-weight:' + v.titulo.peso + ';color:' + (s.color || v.titulo.color) }, s.titulo));
  }

  /** Cuerpo y margen de cada título a la escala de su vista previa. */
  function escalar() {
    [].forEach.call(raiz.querySelectorAll('.lmq-ed-capa'), function (capa) {
      var f = capa.getAttribute('data-formato');
      var ancho = capa.getBoundingClientRect().width;
      if (!ancho) return;
      var escala = ancho / PANTALLA[f][0];
      var margen = Math.min(48, Math.max(16, PANTALLA[f][0] * 0.04));   // el clamp(1rem, 4vw, 3rem) del CSS
      capa.style.padding = (margen * escala) + 'px';
      capa.firstChild.style.fontSize = (pxReales(capa.getAttribute('data-cuerpo'), f) * escala) + 'px';
    });
  }
  w.addEventListener('resize', escalar);

  /** Estilo de la vista previa: su proporción, y como mucho 220 px de alto sin deformarse. */
  function estiloPrevia(prop) {
    return 'aspect-ratio:' + prop[0] + ' / ' + prop[1] + ';width:min(100%, ' + Math.round(220 * prop[0] / prop[1]) + 'px)';
  }

  /** Mientras se teclea una proporción, se mueven las vistas previas y los marcos sin volver a pintar. */
  function refrescarProporcion(v, f) {
    var prop = proporcion(v, f);
    [].forEach.call(raiz.querySelectorAll('[data-lmq-formato="' + f + '"]'), function (caja) {
      var prev = caja.querySelector('.lmq-previa');
      if (prev) prev.setAttribute('style', estiloPrevia(prop));
      var img = caja.querySelector('.lmq-foco img'), marco = caja.querySelector('.lmq-foco__marco');
      if (img && marco) marcar(img, marco, prop, caja.getAttribute('data-lmq-foco'));
    });
    escalar();
  }
  function tieneImagen(s) { return !!(s.imagenes && s.imagenes.escritorio && s.imagenes.escritorio.id); }
  function aInput(f) { return (f || '').replace(' ', 'T'); }     // «2026-12-01 00:00» ⇄ datetime-local
  function deInput(f) { return (f || '').replace('T', ' '); }
  function fechaCorta(f) { return f ? f.slice(8, 10) + '/' + f.slice(5, 7) : '…'; }

  // ─── piezas ───────────────────────────────────────────────────────

  function pestanas() {
    var puedeProgramar = datos.defecto.slides.some(tieneImagen);
    var botones = [h('button', { type: 'button', class: actual < 0 ? 'es-activa' : '', onclick: function () { actual = -1; pintar(); } }, 'Por defecto')];
    datos.programaciones.forEach(function (p, i) {
      var nombre = p.nombre ? '«' + p.nombre + '»' : 'Programación ' + (i + 1);
      botones.push(h('button', { type: 'button', class: actual === i ? 'es-activa' : '', onclick: function () { actual = i; pintar(); } },
        nombre + ' · ' + fechaCorta(p.desde) + '–' + fechaCorta(p.hasta)));
    });
    botones.push(h('button', {
      type: 'button', class: 'lmq-programar', disabled: !puedeProgramar,
      title: puedeProgramar ? 'Copia el por defecto entero para cambiar lo que haga falta' : 'Antes, el por defecto necesita al menos un slide con imagen de escritorio',
      onclick: function () {
        var p = copia(datos.defecto);
        p.nombre = ''; p.desde = ''; p.hasta = '';
        datos.programaciones.push(p);
        actual = datos.programaciones.length - 1;
        guardar(); pintar();
      }
    }, '+ Programar a partir del por defecto'));
    return h('div', { class: 'lmq-pestanas' }, botones);
  }

  function fechas(p) {
    var aviso = null;
    if (!p.desde || !p.hasta) aviso = 'Sin las dos fechas, esta programación no se verá nunca.';
    else if (p.desde >= p.hasta) aviso = 'La fecha de fin tiene que ser posterior a la de inicio.';
    return h('fieldset', { class: 'lmq-bloque' }, [
      h('legend', {}, 'Programación'),
      h('div', { class: 'lmq-fila' }, [
        h('label', {}, ['Nombre ', h('input', { type: 'text', value: p.nombre, placeholder: 'Navidad', oninput: function (e) { p.nombre = e.target.value; guardar(); }, onchange: function () { pintar(); } })]),
        h('label', {}, ['Desde ', h('input', { type: 'datetime-local', value: aInput(p.desde), onchange: function (e) { p.desde = deInput(e.target.value); guardar(); pintar(); } })]),
        h('label', {}, ['Hasta (sin incluir) ', h('input', { type: 'datetime-local', value: aInput(p.hasta), onchange: function (e) { p.hasta = deInput(e.target.value); guardar(); pintar(); } })]),
        h('button', { type: 'button', class: 'button-link lmq-borrar', onclick: function () {
          if (!w.confirm('¿Borrar esta programación?')) return;
          datos.programaciones.splice(actual, 1); actual = -1; guardar(); pintar();
        } }, 'Borrar programación')
      ]),
      h('p', { class: 'description' }, 'Hora de la web (' + E.zona + '). Si se solapan dos, se ve la que empieza más tarde.'),
      aviso ? h('p', { class: 'lmq-aviso' }, aviso) : null
    ]);
  }

  function ajustes(v) {
    var pantalla = v.modo === 'pantalla';
    var props = E.formatos.map(function (f) {
      return h('label', {}, [f.nombre + ' ', h('input', {
        type: 'text', size: 5, value: v.proporciones[f.id], disabled: pantalla, placeholder: '16:9',
        oninput: function (e) { v.proporciones[f.id] = e.target.value; guardar(); refrescarProporcion(v, f.id); },
        onchange: function () { pintar(); }
      })]);
    });
    return h('fieldset', { class: 'lmq-bloque' }, [
      h('legend', {}, 'Cómo se ve'),
      h('div', { class: 'lmq-fila' }, [
        h('label', {}, [h('input', { type: 'radio', name: 'lmq-modo', checked: !pantalla, onchange: function () { v.modo = 'proporcion'; guardar(); pintar(); } }), ' Alto según la proporción de cada formato']),
        h('label', {}, [h('input', { type: 'radio', name: 'lmq-modo', checked: pantalla, onchange: function () { v.modo = 'pantalla'; guardar(); pintar(); } }), ' Pantalla completa'])
      ]),
      h('div', { class: 'lmq-fila' + (pantalla ? ' es-apagada' : '') }, [h('span', { class: 'lmq-etiqueta' }, 'Proporciones')].concat(props)),
      pantalla ? h('p', { class: 'description' }, 'En pantalla completa las imágenes se recortan al alto de la ventana, alrededor del foco de cada una.') : null,
      h('div', { class: 'lmq-fila' }, [
        h('label', {}, ['Cada slide se ve ', h('input', { type: 'number', min: 1, max: 60, step: 0.5, value: v.tiempo, style: 'width:5em', oninput: function (e) { v.tiempo = parseFloat(e.target.value) || 5; guardar(); } }), ' segundos']),
        h('label', {}, ['Transición ', h('select', { onchange: function (e) { v.transicion = e.target.value; guardar(); } },
          Object.keys(E.transiciones).map(function (k) { return h('option', { value: k, selected: v.transicion === k }, E.transiciones[k]); }))])
      ])
    ]);
  }

  function titulo(v) {
    var t = v.titulo;
    var pesos = [100, 200, 300, 400, 500, 600, 700, 800, 900].map(function (p) {
      return h('option', { value: p, selected: String(t.peso) === String(p) }, String(p));
    });
    var rejilla = h('div', { class: 'lmq-rejilla', role: 'radiogroup', 'aria-label': 'Posición del título' }, E.posiciones.map(function (p) {
      return h('label', { title: p.replace('-', ' ') }, [h('input', { type: 'radio', name: 'lmq-pos', checked: t.posicion === p, onchange: function () { t.posicion = p; guardar(); pintar(); } }), h('span', {})]);
    }));
    return h('fieldset', { class: 'lmq-bloque' }, [
      h('legend', {}, 'Título encima de la imagen'),
      h('div', { class: 'lmq-fila' }, [
        h('label', {}, ['Cuerpo ', h('input', { type: 'text', size: 6, value: t.tamano, placeholder: '2.5rem', oninput: function (e) { t.tamano = e.target.value; guardar(); }, onchange: pintar })]),
        h('label', {}, ['En móvil ', h('input', { type: 'text', size: 6, value: t.tamano_movil, placeholder: '1.5rem', oninput: function (e) { t.tamano_movil = e.target.value; guardar(); }, onchange: pintar })]),
        h('label', {}, ['Grosor ', h('select', { onchange: function (e) { t.peso = e.target.value; guardar(); pintar(); } }, pesos)]),
        h('label', {}, ['Color ', h('input', { type: 'color', value: t.color, oninput: function (e) { t.color = e.target.value; guardar(); }, onchange: pintar })]),
        h('div', { class: 'lmq-posicion' }, [h('span', { class: 'lmq-etiqueta' }, 'Posición'), rejilla])
      ]),
      h('p', { class: 'description' }, 'Cuerpo en px, rem, em, vw o svh. La tipografía es la del theme. El color se puede cambiar en cada slide, y la posición en cada slide y en cada formato.')
    ]);
  }

  function imagen(v, s, f) {
    var i = s.imagenes[f.id];
    var url = i && E.miniaturas[i.id];
    var foco = (i && i.foco) || '50% 50%';
    var xy = foco.split(' ');
    var prop = proporcion(v, f.id);
    var esc = s.imagenes.escritorio;
    var urlEsc = esc && E.miniaturas[esc.id];

    function elegir() {
      var marco = w.wp.media({ title: 'Imagen ' + f.nombre.toLowerCase(), library: { type: 'image' }, button: { text: 'Usar esta imagen' }, multiple: false });
      marco.on('select', function () {
        var a = marco.state().get('selection').first().toJSON();
        E.miniaturas[a.id] = (a.sizes && (a.sizes.medium || a.sizes.large || a.sizes.full) || a).url;
        s.imagenes[f.id] = { id: a.id, foco: (i && i.foco) || '50% 50%' };
        guardar(); pintar();
      });
      marco.open();
    }

    var marco = h('span', { class: 'lmq-foco__marco' }, capaTitulo(v, s, f.id));
    var fotoFoco = url ? h('img', { src: url, alt: '', onload: function (e) { marcar(e.target, marco, prop, foco); } }) : null;

    var cuadro = url
      ? h('div', {
          class: 'lmq-foco', title: 'Pincha donde tenga que quedar siempre el centro de interés',
          onclick: function (e) {
            var img = e.currentTarget.querySelector('img');
            var r = img.getBoundingClientRect();
            var x = Math.round(Math.min(100, Math.max(0, (e.clientX - r.left) / r.width * 100)));
            var y = Math.round(Math.min(100, Math.max(0, (e.clientY - r.top) / r.height * 100)));
            s.imagenes[f.id].foco = x + '% ' + y + '%';
            guardar(); pintar();
          }
        }, [
          fotoFoco,
          marco,
          h('span', { class: 'lmq-foco__v', style: 'left:' + xy[0] }),
          h('span', { class: 'lmq-foco__h', style: 'top:' + xy[1] })
        ])
      : (f.id !== 'escritorio' && urlEsc
          // Sin imagen propia: cómo saldrá en la web con la de escritorio.
          ? h('div', { class: 'lmq-previa', style: estiloPrevia(prop) }, [
              h('img', { src: urlEsc, alt: '', style: 'object-position:' + (esc.foco || '50% 50%') }),
              capaTitulo(v, s, f.id)
            ])
          : h('button', { type: 'button', class: 'lmq-hueco', onclick: elegir }, f.id === 'escritorio' ? 'Elegir imagen (obligatoria)' : 'Elegir imagen (opcional)'));

    var pie;
    if (url) {
      pie = h('div', { class: 'lmq-formato__pie' }, [
        h('span', {}, 'foco ' + foco + ' · lo oscuro no se verá'),
        h('button', { type: 'button', class: 'button-link', onclick: elegir }, 'Cambiar'),
        h('button', { type: 'button', class: 'button-link lmq-borrar', onclick: function () { delete s.imagenes[f.id]; guardar(); pintar(); } }, 'Quitar')
      ]);
    } else if (f.id !== 'escritorio' && urlEsc) {
      pie = h('div', { class: 'lmq-formato__pie' }, [
        h('span', {}, 'Así saldrá con la de escritorio.'),
        h('button', { type: 'button', class: 'button-link', onclick: elegir }, 'Elegir imagen propia')
      ]);
    }

    var origen = origenDe(s, f.id);
    var efectiva = posicionDe(v, s, f.id);
    var posicion = (url || (f.id !== 'escritorio' && urlEsc)) ? h('div', { class: 'lmq-pos-formato' }, [
      h('div', { class: 'lmq-rejilla lmq-rejilla--mini' + (origen === 'propia' ? '' : ' es-heredada'), role: 'radiogroup', 'aria-label': 'Posición del título en ' + f.nombre.toLowerCase() },
        E.posiciones.map(function (p) {
          return h('label', { title: p.replace('-', ' ') }, [h('input', {
            type: 'radio', name: 'lmq-pos-' + v.slides.indexOf(s) + '-' + f.id, checked: efectiva === p,
            onchange: function () { s.posiciones[f.id] = p; guardar(); pintar(); }
          }), h('span', {})]);
        })),
      h('span', { class: 'lmq-pos-formato__texto' }, [
        'Título: ' + origen,
        origen === 'propia' ? h('button', { type: 'button', class: 'button-link', onclick: function () { delete s.posiciones[f.id]; guardar(); pintar(); } },
          f.id === 'escritorio' ? 'volver a la del slider' : 'volver a heredar') : null
      ])
    ]) : null;

    return h('figure', { class: 'lmq-formato' + (f.id === 'escritorio' && !url ? ' es-falta' : ''), 'data-lmq-formato': f.id, 'data-lmq-foco': url ? foco : ((esc && esc.foco) || '50% 50%') }, [
      h('figcaption', {}, f.nombre + ' · ' + (v.modo === 'pantalla' ? 'pantalla típica ' + prop.join(':') : v.proporciones[f.id])),
      cuadro,
      pie,
      posicion
    ]);
  }

  function slide(v, s, n) {
    var total = v.slides.length;
    var colorPropio = !!s.color;
    return h('div', { class: 'lmq-slide-ed' + (tieneImagen(s) ? '' : ' es-incompleto') }, [
      h('div', { class: 'lmq-slide-ed__cab' }, [
        h('strong', {}, 'Slide ' + (n + 1)),
        tieneImagen(s) ? null : h('span', { class: 'lmq-aviso' }, 'Sin imagen de escritorio: este slide no se verá'),
        h('span', { class: 'lmq-slide-ed__botones' }, [
          h('button', { type: 'button', class: 'button', disabled: n === 0, 'aria-label': 'Subir', onclick: function () { v.slides.splice(n - 1, 0, v.slides.splice(n, 1)[0]); guardar(); pintar(); } }, '↑'),
          h('button', { type: 'button', class: 'button', disabled: n === total - 1, 'aria-label': 'Bajar', onclick: function () { v.slides.splice(n + 1, 0, v.slides.splice(n, 1)[0]); guardar(); pintar(); } }, '↓'),
          h('button', { type: 'button', class: 'button lmq-borrar', onclick: function () { if (w.confirm('¿Quitar el slide ' + (n + 1) + '?')) { v.slides.splice(n, 1); guardar(); pintar(); } } }, 'Quitar')
        ])
      ]),
      h('div', { class: 'lmq-formatos' }, E.formatos.map(function (f) { return imagen(v, s, f); })),
      h('div', { class: 'lmq-fila' }, [
        h('label', { class: 'lmq-ancho' }, ['Título ', h('input', { type: 'text', value: s.titulo, placeholder: 'Sin título', oninput: function (e) {
          s.titulo = e.target.value; guardar();
          [].forEach.call(e.target.closest('.lmq-slide-ed').querySelectorAll('.lmq-ed-titulo'), function (t) { t.textContent = s.titulo; });
        }, onchange: pintar })]),
        h('label', { class: 'lmq-ancho' }, ['Enlace al pulsar ', h('input', { type: 'text', value: s.url, placeholder: 'https://… o /pagina/', oninput: function (e) { s.url = e.target.value; guardar(); } })]),
        h('label', {}, [
          h('input', { type: 'checkbox', checked: colorPropio, onchange: function (e) { s.color = e.target.checked ? (v.titulo.color || '#ffffff') : ''; guardar(); pintar(); } }),
          ' Color propio del título ',
          colorPropio ? h('input', { type: 'color', value: s.color, oninput: function (e) { s.color = e.target.value; guardar(); }, onchange: pintar }) : null
        ])
      ])
    ]);
  }

  function slides(v) {
    return h('fieldset', { class: 'lmq-bloque' }, [
      h('legend', {}, 'Slides (' + v.slides.length + ')'),
      h('div', {}, v.slides.map(function (s, n) { return slide(v, s, n); })),
      h('button', { type: 'button', class: 'button button-primary', onclick: function () {
        v.slides.push({ titulo: '', url: '', color: '', imagenes: {}, posiciones: {} }); guardar(); pintar();
      } }, '+ Añadir slide')
    ]);
  }

  function pintar() {
    if (actual >= datos.programaciones.length) actual = -1;
    var v = version();
    var y = w.scrollY;
    // replaceChildren convertiría un null en el texto «null»: se filtra antes.
    raiz.replaceChildren.apply(raiz, [
      pestanas(),
      actual >= 0 ? fechas(v) : null,
      ajustes(v),
      titulo(v),
      slides(v)
    ].filter(Boolean));
    escalar();
    w.scrollTo(0, y);
  }

  guardar();
  pintar();
})(window, document);
