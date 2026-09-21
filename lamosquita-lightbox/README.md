# lamosquita-lightbox

Visor de fotos y páginas en ventana para WordPress. Sin jQuery ni librerías:
unos 8 KB comprimidos el visor y 3 KB la ventana, y cada uno sólo en las
páginas que lo necesitan.

## Qué hace

- **Sin tocar el theme ni el contenido**: abre cualquier enlace a una foto: `rel="lightbox"` de los themes, galerías `[gallery link="file"]` y bloques de galería, y enlaces sueltos en el texto.
- **Deslizar con el dedo**: la foto sigue al dedo y la siguiente va entrando; si se suelta pronto, vuelve a su sitio.
- **Zoom**: pellizcando o con doble toque en el móvil; con la rueda o doble clic en el ordenador. Con zoom, se arrastra para moverse por la foto.
- **Fotos más ligeras**: abre la versión de WordPress de hasta 2048 px, nunca el original, y el móvil baja una menor si le basta.
- **Galerías sin repeticiones**: agrupa por `rel`, por galería o la foto sola, sólo con los enlaces que se ven y sin repetir la misma foto.
- **Pie de foto**: debajo de la foto, sin taparla, el título y la descripción de la biblioteca de medios. Sobre el fondo oscuro o en un paspartú blanco, a elegir por web. Los títulos que son el nombre del fichero o de la cámara no salen, y los datos EXIF en JSON se resumen en una línea (cámara, focal, diafragma, velocidad, ISO y fecha).
- **Páginas en ventana**: los enlaces a las páginas elegidas (o con la clase `lmq-modal`) abren la página encima de la actual, sólo con su título y su contenido, pero con todo lo que necesita: formularios, mapas, sliders… La ventana se ajusta al alto del contenido.
- **Móvil**: el botón «atrás» cierra el visor en vez de salir de la página.
- **Teclado y accesibilidad**: ← → para pasar, Esc para cerrar, el foco vuelve al enlace, y respeta «reducir movimiento».

## Uso

Se activa y ya está. Lo que cambia de una web a otra, en **Ajustes › Visor y
ventanas**: estilo del pie, qué textos salen, qué páginas se abren en ventana y
su ancho.

Para que un enlace a una foto no se abra en el visor, la clase `nolightbox` en
el enlace o en un contenedor suyo. Para abrir en ventana un enlace suelto a
cualquier página, la clase `lmq-modal`.

## Documentación

El [`LEEME.md`](LEEME.md) tiene el mapa de ficheros, los ajustes, cómo
funciona por dentro y las pruebas.

## Licencia

GPL-2.0-or-later.
