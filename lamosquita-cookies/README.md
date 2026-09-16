# lamosquita-cookies

Aviso de cookies y consentimiento para WordPress, sin dependencias.
Sustituye a Complianz, CookieYes y CookieLawInfo.

Funciona además **fuera de WordPress**: la carpeta `nucleo/` no depende de
él y se puede llevar a una web en PHP plano (§8 del `LEEME.md`).

## Qué hace

- **Aviso abajo, sin tapar la página.** Aceptar, Rechazar y Configurar con
  el mismo peso visual, como pide la AEPD. Desaparece al decidir.
- **Bloqueo previo.** Ningún script ni iframe de terceros conocido se carga
  hasta aceptar su categoría. Las necesarias van siempre.
- **Modo de consentimiento v2 de Google.** Todo denegado de salida; al
  decidir se envían las señales de Analytics y Ads y el evento
  `lmc_consentimiento` para Tag Manager.
- **Sólo se concede lo que se enseña.** Si una web no ofrece la categoría
  de marketing, aceptar no manda `ad_storage: granted`.
- **Vimeo sin seguimiento** (`dnt=1`) y **YouTube sin cookies**.
- **Detección de servicios**: cada página servida anota los scripts, los
  iframes y las cookies que envía el servidor, y de ahí salen el panel de
  preferencias y la tabla `[lmc_tabla_cookies]`.
- **Registro de cada decisión** como prueba, con la IP recortada, y
  exportación en CSV.

## Requisitos

WordPress 5.8 o superior y PHP 7.4 o superior. Sin Composer ni `vendor/`.

## Instalar

1. Copia la carpeta a `wp-content/plugins/lamosquita-cookies/`.
2. **Desactiva antes el gestor de cookies anterior.** Dos avisos a la vez
   se pisan.
3. Actívalo. Crea la tabla del registro y la purga diaria.
4. Navega por la web sin sesión para que anote los servicios de cada página.
5. Revisa lo detectado en **Herramientas › Cookies**.
6. En la política de cookies, pon `[lmc_tabla_cookies]` y un enlace
   `[lmc_ajustes]`.

Un administrador puede ver cualquier página sin el plugin añadiendo
`?lmc-desactivar` a la URL.

## Ajustar

Todo está en el bloque `AJUSTES` de `lamosquita-cookies.php`, y se
sobrescribe por web sin tocarlo:

```php
add_filter( 'lmc_ajustes', function ( $a ) {
    $a['colores']['acento'] = '#009999';
    $a['posicion_icono']    = 'derecha';
    return $a;
} );
```

Los colores también se cambian desde **Herramientas › Cookies › Colores del
aviso**, con vista previa.

## Documentación

El [`LEEME.md`](LEEME.md) tiene el mapa de ficheros, los ajustes uno a uno,
Google y Tag Manager, Vimeo y YouTube, WPML, cómo llevarlo a una web sin
WordPress, las trampas conocidas y el historial de cambios.

## Licencia

GPL-2.0-or-later.
