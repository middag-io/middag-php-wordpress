<?php

declare(strict_types=1);

namespace Middag\Wordpress\Tests;

use PHPUnit\Framework\TestCase;

final class NegativeDeprecationProofTest extends TestCase
{
    public function testADeprecationFailsTheSuite(): void
    {
        trigger_error('negative proof', E_USER_DEPRECATED);
        self::assertTrue(true);
    }
}
