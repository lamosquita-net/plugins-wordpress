# lamosquita-lightbox

Visor de fotos ligero para WordPress, sin jQuery. Versión 0.1.0 · 21/09/2026.

---

## 1 · Mapa

```
lamosquita-lightbox/
  lamosquita-lightbox.php  cabecera y bloque AJUSTES (lado máximo, cíclico, pie)
  actualizador.php         el común de todos nuestros plugins
  nucleo/                  lo que no depende de WordPress
    enlaces.php            encontrar los enlaces a fotos y anotar sus versiones
    lmq-lightbox.js        el visor; AJUSTES del gesto y del zoom al principio
    lmq-lightbox.css       aspecto; AJUSTES en variables
  wordpress/
    web.php                la página por el búfer, la consulta y el aviso de Firelight
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

## 4 · Ajustes

- **`lamosquita-lightbox.php`**: lado máximo (2048), cíclico (no) y pie (sí).
- **`nucleo/lmq-lightbox.css`**: fondo, color, margen, duración y tamaño de los
  botones. Se pueden cambiar por web desde el CSS del theme:
  ```css
  .lmq-lb { --lmq-lb-fondo: rgb(20 20 20 / .95); }
  ```
- **`nucleo/lmq-lightbox.js`**: cuánto hay que arrastrar para pasar, la
  velocidad que cuenta como gesto rápido y los niveles de zoom.

## 5 · Cambiar desde Firelight Lightbox (Easy FancyBox)

Activar este y desactivar Firelight. Mientras los dos estén activos, las fotos
se abren con este (se adelanta al clic) y el escritorio avisa de que Firelight
sigue activo. Firelight no hace falta desinstalarlo para probar: se puede
volver atrás activándolo otra vez.

## 6 · Pruebas

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
modo cíclico.

## 7 · Cambios

**0.1.0** · 21/09/2026

- Primera versión: sustituye a Firelight Lightbox sin tocar los themes.
