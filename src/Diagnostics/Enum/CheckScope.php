<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Diagnostics\Enum;

/**
 * What layer a diagnostic check proves.
 *
 * `Boot` checks prove that a package loaded: its autoloader resolved, its
 * kernel booted, its callbacks registered. `Render` checks prove that what the
 * package serves comes out whole, for example that an admin page still emits
 * the HTML shell and the core script bundles. Every package can be green on
 * boot while its pages render broken, so a report with no `Render` check says
 * so explicitly instead of letting a green result read as "the interface
 * works".
 *
 * @api
 */
enum CheckScope: string
{
    case Boot = 'boot';

    case Render = 'render';
}
