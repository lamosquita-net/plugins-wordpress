# lamosquita-slider

Sliders ligeros para WordPress, sin jQuery en la web. Versión 0.1.0 · 21/09/2026.

---

## 1 · Mapa

```
lamosquita-slider/
  lamosquita-slider.php    cabecera y bloque AJUSTES (cuándo se cargan CSS y JS)
  actualizador.php         el común de todos nuestros plugins
  wpml-config.xml          cada slider se traduce como una entrada
  nucleo/                  lo que no depende de WordPress
    elegir.php             qué versión toca ahora (por defecto o programada)
    pintar.php             el HTML; AJUSTES de formatos y valores por defecto
    lmq-slider.css         aspecto, formatos y transiciones; AJUSTES en variables
    lmq-slider.js          el movimiento, sin dependencias
  wordpress/
    datos.php              tipo de entrada «lmq_slider», saneado de lo que llega
    web.php                shortcode, lmq_slider() y carga de CSS y JS
    editor.php · .js · .css  la caja de edición
    importar.php           el slider de los themes antiguos
```

## 2 · Formatos y tramos de pantalla

| formato | tramo |
|---|---|
| móvil vertical | `(max-width: 599px) and (orientation: portrait)` |
| tableta vertical | `(max-width: 1199px) and (orientation: portrait)` |
| tableta horizontal | `(max-width: 1199px) and (orientation: landscape)` — también móviles apaisados |
| escritorio | el resto |

**Los tramos están en dos sitios**: `lmq_slider_formatos()` en
`nucleo/pintar.php` (para los `<source>` de cada imagen) y el bloque AJUSTES de
`nucleo/lmq-slider.css` (para el alto del hueco y el foco). Las variables CSS
no funcionan dentro de `@media`, así que no pueden estar en uno solo. Si cambias
uno, cambia el otro: `pruebas/slider.php` falla si no coinciden.

## 3 · En un theme

- **En el contenido**: `[lmq_slider id="12"]`. El CSS y el JS se cargan solos en esa página.
- **En una plantilla**: `<?php lmq_slider( 12 ); ?>`, y en el `functions.php` del theme:
  ```php
  add_filter( 'lmq_slider_encolar', '__return_true' );
  ```
  Sin eso el CSS llega igual, pero al final de la página, y el hueco puede saltar al cargar.
- **El theme sólo coloca el contenedor** (`.lmq-slider`): ancho, márgenes, columna. Lo de dentro sale del bloque AJUSTES del CSS del plugin, y se puede cambiar por web sin tocarlo:
  ```css
  .lmq-slider { --lmq-duracion: 1.5s; --lmq-titulo-sombra: 0 1px 8px rgb(0 0 0 / .5); }
  ```
- **`sizes`**: si el slider no ocupa todo el ancho, `[lmq_slider id="12" sizes="(min-width: 1200px) 1100px, 100vw"]` ayuda al navegador a bajar la imagen justa.

## 4 · Programación

- Cada programación es una versión completa, con fecha de inicio y de fin (el fin no se incluye).
- «+ Programar a partir del por defecto» la crea copiando el por defecto entero.
- No se puede programar hasta que el por defecto tenga al menos un slide con imagen de escritorio.
- Si se solapan dos, se ve la que empieza más tarde.
- Las fechas son hora de la web (Ajustes › Generales › Zona horaria). El slider de los themes las leía en UTC y arrancaba una o dos horas tarde.

## 5 · Importar el slider de los themes antiguos

**Sliders › Importar del theme** aparece sólo en las webs que tienen la opción
`cob-home-slider-sliders`. Enseña primero lo que ha entendido y sólo importa al
pulsar el botón. No borra nada: la opción y el theme se quedan como estaban.

- `alt` pasa a ser el título; `lnk`, el enlace; el orden, el de `ord`.
- `tbi` está en centésimas de segundo (1000 = 10 s). Mínimo, 1 s.
- Como el slider antiguo usaba la misma imagen en todos los dispositivos, los cuatro formatos se quedan con la proporción de la primera imagen.
- **Las fechas se guardaban en dos formatos** (día/mes y mes/día, a veces en la misma programación). Se lee día/mes y, si no vale, mes/día. Si una fecha vale de las dos maneras, avisa. Las caducadas no se importan.
- Con WPML, un slider por idioma, enlazados como traducciones (`cat` → `ca`). Sin WPML, sólo el idioma principal.

Después de importar: revisar cada slider, poner el shortcode o `lmq_slider()`
donde iba el del theme y, cuando esté bien, quitar el `include('slider.php')`
del theme.

## 6 · Pruebas

```bash
php pruebas/slider.php            # núcleo: programación, HTML, saneado, tramos CSS = PHP
php pruebas/slider-wordpress.php  # saneado del editor e importador
php pruebas/actualizador.php lamosquita-slider
```

Bancos para el navegador, sin WordPress:

```bash
PHP_CLI_SERVER_WORKERS=4 php -S 127.0.0.1:8490 -t pruebas/slider-banco         # el slider
php -S 127.0.0.1:8492 -t pruebas/slider-editor-banco                           # el editor
```

El del slider tiene un reloj sintético (`?reloj=1`) porque el navegador de
pruebas frena los temporizadores de las pestañas ocultas.

## 7 · Cambios

**0.1.0** · 21/09/2026

Primera versión. Sustituye al slider que llevaban los themes a medida
(`slider.php`, `alslider.js`, `config-homeslider-*.php`), que estaba en 15
webs con 15 versiones distintas. De lo que se vio al revisarlas:

- Sin jQuery. El antiguo usaba `.load(fn)`, retirado en jQuery 3, y sólo
  funcionaba porque WordPress sigue cargando `jquery-migrate`.
- Espera a que llegue la imagen **siguiente** antes de pasar a ella. El
  vanilla de algunos themes comprobaba la actual y enseñaba un hueco negro.
- No toca `HTMLElement.prototype`. El vanilla de algunos themes redefinía
  `animate`, que es la función de animaciones del propio navegador.
- No arranca dos veces aunque el script llegue dos veces a la página.
- Programación en hora de la web, no en UTC.
