<?php

declare(strict_types=1);

/**
 * PHPStan-only stub for the WordPress `$wpdb` output-format constants.
 *
 * php-stubs/wordpress-stubs ships the function/class signatures but not these
 * runtime constants (WordPress defines them in wp-includes/wp-db.php), so a
 * standalone static analysis reports `constant.notFound`. This file is listed
 * under `scanFiles` in .phpstan.neon — it is parsed for symbols, never
 * executed, so it carries no runtime weight and never collides with the real
 * WordPress definitions.
 *
 * @see https://developer.wordpress.org/reference/classes/wpdb/get_results/
 */

define('OBJECT', 'OBJECT');
define('OBJECT_K', 'OBJECT_K');
define('ARRAY_A', 'ARRAY_A');
define('ARRAY_N', 'ARRAY_N');
