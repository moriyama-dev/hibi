#!/usr/bin/env bash
# Sets up the WordPress PHPUnit test suite outside the repository.
#
# Everything lands in ~/.cache/hibi-wp-tests/<version>: a separate copy of
# WordPress core (so tests never write into a live site's uploads) and the
# test library. The test database must already exist and must not be the
# site's own database: the suite drops every table in it.
#
# Usage: bin/install-wp-tests.sh [wp-version]
#   HIBI_TEST_DB_NAME  (default: wp_hibi_test)
#   HIBI_TEST_DB_USER / HIBI_TEST_DB_PASSWORD / HIBI_TEST_DB_HOST
#     default to the values in $HIBI_SITE_PATH/wp-config.php
#   HIBI_SITE_PATH     (default: /var/www/html)

set -euo pipefail

SITE="${HIBI_SITE_PATH:-/var/www/html}"
WP_VERSION="${1:-$(wp core version --path="$SITE")}"
BASE="$HOME/.cache/hibi-wp-tests/$WP_VERSION"
CORE="$BASE/wordpress"
LIB="$BASE/tests-lib"

DB_NAME="${HIBI_TEST_DB_NAME:-wp_hibi_test}"
DB_USER="${HIBI_TEST_DB_USER:-$(wp config get DB_USER --path="$SITE")}"
DB_PASSWORD="${HIBI_TEST_DB_PASSWORD:-$(wp config get DB_PASSWORD --path="$SITE")}"
DB_HOST="${HIBI_TEST_DB_HOST:-$(wp config get DB_HOST --path="$SITE")}"
SITE_DB="$(wp config get DB_NAME --path="$SITE")"

if [ "$DB_NAME" = "$SITE_DB" ]; then
	echo "Refusing: the test database ($DB_NAME) is the site's database. The test suite drops all of its tables." >&2
	exit 1
fi

mkdir -p "$BASE"

if [ ! -f "$CORE/wp-settings.php" ]; then
	echo "Downloading WordPress $WP_VERSION core to $CORE"
	wp core download --version="$WP_VERSION" --path="$CORE" --force
fi

if [ ! -f "$LIB/includes/functions.php" ]; then
	echo "Exporting the test library for $WP_VERSION"
	svn export --quiet --force "https://develop.svn.wordpress.org/tags/$WP_VERSION/tests/phpunit/includes/" "$LIB/includes"
	svn export --quiet --force "https://develop.svn.wordpress.org/tags/$WP_VERSION/tests/phpunit/data/" "$LIB/data"
fi

CONFIG="$LIB/wp-tests-config.php"
umask 077
cat > "$CONFIG" <<PHP
<?php
define( 'ABSPATH', '$CORE/' );
define( 'WP_DEFAULT_THEME', 'default' );
define( 'WP_TESTS_MULTISITE', false );
define( 'WP_DEBUG', true );
define( 'DB_NAME', '$DB_NAME' );
define( 'DB_USER', '$DB_USER' );
define( 'DB_PASSWORD', '$DB_PASSWORD' );
define( 'DB_HOST', '$DB_HOST' );
define( 'DB_CHARSET', 'utf8mb4' );
define( 'DB_COLLATE', '' );
\$table_prefix = 'wptests_';
define( 'WP_TESTS_DOMAIN', 'example.org' );
define( 'WP_TESTS_EMAIL', 'admin@example.org' );
define( 'WP_TESTS_TITLE', 'Test Blog' );
define( 'WP_PHP_BINARY', 'php' );
define( 'WPLANG', '' );
PHP

echo "Ready. Run: WP_TESTS_DIR=$LIB vendor/bin/phpunit"
