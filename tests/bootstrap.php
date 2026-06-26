<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

// Load WP stubs for testing without WordPress runtime
require_once __DIR__ . '/stubs/wp-stubs.php';

// Composer autoloader — try local vendor first, then root project vendor (path repo symlink setup)
$localAutoloader = dirname(__DIR__) . '/vendor/autoload.php';
$rootAutoloader = dirname(__DIR__, 3) . '/vendor/autoload.php';

if (file_exists($localAutoloader)) {
    require_once $localAutoloader;
} elseif (file_exists($rootAutoloader)) {
    require_once $rootAutoloader;
} else {
    throw new RuntimeException('Cannot find Composer autoloader. Run composer install.');
}
