# lamosquita-slider

Sliders ligeros para WordPress. En la web no carga jQuery ni ninguna
librería: unos 2 KB de CSS y 2 KB de JS, comprimidos. Las transiciones las
hace el navegador con CSS.

## Qué hace

- **Un formato de imagen por dispositivo**: escritorio, tableta horizontal, tableta vertical y móvil vertical. Sólo la de escritorio es obligatoria; los formatos sin imagen propia usan la de escritorio recortada alrededor de su foco.
- **Foco por imagen**: se marca pinchando en la miniatura, y es el punto que se queda siempre dentro del encuadre.
- **El hueco se reserva antes de cargar**: la proporción de cada formato se conoce de antemano y la página no salta.
- **Programación por fechas**: una versión por defecto y las programadas que se quieran, cada una completa (imágenes, tiempos, tipografía, transición).
- **Título encima de la imagen**, con cuerpo, cuerpo en móvil, grosor, color y posición en una rejilla de 3 × 3. El color se puede cambiar por slide, y la posición por slide y por formato (hereda si no se toca). El editor muestra el título a escala sobre la vista previa de cada formato.
- **Enlace por slide**.
- **Tres transiciones**: fundido, desplazar y fundido con zoom lento.
- **Varios sliders por web**, con shortcode.
- **Preparado para WPML**: cada idioma, su slider.
- **Importador** del slider que llevaban los themes a medida anteriores.
- **Accesible**: los slides que no se ven no reciben el foco del teclado, se para con el ratón o el foco encima, y respeta «reducir movimiento».

## Uso

```
[lmq_slider id="12"]
[lmq_slider nombre="portada"]
<?php lmq_slider( 12 ); ?>
<?php echo do_shortcode( '[lmq_slider id="12"]' ); ?>
```

Ocupa todo el ancho del elemento en el que se mete, o el de la página si no va dentro de nada.

## Documentación

El [`LEEME.md`](LEEME.md) tiene el mapa de ficheros, los ajustes, cómo se
integra en un theme, el importador y las pruebas.

## Licencia

GPL-2.0-or-later.
