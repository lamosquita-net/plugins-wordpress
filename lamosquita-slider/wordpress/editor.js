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
      return s;
    });
    return v;
  }
  datos.defecto = normalizar(datos.defecto);
  datos.programaciones = (datos.programaciones || []).map(normalizar);

  function version() { return actual < 0 ? datos.defecto : datos.programaciones[actual]; }
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
        oninput: function (e) { v.proporciones[f.id] = e.target.value; guardar(); }
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
      return h('label', { title: p.replace('-', ' ') }, [h('input', { type: 'radio', name: 'lmq-pos', checked: t.posicion === p, onchange: function () { t.posicion = p; guardar(); } }), h('span', {})]);
    }));
    return h('fieldset', { class: 'lmq-bloque' }, [
      h('legend', {}, 'Título encima de la imagen'),
      h('div', { class: 'lmq-fila' }, [
        h('label', {}, ['Cuerpo ', h('input', { type: 'text', size: 6, value: t.tamano, placeholder: '2.5rem', oninput: function (e) { t.tamano = e.target.value; guardar(); } })]),
        h('label', {}, ['En móvil ', h('input', { type: 'text', size: 6, value: t.tamano_movil, placeholder: '1.5rem', oninput: function (e) { t.tamano_movil = e.target.value; guardar(); } })]),
        h('label', {}, ['Grosor ', h('select', { onchange: function (e) { t.peso = e.target.value; guardar(); } }, pesos)]),
        h('label', {}, ['Color ', h('input', { type: 'color', value: t.color, oninput: function (e) { t.color = e.target.value; guardar(); } })]),
        h('div', { class: 'lmq-posicion' }, [h('span', { class: 'lmq-etiqueta' }, 'Posición'), rejilla])
      ]),
      h('p', { class: 'description' }, 'Cuerpo en px, rem, em, vw o svh. La tipografía es la del theme. El color se puede cambiar en cada slide.')
    ]);
  }

  function imagen(v, s, f) {
    var i = s.imagenes[f.id];
    var url = i && E.miniaturas[i.id];
    var foco = (i && i.foco) || '50% 50%';
    var xy = foco.split(' ');

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
          h('img', { src: url, alt: '' }),
          h('span', { class: 'lmq-foco__v', style: 'left:' + xy[0] }),
          h('span', { class: 'lmq-foco__h', style: 'top:' + xy[1] })
        ])
      : h('button', { type: 'button', class: 'lmq-hueco', onclick: elegir }, f.id === 'escritorio' ? 'Elegir imagen (obligatoria)' : 'Elegir imagen (opcional)');

    return h('figure', { class: 'lmq-formato' + (f.id === 'escritorio' && !url ? ' es-falta' : '') }, [
      h('figcaption', {}, f.nombre + ' · ' + v.proporciones[f.id]),
      cuadro,
      url ? h('div', { class: 'lmq-formato__pie' }, [
        h('span', {}, 'foco ' + foco),
        h('button', { type: 'button', class: 'button-link', onclick: elegir }, 'Cambiar'),
        h('button', { type: 'button', class: 'button-link lmq-borrar', onclick: function () { delete s.imagenes[f.id]; guardar(); pintar(); } }, 'Quitar')
      ]) : (f.id === 'escritorio' ? null : h('div', { class: 'lmq-formato__pie' }, 'Sin imagen propia: usa la de escritorio, recortada alrededor de su foco.'))
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
        h('label', { class: 'lmq-ancho' }, ['Título ', h('input', { type: 'text', value: s.titulo, placeholder: 'Sin título', oninput: function (e) { s.titulo = e.target.value; guardar(); } })]),
        h('label', { class: 'lmq-ancho' }, ['Enlace al pulsar ', h('input', { type: 'text', value: s.url, placeholder: 'https://… o /pagina/', oninput: function (e) { s.url = e.target.value; guardar(); } })]),
        h('label', {}, [
          h('input', { type: 'checkbox', checked: colorPropio, onchange: function (e) { s.color = e.target.checked ? (v.titulo.color || '#ffffff') : ''; guardar(); pintar(); } }),
          ' Color propio del título ',
          colorPropio ? h('input', { type: 'color', value: s.color, oninput: function (e) { s.color = e.target.value; guardar(); } }) : null
        ])
      ])
    ]);
  }

  function slides(v) {
    return h('fieldset', { class: 'lmq-bloque' }, [
      h('legend', {}, 'Slides (' + v.slides.length + ')'),
      h('div', {}, v.slides.map(function (s, n) { return slide(v, s, n); })),
      h('button', { type: 'button', class: 'button button-primary', onclick: function () {
        v.slides.push({ titulo: '', url: '', color: '', imagenes: {} }); guardar(); pintar();
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
    w.scrollTo(0, y);
  }

  guardar();
  pintar();
})(window, document);
