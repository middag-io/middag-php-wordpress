#!/usr/bin/env bash
#
# Prepares a real WordPress tree for the integration suite.
#
# Usage: bin/wp-integration-setup.sh <major> [target-dir]
#
# Resolves the newest patch release of the WordPress major (for example 7.1
# gives 7.1.4) from the api.wordpress.org stable-check list, downloads the
# official zip from wordpress.org, verifies its SHA-1 against the published
# checksum, unpacks it into target-dir (default .wordpress/<major>) and writes
# a wp-config.php that reads the database settings from the environment:
# WORDPRESS_DB_HOST, WORDPRESS_DB_NAME, WORDPRESS_DB_USER and
# WORDPRESS_DB_PASSWORD. The database itself is installed by the integration
# bootstrap. Prints the target directory on success.

set -euo pipefail

readonly STABLE_CHECK_URL='https://api.wordpress.org/core/stable-check/1.0/'
readonly DOWNLOAD_BASE_URL='https://wordpress.org'

die() {
    echo "wp-integration-setup: $*" >&2
    exit 1
}

major="${1:-}"
[[ "$major" =~ ^[0-9]+\.[0-9]+$ ]] || die "usage: $0 <major, for example 7.1> [target-dir]"

target="${2:-.wordpress/$major}"
php_bin="${PHP_BINARY:-php}"

command -v curl >/dev/null || die 'curl is required'
command -v unzip >/dev/null || die 'unzip is required'

# Newest release of the major: "7.1" itself or any "7.1.N".
version="$(curl -fsSL "$STABLE_CHECK_URL" | "$php_bin" -r '
    $major = $argv[1];
    $releases = json_decode((string) stream_get_contents(STDIN), true, 512, JSON_THROW_ON_ERROR);
    $matches = array_filter(
        array_map("strval", array_keys($releases)),
        static fn (string $v): bool => preg_match("/^" . preg_quote($major, "/") . "(\\.\\d+)?$/", $v) === 1,
    );
    usort($matches, "version_compare");
    echo end($matches) ?: "";
' "$major")"
[[ -n "$version" ]] || die "no WordPress release found for major $major"

if [[ -f "$target/wp-includes/version.php" ]] && grep -q "\$wp_version = '$version';" "$target/wp-includes/version.php"; then
    echo "WordPress $version already in $target" >&2
else
    mkdir -p "$(dirname "$target")"
    work="$(mktemp -d "${target%/}.download.XXXXXX")"
    trap 'rm -rf "$work"' EXIT

    zip="$work/wordpress-$version.zip"
    curl -fsSL -o "$zip" "$DOWNLOAD_BASE_URL/wordpress-$version.zip"
    expected="$(curl -fsSL "$DOWNLOAD_BASE_URL/wordpress-$version.zip.sha1" | tr -d '[:space:]')"
    actual="$("$php_bin" -r 'echo sha1_file($argv[1]);' "$zip")"
    [[ -n "$expected" && "$expected" == "$actual" ]] || die "SHA-1 mismatch for wordpress-$version.zip (expected $expected, got $actual)"

    unzip -q "$zip" -d "$work"
    rm -rf "$target"
    mv "$work/wordpress" "$target"
    echo "WordPress $version unpacked in $target" >&2
fi

cat >"$target/wp-config.php" <<'PHP'
<?php

// Written by bin/wp-integration-setup.sh for the integration suite only.

define('DB_NAME', getenv('WORDPRESS_DB_NAME') ?: 'wordpress');
define('DB_USER', getenv('WORDPRESS_DB_USER') ?: 'root');
define('DB_PASSWORD', getenv('WORDPRESS_DB_PASSWORD') ?: '');
define('DB_HOST', getenv('WORDPRESS_DB_HOST') ?: '127.0.0.1');
define('DB_CHARSET', 'utf8mb4');
define('DB_COLLATE', '');

$table_prefix = 'wp_';

define('WP_DEBUG', true);
define('WP_DEBUG_DISPLAY', true);
define('WP_DEBUG_LOG', false);
define('DISABLE_WP_CRON', true);
define('AUTOMATIC_UPDATER_DISABLED', true);
define('WP_HTTP_BLOCK_EXTERNAL', true);

if (!defined('ABSPATH')) {
    define('ABSPATH', __DIR__ . '/');
}

require_once ABSPATH . 'wp-settings.php';
PHP

echo "$target"
