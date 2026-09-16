#!/bin/bash
# =================================================================
# Empaqueta un plugin para distribuirlo y genera su JSON de
# actualizaciones, leyendo la versión de la propia cabecera para que
# no puedan descuadrarse.
#
#   ./empaquetar.sh lamosquita-cookies
#
# Deja en dist/:
#   lamosquita-cookies-0.4.0.zip   el paquete que instala WordPress
#   lamosquita-cookies.json        lo que lee el actualizador
#
# Los dos se suben a la raíz de BASE (abajo). El JSON siempre con el
# mismo nombre: es la dirección fija que consultan las webs.
# =================================================================

# ── AJUSTES ──────────────────────────────────────────────────────
# Dónde se sirven el JSON y los ZIP. Tiene que coincidir con la
# cabecera «Update URI» de los plugins.
BASE="https://plugins.lamosquita.net"
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

rm -rf dist && mkdir -p dist
ZIP="$PLUGIN-$VERSION.zip"
zip -rq "dist/$ZIP" "$PLUGIN" \
    -x '*.DS_Store' -x '*/._*' -x '*-interno.md' -x '*-INTERNO.md'
echo "  dist/$ZIP  ($(du -h "dist/$ZIP" | cut -f1 | xargs))"

cat > "dist/$PLUGIN.json" <<JSON
{
  "id": "$URI",
  "slug": "$PLUGIN",
  "version": "$VERSION",
  "url": "https://github.com/lamosquita-net/plugins-wordpress/tree/main/$PLUGIN",
  "package": "$BASE/$ZIP",
  "requires": "$REQUIERE",
  "requires_php": "$REQUIERE_PHP"
}
JSON
echo "  dist/$PLUGIN.json"
echo
echo "Subir los dos a $BASE/ y comprobar:"
echo "  curl -s $BASE/$PLUGIN.json"
