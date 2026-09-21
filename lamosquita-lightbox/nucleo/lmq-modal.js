/* =================================================================
   lamosquita-lightbox · páginas en ventana (sin jQuery ni librerías)
   -----------------------------------------------------------------
   - Se abren en ventana los enlaces con la clase «lmq-modal» y los que
     van a las páginas elegidas en Ajustes › Visor y ventanas.
   - Dentro va la página con ?lmq_modal=1: sólo su título y su contenido,
     pero con todo lo que necesita (formularios, mapas, sliders…).
   - Se cierra con la ×, con Esc, tocando fuera o con «atrás» en el móvil.
   - Sin JS, o con Ctrl/Cmd pulsado, el enlace abre la página como siempre.
   ================================================================= */
(function () {
  'use strict';

  var C = window.LMQ_MODAL || {};
  var T = C.textos || {};

  function normal(url) {
    try {
      var u = new URL(url, location.href);
      return u.host.toLowerCase() + u.pathname.replace(/\/+$/, '');
    } catch (e) { return ''; }
  }
  var buscadas = {};
  (C.urls || []).forEach(function (u) { buscadas[normal(u)] = true; });

  var capa, caja, marco, abierto = false, origen = null, vigia = null;

  function valida(a) {
    if (!a || !a.href || a.hasAttribute('download') || a.closest('.nolmqmodal')) return false;
    if (!/^https?:$/.test(a.protocol) || a.origin !== location.origin) return false;   // sólo páginas de esta web
    if (a.classList.contains('lmq-modal')) return true;
    var n = normal(a.href);
    return !!buscadas[n] && n !== normal(location.href);   // la propia página, no
  }

  document.addEventListener('click', function (e) {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    var a = e.target.closest && e.target.closest('a[href]');
    if (!valida(a)) return;
    e.preventDefault();
    e.stopPropagation();
    abrir(a.href, a);
  }, true);

  function crear() {
    capa = document.createElement('dialog');
    capa.className = 'lmq-modal';
    capa.setAttribute('aria-label', T.ventana || 'Ventana');
    if (C.ancho) capa.style.setProperty('--lmq-modal-ancho', C.ancho + 'px');
    caja = document.createElement('div');
    caja.className = 'lmq-modal__caja';
    var cerrarB = document.createElement('button');
    cerrarB.type = 'button';
    cerrarB.className = 'lmq-modal__cerrar';
    cerrarB.setAttribute('aria-label', T.cerrar || 'Cerrar');
    cerrarB.textContent = '×';
    cerrarB.addEventListener('click', cerrar);
    caja.appendChild(cerrarB);
    capa.appendChild(caja);
    document.body.appendChild(capa);

    capa.addEventListener('cancel', function (e) { e.preventDefault(); cerrar(); });   // Esc
    capa.addEventListener('click', function (e) { if (e.target === capa) cerrar(); });   // fuera de la caja
    window.addEventListener('message', function (e) {
      if (e.origin === location.origin && e.data && e.data.lmqModal === 'cerrar' && abierto) cerrar();
    });
    window.addEventListener('popstate', function () { if (abierto) cerrarYa(); });
    window.addEventListener('resize', function () { if (abierto) medir(); });
  }

  /** El alto del marco: el de su contenido, sin pasar de la pantalla (si no
   *  cabe, se desplaza dentro). En el móvil lo pone el CSS. */
  function medir() {
    if (!marco) return;
    var doc;
    try { doc = marco.contentDocument; } catch (e) { return; }
    if (!doc || !doc.body) return;
    // Lo que mide el contenido (no el documento, que nunca baja del alto del marco).
    var c = doc.querySelector('.lmq-modal-contenido');
    var alto = c ? c.getBoundingClientRect().bottom + (doc.defaultView.scrollY || 0) : doc.documentElement.scrollHeight;
    marco.style.height = Math.ceil(alto) + 'px';
  }

  function abrir(url, desde) {
    if (!capa) crear();
    origen = desde;
    var u = new URL(url, location.href);
    u.hash = '';
    u.searchParams.set('lmq_modal', '1');

    marco = document.createElement('iframe');
    marco.className = 'lmq-modal__marco';
    marco.title = (desde && desde.textContent.trim()) || T.ventana || 'Ventana';
    marco.addEventListener('load', function () {
      caja.classList.remove('es-cargando');
      medir();
      try {
        // Lo que cambie dentro (un mapa que carga, un formulario que responde) cambia el alto.
        // El vigía, del propio marco: uno de esta página no se entera de lo que pasa dentro.
        var RO = marco.contentWindow.ResizeObserver;
        var d = marco.contentDocument;
        if (RO) { vigia = new RO(medir); vigia.observe(d.querySelector('.lmq-modal-contenido') || d.body); }
      } catch (e) { /* otra web: se queda con el alto del CSS */ }
    });
    marco.src = u.href;
    caja.classList.add('es-cargando');
    caja.appendChild(marco);

    capa.showModal();
    abierto = true;
    document.documentElement.style.overflow = 'hidden';
    history.pushState({ lmqModal: true }, '');
  }

  function cerrar() {
    if (history.state && history.state.lmqModal) history.back();   // y el popstate cierra
    else cerrarYa();
  }
  function cerrarYa() {
    abierto = false;
    if (vigia) { vigia.disconnect(); vigia = null; }
    if (marco) { marco.remove(); marco = null; }   // para vídeos, mapas…: fuera de verdad
    capa.close();
    document.documentElement.style.overflow = '';
    if (origen && origen.focus) origen.focus({ preventScroll: true });
  }
})();
