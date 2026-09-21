/* =================================================================
   lamosquita-lightbox · el visor (sin jQuery ni librerías)
   -----------------------------------------------------------------
   - Se abre con cualquier enlace a una foto, salvo los que llevan (ellos
     o un contenedor suyo) la clase «nolightbox».
   - Agrupa: por rel (rel="lightbox"), si no por galería (.gallery,
     .wp-block-gallery, [data-lmq-grupo]), si no la foto sola. Sólo los
     enlaces que se ven y sin repetir la misma foto.
   - Con el dedo: deslizar para pasar (la foto sigue al dedo), pellizcar o
     doble toque para el zoom y, con zoom, arrastrar para moverse. Con
     ratón: flechas, rueda para el zoom y doble clic. Teclado: ← → y Esc.
   - El botón «atrás» del móvil cierra el visor en vez de salir de la página.
   ================================================================= */
(function () {
  'use strict';

  // ── AJUSTES ─────────────────────────────────────────────────────
  var UMBRAL = 0.18;       // parte del ancho que hay que arrastrar para pasar de foto
  var RAPIDO = 0.45;       // …o esta velocidad (px/ms), aunque el arrastre sea corto
  var ZOOM_DOBLE = 2.5;    // zoom del doble toque
  var ZOOM_MAX = 4;
  var GRUPOS = '.gallery, .wp-block-gallery, .tiled-gallery, [data-lmq-grupo]';
  // ── fin de AJUSTES ──────────────────────────────────────────────

  var C = window.LMQ_LIGHTBOX || {};
  var T = C.textos || {};
  var FOTO = /\.(jpe?g|png|webp|avif|gif)(?:[?#]|$)/i;
  var REL_NO = /^(noopener|noreferrer|nofollow|external|ugc|sponsored|me|author|bookmark|tag)$/i;

  var capa, pista, paneles, contador, pie, bAnterior, bSiguiente;
  var fotos = [], actual = 0, origen = null, abierto = false, animando = false, terminar = null;
  var z = { s: 1, x: 0, y: 0 };           // zoom de la foto actual
  var punteros = {}, gesto = null, toque = null;

  // ─── Qué se abre ─────────────────────────────────────────────────

  function valido(a) {
    if (!a || !a.getAttribute || a.closest('.nolightbox') || a.hasAttribute('download')) return false;
    return a.hasAttribute('data-lmq-src') || FOTO.test(a.getAttribute('href') || '');
  }

  function foto(a) {
    return {
      src: a.getAttribute('data-lmq-src') || a.href,
      srcset: a.getAttribute('data-lmq-srcset') || '',
      ancho: +a.getAttribute('data-lmq-ancho') || 0,
      alto: +a.getAttribute('data-lmq-alto') || 0,
      pie: pieDe(a)
    };
  }

  function pieDe(a) {
    if (C.pie === false) return '';
    var caja = a.closest('figure, .gallery-item, .wp-caption');
    var leyenda = caja && caja.querySelector('figcaption, .wp-caption-text, .gallery-caption');
    var t = leyenda ? leyenda.textContent.trim() : '';
    if (!t) { var img = a.querySelector('img'); t = img ? (img.getAttribute('alt') || '').trim() : ''; }
    return t || (a.getAttribute('title') || '').trim();
  }

  /** Las fotos del grupo del enlace pulsado, y cuál es la suya. */
  function grupo(a) {
    var rel = (a.getAttribute('rel') || '').split(/\s+/).filter(function (r) { return r && !REL_NO.test(r); })[0];
    var lista;
    if (rel) {
      lista = [].filter.call(document.querySelectorAll('a[rel]'), function (x) {
        return (' ' + x.getAttribute('rel') + ' ').indexOf(' ' + rel + ' ') > -1;
      });
    } else {
      var g = a.closest(GRUPOS);
      lista = g ? [].slice.call(g.querySelectorAll('a')) : [a];
    }
    var vistas = {}, res = [], suya = 0;
    lista.forEach(function (x) {
      if (!valido(x) || (x !== a && !x.getClientRects().length)) return;   // oculto (p. ej. la versión de otro formato)
      var f = foto(x);
      if (vistas[f.src] !== undefined) { if (x === a) suya = vistas[f.src]; return; }
      vistas[f.src] = res.length;
      if (x === a) suya = res.length;
      res.push(f);
    });
    return { fotos: res, actual: suya };
  }

  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var a = e.target.closest && e.target.closest('a');
    if (!valido(a)) return;
    e.preventDefault();
    e.stopPropagation();   // en captura: ningún otro visor (Firelight…) llega a enterarse
    var g = grupo(a);
    abrir(g.fotos, g.actual, a);
  }, true);

  // ─── La capa ─────────────────────────────────────────────────────

  function el(etiqueta, clase, texto) {
    var n = document.createElement(etiqueta);
    if (clase) n.className = clase;
    if (texto) n.textContent = texto;
    return n;
  }
  function boton(clase, texto, etiqueta, fn) {
    var b = el('button', clase, texto);
    b.type = 'button';
    b.setAttribute('aria-label', etiqueta);
    b.addEventListener('click', fn);
    return b;
  }

  function crear() {
    capa = el('dialog', 'lmq-lb');
    capa.setAttribute('aria-label', T.visor || 'Visor de fotos');
    pista = el('div', 'lmq-lb__pista');
    paneles = [0, 1, 2].map(function () {
      var p = el('figure', 'lmq-lb__foto');
      var img = el('img');
      img.decoding = 'async';
      img.draggable = false;
      img.addEventListener('load', function () { img.classList.add('es-cargada'); });
      img.addEventListener('error', function () { img.classList.add('es-cargada'); });
      p.appendChild(img);
      pista.appendChild(p);
      return p;
    });
    contador = el('span', 'lmq-lb__contador');
    pie = el('p', 'lmq-lb__pie');
    bAnterior = boton('lmq-lb__anterior', '‹', T.anterior || 'Foto anterior', function () { ir(-1); });
    bSiguiente = boton('lmq-lb__siguiente', '›', T.siguiente || 'Foto siguiente', function () { ir(1); });
    capa.append(pista, contador, pie, bAnterior, bSiguiente,
      boton('lmq-lb__cerrar', '×', T.cerrar || 'Cerrar', cerrar));
    document.body.appendChild(capa);

    capa.addEventListener('cancel', function (e) { e.preventDefault(); cerrar(); });   // Esc
    capa.addEventListener('keydown', function (e) {
      if (e.key === 'ArrowLeft') { e.preventDefault(); ir(-1); }
      else if (e.key === 'ArrowRight') { e.preventDefault(); ir(1); }
    });
    pista.addEventListener('pointerdown', abajo);
    pista.addEventListener('pointermove', mover);
    pista.addEventListener('pointerup', arriba);
    pista.addEventListener('pointercancel', arriba);
    pista.addEventListener('wheel', rueda, { passive: false });
    window.addEventListener('resize', function () { if (abierto) quitarZoom(false); });
    window.addEventListener('popstate', function () { if (abierto) cerrarYa(); });
  }

  function indice(i) {
    if (i >= 0 && i < fotos.length) return i;
    if (C.ciclico && fotos.length > 1) return (i + fotos.length) % fotos.length;
    return -1;
  }

  function pintarPanel(p, i) {
    var img = p.firstChild, f = fotos[i];
    if (!f) { p.removeAttribute('data-src'); img.removeAttribute('srcset'); img.removeAttribute('src'); img.hidden = true; return; }
    img.hidden = false;
    if (p.getAttribute('data-src') === f.src) return;   // ya estaba (al pasar se reaprovecha)
    p.setAttribute('data-src', f.src);
    img.classList.remove('es-cargada');
    img.alt = f.pie;
    if (f.ancho) { img.width = f.ancho; img.height = f.alto; } else { img.removeAttribute('width'); img.removeAttribute('height'); }
    img.sizes = '100vw';
    if (f.srcset) img.srcset = f.srcset; else img.removeAttribute('srcset');
    img.src = f.src;
    if (img.complete && img.naturalWidth) img.classList.add('es-cargada');
  }

  function pintar() {
    pintarPanel(paneles[0], indice(actual - 1));
    pintarPanel(paneles[1], actual);
    pintarPanel(paneles[2], indice(actual + 1));
    var varias = fotos.length > 1;
    contador.textContent = varias ? (actual + 1) + ' / ' + fotos.length : '';
    pie.textContent = fotos[actual].pie;
    bAnterior.hidden = bSiguiente.hidden = !varias;
    bAnterior.disabled = indice(actual - 1) < 0;
    bSiguiente.disabled = indice(actual + 1) < 0;
  }

  function abrir(lista, i, desde) {
    if (!lista.length) return;
    if (!capa) crear();
    fotos = lista; actual = i; origen = desde;
    quitarZoom(false);
    pista.style.transform = '';
    pintar();
    capa.showModal();
    abierto = true;
    document.documentElement.style.overflow = 'hidden';
    history.pushState({ lmqLb: true }, '');
  }

  /** Desde un botón o Esc: si abrimos con una entrada en el historial, se
   *  cierra yendo atrás (y el popstate hace el resto). */
  function cerrar() {
    if (history.state && history.state.lmqLb) history.back();
    else cerrarYa();
  }
  function cerrarYa() {
    abierto = false;
    capa.close();
    document.documentElement.style.overflow = '';
    if (origen && origen.focus) origen.focus({ preventScroll: true });
  }

  // ─── Pasar de foto ───────────────────────────────────────────────

  function ancho() { return pista.clientWidth || window.innerWidth; }

  function desplazar(px, animar, luego) {
    pista.classList.toggle('es-animando', !!animar);
    pista.style.transform = px ? 'translateX(' + px + 'px)' : '';
    if (!animar) { if (luego) luego(); return; }
    animando = true;
    var hecho = false;
    var fin = terminar = function () {
      if (hecho) return;
      hecho = true;
      animando = false;
      terminar = null;
      pista.classList.remove('es-animando');
      pista.removeEventListener('transitionend', fin);
      if (luego) luego();
    };
    pista.addEventListener('transitionend', fin);
    setTimeout(fin, 600);   // por si no llega el transitionend (sin animación, pestaña oculta…)
  }

  /** Si hay una animación a medias (clic o dedo rápidos), se acaba ya. */
  function acabar() { if (terminar) terminar(); }

  function ir(dir) {
    if (!abierto) return;
    acabar();
    var destino = indice(actual + dir);
    if (destino < 0) { desplazar(0, true); return; }
    quitarZoom(false);
    desplazar(-dir * ancho(), true, function () {
      // La foto que entra pasa al centro sin recargarse: se mueven los paneles.
      if (dir > 0) { pista.appendChild(paneles[0]); paneles.push(paneles.shift()); }
      else { pista.insertBefore(paneles[2], paneles[0]); paneles.unshift(paneles.pop()); }
      actual = destino;
      desplazar(0, false);
      pintar();
    });
  }

  // ─── Zoom ────────────────────────────────────────────────────────

  function imgActual() { return paneles[1].firstChild; }

  function aplicarZoom(animar) {
    var img = imgActual();
    img.classList.toggle('es-animando', !!animar);
    img.style.transform = z.s === 1 ? '' : 'translate(' + z.x + 'px,' + z.y + 'px) scale(' + z.s + ')';
    img.classList.toggle('es-zoom', z.s > 1);
    capa.classList.toggle('es-zoom', z.s > 1);
  }
  function quitarZoom(animar) {
    z = { s: 1, x: 0, y: 0 };
    if (paneles) aplicarZoom(animar);
  }

  /** Que la foto con zoom no se despegue de los bordes. */
  function limitar() {
    var img = imgActual(), p = paneles[1];
    var mx = Math.max(0, (img.offsetWidth * z.s - p.clientWidth) / 2);
    var my = Math.max(0, (img.offsetHeight * z.s - p.clientHeight) / 2);
    z.x = Math.min(mx, Math.max(-mx, z.x));
    z.y = Math.min(my, Math.max(-my, z.y));
  }

  /** Zoom a s, dejando quieto el punto de la foto que estaba en (px, py). */
  function zoomEn(s, px, py, deS, deX, deY, aX, aY) {
    var r = paneles[1].getBoundingClientRect();
    var cx = r.left + r.width / 2, cy = r.top + r.height / 2;
    s = Math.min(ZOOM_MAX, Math.max(1, s));
    var qx = (px - cx - deX) / deS, qy = (py - cy - deY) / deS;
    z.s = s;
    z.x = (aX === undefined ? px : aX) - cx - s * qx;
    z.y = (aY === undefined ? py : aY) - cy - s * qy;
    if (s === 1) { z.x = 0; z.y = 0; }
    limitar();
  }

  function rueda(e) {
    e.preventDefault();
    acabar();
    zoomEn(z.s * Math.exp(-e.deltaY * 0.002), e.clientX, e.clientY, z.s, z.x, z.y);
    aplicarZoom(false);
  }

  // ─── El dedo (y el ratón) ────────────────────────────────────────

  function lista() { return Object.keys(punteros).map(function (k) { return punteros[k]; }); }
  function distancia(a, b) { return Math.hypot(a.x - b.x, a.y - b.y); }

  function empezar() {
    var ps = lista();
    if (ps.length >= 2) {
      var m = { x: (ps[0].x + ps[1].x) / 2, y: (ps[0].y + ps[1].y) / 2 };
      gesto = { tipo: 'pellizco', d: distancia(ps[0], ps[1]) || 1, m: m, s: z.s, x: z.x, y: z.y };
    } else if (ps.length === 1) {
      gesto = { tipo: z.s > 1 ? 'mover' : 'pasar', x0: ps[0].x, y0: ps[0].y, t0: Date.now(), zx: z.x, zy: z.y, dx: 0 };
    } else {
      gesto = null;
    }
  }

  function abajo(e) {
    if (e.pointerType === 'mouse' && e.button !== 0) return;
    acabar();
    try { pista.setPointerCapture(e.pointerId); } catch (x) { /* puntero ya suelto */ }
    punteros[e.pointerId] = { x: e.clientX, y: e.clientY };
    if (lista().length === 1) toque = { x: e.clientX, y: e.clientY, t: Date.now(), en: e.target, movido: false };
    else toque = null;   // dos dedos: no es un toque
    empezar();
  }

  function mover(e) {
    if (!punteros[e.pointerId] || !gesto) return;
    punteros[e.pointerId] = { x: e.clientX, y: e.clientY };
    if (toque && Math.hypot(e.clientX - toque.x, e.clientY - toque.y) > 10) toque.movido = true;
    var ps = lista();
    if (gesto.tipo === 'pellizco' && ps.length >= 2) {
      var m = { x: (ps[0].x + ps[1].x) / 2, y: (ps[0].y + ps[1].y) / 2 };
      zoomEn(gesto.s * distancia(ps[0], ps[1]) / gesto.d, gesto.m.x, gesto.m.y, gesto.s, gesto.x, gesto.y, m.x, m.y);
      aplicarZoom(false);
    } else if (gesto.tipo === 'mover') {
      z.x = gesto.zx + e.clientX - gesto.x0;
      z.y = gesto.zy + e.clientY - gesto.y0;
      limitar();
      aplicarZoom(false);
    } else if (gesto.tipo === 'pasar' && fotos.length > 1) {
      var dx = e.clientX - gesto.x0;
      if (indice(actual + (dx < 0 ? 1 : -1)) < 0) dx *= 0.3;   // al final: se resiste
      gesto.dx = dx;
      desplazar(dx, false);
    }
  }

  function arriba(e) {
    if (!punteros[e.pointerId]) return;
    delete punteros[e.pointerId];
    var g = gesto;

    if (g && g.tipo === 'pasar') {
      var v = g.dx / Math.max(1, Date.now() - g.t0);
      if (g.dx < -ancho() * UMBRAL || (v < -RAPIDO && g.dx < -20)) ir(1);
      else if (g.dx > ancho() * UMBRAL || (v > RAPIDO && g.dx > 20)) ir(-1);
      else if (g.dx) desplazar(0, true);
    }
    if (g && g.tipo === 'pellizco' && z.s < 1.05) quitarZoom(true);

    if (toque && !toque.movido && e.type === 'pointerup' && Date.now() - toque.t < 300) tocar(toque);
    toque = null;
    empezar();   // si queda un dedo tras pellizcar, sigue moviendo
  }

  var ultimo = null;
  function tocar(t) {
    var enFoto = t.en === imgActual();
    if (!enFoto) { ultimo = null; cerrar(); return; }   // tocar fuera de la foto: cerrar
    if (ultimo && Date.now() - ultimo.t < 300 && Math.hypot(t.x - ultimo.x, t.y - ultimo.y) < 30) {
      if (z.s > 1) quitarZoom(true);
      else { zoomEn(ZOOM_DOBLE, t.x, t.y, 1, 0, 0); aplicarZoom(true); }
      ultimo = null;
    } else {
      ultimo = { x: t.x, y: t.y, t: Date.now() };
    }
  }
})();
