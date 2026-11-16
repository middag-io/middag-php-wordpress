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
 * Outcome of one diagnostic check, ordered by severity.
 *
 * `Pass` is healthy, `Warn` is degraded but usable (a strict run treats it as
 * broken), `Fail` is broken. A package and a whole report take the worst status
 * of what they contain.
 *
 * @api
 */
enum CheckStatus: string
{
    case Pass = 'pass';

    case Warn = 'warn';

    case Fail = 'fail';

    /**
     * Severity rank: higher is worse.
     */
    public function severity(): int
    {
        return match ($this) {
            self::Pass => 0,
            self::Warn => 1,
            self::Fail => 2,
        };
    }

    /**
     * The worse of this status and another one.
     */
    public function worst(self $other): self
    {
        return $other->severity() > $this->severity() ? $other : $this;
    }
}
