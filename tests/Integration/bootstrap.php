<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

// Bootstrap of the integration suite: a real WordPress, never tests/stubs.
// WORDPRESS_DIR names a tree prepared by bin/wp-integration-setup.sh; its
// wp-config.php reads the WORDPRESS_DB_* variables.

$wordpressDir = getenv('WORDPRESS_DIR');
if (!is_string($wordpressDir) || $wordpressDir === '' || !is_file($wordpressDir . '/wp-config.php')) {
    throw new RuntimeException('Set WORDPRESS_DIR to a WordPress tree prepared by bin/wp-integration-setup.sh.');
}

$wordpressDir = rtrim($wordpressDir, '/');

require_once dirname(__DIR__, 2) . '/vendor/autoload.php';

// A fresh site per run, installed in a separate process (see install.php).
passthru(escapeshellarg(PHP_BINARY) . ' ' . escapeshellarg(__DIR__ . '/install.php') . ' ' . escapeshellarg($wordpressDir), $exitCode);
if ($exitCode !== 0) {
    throw new RuntimeException(sprintf('WordPress install failed with exit code %d.', $exitCode));
}

// PHPUnit includes this file inside a method, while wp-config.php and
// wp-settings.php expect the global scope for these variables.
global $wpdb, $table_prefix, $current_site, $current_blog, $wp_rewrite, $shortcode_tags, $wp, $phpmailer, $wp_theme_directories;

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';

define('WP_USE_THEMES', false);

require_once $wordpressDir . '/wp-load.php';
