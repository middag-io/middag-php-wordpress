<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Http\Routing;

use Middag\WordPress\Http\Routing\RestPathTranslator;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(RestPathTranslator::class)]
final class RestPathTranslatorTest extends TestCase
{
    #[Test]
    public function leavesAStaticPathUnchanged(): void
    {
        self::assertSame('/affiliates/profile', RestPathTranslator::toWordPress('/affiliates/profile'));
    }

    #[Test]
    public function inlinesAPlaceholderRequirementAsANamedGroup(): void
    {
        self::assertSame(
            '/organizations/(?P<id>\d+)',
            RestPathTranslator::toWordPress('/organizations/{id}', ['id' => '\d+']),
        );
    }

    #[Test]
    public function fallsBackToASingleSegmentWhenNoRequirementIsGiven(): void
    {
        self::assertSame(
            '/organizations/(?P<slug>[^/]+)',
            RestPathTranslator::toWordPress('/organizations/{slug}'),
        );
    }

    #[Test]
    public function translatesEveryPlaceholderInAMultiParamPath(): void
    {
        self::assertSame(
            '/orgs/(?P<orgId>\d+)/members/(?P<userId>\d+)',
            RestPathTranslator::toWordPress(
                '/orgs/{orgId}/members/{userId}',
                ['orgId' => '\d+', 'userId' => '\d+'],
            ),
        );
    }
}
