#!/usr/bin/env bash
# Builds the distributable ZIPs into dist-zip/:
#   hibi-journal-<version>.zip  the plugin (for WordPress.org)
#   hibi-<version>.zip          the theme
#
# Left out: dotfiles (Plugin Check rejects them, including vendor/**/.github),
# tests, development files, and the plugin's .po/.mo (translate.wordpress.org
# provides those). The theme keeps its Japanese .po/.mo: it is not on
# WordPress.org, so no language pack reaches it.

set -euo pipefail

ROOT="$(cd "$(dirname "$0")/.." && pwd)"
OUT="$ROOT/dist-zip"
WORK="$(mktemp -d)"
trap 'rm -rf "$WORK"' EXIT

header() { grep -m1 -E "^[ \t*]*$2:" "$1" | sed -E "s/^[ \t*]*$2:[ \t]*//" | tr -d '\r'; }

PLUGIN_VERSION="$(header "$ROOT/plugin/hibi-journal.php" 'Version')"
STABLE_TAG="$(header "$ROOT/plugin/readme.txt" 'Stable tag')"
THEME_VERSION="$(header "$ROOT/theme/style.css" 'Version')"
THEME_STABLE="$(header "$ROOT/theme/readme.txt" 'Stable tag')"

if [ "$PLUGIN_VERSION" != "$STABLE_TAG" ]; then
	echo "Plugin Version ($PLUGIN_VERSION) and readme.txt Stable tag ($STABLE_TAG) differ." >&2
	exit 1
fi
if [ "$THEME_VERSION" != "$THEME_STABLE" ]; then
	echo "Theme Version ($THEME_VERSION) and readme.txt Stable tag ($THEME_STABLE) differ." >&2
	exit 1
fi
if ! grep -q "define( 'HIBI_VERSION', '$PLUGIN_VERSION' );" "$ROOT/plugin/hibi-journal.php"; then
	echo "HIBI_VERSION does not match the plugin header ($PLUGIN_VERSION)." >&2
	exit 1
fi

# The theme ships the built app: make sure it is current.
( cd "$ROOT" && npm run build --silent >/dev/null )

copy() {
	# $1 source dir, $2 destination dir, $3 extra pattern to leave out (regex).
	# Only files Git would track: anything ignored, locally or globally, stays
	# out. Hidden files and development folders are dropped from the list.
	local src="$1" dst="$2" extra="${3:-^$}"
	( cd "$src" && git ls-files --cached --others --exclude-standard ) \
		| grep -vE '(^|/)\.' \
		| grep -vE '^(tests|bin|node_modules)/|(^|/)README\.md$|\.zip$' \
		| grep -vE "$extra" > "$WORK/files.txt" || true
	rsync -a --files-from="$WORK/files.txt" "$src/" "$dst/"
}

mkdir -p "$OUT" "$WORK/hibi-journal" "$WORK/hibi"
copy "$ROOT/plugin" "$WORK/hibi-journal" '\.(po|mo)$|^composer\.lock$'
copy "$ROOT/theme" "$WORK/hibi"

# Nothing hidden may slip through, at any depth.
if find "$WORK" -name '.*' -not -path "$WORK" | grep -q .; then
	echo "Dotfiles left in the build:" >&2
	find "$WORK" -name '.*' -not -path "$WORK" >&2
	exit 1
fi

rm -f "$OUT/hibi-journal-$PLUGIN_VERSION.zip" "$OUT/hibi-$THEME_VERSION.zip"
# Python's zipfile, so the build needs no extra package on the server.
pack() {
	python3 - "$WORK" "$1" "$2" <<'PY'
import os, sys, zipfile
work, folder, target = sys.argv[1:]
with zipfile.ZipFile(target, 'w', zipfile.ZIP_DEFLATED, compresslevel=9) as z:
    for base, dirs, files in os.walk(os.path.join(work, folder)):
        dirs.sort()
        for name in sorted(files):
            path = os.path.join(base, name)
            z.write(path, os.path.relpath(path, work))
PY
}
pack hibi-journal "$OUT/hibi-journal-$PLUGIN_VERSION.zip"
pack hibi "$OUT/hibi-$THEME_VERSION.zip"

echo "$OUT/hibi-journal-$PLUGIN_VERSION.zip ($(du -h "$OUT/hibi-journal-$PLUGIN_VERSION.zip" | cut -f1))"
echo "$OUT/hibi-$THEME_VERSION.zip ($(du -h "$OUT/hibi-$THEME_VERSION.zip" | cut -f1))"
