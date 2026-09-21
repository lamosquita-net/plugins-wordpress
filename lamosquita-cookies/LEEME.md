# lamosquita-cookies

Aviso de cookies y consentimiento propios para las webs de lamosquita. Sustituye a Complianz, CookieYes y CookieLawInfo.

- Se desarrolla y se valida primero en una web, y de ahí se lleva a las demás,
  incluida una en **PHP sin WordPress** (§8).
- Si algo falla en otra web, se corrige aquí y se vuelve a copiar.

Versión 0.4.1 · 21/09/2026. Cambios en §11.

---

## 1 · Qué hace

1. **Aviso abajo, sin tapar la página.** Botones Aceptar, Rechazar y Configurar con el mismo peso visual, como exige la AEPD. Desaparece al decidir.
2. **Bloqueo previo.** Ningún script ni iframe de terceros conocido se carga hasta aceptar su categoría: estadística, marketing o preferencias. Las necesarias van siempre.
3. **Google, modo de consentimiento v2.** Todo denegado de salida. Al decidir se envían las cuatro señales (Analytics y Ads) y el evento `lmc_consentimiento` para Tag Manager.
4. **Vimeo sin seguimiento (`dnt=1`) y YouTube sin cookies.** Vimeo con `dnt=1` no necesita bloqueo.
5. **Volver a elegir.** Con el icono de la esquina, con `[lmc_ajustes]` o con cualquier enlace a `#lmc-ajustes`.
6. **Registro de cada decisión** como prueba, con la IP recortada. Se exporta en CSV.
7. **Detección de servicios.** Cada página servida anota los servicios que lleva: por su HTML (scripts e iframes) y por las cookies que envía el servidor (PHPSESSID, WooCommerce…). De ahí salen el panel de preferencias y la tabla `[lmc_tabla_cookies]`.

---

## 2 · Mapa

```
lamosquita-cookies/
  lamosquita-cookies.php   cabecera del plugin y bloque AJUSTES          (WordPress)
  actualizador.php         avisos de versión nueva desde nuestro servidor (§3.1)
  nucleo/                  lo que no depende de WordPress
    cookie-servidor.php    la cookie de la decisión, fijada desde el servidor (0.4.1)
    lmc-cabecera.js        consentimiento de Google por defecto; va lo PRIMERO del <head>
    lmc.js                 aviso, panel, activación de lo bloqueado, señales, registro
    lmc.css                aspecto; bloque AJUSTES de variables --lmc-*
    servicios.json         base de servicios: patrones, categoría, cookies, finalidad
    reescribir.php         reescritura del HTML (bloqueo, Vimeo, YouTube, cabecera)
  wordpress/               el envoltorio
    servicios.php          textos (WPML), servicios detectados, window.LMC_AJUSTES
    bloqueo.php            búfer de salida, carga de CSS y JS, shortcodes
    registro.php           tabla {prefijo}lmc_consentimientos, REST, purga diaria
    escritorio.php         Herramientas › Cookies y exportación CSV
```

**Orden en el navegador, que es lo que no se puede romper:**

1. `window.LMC_AJUSTES` y `lmc-cabecera.js`, en línea y lo primero del `<head>`.
2. Las etiquetas del theme o de Tag Manager, bloqueadas o no según el modo.
3. `lmc.js` (defer), que pinta el aviso y activa lo aceptado.

---

## 3 · Instalación en una web WordPress

1. Copia la carpeta a `wp-content/plugins/lamosquita-cookies/`, con el mismo dueño y grupo que el resto de plugins.
2. **Desactiva primero el gestor anterior**: Complianz, CookieYes, CookieLawInfo… Dos avisos a la vez se pisan.
3. Activa **lamosquita-cookies**. Crea la tabla del registro y la purga diaria.
4. Navega por la web sin sesión: portada, una página de cada tipo y, si hay tienda, producto, carrito y pago. Cada página anota sus servicios.
5. En **Herramientas › Cookies** revisa los servicios detectados.
6. En la política de cookies, pon `[lmc_tabla_cookies]` y un enlace `[lmc_ajustes]`.
7. Comprueba sin sesión, en una ventana privada:
   - antes de decidir, no hay cookies `_ga`, `_fbp` ni parecidas;
   - en `dataLayer` está el `consent default` denegado;
   - al aceptar se envía el `consent update` y cargan las etiquetas;
   - al rechazar, no carga nada.

**Diagnóstico:** un administrador puede ver cualquier página sin el plugin añadiendo `?lmc-desactivar` a la URL.

### 3.1 · Actualizaciones

El plugin avisa de las versiones nuevas en **Escritorio › Actualizaciones**,
como cualquier otro, pero contra nuestro servidor. No usa librerías de
terceros: es el mecanismo que trae WordPress desde la 5.8, la cabecera
`Update URI` más el filtro `update_plugins_<anfitrión>`.

**La primera vez hay que subirlo a mano.** WordPress lee `Update URI` del
plugin **instalado**; si la versión que hay puesta no la lleva, en
`wp-includes/update.php` hace `continue` y no pregunta nada. Así que
cualquier web con una versión anterior a la 0.4.0 necesita una subida
manual, y a partir de ahí ya se actualiza sola.

Para publicar una versión, desde la raíz del repositorio:

```bash
./empaquetar.sh lamosquita-cookies
```

Lee la versión de la cabecera —y falla si no coincide con `LMC_VERSION`—,
comprueba la sintaxis de todos los `.php` y `.js`, y deja en `dist/` el ZIP
y el JSON. Los dos se suben a `plugins.lamosquita.net`. El JSON conserva
siempre el mismo nombre, porque es la dirección fija que consultan las
webs; el ZIP lleva el número de versión, así que las versiones antiguas
siguen ahí y se puede volver atrás.

Cada web pregunta como mucho una vez cada 6 horas (`Lamosquita_Actualizador::CACHE`),
y si el servidor no contesta se calla durante 30 minutos en vez de
insistir en cada carga del escritorio. Que el servidor esté caído no rompe
nada: sólo no hay aviso.

**De paso, el recuento de instalaciones.** WordPress manda en cada petición
un `User-Agent` con la dirección del sitio y su versión:

```
WordPress/7.1; https://ejemplo.com
```

Así que el log de Apache de `plugins.lamosquita.net` ya dice qué webs
preguntan, con qué WordPress y cuándo, sin añadir telemetría. Si el plugin
se instala fuera de casa, conviene decirlo en su página: la dirección del
sitio queda registrada al comprobar actualizaciones.

---

## 4 · Ajustes por web

Todo está en el bloque **AJUSTES** de `lamosquita-cookies.php`. Para no tocarlo en cada web, se sobrescribe desde el theme o desde un mu-plugin:

```php
add_filter( 'lmc_ajustes', function ( $a ) {
    $a['colores']      = array( 'acento' => '#009999', 'boton-fondo-hover' => '#009999' );
    $a['posicion_icono'] = 'derecha';
    $a['textos']['texto'] = 'Otro texto… Más información en la {politica}.';
    $a['servicios'][]  = 'meta-pixel'; // declararlo aunque no se detecte
    return $a;
} );
```

- **`version`:** súbela si cambian los textos legales o las finalidades, y se vuelve a preguntar a todos. Un servicio nuevo detectado ya cambia la versión solo.
- **`modo_google`:** `basico` en todas las webs (decisión del 15/09/2026). Con `avanzado`, las etiquetas de Google cargan siempre con todo denegado.
- **Colores:** lo más cómodo es **Herramientas › Cookies › Colores del aviso**: fondo, textos, botones y botones al pasar el ratón, con vista previa. Lo que se guarde ahí manda sobre `$a['colores']`, y los dos sobre el CSS del plugin. También se puede hacer desde el CSS de la web: `.lmc { --lmc-acento: #009999; }`.
- **Servicio que no está en la base:** se añade con el filtro `lmc_servicios`, con la misma forma que `servicios.json`, o directamente en el JSON si va a servir a más webs.

---

## 5 · Google (Analytics, Ads, Tag Manager)

| Categoría | Señales que pasan a `granted` |
|---|---|
| Estadística | `analytics_storage` |
| Marketing | `ad_storage`, `ad_user_data`, `ad_personalization` |
| Preferencias | `functionality_storage`, `personalization_storage` |

`security_storage` va siempre concedido. `ads_data_redaction` está activado mientras no se acepte marketing.

**Trampas:**

- **El theme no debe fijar otro `consent default`.** Uno posterior al nuestro puede devolver a «denegado» lo que el visitante aceptó. Un theme con su propio `analyticstracking.php` debe saltárselo si existe `LMC_VERSION`.
- **Tag Manager:** en su contenedor, el consentimiento se configura como «integrado», con las etiquetas de Google en «sin consentimiento adicional necesario». Para etiquetas que no son de Google, usa el disparador de evento personalizado `lmc_consentimiento` y la variable de capa de datos `lmc_categorias.marketing`.
- **Google Ads:** el script `gtag/js?id=AW-` y el `gtag('config','AW-…')` quedan en marketing aunque vayan junto a Analytics.
- **Píxel de Meta:** recibe `fbq('consent', 'grant' | 'revoke')`. **Clarity:** recibe `clarity('consent', …)`.

---

## 6 · Vimeo y YouTube

Lo que apareció en el primer escaneo de una web con vídeo: **76 reproductores de Vimeo** creaban cookies antes de cualquier consentimiento.

- **Vimeo:** el plugin añade `dnt=1` a todo `player.vimeo.com/video/…` al servir la página, lo ponga quien lo ponga. Con `dnt=1` el reproductor no rastrea y se declara como necesario («reproductor sin seguimiento»). Sin `dnt`, se bloquea en marketing.
- **YouTube:** pasa a `youtube-nocookie.com`, pero **se sigue bloqueando**, porque crea cookies al reproducir. Se ve un recuadro con «Aceptar y ver».
- Si el theme trae además su propio parche de `dnt` para Vimeo, no choca: si la URL ya lleva `dnt`, no se repite. Se puede retirar cuando el plugin esté instalado.

---

## 7 · WPML

- Los textos se registran solos en **WPML › Traducción de cadenas**, dominio `lamosquita-cookies`, al entrar en el escritorio, y se traducen al servir la página.
- La política de cookies traducida se enlaza cambiando `url_politica` según el idioma:
  ```php
  add_filter( 'lmc_ajustes', function ( $a ) {
      $a['url_politica'] = apply_filters( 'wpml_permalink', home_url( '/politica-de-cookies/' ), apply_filters( 'wpml_current_language', null ) );
      return $a;
  } );
  ```
- **Polylang:** lo mismo con `pll_register_string()` y `pll__()`. No está hecho: añádelo en `lmc_textos()` si hace falta.

---

## 8 · Llevarlo a una web sin WordPress (www.lamosquita.net)

Solo sirve `nucleo/`. Todo lo de `wordpress/` y `lamosquita-cookies.php` se queda fuera.

### 8.1 · Qué copiar

```
assets/lmc/lmc-cabecera.js
assets/lmc/lmc.js
assets/lmc/lmc.css
inc/lmc/servicios.json        fuera del alcance público (inc/ ya tiene .htaccess deny)
inc/lmc/reescribir.php
```

### 8.2 · En el `<head>`, lo primero

La configuración va escrita a mano, porque no hay escritorio. Las categorías son solo las de los servicios que use la web. Si no usa ninguno de terceros, **no hace falta el aviso**: la cookie de sesión de `/ferramenta` es técnica.

```php
<head>
<script data-lmc-cabecera>
window.LMC_AJUSTES = {
  version: '1',                       // súbela si cambian los servicios
  meses: 12,
  cookie: 'lmc_consentimiento',
  icono: true,
  posicion_icono: 'izquierda',
  url_politica: '/aviso-legal.php#cookies',
  endpoint: '/lmc-registro.php',      // o '' para no registrar
  categorias: [
    { id: 'necesarias', nombre: 'Necesarias', descripcion: 'Imprescindibles para que la web funcione.', servicios: [] },
    { id: 'estadistica', nombre: 'Estadística', descripcion: 'Saber de forma agregada cómo se usa la web.',
      servicios: [{ nombre: 'Google Analytics', proveedor: 'Google Ireland Ltd.', finalidad: 'Medición', cookies: ['_ga', '_ga_*'] }] }
  ],
  borrar: { estadistica: ['_ga', '_ga_*'] },
  textos: {}                          // vacío = los de lmc.js
};
<?php readfile( __DIR__ . '/assets/lmc/lmc-cabecera.js' ); ?>
</script>
<link rel="stylesheet" href="/assets/lmc/lmc.css?v=<?= filemtime( __DIR__ . '/assets/lmc/lmc.css' ) ?>">
<script defer src="/assets/lmc/lmc.js?v=<?= filemtime( __DIR__ . '/assets/lmc/lmc.js' ) ?>"></script>
```

El `?v=filemtime` es el mismo *cache-busting* que ya usa la portada.

### 8.3 · Bloquear

Hay dos formas:

- **A mano.** Más simple si hay pocas etiquetas:
  ```html
  <script type="text/plain" data-lmc="estadistica" async src="https://www.googletagmanager.com/gtag/js?id=G-XXXX"></script>
  <script type="text/plain" data-lmc="estadistica">gtag('js', new Date()); gtag('config', 'G-XXXX');</script>
  <iframe data-lmc="marketing" data-lmc-nombre="YouTube" data-lmc-src="https://www.youtube-nocookie.com/embed/…"></iframe>
  ```
- **Automático**, con el mismo `reescribir.php` que usa WordPress, en un búfer al principio de `index.php`:
  ```php
  require __DIR__ . '/inc/lmc/reescribir.php';
  ob_start( function ( $html ) {
      $servicios = json_decode( file_get_contents( __DIR__ . '/inc/lmc/servicios.json' ), true );
      list( $nuevo ) = lmc_reescribir_html( $html, $servicios, array( 'modo_google' => 'basico' ) );
      return $nuevo ?? $html; // si falla, la página original
  } );
  ```
  En ese caso la cabecera del §8.2 se escribe igual, a mano, porque `lmc_insertar_cabecera()` solo la mete si existe `data-lmc-cabecera`.

### 8.4 · Registro sin WordPress

Un `lmc-registro.php` con PDO, que es lo que ya usa la portada:

```php
<?php
$d = json_decode( file_get_contents( 'php://input' ), true );
if ( ! is_array( $d ) || ! preg_match( '/^[a-f0-9]{32}$/', $d['id'] ?? '' ) ) { http_response_code( 400 ); exit; }
require __DIR__ . '/inc/db.php';            // la conexión PDO de la portada
$ip = preg_replace( '/\.\d+$/', '.0', $_SERVER['REMOTE_ADDR'] ?? '' );
$db->prepare( 'INSERT INTO lmc_consentimientos (fecha, uid, version, preferencias, estadistica, marketing, origen, ip, agente)
               VALUES (UTC_TIMESTAMP(), ?, ?, ?, ?, ?, ?, ?, ?)' )
   ->execute( array( $d['id'], substr( (string) ( $d['v'] ?? '' ), 0, 40 ),
                     (int) ! empty( $d['c']['preferencias'] ), (int) ! empty( $d['c']['estadistica'] ), (int) ! empty( $d['c']['marketing'] ),
                     substr( (string) ( $d['origen'] ?? '' ), 0, 20 ), $ip, substr( $_SERVER['HTTP_USER_AGENT'] ?? '', 0, 190 ) ) );
http_response_code( 201 );
```

- La tabla, con las mismas columnas que `wordpress/registro.php`, va en `sql/01-esquema.sql`.
- Añade un límite por IP si el endpoint se abre al público.

### 8.5 · Reabrir la configuración

Un enlace en el aviso legal o en el pie: `<a href="#lmc-ajustes">Configurar cookies</a>`. O el icono (`icono: true`).

---

## 9 · Trampas ya vistas al instalarlo

- **Caché del HTML.** Con `ExpiresDefault "access plus 1 month"` los navegadores enseñaban páginas de hace semanas, con el aviso y los anuncios viejos. El HTML tiene que caducar al momento: `ExpiresByType text/html "access plus 0 seconds"`.
- **Comprobar rutas cerradas desde el propio servidor** (`curl --resolve dominio:443:127.0.0.1`), no desde fuera: un cortafuegos que reaccione a los 403 puede dejarte sin acceso.
- **Muros de consentimiento.** Un theme puede traer un muro propio que esconda el aviso del gestor anterior. Antes de instalar, busca CSS que oculte avisos de cookies (`.cmplz-*`, `.cli-*`, `#cookie-law-info-bar`).
- **Suscriptores o colaboradores.** Si la web fuerza un consentimiento propio para usuarios de pago apoyándose en las cookies del gestor anterior, hay que pasarlo a `lmc_consentimiento`.

---

## 10 · Pendiente

- **Recolector en el navegador:** modo en el que el navegador del administrador envía las cookies que crean los scripts al ejecutarse, que el escaneo desde el servidor no ve.
- Importar la clasificación de cookies de Complianz o CookieYes al migrar una web.
- Polylang (§7).

---

## 11 · Cambios

**0.4.1** · 21/09/2026

- **La decisión ya no se olvida a la semana en Safari ni en iPhone.** Safari,
  y todos los navegadores de iPhone porque usan su motor, borran a los 7 días
  las cookies que escribe JavaScript (`document.cookie`), aunque pidan un
  año. Era el caso de la nuestra: el aviso volvía a salir cada semana.
- Ahora, al decidir, `lmc.js` escribe la cookie como antes, para que valga al
  momento, y además manda la decisión a la propia web. **El servidor la vuelve
  a fijar** con `Set-Cookie`, con la misma forma y los 12 meses de verdad. Lo
  hace siempre, esté o no activado el registro de consentimientos.
- La pieza que arma esa cookie está en `nucleo/cookie-servidor.php`, sin
  WordPress, para poder usarla también en una web en PHP plano. En el
  navegador, la clave nueva es `servidor` (la URL); si falta, se usa `endpoint`.
- Si tras decidir hay que recargar la página, se espera a que conteste el
  servidor, hasta 1,5 s (`espera_servidor`). Si tarda más, se recarga igual:
  la cookie del navegador ya está puesta, y la del servidor llega en cuanto
  conteste, porque la petición sobrevive a la recarga (`keepalive`).
- El servidor sólo fija la cookie si la versión que manda el navegador es la
  vigente. Una página vieja abierta desde antes de un cambio no la pisa.
- Comprobado en un banco local: la cookie de la respuesta es byte a byte la
  que escribe `lmc.js`, y dura 365 días aunque la del navegador dure 4
  minutos. Lo que no se ha podido probar aquí es Safari: hay que mirarlo en
  su inspector web, en Almacenamiento › Cookies.

**0.4.0** · 16/09/2026

- **Actualizaciones desde el escritorio** (§3.1). Nuevo `actualizador.php`,
  común a todos nuestros plugins, y cabecera `Update URI`. Sin librerías de
  terceros.
- **Ojo con la primera vez:** las instalaciones anteriores a esta versión no
  se enteran solas, porque WordPress mira la cabecera del plugin ya
  instalado. Hay que subir la 0.4.0 a mano una vez en cada web.
- Nuevo `empaquetar.sh` en la raíz del repositorio: genera el ZIP y el JSON
  leyendo la versión de la cabecera, y se niega a empaquetar si la cabecera
  y `LMC_VERSION` no coinciden o si algún fichero no pasa el lint.

**0.3.2** · 16/09/2026, al integrarlo en una web sin WordPress

- **Aceptar ya no concede categorías que la web no ofrece.** «Aceptar» marcaba
  siempre las tres categorías fijas (`preferencias`, `estadistica`,
  `marketing`), las enseñara el panel o no. En una web que sólo declara
  estadística, eso mandaba a Google `ad_storage`, `ad_user_data` y
  `ad_personalization` en `granted` sin que nadie hubiera visto una
  categoría de marketing: un permiso que no se había pedido, y que en una
  propiedad con Google Signals activado sí tiene efecto.
  Ahora `todas()` sólo mueve las categorías declaradas en `A.categorias`.
- **Se corrige también a quien ya había aceptado.** La comprobación está en
  `senales()` de `lmc-cabecera.js`, que es de donde salen las señales en las
  dos partes, así que una cookie guardada con `marketing: true` de antes de
  este arreglo ya no concede publicidad si la web no ofrece esa categoría.
  No hace falta subir la versión del consentimiento ni volver a preguntar.
- **No cambia nada en las webs que sí declaran varias categorías**
  (comprobado en banco de pruebas con las tres: aceptar las concede las
  tres y activa sus scripts; rechazar las vuelve a bloquear).
- `permite()` no se ha tocado a propósito: un script marcado con una
  categoría que la web no declara sigue activándose si la cookie lo trae.
  Cambiarlo dejaría ese contenido bloqueado para siempre en una web mal
  configurada, y lo que había que cortar era lo que se le manda a Google.

**0.3.1** · 15/09/2026, tras el primer escaneo completo

- **La versión del consentimiento solo cambia con servicios que piden permiso.** Detectar uno necesario (Vimeo sin seguimiento, una librería de un CDN) ya no obliga a volver a preguntar a todos. Esta actualización la cambia una última vez.
- **Nuevo servicio «Librerías de CDN públicos»** (necesario, sin cookies): jQuery, cdnjs, jsDelivr, unpkg y Google Hosted Libraries. Ya no salen como «sin clasificar».
- **La API de Vimeo** (`player.vimeo.com/api/player.js`) cuenta como Vimeo sin seguimiento.
- **Escaneo:**
  - un aviso de PHP al elegir un término de taxonomía;
  - la portada salía dos veces, con y sin barra final.

**0.3.0** · 15/09/2026

- **Escaneo desde el escritorio** (Herramientas › Cookies › «Escanear ahora»), en cuatro partes:
  1. **Páginas.** Una muestra representativa: portada, cada tipo de contenido y su archivo, una página por plantilla (o todas si hay 25 o menos), una de cada taxonomía y, en WooCommerce, tienda, carrito, pago y cuenta. El servidor se las pide **a sí mismo por 127.0.0.1**, de una en una y con pausa, sin salir a internet ni pasar por el cortafuegos. Usa un token que dura lo que el escaneo, para recibirlas sin reescribir. Lee el HTML y las cookies que envía.
  2. **Contenidos.** Todas las entradas publicadas, desde la base de datos y por lotes de 200. Incluye los enlaces sueltos de YouTube y Vimeo que WordPress convierte en vídeo.
  3. **Theme.** Busca servicios en sus ficheros PHP, JS y HTML. **Solo lo señala para revisar**, porque el código puede mencionar un servicio sin cargarlo.
  4. **Sin clasificar.** Dominios externos y cookies que el catálogo no conoce.
  Lo de páginas y contenidos se añade solo al aviso. Los límites están en el bloque AJUSTES de `wordpress/escaneo.php`.
- **Recuadro en lo que se monta con script.** Un mapa de Google pintado por el theme ya no deja un hueco vacío: en el contenedor sale el mismo recuadro «Aceptar y ver» que en los iframes, y al aceptar se recarga la página.
  - Contenedores habituales en `servicios.json` (`"contenedores"`): `#map`, `#mapa`, `.acf-map`, `.wpgmza_map`, `.wpgmp_map_container`…
  - Los de cada web se ponen en Herramientas › Cookies › Contenido bloqueado, un selector CSS por servicio.
  - El contenido del theme no se borra, solo se oculta mientras tanto.
- Lo que ya viene marcado (por ejemplo, una página servida desde una caché) se detecta igual.

**0.2.2** · 15/09/2026

- **Aceptar con la página abierta no pintaba el mapa** (una página «Dónde estamos»): el theme intenta montar el mapa al cargar la página y, si el script de Google Maps llega después, no lo repite. Los servicios con `"recargar": true` en `servicios.json` (Google Maps y la API de YouTube) se marcan con `data-lmc-recargar`, y al aceptar su categoría la página se recarga sola. Analytics, píxeles e iframes siguen activándose sin recargar.
- Si un theme tiene otro script en ese caso, basta con añadir `"recargar": true` a su servicio.
- **Quien ya aceptó recibe la página sin bloquear** lo de sus categorías. Antes se le bloqueaba igual y lmc.js lo activaba después: los scripts llegaban tarde y el theme podía intentar usarlos antes de que existieran. Ahora el servidor lee la cookie `lmc_consentimiento` (si es de la versión vigente) y no marca esas categorías. Si en la página aparece un servicio que esa elección no cubría, se bloquea todo como antes.
- **Con caché de página completa:** la copia guardada puede salir bloqueada a quien ya aceptó. Sigue funcionando, porque lmc.js lo activa, pero con el retraso de antes. Lo ideal es que la caché no sirva copias a quien tenga la cookie `lmc_consentimiento`.

**0.2.1** · 15/09/2026, tras la primera instalación en una segunda web

- **Los colores guardados en el escritorio no se veían.** El estilo salía con la cabecera, antes de `lmc.css`, y este lo pisaba. Ahora va justo después de `lmc.css`.
- **Detección de cookies enviadas por el servidor.** Se leen las cabeceras `Set-Cookie` y la sesión PHP abierta, y se cruzan con el catálogo. Nuevo servicio **«Sesión PHP»** (`PHPSESSID`, necesaria), para themes que abren sesión en todas las páginas.
- **Google Maps cargado desde `maps.google.com/maps/api/js`.** No se detectaba ni se bloqueaba, y ponía la cookie `NID` sin consentimiento. Nuevo patrón en `google-maps`. **Ojo:** al bloquear el script, el mapa queda vacío hasta aceptar marketing, y si el theme llama a `google.maps` al cargar, puede dar un error de JavaScript. Hay que revisarlo en la página del mapa.

**0.2.0** · Colores del aviso en Herramientas › Cookies.

**0.1.0** · Primera versión.

