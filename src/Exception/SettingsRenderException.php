<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Exception;

use InvalidArgumentException;

/**
 * Raised when a declarative settings field cannot be rendered safely.
 *
 * @api
 */
final class SettingsRenderException extends InvalidArgumentException {}
