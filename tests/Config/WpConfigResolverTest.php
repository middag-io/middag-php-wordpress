<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Config;

use Middag\WordPress\Config\WpConfigResolver;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(WpConfigResolver::class)]
final class WpConfigResolverTest extends TestCase
{
    /** @var array<int, string> env var names set by the test, cleared in tearDown */
    private array $envVars = [];

    protected function setUp(): void
    {
        $GLOBALS['__wp_test_options'] = [];
    }

    protected function tearDown(): void
    {
        foreach ($this->envVars as $name) {
            putenv($name);
        }
        $this->envVars = [];
        unset($GLOBALS['__wp_test_options']);
    }

    #[Test]
    public function envVarWinsOverOptionAndDefault(): void
    {
        $this->setEnv('MIDDAG_SMTP_HOST', 'env.example.test');
        $GLOBALS['__wp_test_options']['middag_smtp_host'] = 'option.example.test';

        $resolver = new WpConfigResolver();

        self::assertSame('env.example.test', $resolver->get('smtp_host', null, 'default.example.test'));
    }

    #[Test]
    public function optionIsUsedWhenNoEnvVarExists(): void
    {
        $GLOBALS['__wp_test_options']['middag_smtp_host'] = 'option.example.test';

        $resolver = new WpConfigResolver();

        self::assertSame('option.example.test', $resolver->get('smtp_host'));
    }

    #[Test]
    public function defaultIsReturnedWhenNothingResolves(): void
    {
        $resolver = new WpConfigResolver();

        self::assertSame('fallback', $resolver->get('missing_key', null, 'fallback'));
        self::assertSame('', $resolver->get('missing_key'));
    }

    #[Test]
    public function perEntityKeyWinsOverTheGlobalKey(): void
    {
        $this->setEnv('MIDDAG_PROVIDER_KEY', 'global-secret');
        $this->setEnv('MIDDAG_PROVIDER_KEY_ACME', 'acme-secret');

        $resolver = new WpConfigResolver();

        self::assertSame('acme-secret', $resolver->get('provider_key', 'acme'));
    }

    #[Test]
    public function globalKeyIsTheFallbackForAnUnknownEntitySlug(): void
    {
        $this->setEnv('MIDDAG_PROVIDER_KEY', 'global-secret');

        $resolver = new WpConfigResolver();

        self::assertSame('global-secret', $resolver->get('provider_key', 'unknown'));
    }

    #[Test]
    public function customPrefixesAreHonoured(): void
    {
        $this->setEnv('ACME_API_TOKEN', 'env-token');
        $GLOBALS['__wp_test_options']['acme_api_url'] = 'https://api.acme.test';

        $resolver = new WpConfigResolver('ACME_', 'acme_');

        self::assertSame('env-token', $resolver->get('api_token'));
        self::assertSame('https://api.acme.test', $resolver->get('api_url'));
    }

    #[Test]
    public function hasReflectsResolvability(): void
    {
        $this->setEnv('MIDDAG_PRESENT', 'yes');

        $resolver = new WpConfigResolver();

        self::assertTrue($resolver->has('present'));
        self::assertFalse($resolver->has('absent'));
    }

    private function setEnv(string $name, string $value): void
    {
        putenv($name . '=' . $value);
        $this->envVars[] = $name;
    }
}
