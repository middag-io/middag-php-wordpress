<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Support;

use DateInterval;
use DateTimeImmutable;
use Psr\SimpleCache\CacheInterface;

/**
 * PSR-16 adapter over the WordPress Object Cache (`wp_cache_*`), scoped to a
 * single cache area (mapped to a `wp_cache` group). Persistent when a backend
 * drop-in (Redis, Memcached) is installed; request-scoped otherwise.
 *
 * Allows platform-agnostic framework collaborators (e.g. the core cached item
 * repository decorator) to consume the WP object cache via {@see CacheInterface}
 * without depending on the `(key, group)` calling convention or WP statics.
 * WordPress counterpart of the Moodle adapter's CacheSupportPsr16.
 *
 * Area purge without a portable `wp_cache_flush_group()`: WordPress exposes no
 * reliable per-group flush across every backend, so {@see clear()} bumps a
 * per-area generation counter kept inside the group. Every entry is stored under
 * a `{generation}:{key}` name, so a bump orphans the whole area at once without
 * touching other groups (unlike `wp_cache_flush()`, which nukes everything).
 *
 * @api
 */
final readonly class CacheSupportPsr16 implements CacheInterface
{
    private const GROUP_PREFIX = 'middag_';

    private const GENERATION_KEY = '__gen';

    private string $group;

    public function __construct(string $area = 'default')
    {
        $this->group = self::GROUP_PREFIX . $area;
    }

    public function get(string $key, mixed $default = null): mixed
    {
        if (!\function_exists('wp_cache_get')) {
            return $default;
        }

        $found = false;
        $value = wp_cache_get($this->scopedKey($key), $this->group, false, $found);

        return $found ? $value : $default;
    }

    public function set(string $key, mixed $value, DateInterval|int|null $ttl = null): bool
    {
        if (!\function_exists('wp_cache_set')) {
            return false;
        }

        return wp_cache_set($this->scopedKey($key), $value, $this->group, $this->ttlSeconds($ttl));
    }

    public function delete(string $key): bool
    {
        if (!\function_exists('wp_cache_delete')) {
            return false;
        }

        return wp_cache_delete($this->scopedKey($key), $this->group);
    }

    /**
     * Purge the whole area by bumping its generation; older entries become
     * unreachable behind the new `{generation}:` prefix.
     */
    public function clear(): bool
    {
        if (!\function_exists('wp_cache_set')) {
            return false;
        }

        return wp_cache_set(self::GENERATION_KEY, $this->generation() + 1, $this->group, 0);
    }

    /**
     * @param iterable<string> $keys
     *
     * @return iterable<string, mixed>
     */
    public function getMultiple(iterable $keys, mixed $default = null): iterable
    {
        $result = [];

        foreach ($keys as $key) {
            $result[$key] = $this->get($key, $default);
        }

        return $result;
    }

    /**
     * @param iterable<string, mixed> $values
     */
    public function setMultiple(iterable $values, DateInterval|int|null $ttl = null): bool
    {
        $ok = true;

        foreach ($values as $key => $value) {
            $ok = $this->set((string) $key, $value, $ttl) && $ok;
        }

        return $ok;
    }

    /**
     * @param iterable<string> $keys
     */
    public function deleteMultiple(iterable $keys): bool
    {
        $ok = true;

        foreach ($keys as $key) {
            $ok = $this->delete($key) && $ok;
        }

        return $ok;
    }

    public function has(string $key): bool
    {
        if (!\function_exists('wp_cache_get')) {
            return false;
        }

        $found = false;
        wp_cache_get($this->scopedKey($key), $this->group, false, $found);

        return $found;
    }

    /**
     * Prefix a caller key with the current area generation.
     */
    private function scopedKey(string $key): string
    {
        return $this->generation() . ':' . $key;
    }

    /**
     * Current generation for this area, initialised to 1 on first use.
     */
    private function generation(): int
    {
        if (!\function_exists('wp_cache_get')) {
            return 1;
        }

        $found = false;
        $generation = wp_cache_get(self::GENERATION_KEY, $this->group, false, $found);

        if (!$found || !\is_int($generation)) {
            wp_cache_set(self::GENERATION_KEY, 1, $this->group, 0);

            return 1;
        }

        return $generation;
    }

    /**
     * Normalise a PSR-16 TTL to whole seconds (0 = no expiration).
     */
    private function ttlSeconds(DateInterval|int|null $ttl): int
    {
        if ($ttl === null) {
            return 0;
        }

        if ($ttl instanceof DateInterval) {
            $now = new DateTimeImmutable();

            return max(0, $now->add($ttl)->getTimestamp() - $now->getTimestamp());
        }

        return max(0, $ttl);
    }
}
