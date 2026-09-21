#!/bin/bash
# =================================================================
# Empaqueta un plugin para distribuirlo y genera su JSON de
# actualizaciones, leyendo la versión de la propia cabecera para que
# no puedan descuadrarse.
#
#   ./empaquetar.sh lamosquita-cookies
#
# Deja en dist/:
#   lamosquita-cookies-0.4.2.zip   el paquete que instala WordPress
#   lamosquita-cookies.json        lo que lee el actualizador: versión,
#                                  descripción, cambios, icono…
#   lamosquita-cookies.svg         el icono, si lo hay
#
# Todo se sube a la raíz de BASE (abajo). El JSON y el icono siempre con
# el mismo nombre: son las direcciones fijas que consultan las webs.
# =================================================================

# ── AJUSTES ──────────────────────────────────────────────────────
# Dónde se sirven el JSON y los ZIP. Tiene que coincidir con la
# cabecera «Update URI» de los plugins.
BASE="https://plugins.lamosquita.net"
# El icono de cada plugin, en SVG cuadrado. %s es el nombre del plugin
# sin «lamosquita-» (cookies, slider…). Se busca el primero en ICONO y, si
# no está, en ICONO_2. Si no existe ninguno, se publica sin icono.
ICONO="imagen-plugins/plugin-%s-fondo.svg"
ICONO_2="imagen-plugins/plugin-%s.svg"
# ── fin de AJUSTES ───────────────────────────────────────────────

set -e
cd "$(dirname "$0")"

PLUGIN="$1"
[ -z "$PLUGIN" ] && { echo "uso: $0 <carpeta-del-plugin>"; exit 1; }
[ -d "$PLUGIN" ] || { echo "no existe la carpeta $PLUGIN"; exit 1; }

PRINCIPAL="$PLUGIN/$PLUGIN.php"
[ -f "$PRINCIPAL" ] || { echo "no encuentro $PRINCIPAL"; exit 1; }

leer() { grep -m1 -i "^ \* $1:" "$PRINCIPAL" | sed "s/^ \* $1: *//I" | tr -d '\r' | xargs; }

VERSION=$(leer "Version")
URI=$(leer "Update URI")
REQUIERE=$(leer "Requires at least")
REQUIERE_PHP=$(leer "Requires PHP")

[ -z "$VERSION" ] && { echo "sin 'Version' en la cabecera"; exit 1; }
[ -z "$URI" ]     && { echo "sin 'Update URI' en la cabecera: WordPress no preguntaría nunca"; exit 1; }

# La versión de la cabecera y la de la constante tienen que ser la misma.
CONST=$(grep -m1 "LMC_VERSION\s*=" "$PRINCIPAL" | sed "s/.*'\(.*\)'.*/\1/" || true)
if [ -n "$CONST" ] && [ "$CONST" != "$VERSION" ]; then
  echo "AVISO: cabecera $VERSION y constante $CONST no coinciden"; exit 1
fi

echo "== $PLUGIN $VERSION =="

# Sintaxis, antes de empaquetar nada.
find "$PLUGIN" -name '*.php' -print0 | xargs -0 -n1 php -l > /dev/null
command -v node > /dev/null && find "$PLUGIN" -name '*.js' -print0 | xargs -0 -n1 node --check
echo "  sintaxis correcta"

# Sólo lo de este plugin: los paquetes de los demás se quedan donde están.
mkdir -p dist && rm -f "dist/$PLUGIN"-*.zip "dist/$PLUGIN.json" "dist/$PLUGIN.svg"
ZIP="$PLUGIN-$VERSION.zip"
zip -rq "dist/$ZIP" "$PLUGIN" \
    -x '*.DS_Store' -x '*/._*' -x '*-interno.md' -x '*-INTERNO.md'
echo "  dist/$ZIP  ($(du -h "dist/$ZIP" | cut -f1 | xargs))"

# El icono, con el nombre fijo del plugin.
ORIGEN_ICONO=$(printf "$ICONO" "${PLUGIN#lamosquita-}")
[ -f "$ORIGEN_ICONO" ] || ORIGEN_ICONO=$(printf "$ICONO_2" "${PLUGIN#lamosquita-}")
if [ -f "$ORIGEN_ICONO" ]; then
  cp "$ORIGEN_ICONO" "dist/$PLUGIN.svg"
  NOMBRE_ICONO="$PLUGIN.svg"
  echo "  dist/$PLUGIN.svg  (de $ORIGEN_ICONO)"
else
  NOMBRE_ICONO="-"
  echo "  (sin icono: no existe $ORIGEN_ICONO)"
fi

# El JSON lo arma PHP: convierte el Markdown y escapa lo que haga falta.
php herramientas/publicacion.php "$PLUGIN" "$BASE" "$ZIP" "$NOMBRE_ICONO" "dist/$PLUGIN.json"
php -r 'json_decode(file_get_contents($argv[1])); exit(json_last_error() ? 1 : 0);' "dist/$PLUGIN.json" \
  || { echo "el JSON no es válido"; exit 1; }
echo "  dist/$PLUGIN.json"
echo
echo "Subir todo lo de dist/ a $BASE/ y comprobar:"
echo "  curl -s $BASE/$PLUGIN.json"
