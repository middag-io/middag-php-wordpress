<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Tests\Privacy;

use Middag\WordPress\Privacy\Contract\PersonalDataProviderInterface;
use Middag\WordPress\Privacy\PrivacyRegistrar;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversClass(PrivacyRegistrar::class)]
final class PrivacyRegistrarTest extends TestCase
{
    protected function setUp(): void
    {
        $GLOBALS['__wp_test_filters'] = [];
        $GLOBALS['__wp_test_privacy_policy_content'] = [];
    }

    protected function tearDown(): void
    {
        unset(
            $GLOBALS['__wp_test_filters'],
            $GLOBALS['__wp_test_privacy_policy_content'],
        );
    }

    #[Test]
    public function registerHooksBothPrivacyFilters(): void
    {
        $registrar = new PrivacyRegistrar();
        $registrar->register();

        self::assertArrayHasKey('wp_privacy_personal_data_exporters', $GLOBALS['__wp_test_filters']);
        self::assertArrayHasKey('wp_privacy_personal_data_erasers', $GLOBALS['__wp_test_filters']);
    }

    #[Test]
    public function registeredExporterFilterDispatchesToTheProviderEndToEnd(): void
    {
        $provider = $this->makeProvider('my-plugin-orders', 'My Plugin Orders');
        $registrar = new PrivacyRegistrar();
        $registrar->addProvider($provider);
        $registrar->register();

        // Pull the exact callback WordPress would have hooked, then dispatch it
        // the way WP does — this proves register() wired the RIGHT callable, not
        // just that collectExporters() works in isolation.
        $filter = $this->hookedFilter('wp_privacy_personal_data_exporters');
        $exporters = $filter([]);

        self::assertArrayHasKey('my-plugin-orders', $exporters);
        self::assertSame('My Plugin Orders', $exporters['my-plugin-orders']['exporter_friendly_name']);

        // WordPress then calls the per-provider callback with the email + page.
        $result = ($exporters['my-plugin-orders']['callback'])('jane@example.com', 2);

        self::assertSame(['export', 'jane@example.com', 2], $provider->lastCall);
        self::assertTrue($result['done']);
        self::assertSame([], $result['data']);
    }

    #[Test]
    public function registeredEraserFilterDispatchesToTheProviderEndToEnd(): void
    {
        $provider = $this->makeProvider('my-plugin-orders', 'My Plugin Orders');
        $registrar = new PrivacyRegistrar();
        $registrar->addProvider($provider);
        $registrar->register();

        $filter = $this->hookedFilter('wp_privacy_personal_data_erasers');
        $erasers = $filter([]);

        self::assertArrayHasKey('my-plugin-orders', $erasers);
        self::assertSame('My Plugin Orders', $erasers['my-plugin-orders']['eraser_friendly_name']);

        $result = ($erasers['my-plugin-orders']['callback'])('jane@example.com', 1);

        self::assertSame(['erase', 'jane@example.com', 1], $provider->lastCall);
        self::assertTrue($result['items_removed']);
        self::assertTrue($result['done']);
    }

    #[Test]
    public function registeredFilterIsSafeWithNoProviders(): void
    {
        $registrar = new PrivacyRegistrar();
        $registrar->register();

        // No providers: the hooked callback returns the running registry
        // unchanged (other plugins' entries preserved, nothing of ours added).
        $filter = $this->hookedFilter('wp_privacy_personal_data_exporters');
        $existing = ['core' => ['exporter_friendly_name' => 'Core', 'callback' => static fn (): array => []]];

        self::assertSame($existing, $filter($existing));
    }

    #[Test]
    public function collectorsPreserveExistingEntriesFromOtherPlugins(): void
    {
        $registrar = new PrivacyRegistrar();
        $registrar->addProvider($this->makeProvider('mine', 'Mine'));

        $exporters = $registrar->collectExporters(['core' => ['exporter_friendly_name' => 'Core', 'callback' => static fn (): array => []]]);

        self::assertArrayHasKey('core', $exporters);
        self::assertArrayHasKey('mine', $exporters);
    }

    #[Test]
    public function addProviderIsKeyedSoLaterRegistrationWins(): void
    {
        $registrar = new PrivacyRegistrar();
        $registrar->addProvider($this->makeProvider('same', 'First'));
        $registrar->addProvider($this->makeProvider('same', 'Second'));

        self::assertSame(['same'], $registrar->getRegisteredKeys());

        $exporters = $registrar->collectExporters([]);
        self::assertSame('Second', $exporters['same']['exporter_friendly_name']);
    }

    #[Test]
    public function addPrivacyPolicyContentDelegatesThroughTheSeam(): void
    {
        $registrar = new PrivacyRegistrar();
        $registrar->addPrivacyPolicyContent('My Plugin', '<p>policy</p>');

        $recorded = $GLOBALS['__wp_test_privacy_policy_content'][0] ?? null;
        self::assertNotNull($recorded);
        self::assertSame('My Plugin', $recorded['plugin_name']);
    }

    /**
     * The callback the registrar hooked onto a WordPress filter (as recorded by
     * the add_filter stub).
     */
    private function hookedFilter(string $hook): callable
    {
        $entry = $GLOBALS['__wp_test_filters'][$hook][0] ?? null;
        self::assertIsArray($entry, sprintf('no callback hooked onto %s', $hook));

        /** @var callable $callback */
        $callback = $entry['callback'];

        return $callback;
    }

    /**
     * A spy provider that records its last call and returns canned WP shapes.
     */
    private function makeProvider(string $key, string $label): PersonalDataProviderInterface
    {
        return new class($key, $label) implements PersonalDataProviderInterface {
            /** @var null|array{0: string, 1: string, 2: int} */
            public ?array $lastCall = null;

            public function __construct(private readonly string $key, private readonly string $label) {}

            public function key(): string
            {
                return $this->key;
            }

            public function label(): string
            {
                return $this->label;
            }

            public function export(string $email, int $page): array
            {
                $this->lastCall = ['export', $email, $page];

                return ['data' => [], 'done' => true];
            }

            public function erase(string $email, int $page): array
            {
                $this->lastCall = ['erase', $email, $page];

                return ['items_removed' => true, 'items_retained' => false, 'messages' => [], 'done' => true];
            }
        };
    }
}
