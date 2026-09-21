# lamosquita-lightbox

Visor de fotos ligero para WordPress. Sin jQuery ni librerías: unos 5 KB de
JS y 1,5 KB de CSS comprimidos, y sólo en las páginas que tienen enlaces a
fotos.

## Qué hace

- **Sin tocar el theme ni el contenido**: abre cualquier enlace a una foto: `rel="lightbox"` de los themes, galerías `[gallery link="file"]` y bloques de galería, y enlaces sueltos en el texto.
- **Deslizar con el dedo**: la foto sigue al dedo y la siguiente va entrando; si se suelta pronto, vuelve a su sitio.
- **Zoom**: pellizcando o con doble toque en el móvil; con la rueda o doble clic en el ordenador. Con zoom, se arrastra para moverse por la foto.
- **Fotos más ligeras**: abre la versión de WordPress de hasta 2048 px, nunca el original, y el móvil baja una menor si le basta.
- **Galerías sin repeticiones**: agrupa por `rel`, por galería o la foto sola, sólo con los enlaces que se ven y sin repetir la misma foto.
- **Pie de foto**: el de la galería, el `alt` de la miniatura o el `title` del enlace.
- **Móvil**: el botón «atrás» cierra el visor en vez de salir de la página.
- **Teclado y accesibilidad**: ← → para pasar, Esc para cerrar, el foco vuelve al enlace, y respeta «reducir movimiento».

## Uso

Se activa y ya está. Para que un enlace a una foto no se abra en el visor, la
clase `nolightbox` en el enlace o en un contenedor suyo.

## Documentación

El [`LEEME.md`](LEEME.md) tiene el mapa de ficheros, los ajustes, cómo
funciona por dentro y las pruebas.

## Licencia

GPL-2.0-or-later.
