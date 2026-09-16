# plugins-wordpress

Colección de plugins creados a medida para mis proyectos, con licencia
GPL 2.0. Uno por función, para no arrastrar a cada web código que no usa.

## Por qué

Estas webs comparten funcionalidades pero no themes. Tener cada trozo
repetido en cada theme significa arreglar el mismo fallo tantas veces como
sitios haya, y que una de esas veces se quede sin hacer. Un plugin por
función se instala sólo donde hace falta y se corrige en un sitio.

## Plugins

| plugin | qué hace |
|---|---|
| [`lamosquita-cookies`](lamosquita-cookies/) | Aviso de cookies y consentimiento, con bloqueo previo de terceros y modo de consentimiento v2 de Google. Funciona también fuera de WordPress. |

## Cómo están hechos

Todos siguen el mismo patrón, que es el que permite reutilizarlos:

```
plugin/
  plugin.php     cabecera de WordPress y bloque AJUSTES
  nucleo/        lo que NO depende de WordPress
  wordpress/     la capa fina que lo engancha a WordPress
```

Lo que vive en `nucleo/` puede usarse en una web en PHP plano copiando esa
carpeta y escribiendo la configuración a mano. `lamosquita-cookies` está en
producción de las dos maneras.

## Actualizaciones

Cada plugin lleva `actualizador.php`, que hace que las versiones nuevas
aparezcan en **Escritorio › Actualizaciones** de cada web. Usa el mecanismo
nativo de WordPress (cabecera `Update URI` y filtro
`update_plugins_<anfitrión>`, desde la 5.8), sin librerías de terceros.

Para publicar una versión:

```bash
./empaquetar.sh lamosquita-cookies
```

Comprueba la sintaxis, lee la versión de la cabecera del plugin y deja en
`dist/` el ZIP y el JSON que consultan las webs.

Antes de publicar, pasa las pruebas del actualizador. No necesitan
WordPress: corren contra un simulador que vive en `pruebas/`.

```bash
php pruebas/actualizador.php lamosquita-cookies
```

Cubren la comparación de versiones, la convivencia con otros plugins
nuestros (el filtro es por anfitrión, así que a cada actualizador le
llegan las consultas de los demás), qué pasa cuando el servidor falla o
devuelve basura, y que la caché no machaque a un servidor caído.

**La primera instalación siempre es manual:** WordPress lee `Update URI` del
plugin ya instalado, así que una versión que no la lleve no se entera de
nada.

## Instalar

Copia la carpeta del plugin a `wp-content/plugins/` y actívalo desde el
escritorio. Cada plugin lleva su `LEEME.md` con los detalles, los ajustes y
las trampas conocidas.

## Licencia

GPL-2.0-or-later, como WordPress.
