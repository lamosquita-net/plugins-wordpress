# lamosquita-lightbox

Visor de fotos y páginas en ventana para WordPress, sin jQuery. Versión 0.2.0 · 21/09/2026.

---

## 1 · Mapa

```
lamosquita-lightbox/
  lamosquita-lightbox.php  cabecera y bloque AJUSTES (lado máximo, cíclico)
  actualizador.php         el común de todos nuestros plugins
  nucleo/                  lo que no depende de WordPress
    enlaces.php            enlaces a fotos, sus versiones, el pie; enlaces a ventana
    lmq-lightbox.js        el visor; AJUSTES del gesto y del zoom al principio
    lmq-lightbox.css       su aspecto, el pie y el paspartú; AJUSTES en variables
    lmq-modal.js           la ventana
    lmq-modal.css          su aspecto; AJUSTES en variables
  wordpress/
    ajustes.php            Ajustes › Visor y ventanas (lo que cambia por web)
    web.php                la página por el búfer, la consulta y el aviso de Firelight
    plantilla-modal.php    la página dentro de la ventana: título y contenido
```

## 2 · Cómo funciona

1. **En el servidor**, antes de enviar la página (búfer desde `template_redirect`):
   - busca los enlaces `<a href="…">` que terminan en `.jpg`, `.jpeg`, `.png`,
     `.webp`, `.avif` o `.gif`;
   - si no hay ninguno, la página sale tal cual y **no se carga nada**;
   - si los hay, busca en la biblioteca de medios los que son de la web, **con
     una sola consulta** para todos. Reconoce el enlace a la foto tal cual, a un
     tamaño intermedio (`-1024x768`) y al original de una foto grande que
     WordPress guardó reducida (`-scaled`);
   - a cada uno le escribe `data-lmq-src` (la versión mayor que no pasa de
     `LMQ_LIGHTBOX_LADO_MAX`), `data-lmq-srcset` (las menores de la misma
     proporción) y sus medidas. **El `href` no se toca**: sin JS, el enlace
     abre la foto como siempre;
   - añade el CSS y el JS justo antes de `</body>`.
2. **En el navegador**, al pulsar un enlace a foto, abre el visor (un
   `<dialog>`) con su grupo.

Las fotos de otras webs o que no están en la biblioteca también se abren, con
su `href` tal cual.

## 3 · Grupos

- **`rel`**: todos los enlaces con el mismo `rel` (el `rel="lightbox"` de los
  themes). Se ignoran `noopener`, `nofollow` y parecidos.
- **Galería**: si no hay `rel`, los enlaces del mismo `.gallery`,
  `.wp-block-gallery`, `.tiled-gallery` o `[data-lmq-grupo]`.
- **Sola**: si no, la foto sola.

En todos los casos, **sólo los enlaces que se ven** y **sin repetir la misma
foto**. Así, en los themes que ponen la primera foto cuatro veces (una por
formato, tres ocultas), sale una.

## 4 · Pie de foto

Sale **debajo de la foto**, nunca encima, con el mismo ancho que ella:

- **Título** de la foto en la biblioteca de medios, salvo que sea el nombre del
  fichero o el de la cámara (`IMG_1165`, `_DSC1337`…).
- **Descripción**, con sus saltos de línea. Es el campo que conviene rellenar:
  WordPress no la enseña en las galerías de la página, sólo sale aquí (la
  *leyenda*, en cambio, sale bajo cada miniatura de la galería).
- **Datos de la toma**: si la descripción es el EXIF de la cámara en JSON, se
  resume en una línea: cámara · focal · diafragma · velocidad · ISO · fecha.
- Los marcadores sin rellenar («#image_title») no salen.

Foto y pie caben siempre en la pantalla: si el texto es largo la foto se
reduce, y el pie no pasa de un tercio de la pantalla (lo demás se desplaza).
Las fotos que no están en la biblioteca usan el pie de la galería, el `alt` o
el `title` del enlace.

## 5 · Páginas en ventana

- Se abren en ventana los enlaces a las páginas elegidas en los ajustes (con
  WPML, en todos los idiomas) y los que llevan la clase `lmq-modal`. La clase
  `nolmqmodal` en un enlace o contenedor lo deja fuera.
- Dentro va la misma página con `?lmq_modal=1`, pintada con
  `wordpress/plantilla-modal.php`: `wp_head()` y `wp_footer()` (cargan el CSS
  del theme y todo lo que necesite el contenido: formularios, mapas,
  sliders…), el título y el contenido; sin cabecera, menú ni pie. Esa versión
  lleva `noindex`.
- La ventana toma el alto de su contenido y lo sigue si cambia (un mapa que
  carga, un formulario que responde); si no cabe, se desplaza dentro. En el
  móvil ocupa toda la pantalla.
- Los enlaces de dentro que van a otras páginas se abren en la ventana
  principal. Se cierra con la ×, con Esc (también con el foco dentro),
  tocando fuera o con «atrás» en el móvil.
- Si el theme sólo da estilo al contenido dentro de un contenedor suyo, el
  contenido lleva también las clases `entry-content` y `entrada`.

## 6 · Ajustes

- **Escritorio, Ajustes › Visor y ventanas** (se guardan en la base de datos,
  las actualizaciones no los tocan): estilo del pie (fondo oscuro o
  paspartú), título sí/no, descripción sí/no, páginas en ventana y ancho
  máximo de la ventana.
- **`lamosquita-lightbox.php`**: lado máximo (2048) y cíclico (no), iguales en
  todas las webs.
- **`nucleo/lmq-lightbox.css`**: fondo, color, margen, duración, botones,
  colores del pie y del paspartú y su grosor. **`nucleo/lmq-modal.css`**: fondo,
  margen, esquinas y color de la ×. Se pueden cambiar por web desde el CSS del theme:
  ```css
  .lmq-lb { --lmq-lb-fondo: rgb(20 20 20 / .95); }
  ```
- **`nucleo/lmq-lightbox.js`**: cuánto hay que arrastrar para pasar, la
  velocidad que cuenta como gesto rápido y los niveles de zoom.

## 7 · Cambiar desde Firelight Lightbox (Easy FancyBox)

Activar este y desactivar Firelight. Mientras los dos estén activos, las fotos
se abren con este (se adelanta al clic) y el escritorio avisa de que Firelight
sigue activo. Firelight no hace falta desinstalarlo para probar: se puede
volver atrás activándolo otra vez.

## 8 · Pruebas

```bash
php pruebas/lightbox.php               # el núcleo: enlaces, candidatos, versiones, anotar
php pruebas/lightbox-wordpress.php     # web.php con la base de datos simulada
php pruebas/actualizador.php lamosquita-lightbox
php -S 127.0.0.1:8492 -t pruebas       # banco: http://127.0.0.1:8492/lightbox-banco/
```

El banco reproduce el `single.php` de los themes de fotografía (la primera foto
en cuatro formatos), una galería con pies, un enlace suelto a un tamaño
intermedio, uno excluido y uno de otra web. Las «fotos» son SVG con su tamaño
escrito encima, para ver cuál baja el navegador. `?ciclico=1` para probar el
modo cíclico y `?paspartu=1` el pie en paspartú. Los textos imitan los de una
biblioteca real (título propio, EXIF en JSON, descripción larga, título de la
cámara, marcador sin rellenar). `contacto/` es una página que se abre en
ventana, con un formulario y un «mapa» que necesitan su JS.

## 9 · Cambios

**0.2.0** · 21/09/2026

- **Pie de foto** con el título y la descripción de la biblioteca, debajo de la
  foto; sobre el fondo oscuro o en un paspartú blanco. Datos EXIF en JSON
  resumidos en una línea. El pie se desliza con su foto.
- **Páginas en ventana**: elegidas en los ajustes o con la clase `lmq-modal`;
  sólo título y contenido, con todo su JS y su CSS.
- **Ajustes › Visor y ventanas**, para lo que cambia de una web a otra.
- Tocar el pie o el paspartú ya no cierra el visor: sólo el fondo.

**0.1.0** · 21/09/2026

- Primera versión: sustituye a Firelight Lightbox sin tocar los themes.
