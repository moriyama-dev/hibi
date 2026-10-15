#!/usr/bin/env bash
# Installs the plugin's PHP libraries and moves them under the Hibi\Vendor
# namespace in plugin/vendor-prefixed (Strauss), so another plugin bundling
# the same libraries cannot clash. Run after changing plugin/composer.json.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
TOOLS="${HOME}/.cache/hibi-tools"
STRAUSS="$TOOLS/strauss.phar"
VERSION="0.31.1"

if [ ! -f "$STRAUSS" ]; then
	mkdir -p "$TOOLS"
	curl -sSL -o "$STRAUSS" "https://github.com/BrianHenryIE/strauss/releases/download/${VERSION}/strauss.phar"
fi

cd "$ROOT/plugin"
rm -rf vendor vendor-prefixed
composer install --no-dev --no-interaction --quiet
php "$STRAUSS"
echo "Prefixed libraries are in plugin/vendor-prefixed."
