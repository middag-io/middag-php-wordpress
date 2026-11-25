<?php

declare(strict_types=1);

namespace Middag\WordPress\Tests;

use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
final class SuppressedDeprecationProofTest extends TestCase
{
    public function testSuppressedDeprecationFailsTheSuite(): void
    {
        @trigger_error('suppressed proof', E_USER_DEPRECATED);

        self::assertTrue(true);
    }
}
