/*!
 * lamosquita-cookies · núcleo
 * -----------------------------------------------------------------
 * Aviso, preferencias, activación de lo bloqueado y señales a Google.
 * Sin dependencias. Antes tiene que haberse ejecutado lmc-cabecera.js.
 *
 * Lo bloqueado se marca en el HTML así (en WordPress lo hace el plugin solo):
 *   <script type="text/plain" data-lmc="estadistica" src="…"></script>
 *   <script type="text/plain" data-lmc="marketing" data-lmc-recargar src="…mapa…"></script>
 *     (data-lmc-recargar: si se acepta con la página abierta, se recarga,
 *      porque el theme ya intentó usar ese script al cargar y no lo repite)
 *   <iframe data-lmc="marketing" data-lmc-src="…" data-lmc-nombre="YouTube"></iframe>
 *
 * API para el theme o para otros scripts:
 *   LMC.abrir()            abre la configuración
 *   LMC.permite('marketing')
 *   LMC.estado()           { v, c: { preferencias, estadistica, marketing }, f, id }
 *   document.addEventListener('lmc:consentimiento', e => e.detail.categorias)
 */
(function (w, d) {
  'use strict';

  // ===============================================================
  // AJUSTES — valores por defecto. Cada web los sobrescribe con
  // window.LMC_AJUSTES; WordPress lo imprime desde sus propios AJUSTES.
  // ===============================================================
  var POR_DEFECTO = {
    version: '1',
    meses: 12,
    cookie: 'lmc_consentimiento',
    icono: true,
    posicion_icono: 'izquierda',
    url_politica: '/politica-de-cookies/',
    endpoint: '',   // URL donde se registra cada decisión; vacío = no se registra
    categorias: [], // [{ id, nombre, descripcion, servicios: [{ nombre, proveedor, finalidad, cookies }] }]
    borrar: {},     // { estadistica: ['_ga', '_ga_*'] } cookies que se borran al retirar el permiso
    textos: {
      titulo: 'Cookies',
      texto: 'Esta web usa cookies propias y de terceros para funcionar, analizar cómo se usa y mostrar contenidos de otras plataformas. Se pueden aceptar, rechazar o elegir por categorías. Más información en la {politica}.',
      politica: 'política de cookies',
      aceptar: 'Aceptar',
      rechazar: 'Rechazar',
      configurar: 'Configurar',
      guardar: 'Guardar selección',
      aceptar_todo: 'Aceptar todo',
      rechazar_todo: 'Rechazar todo',
      cerrar: 'Cerrar',
      icono: 'Configurar cookies',
      servicios: 'Servicios y cookies',
      siempre: 'Siempre activas',
      bloqueado: 'Este contenido de {servicio} necesita cookies de {categoria}.',
      bloqueado_boton: 'Aceptar y ver',
      bloqueado_configurar: 'Configurar cookies'
    }
  };
  // ===== fin de AJUSTES =====

  if (!w.LMC_AJUSTES) return; // sin configuración no hay nada que hacer (la cabecera no se ha impreso)

  var A = mezclar(POR_DEFECTO, w.LMC_AJUSTES);
  var T = A.textos;
  var CATEGORIAS = ['preferencias', 'estadistica', 'marketing'];
  var ICONO = '<svg viewBox="0 0 24 24" width="22" height="22" aria-hidden="true" focusable="false"><path fill="currentColor" d="M12 2a10 10 0 1 0 10 10 4 4 0 0 1-5-5 4 4 0 0 1-5-5zM8.5 10a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm4 5a1.5 1.5 0 1 1 0 3 1.5 1.5 0 0 1 0-3zm4-1a1 1 0 1 1 0 2 1 1 0 0 1 0-2zM7 16a1 1 0 1 1 0 2 1 1 0 0 1 0-2z"/></svg>';

  var estado = leer();
  var raiz, aviso, panel, icono, botonConfigurar, ultimoFoco;

  // ---------------------------------------------------------------
  // Utilidades
  // ---------------------------------------------------------------
  function mezclar(a, b) {
    var r = {}, k;
    for (k in a) r[k] = a[k];
    for (k in b) {
      if (b[k] && typeof b[k] === 'object' && !Array.isArray(b[k]) && a[k] && typeof a[k] === 'object' && !Array.isArray(a[k])) r[k] = mezclar(a[k], b[k]);
      else if (b[k] !== undefined && b[k] !== null) r[k] = b[k];
    }
    return r;
  }

  function esc(s) {
    return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
      return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
    });
  }

  function lista(nodos) { return Array.prototype.slice.call(nodos); }

  function nombreCategoria(id) {
    for (var i = 0; i < A.categorias.length; i++) if (A.categorias[i].id === id) return A.categorias[i].nombre;
    return id;
  }

  function hayQueDecidir() {
    return A.categorias.some(function (c) { return c.id !== 'necesarias'; });
  }

  /**
   * Las categorías que esta web ofrece en el panel. Aceptar y rechazar
   * sólo mueven estas: conceder una categoría que no se ha enseñado es
   * dar un permiso que nadie ha pedido.
   */
  function ofrecidas() {
    return CATEGORIAS.filter(function (id) {
      return (A.categorias || []).some(function (c) { return c && c.id === id; });
    });
  }

  function todas(valor) {
    var c = {};
    ofrecidas().forEach(function (id) { c[id] = !!valor; });
    return c;
  }

  function permite(categoria) {
    return categoria === 'necesarias' || !!(estado && estado.c && estado.c[categoria]);
  }

  // ---------------------------------------------------------------
  // Cookie de la elección
  // ---------------------------------------------------------------
  function leer() {
    if (w.LMC_CABECERA && w.LMC_CABECERA.leer) return w.LMC_CABECERA.leer();
    var m = d.cookie.match(new RegExp('(?:^|; )' + A.cookie + '=([^;]*)'));
    if (!m) return null;
    try {
      var e = JSON.parse(decodeURIComponent(m[1]));
      return e && e.c && String(e.v) === String(A.version) ? e : null;
    } catch (x) { return null; }
  }

  function guardar(e) {
    var segura = location.protocol === 'https:' ? '; Secure' : '';
    d.cookie = A.cookie + '=' + encodeURIComponent(JSON.stringify(e)) + '; path=/; max-age=' + Math.round(A.meses * 30.44 * 86400) + '; SameSite=Lax' + segura;
  }

  function nuevoId() {
    var b = new Uint8Array(16);
    (w.crypto || w.msCrypto).getRandomValues(b);
    return lista(b).map(function (x) { return ('0' + x.toString(16)).slice(-2); }).join('');
  }

  function senales(c) {
    if (w.LMC_CABECERA && w.LMC_CABECERA.senales) return w.LMC_CABECERA.senales(c);
    var o = ofrecidas();
    var g = function (id) { return c[id] && o.indexOf(id) !== -1 ? 'granted' : 'denied'; };
    return { analytics_storage: g('estadistica'), ad_storage: g('marketing'), ad_user_data: g('marketing'), ad_personalization: g('marketing'), functionality_storage: g('preferencias'), personalization_storage: g('preferencias') };
  }

  /** Borra las cookies de las categorías retiradas, en todos los dominios posibles. */
  function borrarCookies(categorias) {
    var patrones = [];
    categorias.forEach(function (cat) {
      (A.borrar[cat] || []).forEach(function (p) {
        patrones.push(new RegExp('^' + p.replace(/[.+?^${}()|[\]\\]/g, '\\$&').replace(/\*/g, '.*') + '$'));
      });
    });
    if (!patrones.length) return;

    var partes = location.hostname.split('.');
    var dominios = [''];
    for (var i = 0; i < partes.length - 1; i++) dominios.push('; domain=.' + partes.slice(i).join('.'));

    d.cookie.split(';').forEach(function (par) {
      var nombre = par.split('=')[0].trim();
      if (!patrones.some(function (r) { return r.test(nombre); })) return;
      dominios.forEach(function (dom) { d.cookie = nombre + '=; path=/; max-age=0' + dom; });
    });
  }

  // ---------------------------------------------------------------
  // Registro de la decisión (prueba del consentimiento)
  // ---------------------------------------------------------------
  function registrar(e, origen) {
    if (!A.endpoint) return;
    var datos = JSON.stringify({ id: e.id, v: e.v, c: e.c, origen: origen });
    try {
      if (navigator.sendBeacon && navigator.sendBeacon(A.endpoint, new Blob([datos], { type: 'application/json' }))) return;
    } catch (x) { /* sigue con fetch */ }
    try {
      fetch(A.endpoint, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: datos, keepalive: true, credentials: 'omit' });
    } catch (x) { /* sin registro, la elección vale igual */ }
  }

  // ---------------------------------------------------------------
  // Activar lo bloqueado
  // ---------------------------------------------------------------
  function activar(c) {
    var scripts = lista(d.querySelectorAll('script[type="text/plain"][data-lmc]')).filter(function (s) {
      return c[s.getAttribute('data-lmc')];
    });

    // En orden: un script en línea puede depender de uno externo anterior.
    (function siguiente(i) {
      if (i >= scripts.length) return;
      var viejo = scripts[i];
      var nuevo = d.createElement('script');
      var src = viejo.getAttribute('src');
      var espera = src && !viejo.hasAttribute('async');

      lista(viejo.attributes).forEach(function (at) {
        if (at.name !== 'type' && at.name.indexOf('data-lmc') !== 0) nuevo.setAttribute(at.name, at.value);
      });
      if (viejo.getAttribute('data-lmc-type')) nuevo.type = viejo.getAttribute('data-lmc-type');
      if (!src) nuevo.text = viejo.text;
      if (espera) {
        nuevo.async = false;
        nuevo.onload = nuevo.onerror = function () { siguiente(i + 1); };
      }

      viejo.parentNode.replaceChild(nuevo, viejo);
      if (!espera) siguiente(i + 1);
    })(0);

    lista(d.querySelectorAll('iframe[data-lmc-src][data-lmc]')).forEach(function (f) {
      if (!c[f.getAttribute('data-lmc')]) return;
      var aviso = f.previousElementSibling;
      if (aviso && aviso.classList.contains('lmc-bloqueado')) aviso.parentNode.removeChild(aviso);
      f.setAttribute('src', f.getAttribute('data-lmc-src'));
      f.removeAttribute('data-lmc-src');
      f.classList.remove('lmc-oculto');
    });
  }

  /** Recuadro en lugar de cada iframe bloqueado. */
  function marcarBloqueados() {
    lista(d.querySelectorAll('iframe[data-lmc-src][data-lmc]')).forEach(function (f) {
      var cat = f.getAttribute('data-lmc');
      var previo = f.previousElementSibling;
      if (permite(cat) || (previo && previo.classList.contains('lmc-bloqueado'))) return;

      var recuadro = d.createElement('div');
      recuadro.className = 'lmc-bloqueado';
      if (f.getAttribute('style')) recuadro.setAttribute('style', f.getAttribute('style'));

      var texto = esc(T.bloqueado)
        .replace('{servicio}', '<strong>' + esc(f.getAttribute('data-lmc-nombre') || '') + '</strong>')
        .replace('{categoria}', esc(nombreCategoria(cat).toLowerCase()));

      recuadro.innerHTML = '<div class="lmc-bloqueado-caja"><p>' + texto + '</p><p>' +
        '<button type="button" class="lmc-boton" data-lmc-aceptar-categoria="' + esc(cat) + '">' + esc(T.bloqueado_boton) + '</button> ' +
        '<a href="#lmc-ajustes">' + esc(T.bloqueado_configurar) + '</a></p></div>';

      f.classList.add('lmc-oculto');
      f.parentNode.insertBefore(recuadro, f);
    });
  }

  /**
   * Recuadro dentro de los contenedores donde un script bloqueado pintaría
   * algo (un mapa). No borra nada del theme: oculta su contenido mientras
   * tanto y, al aceptar, la página se recarga para que el theme lo monte.
   */
  function marcarContenedores() {
    (A.contenedores || []).forEach(function (cfg) {
      if (!cfg.selector || permite(cfg.categoria)) return;
      var nodos;
      try { nodos = d.querySelectorAll(cfg.selector); } catch (x) { return; } // selector mal escrito
      lista(nodos).forEach(function (el) {
        if (el.closest('.lmc') || el.classList.contains('lmc-contenedor-bloqueado')) return;
        var recuadro = d.createElement('div');
        recuadro.className = 'lmc-bloqueado';
        recuadro.innerHTML = '<div class="lmc-bloqueado-caja"><p>' + esc(T.bloqueado)
          .replace('{servicio}', '<strong>' + esc(cfg.nombre) + '</strong>')
          .replace('{categoria}', esc(nombreCategoria(cfg.categoria).toLowerCase())) + '</p><p>' +
          '<button type="button" class="lmc-boton" data-lmc-aceptar-categoria="' + esc(cfg.categoria) + '">' + esc(T.bloqueado_boton) + '</button> ' +
          '<a href="#lmc-ajustes">' + esc(T.bloqueado_configurar) + '</a></p></div>';
        el.classList.add('lmc-contenedor-bloqueado');
        el.setAttribute('data-lmc-categoria', cfg.categoria);
        el.insertBefore(recuadro, el.firstChild);
      });
    });
  }

  // ---------------------------------------------------------------
  // Decidir
  // ---------------------------------------------------------------
  function decidir(c, origen) {
    var anterior = estado ? estado.c : null;
    estado = { v: A.version, c: c, f: Math.floor(Date.now() / 1000), id: (estado && estado.id) || nuevoId() };
    guardar(estado);

    w.gtag('consent', 'update', senales(c));
    w.dataLayer.push({ event: 'lmc_consentimiento', lmc_categorias: c });
    if (typeof w.fbq === 'function') w.fbq('consent', c.marketing ? 'grant' : 'revoke');
    if (typeof w.clarity === 'function') w.clarity('consent', !!c.estadistica);

    registrar(estado, origen);

    try { d.dispatchEvent(new CustomEvent('lmc:consentimiento', { detail: { categorias: c, origen: origen } })); } catch (x) { /* navegador antiguo */ }

    var retiradas = anterior ? CATEGORIAS.filter(function (id) { return anterior[id] && !c[id]; }) : [];
    var hayQueRecargar = lista(d.querySelectorAll('script[type="text/plain"][data-lmc][data-lmc-recargar], .lmc-contenedor-bloqueado[data-lmc-categoria]')).some(function (el) {
      return c[el.getAttribute('data-lmc') || el.getAttribute('data-lmc-categoria')];
    });
    if (hayQueRecargar && !retiradas.length) {
      // Un mapa o similar que el theme monta al cargar: activarlo ahora no lo pinta.
      w.location.reload();
      return;
    }
    if (retiradas.length) {
      // Lo que ya se ha ejecutado no se puede «desejecutar»: se borran sus
      // cookies y se recarga la página para que no vuelva a cargar.
      borrarCookies(retiradas);
      w.location.reload();
      return;
    }

    activar(c);
    ocultar();
  }

  // ---------------------------------------------------------------
  // Interfaz
  // ---------------------------------------------------------------
  function boton(accion, texto, extra) {
    return '<button type="button" class="lmc-boton" data-lmc-accion="' + accion + '"' + (extra || '') + '>' + esc(texto) + '</button>';
  }

  function categoriasHtml() {
    return A.categorias.map(function (cat) {
      var id = esc(cat.id);
      var cabecera = cat.id === 'necesarias'
        ? '<span class="lmc-categoria-nombre">' + esc(cat.nombre) + '</span><span class="lmc-siempre">' + esc(T.siempre) + '</span>'
        : '<label class="lmc-interruptor"><input type="checkbox" data-lmc-categoria="' + id + '"><span class="lmc-deslizador" aria-hidden="true"></span><span class="lmc-categoria-nombre">' + esc(cat.nombre) + '</span></label>';

      var servicios = (cat.servicios || []).map(function (s) {
        return '<li><strong>' + esc(s.nombre) + '</strong>' + (s.proveedor ? ' · ' + esc(s.proveedor) : '') +
          (s.finalidad ? '<br>' + esc(s.finalidad) : '') +
          (s.cookies && s.cookies.length ? '<br><code>' + s.cookies.map(esc).join('</code> <code>') + '</code>' : '') + '</li>';
      }).join('');

      return '<div class="lmc-categoria">' +
        '<div class="lmc-categoria-cabecera">' + cabecera + '</div>' +
        (cat.descripcion ? '<p class="lmc-categoria-descripcion">' + esc(cat.descripcion) + '</p>' : '') +
        (servicios ? '<details class="lmc-servicios"><summary>' + esc(T.servicios) + '</summary><ul>' + servicios + '</ul></details>' : '') +
        '</div>';
    }).join('');
  }

  function construir() {
    var politica = A.url_politica ? '<a href="' + esc(A.url_politica) + '">' + esc(T.politica) + '</a>' : esc(T.politica);

    raiz = d.createElement('div');
    raiz.className = 'lmc';
    raiz.id = 'lmc';
    raiz.innerHTML =
      '<div class="lmc-aviso" role="dialog" aria-labelledby="lmc-titulo" aria-describedby="lmc-texto" hidden>' +
        '<button type="button" class="lmc-cerrar" data-lmc-accion="cerrar" aria-label="' + esc(T.cerrar) + '" hidden>&times;</button>' +
        '<p class="lmc-titulo" id="lmc-titulo">' + esc(T.titulo) + '</p>' +
        '<p class="lmc-texto" id="lmc-texto">' + esc(T.texto).replace('{politica}', politica) + '</p>' +
        '<div class="lmc-botones">' +
          boton('aceptar', T.aceptar) + boton('rechazar', T.rechazar) +
          boton('configurar', T.configurar, ' aria-expanded="false" aria-controls="lmc-panel"') +
        '</div>' +
        '<div class="lmc-panel" id="lmc-panel" hidden>' +
          categoriasHtml() +
          '<div class="lmc-botones">' + boton('guardar', T.guardar) + boton('aceptar', T.aceptar_todo) + boton('rechazar', T.rechazar_todo) + '</div>' +
        '</div>' +
      '</div>' +
      (A.icono ? '<button type="button" class="lmc-icono lmc-icono-' + (A.posicion_icono === 'derecha' ? 'derecha' : 'izquierda') + '" data-lmc-accion="abrir" aria-label="' + esc(T.icono) + '" title="' + esc(T.icono) + '" hidden>' + ICONO + '</button>' : '');

    d.body.appendChild(raiz);
    aviso = raiz.querySelector('.lmc-aviso');
    panel = raiz.querySelector('.lmc-panel');
    icono = raiz.querySelector('.lmc-icono');
    botonConfigurar = raiz.querySelector('[data-lmc-accion="configurar"]');
  }

  function sincronizarInterruptores() {
    lista(raiz.querySelectorAll('input[data-lmc-categoria]')).forEach(function (i) {
      i.checked = permite(i.getAttribute('data-lmc-categoria'));
    });
  }

  function seleccion() {
    var c = todas(false);
    lista(raiz.querySelectorAll('input[data-lmc-categoria]')).forEach(function (i) {
      c[i.getAttribute('data-lmc-categoria')] = i.checked;
    });
    return c;
  }

  function mostrarPanel(si) {
    panel.hidden = !si;
    botonConfigurar.setAttribute('aria-expanded', si ? 'true' : 'false');
    raiz.classList.toggle('lmc-con-panel', si);
    if (si) {
      var primero = panel.querySelector('input, summary, button');
      if (primero) primero.focus();
    }
  }

  function abrir(conPanel) {
    if (!hayQueDecidir()) return;
    ultimoFoco = d.activeElement;
    sincronizarInterruptores();
    aviso.hidden = false;
    raiz.querySelector('.lmc-cerrar').hidden = !estado;
    if (icono) icono.hidden = true;
    mostrarPanel(!!conPanel);
    if (!conPanel) {
      var b = aviso.querySelector('.lmc-boton');
      if (b && estado) b.focus();
    }
  }

  function ocultar() {
    aviso.hidden = true;
    mostrarPanel(false);
    if (icono) icono.hidden = !estado;
    if (ultimoFoco && ultimoFoco.focus && d.body.contains(ultimoFoco)) ultimoFoco.focus();
  }

  function alPulsar(e) {
    var el = e.target.closest('[data-lmc-accion]');
    if (!el || !raiz.contains(el)) return;
    switch (el.getAttribute('data-lmc-accion')) {
      case 'aceptar':    decidir(todas(true), 'aceptar'); break;
      case 'rechazar':   decidir(todas(false), 'rechazar'); break;
      case 'guardar':    decidir(seleccion(), 'guardar'); break;
      case 'configurar': mostrarPanel(panel.hidden); break;
      case 'cerrar':     if (estado) ocultar(); break;
      case 'abrir':      abrir(true); break;
    }
  }

  function alPulsarDocumento(e) {
    var enlace = e.target.closest('a[href$="#lmc-ajustes"], [data-lmc-abrir]');
    if (enlace) { e.preventDefault(); abrir(true); return; }

    var aceptar = e.target.closest('[data-lmc-aceptar-categoria]');
    if (aceptar) {
      var c = estado ? mezclar(todas(false), estado.c) : todas(false);
      c[aceptar.getAttribute('data-lmc-aceptar-categoria')] = true;
      decidir(c, 'contenido');
    }
  }

  function iniciar() {
    construir();
    raiz.addEventListener('click', alPulsar);
    d.addEventListener('click', alPulsarDocumento);
    d.addEventListener('keydown', function (e) {
      if (e.key === 'Escape' && !aviso.hidden && estado) ocultar();
    });

    marcarBloqueados();
    marcarContenedores();

    if (estado) {
      activar(estado.c);
      if (icono && hayQueDecidir()) icono.hidden = false;
    } else if (hayQueDecidir()) {
      aviso.hidden = false;
    }

    if (location.hash === '#lmc-ajustes') abrir(true);
  }

  w.LMC = {
    abrir: function () { abrir(true); },
    aceptarTodo: function () { decidir(todas(true), 'api'); },
    rechazarTodo: function () { decidir(todas(false), 'api'); },
    permite: permite,
    estado: function () { return estado; }
  };

  if (d.readyState === 'loading') d.addEventListener('DOMContentLoaded', iniciar);
  else iniciar();
})(window, document);
