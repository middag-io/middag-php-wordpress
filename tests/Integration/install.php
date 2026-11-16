<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

// Installs a fresh WordPress site for the integration suite. Run by the
// bootstrap in its own PHP process, because WordPress refuses to load a site
// that is not installed unless WP_INSTALLING is set, and WP_INSTALLING must
// not leak into the tests. Every table with the configured prefix is dropped
// first, so each run starts from the same state.

if (PHP_SAPI !== 'cli') {
    exit(1);
}

$wordpressDir = $argv[1] ?? '';
if ($wordpressDir === '' || !is_file($wordpressDir . '/wp-config.php')) {
    fwrite(STDERR, "install.php: pass the WordPress directory prepared by bin/wp-integration-setup.sh.\n");

    exit(1);
}

$_SERVER['HTTP_HOST'] = 'localhost';
$_SERVER['SERVER_NAME'] = 'localhost';
$_SERVER['SERVER_PROTOCOL'] = 'HTTP/1.1';
$_SERVER['REQUEST_METHOD'] = 'GET';
$_SERVER['REQUEST_URI'] = '/';

define('WP_INSTALLING', true);
define('WP_USE_THEMES', false);

require_once $wordpressDir . '/wp-load.php';

require_once ABSPATH . 'wp-admin/includes/upgrade.php';

/** @var wpdb $wpdb */
$tables = $wpdb->get_col($wpdb->prepare('SHOW TABLES LIKE %s', $wpdb->esc_like($wpdb->prefix) . '%'));
foreach ($tables as $table) {
    $wpdb->query('DROP TABLE IF EXISTS `' . str_replace('`', '', (string) $table) . '`');
}

wp_cache_flush();

$result = wp_install('Integration', 'admin', 'admin@example.org', false, '', 'password');
if (!isset($result['user_id']) || (int) $result['user_id'] <= 0) {
    fwrite(STDERR, "install.php: wp_install() did not create the administrator.\n");

    exit(1);
}

fwrite(STDOUT, sprintf("Installed WordPress %s in %s\n", $GLOBALS['wp_version'], DB_NAME));
