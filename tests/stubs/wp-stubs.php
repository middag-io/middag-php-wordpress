<?php

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

if (!class_exists('WP_REST_Response')) {
    class WP_REST_Response
    {
        public function __construct(private mixed $data = null, private int $status = 200, private array $headers = []) {}

        public function get_data(): mixed
        {
            return $this->data;
        }

        public function set_data(mixed $data): void
        {
            $this->data = $data;
        }

        public function get_status(): int
        {
            return $this->status;
        }

        public function set_status(int $code): void
        {
            $this->status = $code;
        }

        public function get_headers(): array
        {
            return $this->headers;
        }

        public function header(string $key, string $value): void
        {
            $this->headers[$key] = $value;
        }
    }
}

if (!class_exists('WP_REST_Request')) {
    class WP_REST_Request
    {
        private array $params = [];

        private array $headers = [];

        private string $method = 'GET';

        public function get_method(): string
        {
            return $this->method;
        }

        public function set_method(string $method): void
        {
            $this->method = $method;
        }

        public function get_param(string $key): mixed
        {
            return $this->params[$key] ?? null;
        }

        public function set_param(string $key, mixed $value): void
        {
            $this->params[$key] = $value;
        }

        public function get_header(string $key): ?string
        {
            $normalized = strtolower(str_replace('_', '-', $key));

            return $this->headers[$normalized] ?? null;
        }

        public function set_header(string $key, string $value): void
        {
            $normalized = strtolower(str_replace('_', '-', $key));
            $this->headers[$normalized] = $value;
        }
    }
}

if (!class_exists('WP_Error')) {
    class WP_Error
    {
        public function __construct(private readonly string $code = '', private readonly string $message = '') {}

        public function get_error_code(): string
        {
            return $this->code;
        }

        public function get_error_message(): string
        {
            return $this->message;
        }
    }
}

if (!class_exists('WP_Post')) {
    class WP_Post
    {
        public int $ID = 0;

        public string $post_title = '';

        public string $post_type = 'post';

        public string $post_status = 'publish';

        public int $post_author = 0;

        public int $post_parent = 0;
    }
}

if (!class_exists('WP_Query')) {
    class WP_Query
    {
        public array $posts = [];

        public int $found_posts = 0;

        public int $max_num_pages = 0;

        public function __construct() {}
    }
}

// ─── wpdb stub ────────────────────────────────────────────────────────────────

if (!class_exists('wpdb')) {
    class wpdb
    {
        public string $prefix = 'wp_';

        public int $insert_id = 0;

        public string $last_error = '';

        /** @var null|array<int, array<string, mixed>> Mock results for get_results() */
        public ?array $mock_results = null;

        /** @var null|array<string, mixed> Mock row for get_row() */
        public ?array $mock_row = null;

        /** @var null|string Mock value for get_var() */
        public ?string $mock_var = null;

        /** @var array<int, array{method: string, args: array<int, mixed>}> Recorded calls */
        public array $calls = [];

        private string $charset_collate = 'DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_520_ci';

        public function get_charset_collate(): string
        {
            return $this->charset_collate;
        }

        public function esc_like(string $text): string
        {
            return addcslashes($text, '_%\\');
        }

        public function prepare(string $query, mixed ...$args): string
        {
            $this->calls[] = ['method' => 'prepare', 'args' => func_get_args()];

            // Simple sprintf-style substitution for testing
            $query = str_replace(['%d', '%s', '%f'], ['%s', '%s', '%s'], $query);

            if ($args !== []) {
                // Flatten nested arrays
                $flat = [];
                foreach ($args as $arg) {
                    if (is_array($arg)) {
                        $flat = array_merge($flat, $arg);
                    } else {
                        $flat[] = $arg;
                    }
                }

                return @vsprintf($query, array_map(fn ($v): string => is_null($v) ? 'NULL' : (string) $v, $flat)) ?: $query;
            }

            return $query;
        }

        public function insert(string $table, array $data, mixed $format = null): false|int
        {
            $this->calls[] = ['method' => 'insert', 'args' => [$table, $data, $format]];

            return 1;
        }

        public function update(string $table, array $data, array $where, mixed $format = null, mixed $where_format = null): false|int
        {
            $this->calls[] = ['method' => 'update', 'args' => [$table, $data, $where, $format, $where_format]];

            return 1;
        }

        public function delete(string $table, array $where, mixed $where_format = null): false|int
        {
            $this->calls[] = ['method' => 'delete', 'args' => [$table, $where, $where_format]];

            return 1;
        }

        public function get_row(string $query, string $output = 'OBJECT', int $y = 0): mixed
        {
            $this->calls[] = ['method' => 'get_row', 'args' => [$query, $output, $y]];

            return $this->mock_row;
        }

        public function get_results(string $query, string $output = 'OBJECT'): ?array
        {
            $this->calls[] = ['method' => 'get_results', 'args' => [$query, $output]];

            return $this->mock_results;
        }

        public function get_var(string $query, int $x = 0, int $y = 0): ?string
        {
            $this->calls[] = ['method' => 'get_var', 'args' => [$query, $x, $y]];

            return $this->mock_var;
        }

        public function query(string $query): bool|int
        {
            $this->calls[] = ['method' => 'query', 'args' => [$query]];

            return 1;
        }
    }
}

// ─── WordPress function stubs ─────────────────────────────────────────────────

if (!defined('ARRAY_A')) {
    define('ARRAY_A', 'ARRAY_A');
}

if (!defined('ABSPATH')) {
    define('ABSPATH', '/tmp/wordpress/');
}

if (!defined('DAY_IN_SECONDS')) {
    define('DAY_IN_SECONDS', 86400);
}

// Stubbed get_current_user_id() — returns value from global $__wp_test_user_id.
if (!function_exists('get_current_user_id')) {
    function get_current_user_id(): int
    {
        return $GLOBALS['__wp_test_user_id'] ?? 0;
    }
}

// Stubbed get_option() — reads from global $__wp_test_options.
if (!function_exists('get_option')) {
    function get_option(string $option, mixed $default = false): mixed
    {
        return $GLOBALS['__wp_test_options'][$option] ?? $default;
    }
}

// Stubbed get_transient() — reads from global $__wp_test_transients.
if (!function_exists('get_transient')) {
    function get_transient(string $transient): mixed
    {
        return $GLOBALS['__wp_test_transients'][$transient] ?? false;
    }
}

// Stubbed set_transient() — writes to global $__wp_test_transients.
if (!function_exists('set_transient')) {
    function set_transient(string $transient, mixed $value, int $expiration = 0): bool
    {
        $GLOBALS['__wp_test_transients'][$transient] = $value;

        return true;
    }
}

// Stubbed wp_schedule_single_event() — records calls in global $__wp_test_scheduled_events.
if (!function_exists('wp_schedule_single_event')) {
    function wp_schedule_single_event(int $timestamp, string $hook, array $args = []): bool
    {
        $GLOBALS['__wp_test_scheduled_events'][] = [
            'timestamp' => $timestamp,
            'hook' => $hook,
            'args' => $args,
        ];

        return true;
    }
}

// Stubbed wp_enqueue_script() — records calls in global $__wp_test_enqueued_scripts.
if (!function_exists('wp_enqueue_script')) {
    function wp_enqueue_script(string $handle, string $src = '', array $deps = [], bool|string|null $ver = false, array|bool $args = []): void
    {
        $GLOBALS['__wp_test_enqueued_scripts'][$handle] = [
            'src' => $src,
            'deps' => $deps,
            'ver' => $ver,
            'args' => $args,
        ];
    }
}

// Stubbed wp_enqueue_style() — records calls in global $__wp_test_enqueued_styles.
if (!function_exists('wp_enqueue_style')) {
    function wp_enqueue_style(string $handle, string $src = '', array $deps = [], bool|string|null $ver = false, string $media = 'all'): void
    {
        $GLOBALS['__wp_test_enqueued_styles'][$handle] = [
            'src' => $src,
            'deps' => $deps,
            'ver' => $ver,
            'media' => $media,
        ];
    }
}

// Stubbed is_wp_error() — checks if value is a WP_Error instance.
if (!function_exists('is_wp_error')) {
    function is_wp_error(mixed $thing): bool
    {
        return $thing instanceof WP_Error;
    }
}

/*
 * Stubbed apply_filters() — looks up $GLOBALS['__middag_test_wp_filters'][$tag];
 * if a callable, invokes it with ($value, ...$args); otherwise returns the value
 * directly. When no entry exists, returns $value unchanged.
 */
if (!function_exists('apply_filters')) {
    function apply_filters(string $tag, mixed $value, mixed ...$args): mixed
    {
        $registry = $GLOBALS['__middag_test_wp_filters'] ?? [];

        if (!array_key_exists($tag, $registry)) {
            return $value;
        }

        $callback = $registry[$tag];

        return is_callable($callback) ? $callback($value, ...$args) : $callback;
    }
}

// Stubbed get_stylesheet() — returns the value of $GLOBALS['__middag_test_wp_stylesheet'].
if (!function_exists('get_stylesheet')) {
    function get_stylesheet(): string
    {
        return (string) ($GLOBALS['__middag_test_wp_stylesheet'] ?? '');
    }
}

// Stubbed get_stylesheet_directory() — returns $GLOBALS['__middag_test_wp_stylesheet_directory'].
if (!function_exists('get_stylesheet_directory')) {
    function get_stylesheet_directory(): string
    {
        return (string) ($GLOBALS['__middag_test_wp_stylesheet_directory'] ?? '/var/www/themes/active');
    }
}

// ─── Support-layer stubs (WP-01A) ───────────────────────────────────────────────

if (!class_exists('WP_User')) {
    class WP_User
    {
        /** @var array<int, string> */
        public array $roles = [];

        public function __construct(public int $ID = 0) {}
    }
}

// Stubbed add_action() — records calls in global $__wp_test_actions.
if (!function_exists('add_action')) {
    function add_action(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        $GLOBALS['__wp_test_actions'][$hook][] = [
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
        ];

        return true;
    }
}

// Stubbed remove_action() — drops the matching callback/priority pair from
// global $__wp_test_actions; returns whether a callback was removed (mirrors
// real WP semantics).
if (!function_exists('remove_action')) {
    function remove_action(string $hook, callable $callback, int $priority = 10): bool
    {
        foreach ($GLOBALS['__wp_test_actions'][$hook] ?? [] as $index => $registered) {
            if ($registered['callback'] === $callback && $registered['priority'] === $priority) {
                unset($GLOBALS['__wp_test_actions'][$hook][$index]);

                return true;
            }
        }

        return false;
    }
}

// Stubbed add_filter() — records calls in global $__wp_test_filters.
if (!function_exists('add_filter')) {
    function add_filter(string $hook, callable $callback, int $priority = 10, int $accepted_args = 1): bool
    {
        $GLOBALS['__wp_test_filters'][$hook][] = [
            'callback' => $callback,
            'priority' => $priority,
            'accepted_args' => $accepted_args,
        ];

        return true;
    }
}

// Stubbed wp_mail() — records calls in global $__wp_test_mail; result is
// configurable via $__wp_test_mail_result (defaults to true).
if (!function_exists('wp_mail')) {
    function wp_mail(array|string $to, string $subject, string $message, array|string $headers = '', array $attachments = []): bool
    {
        $GLOBALS['__wp_test_mail'][] = [
            'to' => $to,
            'subject' => $subject,
            'message' => $message,
            'headers' => $headers,
            'attachments' => $attachments,
        ];

        return (bool) ($GLOBALS['__wp_test_mail_result'] ?? true);
    }
}

// Stubbed wp_next_scheduled() — reads from global $__wp_test_next_scheduled.
if (!function_exists('wp_next_scheduled')) {
    function wp_next_scheduled(string $hook, array $args = []): false|int
    {
        return $GLOBALS['__wp_test_next_scheduled'][$hook] ?? false;
    }
}

// Stubbed wp_schedule_event() — records calls in global $__wp_test_recurring_events.
if (!function_exists('wp_schedule_event')) {
    function wp_schedule_event(int $timestamp, string $recurrence, string $hook, array $args = []): bool
    {
        $GLOBALS['__wp_test_recurring_events'][] = [
            'timestamp' => $timestamp,
            'recurrence' => $recurrence,
            'hook' => $hook,
            'args' => $args,
        ];

        return true;
    }
}

// Stubbed wp_unschedule_event() — records calls in global $__wp_test_unscheduled_events.
if (!function_exists('wp_unschedule_event')) {
    function wp_unschedule_event(int $timestamp, string $hook, array $args = []): bool
    {
        $GLOBALS['__wp_test_unscheduled_events'][] = [
            'timestamp' => $timestamp,
            'hook' => $hook,
            'args' => $args,
        ];

        return true;
    }
}

// Stubbed current_user_can() — reads capability map from global $__wp_test_caps.
if (!function_exists('current_user_can')) {
    function current_user_can(string $capability, mixed ...$args): bool
    {
        return (bool) ($GLOBALS['__wp_test_caps'][$capability] ?? false);
    }
}

// Stubbed wp_get_current_user() — returns global $__wp_test_current_user or an
// anonymous WP_User (ID 0).
if (!function_exists('wp_get_current_user')) {
    function wp_get_current_user(): WP_User
    {
        return $GLOBALS['__wp_test_current_user'] ?? new WP_User();
    }
}

// Stubbed wp_create_nonce() — returns a per-action token from
// $GLOBALS['__wp_test_nonces'] when present, else a deterministic default.
// Pairs with wp_verify_nonce() below so a created nonce verifies for its action.
if (!function_exists('wp_create_nonce')) {
    function wp_create_nonce(string $action = '-1'): string
    {
        return $GLOBALS['__wp_test_nonces'][$action] ?? ('nonce-' . $action);
    }
}

// Stubbed wp_verify_nonce() — returns 1 when $nonce matches the action's token
// (per wp_create_nonce() above), false otherwise. Real WP returns 1|2|false.
if (!function_exists('wp_verify_nonce')) {
    function wp_verify_nonce(string $nonce, string $action = '-1'): false|int
    {
        $expected = $GLOBALS['__wp_test_nonces'][$action] ?? ('nonce-' . $action);

        return hash_equals($expected, $nonce) ? 1 : false;
    }
}

// ─── Plugin lifecycle stubs (WP-06) ──────────────────────────────────────────

// Stubbed register_activation_hook() — records callbacks per plugin file in
// global $__wp_test_activation_hooks (keyed by file, mirroring add_action).
if (!function_exists('register_activation_hook')) {
    function register_activation_hook(string $file, callable $callback): void
    {
        $GLOBALS['__wp_test_activation_hooks'][$file][] = $callback;
    }
}

// Stubbed register_deactivation_hook() — records callbacks per plugin file in
// global $__wp_test_deactivation_hooks (keyed by file, mirroring add_action).
if (!function_exists('register_deactivation_hook')) {
    function register_deactivation_hook(string $file, callable $callback): void
    {
        $GLOBALS['__wp_test_deactivation_hooks'][$file][] = $callback;
    }
}

// ─── Roles/caps write-side stubs (WP-07) ─────────────────────────────────────────

// Stubbed WP_Role — capability mutations write to the public $capabilities map so
// tests can assert grants/revokes directly. Mirrors the real WP_Role surface used
// by CapabilitySupport (add_cap/remove_cap + $name/$capabilities).
if (!class_exists('WP_Role', false)) {
    class WP_Role
    {
        /** @var array<string, bool> capability name => granted */
        public array $capabilities = [];

        /**
         * @param array<int, string>|array<string, bool> $capabilities
         */
        public function __construct(public string $name, array $capabilities = [])
        {
            foreach ($capabilities as $key => $value) {
                if (is_int($key)) {
                    $this->capabilities[(string) $value] = true;
                } else {
                    $this->capabilities[$key] = (bool) $value;
                }
            }
        }

        public function add_cap(string $cap, bool $grant = true): void
        {
            $this->capabilities[$cap] = $grant;
        }

        public function remove_cap(string $cap): void
        {
            unset($this->capabilities[$cap]);
        }

        public function has_cap(string $cap): bool
        {
            return !empty($this->capabilities[$cap]);
        }
    }
}

// Stubbed get_role() — resolves from the global $__wp_test_roles registry.
if (!function_exists('get_role')) {
    function get_role(string $role): ?WP_Role
    {
        return $GLOBALS['__wp_test_roles'][$role] ?? null;
    }
}

// Stubbed add_role() — registers a new role in $__wp_test_roles; returns the
// WP_Role when added or null when the role already exists (mirrors real WP).
if (!function_exists('add_role')) {
    function add_role(string $role, string $display_name, array $capabilities = []): ?WP_Role
    {
        if (isset($GLOBALS['__wp_test_roles'][$role])) {
            return null;
        }

        $object = new WP_Role($role, $capabilities);
        $GLOBALS['__wp_test_roles'][$role] = $object;

        return $object;
    }
}

// Stubbed remove_role() — drops the role from $__wp_test_roles.
if (!function_exists('remove_role')) {
    function remove_role(string $role): void
    {
        unset($GLOBALS['__wp_test_roles'][$role]);
    }
}

// ─── Sanitize/escape stubs (WP-04) ───────────────────────────────────────────

// Stubbed sanitize_text_field() — strips tags and collapses all whitespace
// (incl. newlines) to single spaces, then trims. Mirrors WP behavior closely
// enough for delegation assertions.
if (!function_exists('sanitize_text_field')) {
    function sanitize_text_field(string $str): string
    {
        $stripped = strip_tags($str);
        $collapsed = preg_replace('/[\r\n\t ]+/', ' ', $stripped) ?? $stripped;

        return trim($collapsed);
    }
}

// Stubbed sanitize_textarea_field() — strips tags, collapses spaces/tabs but
// preserves newlines, then trims.
if (!function_exists('sanitize_textarea_field')) {
    function sanitize_textarea_field(string $str): string
    {
        $stripped = strip_tags($str);
        $collapsed = preg_replace('/[ \t]+/', ' ', $stripped) ?? $stripped;

        return trim($collapsed);
    }
}

// Stubbed sanitize_key() — lowercases and keeps only [a-z0-9_-].
if (!function_exists('sanitize_key')) {
    function sanitize_key(string $key): string
    {
        return (string) preg_replace('/[^a-z0-9_\-]/', '', strtolower($key));
    }
}

// Stubbed sanitize_email() — keeps a basic local@domain shape, drops anything
// else; returns '' when no valid email survives.
if (!function_exists('sanitize_email')) {
    function sanitize_email(string $email): string
    {
        $clean = (string) preg_replace('/[^a-zA-Z0-9.@_+\-]/', '', $email);

        return filter_var($clean, FILTER_VALIDATE_EMAIL) === false ? '' : $clean;
    }
}

// Stubbed wp_kses_post() — drops all tags except a tiny allowlist (<b>,<strong>,
// <em>,<a>,<p>) so tests can prove disallowed markup (e.g. <script>) is removed
// while allowed markup survives.
if (!function_exists('wp_kses_post')) {
    function wp_kses_post(string $data): string
    {
        return strip_tags($data, '<b><strong><em><a><p>');
    }
}

// Stubbed wp_kses() — strips to the caller-provided allowlist. Accepts the WP
// array-of-elements shape OR a context-name string; ignores attributes
// (sufficient for delegation/coverage assertions).
if (!function_exists('wp_kses')) {
    function wp_kses(string $content, array|string $allowed_html, array $allowed_protocols = []): string
    {
        if (is_string($allowed_html)) {
            // Context name (e.g. 'post'): approximate with the post allowlist.
            return strip_tags($content, '<b><strong><em><a><p>');
        }

        $tags = '';
        foreach (array_keys($allowed_html) as $tag) {
            $tags .= '<' . $tag . '>';
        }

        return strip_tags($content, $tags);
    }
}

// Stubbed esc_html() — HTML-escapes text (quotes included).
if (!function_exists('esc_html')) {
    function esc_html(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// Stubbed esc_attr() — HTML-attribute-escapes text (quotes included).
if (!function_exists('esc_attr')) {
    function esc_attr(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}

// Stubbed esc_url() — drops disallowed schemes (anything outside the allowlist),
// otherwise returns the URL. Default allowlist: http/https/mailto.
if (!function_exists('esc_url')) {
    function esc_url(string $url, ?array $protocols = null, string $_context = 'display'): string
    {
        $allowed = $protocols ?? ['http', 'https', 'mailto'];
        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (is_string($scheme) && !in_array(strtolower($scheme), $allowed, true)) {
            return '';
        }

        return $url;
    }
}

// Stubbed wp_json_encode() — thin json_encode() so render-boundary output can
// be asserted without the WordPress runtime.
if (!function_exists('wp_json_encode')) {
    function wp_json_encode(mixed $value, int $flags = 0, int $depth = 512): false|string
    {
        return json_encode($value, $flags, $depth);
    }
}

// ─── Settings API stubs (WP-05) ───────────────────────────────────────────────

// Stubbed register_setting() — records calls in global $__wp_test_settings.
if (!function_exists('register_setting')) {
    function register_setting(string $option_group, string $option_name, array $args = []): void
    {
        $GLOBALS['__wp_test_settings'][$option_name] = [
            'group' => $option_group,
            'args' => $args,
        ];
    }
}

// Stubbed add_settings_section() — records calls in global $__wp_test_settings_sections.
if (!function_exists('add_settings_section')) {
    function add_settings_section(string $id, string $title, callable $callback, string $page, array $args = []): void
    {
        $GLOBALS['__wp_test_settings_sections'][$id] = [
            'title' => $title,
            'callback' => $callback,
            'page' => $page,
            'args' => $args,
        ];
    }
}

// Stubbed add_settings_field() — records calls in global $__wp_test_settings_fields.
if (!function_exists('add_settings_field')) {
    function add_settings_field(string $id, string $title, callable $callback, string $page, string $section = 'default', array $args = []): void
    {
        $GLOBALS['__wp_test_settings_fields'][$id] = [
            'title' => $title,
            'callback' => $callback,
            'page' => $page,
            'section' => $section,
            'args' => $args,
        ];
    }
}

// ─── Privacy seam stubs (WP-09) ─────────────────────────────────────────────

// Stubbed wp_add_privacy_policy_content() — records calls in global
// $__wp_test_privacy_policy_content.
if (!function_exists('wp_add_privacy_policy_content')) {
    function wp_add_privacy_policy_content(string $plugin_name, string $policy_text): void
    {
        $GLOBALS['__wp_test_privacy_policy_content'][] = [
            'plugin_name' => $plugin_name,
            'policy_text' => $policy_text,
        ];
    }
}

// Stubbed register_post_type() — records calls in global $__wp_test_post_types.
if (!function_exists('register_post_type')) {
    function register_post_type(string $post_type, array $args = []): void
    {
        $GLOBALS['__wp_test_post_types'][$post_type] = $args;
    }
}

// Stubbed register_taxonomy() — records calls in global $__wp_test_taxonomies.
if (!function_exists('register_taxonomy')) {
    function register_taxonomy(string $taxonomy, array|string $object_type, array $args = []): void
    {
        $GLOBALS['__wp_test_taxonomies'][$taxonomy] = [
            'object_type' => $object_type,
            'args' => $args,
        ];
    }
}

// Stubbed delete_transient() — removes from global $__wp_test_transients.
if (!function_exists('delete_transient')) {
    function delete_transient(string $transient): bool
    {
        $existed = isset($GLOBALS['__wp_test_transients'][$transient]);
        unset($GLOBALS['__wp_test_transients'][$transient]);

        return $existed;
    }
}

// Stubbed Object Cache API — backed by global $__wp_test_object_cache.
if (!function_exists('wp_cache_get')) {
    function wp_cache_get(string $key, string $group = '', bool $force = false, ?bool &$found = null): mixed
    {
        $found = isset($GLOBALS['__wp_test_object_cache'][$group][$key]);

        return $GLOBALS['__wp_test_object_cache'][$group][$key] ?? false;
    }
}
if (!function_exists('wp_cache_set')) {
    function wp_cache_set(string $key, mixed $data, string $group = '', int $expire = 0): bool
    {
        $GLOBALS['__wp_test_object_cache'][$group][$key] = $data;

        return true;
    }
}
if (!function_exists('wp_cache_delete')) {
    function wp_cache_delete(string $key, string $group = ''): bool
    {
        $existed = isset($GLOBALS['__wp_test_object_cache'][$group][$key]);
        unset($GLOBALS['__wp_test_object_cache'][$group][$key]);

        return $existed;
    }
}
if (!function_exists('wp_cache_flush')) {
    function wp_cache_flush(): bool
    {
        $GLOBALS['__wp_test_object_cache'] = [];

        return true;
    }
}

// Stubbed Shortcode API — backed by global $__wp_test_shortcodes.
if (!function_exists('add_shortcode')) {
    function add_shortcode(string $tag, callable $callback): void
    {
        $GLOBALS['__wp_test_shortcodes'][$tag] = $callback;
    }
}
if (!function_exists('remove_shortcode')) {
    function remove_shortcode(string $tag): void
    {
        unset($GLOBALS['__wp_test_shortcodes'][$tag]);
    }
}
if (!function_exists('do_shortcode')) {
    function do_shortcode(string $content): string
    {
        return $content;
    }
}

// Stubbed HTTP API — records requests in $__wp_test_http_requests; response
// configurable via $__wp_test_http_response (array or WP_Error).
if (!function_exists('wp_remote_request')) {
    function wp_remote_request(string $url, array $args = []): mixed
    {
        $GLOBALS['__wp_test_http_requests'][] = ['url' => $url, 'args' => $args];

        // Simulate WP_Http's cURL transport firing `http_api_curl`: when a
        // handle is configured via $__wp_test_http_curl_handle, dispatch it to
        // every callback registered on the action (mTLS one-shot hook path).
        if (isset($GLOBALS['__wp_test_http_curl_handle'])) {
            foreach ($GLOBALS['__wp_test_actions']['http_api_curl'] ?? [] as $registered) {
                ($registered['callback'])($GLOBALS['__wp_test_http_curl_handle']);
            }
        }

        return $GLOBALS['__wp_test_http_response'] ?? [
            'response' => ['code' => 200, 'message' => 'OK'],
            'headers' => [],
            'body' => '',
        ];
    }
}
if (!function_exists('wp_remote_retrieve_response_code')) {
    function wp_remote_retrieve_response_code(mixed $response): int|string
    {
        return is_array($response) ? ($response['response']['code'] ?? '') : '';
    }
}
if (!function_exists('wp_remote_retrieve_body')) {
    function wp_remote_retrieve_body(mixed $response): string
    {
        return is_array($response) ? (string) ($response['body'] ?? '') : '';
    }
}
if (!function_exists('wp_remote_retrieve_headers')) {
    function wp_remote_retrieve_headers(mixed $response): array
    {
        return is_array($response) ? (array) ($response['headers'] ?? []) : [];
    }
}

// Stubbed generic Metadata API — backed by $__wp_test_metadata[type][id][key].
if (!function_exists('get_metadata')) {
    function get_metadata(string $meta_type, int $object_id, string $meta_key = '', bool $single = false): mixed
    {
        $value = $GLOBALS['__wp_test_metadata'][$meta_type][$object_id][$meta_key] ?? null;
        if ($value === null) {
            return $single ? '' : [];
        }

        return $single ? $value : [$value];
    }
}
if (!function_exists('update_metadata')) {
    function update_metadata(string $meta_type, int $object_id, string $meta_key, mixed $meta_value): bool
    {
        $GLOBALS['__wp_test_metadata'][$meta_type][$object_id][$meta_key] = $meta_value;

        return true;
    }
}
if (!function_exists('delete_metadata')) {
    function delete_metadata(string $meta_type, int $object_id, string $meta_key, mixed $meta_value = '', bool $delete_all = false): bool
    {
        $existed = isset($GLOBALS['__wp_test_metadata'][$meta_type][$object_id][$meta_key]);
        unset($GLOBALS['__wp_test_metadata'][$meta_type][$object_id][$meta_key]);

        return $existed;
    }
}

// Stubbed admin menu registration — records in $__wp_test_admin_menus.
if (!function_exists('add_menu_page')) {
    function add_menu_page(string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback, string $icon_url = '', ?int $position = null): string
    {
        $GLOBALS['__wp_test_admin_menus'][$menu_slug] = ['page_title' => $page_title, 'menu_title' => $menu_title, 'capability' => $capability, 'icon_url' => $icon_url, 'position' => $position];

        return 'toplevel_page_' . $menu_slug;
    }
}
if (!function_exists('add_submenu_page')) {
    function add_submenu_page(string $parent_slug, string $page_title, string $menu_title, string $capability, string $menu_slug, callable $callback): string
    {
        $GLOBALS['__wp_test_admin_submenus'][$menu_slug] = ['parent_slug' => $parent_slug, 'page_title' => $page_title, 'menu_title' => $menu_title, 'capability' => $capability];

        return $parent_slug . '_page_' . $menu_slug;
    }
}

// Stubbed wp_upload_dir() — configurable via $__wp_test_upload_dir.
if (!function_exists('wp_upload_dir')) {
    function wp_upload_dir(): array
    {
        return $GLOBALS['__wp_test_upload_dir'] ?? [
            'basedir' => sys_get_temp_dir() . '/middag-test-uploads',
            'baseurl' => 'http://example.test/wp-content/uploads',
        ];
    }
}

// ─── Gettext stubs (mirror-test batch B) ─────────────────────────────────────

// Stubbed __() — looks up $__wp_test_translations[domain][text]; falls back to
// the original text (real WP behaviour for untranslated strings).
if (!function_exists('__')) {
    function __(string $text, string $domain = 'default'): string
    {
        return $GLOBALS['__wp_test_translations'][$domain][$text] ?? $text;
    }
}

// Stubbed _n() — plural selection: a translation entry keyed "single|plural"
// may provide [singular, plural]; otherwise falls back to __() per form.
if (!function_exists('_n')) {
    function _n(string $single, string $plural, int $number, string $domain = 'default'): string
    {
        $entry = $GLOBALS['__wp_test_translations'][$domain][$single . '|' . $plural] ?? null;

        if (is_array($entry)) {
            return $number === 1 ? $entry[0] : $entry[1];
        }

        return $number === 1 ? __($single, $domain) : __($plural, $domain);
    }
}

// ─── Users API stubs (mirror-test batch B) ───────────────────────────────────

// Stubbed get_user_by() — resolves from $__wp_test_users_by[field][value]
// (field lowercased so 'id'/'ID' both work, as in real WP).
if (!function_exists('get_user_by')) {
    function get_user_by(string $field, int|string $value): false|WP_User
    {
        $user = $GLOBALS['__wp_test_users_by'][strtolower($field)][(string) $value] ?? null;

        return $user instanceof WP_User ? $user : false;
    }
}

// Stubbed WP_User_Query — records query vars in $__wp_test_user_queries;
// results/total configurable via $__wp_test_user_query_results / _total.
if (!class_exists('WP_User_Query')) {
    class WP_User_Query
    {
        public function __construct(public array $query_vars = [])
        {
            $GLOBALS['__wp_test_user_queries'][] = $query_vars;
        }

        public function get_results(): array
        {
            return $GLOBALS['__wp_test_user_query_results'] ?? [];
        }

        public function get_total(): int
        {
            return (int) ($GLOBALS['__wp_test_user_query_total'] ?? count($this->get_results()));
        }
    }
}

// Stubbed wp_insert_user() — records in $__wp_test_inserted_users; result
// configurable via $__wp_test_insert_user_result (defaults to a fresh ID).
if (!function_exists('wp_insert_user')) {
    function wp_insert_user(array $userdata): int|WP_Error
    {
        $GLOBALS['__wp_test_inserted_users'][] = $userdata;

        return $GLOBALS['__wp_test_insert_user_result'] ?? count($GLOBALS['__wp_test_inserted_users']);
    }
}

// Stubbed wp_update_user() — records in $__wp_test_updated_users; result
// configurable via $__wp_test_update_user_result (defaults to the given ID).
if (!function_exists('wp_update_user')) {
    function wp_update_user(array $userdata): int|WP_Error
    {
        $GLOBALS['__wp_test_updated_users'][] = $userdata;

        return $GLOBALS['__wp_test_update_user_result'] ?? (int) ($userdata['ID'] ?? 0);
    }
}

// ─── User-meta stubs (mirror-test batch B) ───────────────────────────────────
// Backed by the same $__wp_test_metadata['user'] map as the generic Metadata
// API stubs above, so both seams observe one state.

if (!function_exists('get_user_meta')) {
    function get_user_meta(int $user_id, string $key = '', bool $single = false): mixed
    {
        if ($key === '') {
            $all = $GLOBALS['__wp_test_metadata']['user'][$user_id] ?? [];

            return array_map(static fn ($value): array => [$value], $all);
        }

        return get_metadata('user', $user_id, $key, $single);
    }
}
if (!function_exists('update_user_meta')) {
    function update_user_meta(int $user_id, string $meta_key, mixed $meta_value): bool
    {
        return update_metadata('user', $user_id, $meta_key, $meta_value);
    }
}
if (!function_exists('delete_user_meta')) {
    function delete_user_meta(int $user_id, string $meta_key): bool
    {
        return delete_metadata('user', $user_id, $meta_key);
    }
}
if (!function_exists('metadata_exists')) {
    function metadata_exists(string $meta_type, int $object_id, string $meta_key): bool
    {
        return isset($GLOBALS['__wp_test_metadata'][$meta_type][$object_id][$meta_key]);
    }
}
if (!function_exists('maybe_unserialize')) {
    function maybe_unserialize(mixed $data): mixed
    {
        if (is_string($data)) {
            $trimmed = trim($data);
            if ($trimmed === 'b:0;') {
                return false;
            }
            $unserialized = @unserialize($trimmed);
            if ($unserialized !== false) {
                return $unserialized;
            }
        }

        return $data;
    }
}
