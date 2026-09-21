/*!
 * lamosquita-slider · movimiento
 * -----------------------------------------------------------------
 * Sin dependencias. Sólo cambia clases: las transiciones están en el CSS,
 * así que las hace el navegador (y la tarjeta gráfica), no este fichero.
 *
 * Qué hace:
 *  - Pasa al slide siguiente cuando le toca, pero SOLO si su imagen ya ha
 *    llegado. Si tarda, espera; si falla, se la salta.
 *  - Se para mientras la pestaña está oculta y mientras el ratón o el
 *    foco del teclado están encima (así da tiempo a leer o a pulsar).
 *  - Los slides que no se ven llevan «inert»: el tabulador no entra.
 *  - Cada slider de la página va por su cuenta, y ninguno se arranca dos
 *    veces aunque este fichero llegue a cargarse dos veces.
 */
(function (w, d) {
  'use strict';

  // ===============================================================
  // AJUSTES
  // ===============================================================
  var AJUSTES = {
    // Si la imagen del slide siguiente no ha llegado en este tiempo,
    // se prueba con el de después y se vuelve a intentar en la siguiente
    // vuelta. En milisegundos.
    esperaMaxima: 8000,
    // Pararse con el ratón o el foco encima.
    pausaAlPasar: true
  };
  // ===== fin de AJUSTES =====

  /** --lmq-duracion del CSS, en milisegundos. */
  function duracion(raiz) {
    var v = w.getComputedStyle(raiz).getPropertyValue('--lmq-duracion').trim();
    var n = parseFloat(v) || 0;
    return /ms$/.test(v) ? n : n * 1000;
  }

  /** Promesa que dice si la imagen del slide está lista para enseñarse. */
  function lista(slide) {
    var img = slide.querySelector('img');
    if (!img) return Promise.resolve(true);
    // Las diferidas se piden ahora: no esperar a que el navegador decida.
    if (img.loading === 'lazy') img.loading = 'eager';
    if (img.complete) return Promise.resolve(img.naturalWidth > 0);
    return new Promise(function (hecho) {
      var reloj = setTimeout(function () { hecho(false); }, AJUSTES.esperaMaxima);
      img.addEventListener('load', function () { clearTimeout(reloj); hecho(true); }, { once: true });
      img.addEventListener('error', function () { clearTimeout(reloj); hecho(false); }, { once: true });
    });
  }

  function iniciar(raiz) {
    if (raiz.hasAttribute('data-lmq-iniciado')) return;
    raiz.setAttribute('data-lmq-iniciado', '');

    var slides = [].filter.call(raiz.children, function (e) { return e.classList.contains('lmq-slide'); });
    // Leer una medida obliga al navegador a calcular el estilo de partida.
    // Sin esto, «lmq-listo» llega antes del primer cálculo, no hay cambio
    // que animar y la primera imagen no hace el zoom.
    void raiz.offsetWidth;
    raiz.classList.add('lmq-listo');
    if (slides.length < 2) return;

    var tiempo = parseInt(raiz.getAttribute('data-lmq-tiempo'), 10) || 5000;
    var actual = 0;
    var reloj = 0;
    var ocupado = false;          // hay un cambio en marcha
    var pausas = {};              // motivo → true mientras dure

    function pausado() {
      for (var k in pausas) if (pausas[k]) return true;
      return false;
    }

    function programar(ms) {
      clearTimeout(reloj);
      reloj = setTimeout(avanzar, ms);
    }

    function mostrar(i) {
      var sale = slides[actual];
      var entra = slides[i];
      sale.classList.add('es-saliente');
      sale.classList.remove('es-actual');
      sale.setAttribute('inert', '');
      entra.classList.add('es-actual');
      entra.removeAttribute('inert');
      actual = i;
      setTimeout(function () { sale.classList.remove('es-saliente'); }, duracion(raiz));
      // Mientras se ve éste, ir pidiendo la imagen del siguiente.
      lista(slides[(i + 1) % slides.length]);
    }

    function avanzar() {
      reloj = 0;
      if (ocupado || pausado()) return;       // al quitar la pausa se reprograma
      ocupado = true;
      probar((actual + 1) % slides.length, slides.length - 1);
    }

    function probar(i, quedan) {
      lista(slides[i]).then(function (ok) {
        if (pausado()) { ocupado = false; return; }
        if (ok) {
          mostrar(i);
          ocupado = false;
          programar(duracion(raiz) + tiempo);
        } else if (quedan > 1) {
          probar((i + 1) % slides.length, quedan - 1);
        } else {
          ocupado = false;
          programar(tiempo);                  // ninguna lista: otra vuelta más tarde
        }
      });
    }

    function pausa(motivo, si) {
      pausas[motivo] = si;
      if (si) { clearTimeout(reloj); reloj = 0; }
      else if (!pausado() && !reloj && !ocupado) programar(tiempo);
    }

    d.addEventListener('visibilitychange', function () { pausa('oculta', d.hidden); });
    if (AJUSTES.pausaAlPasar) {
      raiz.addEventListener('mouseenter', function () { pausa('raton', true); });
      raiz.addEventListener('mouseleave', function () { pausa('raton', false); });
      raiz.addEventListener('focusin', function () { pausa('foco', true); });
      raiz.addEventListener('focusout', function (e) {
        if (!raiz.contains(e.relatedTarget)) pausa('foco', false);
      });
    }

    lista(slides[1]);                         // la segunda, pedida desde ya
    if (d.hidden) pausas.oculta = true;
    else programar(tiempo);
  }

  function todos() {
    [].forEach.call(d.querySelectorAll('.lmq-slider'), iniciar);
  }

  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', todos);
  else todos();
})(window, document);
