/*!
 * lamosquita-cookies · cabecera
 * -----------------------------------------------------------------
 * Tiene que ejecutarse LO PRIMERO del <head>: en línea o como <script> sin
 * async ni defer, antes de cualquier etiqueta de Google. Necesita que
 * window.LMC_AJUSTES exista ya (versión y nombre de la cookie).
 *
 * 1. Fija el modo de consentimiento v2 de Google con todo denegado.
 * 2. Si el visitante ya decidió en otra visita, envía su elección antes de
 *    que carguen las etiquetas, para que Analytics y Ads la respeten desde
 *    la primera petición.
 */
(function (w, d) {
  'use strict';

  var A = w.LMC_AJUSTES || {};
  var NOMBRE = A.cookie || 'lmc_consentimiento';

  // Categorías que esta web ofrece de verdad en el panel. Sólo se puede
  // conceder lo que se ha enseñado: si una web no ofrece «marketing»,
  // aceptar no puede mandarle a Google ad_storage 'granted'. Se comprueba
  // aquí, y no sólo al pulsar, para que también se corrijan las
  // elecciones ya guardadas antes de esta comprobación.
  var OFRECIDAS = (A.categorias || []).map(function (c) { return c && c.id; });

  function ofrecida(id) {
    return OFRECIDAS.indexOf(id) !== -1;
  }

  w.dataLayer = w.dataLayer || [];
  if (typeof w.gtag !== 'function') {
    w.gtag = function () { w.dataLayer.push(arguments); };
  }

  w.gtag('consent', 'default', {
    ad_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
    analytics_storage: 'denied',
    functionality_storage: 'denied',
    personalization_storage: 'denied',
    security_storage: 'granted',
    wait_for_update: 500
  });
  // Sin permiso de publicidad, Google quita los identificadores de las peticiones de Ads.
  w.gtag('set', 'ads_data_redaction', true);

  function leer() {
    var m = d.cookie.match(new RegExp('(?:^|; )' + NOMBRE + '=([^;]*)'));
    if (!m) return null;
    var e;
    try { e = JSON.parse(decodeURIComponent(m[1])); } catch (x) { return null; }
    // Una elección de otra versión de la configuración no vale: se vuelve a preguntar.
    if (!e || !e.c || String(e.v) !== String(A.version)) return null;
    return e;
  }

  function senales(c) {
    var g = function (id) { return c[id] && ofrecida(id) ? 'granted' : 'denied'; };
    return {
      analytics_storage: g('estadistica'),
      ad_storage: g('marketing'),
      ad_user_data: g('marketing'),
      ad_personalization: g('marketing'),
      functionality_storage: g('preferencias'),
      personalization_storage: g('preferencias')
    };
  }

  var estado = leer();
  if (estado) {
    w.gtag('consent', 'update', senales(estado.c));
    w.dataLayer.push({ event: 'lmc_consentimiento', lmc_categorias: estado.c });
  }

  w.LMC_CABECERA = { leer: leer, senales: senales, estado: estado };
})(window, document);
