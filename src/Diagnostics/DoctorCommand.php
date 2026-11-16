<?php

declare(strict_types=1);

/**
 * middag-io/wordpress — MIDDAG WordPress adapter.
 *
 * @author      Michael Meneses <michael@middag.io>
 * @copyright   2026 MIDDAG (https://middag.io)
 * @license     Apache-2.0
 */

namespace Middag\WordPress\Diagnostics;

use Closure;
use JsonException;
use WP_CLI;

/**
 * WP-CLI `doctor` command: prints the boot report and exits non-zero when
 * something is broken, so health checks and pipelines can gate on it.
 *
 * Exit codes: {@see BootReport::EXIT_HEALTHY} (0) when healthy,
 * {@see BootReport::EXIT_BROKEN} (1) when a check failed, an expected package
 * is missing or, with `--strict`, a check warned, and {@see self::EXIT_USAGE}
 * (2) on invalid arguments.
 *
 * The logic lives in {@see run()}, which returns the exit code and writes
 * through an injected writer; {@see __invoke()} is the thin WP-CLI glue that
 * halts with that code. Register it with {@see DoctorCommandRegistrar}.
 *
 * A health gate should pass the full list of packages it expects with
 * `--expect`. The constructor list belongs to the package that registered the
 * command: if that package fails to load, its list never runs, and a
 * sibling's registration of the same root may answer instead. So a
 * constructor list should name the sibling packages too.
 *
 * @api
 */
final readonly class DoctorCommand
{
    public const int EXIT_USAGE = 2;

    private const array FORMATS = ['text', 'json'];

    private Closure $writer;

    /**
     * @param list<string>               $expected package names that must always be registered;
     *                                             merged with the `--expect` option
     * @param null|Closure(string): void $writer   line sink; defaults to WP-CLI output
     */
    public function __construct(
        private DiagnosticsRegistry $registry,
        private array $expected = [],
        ?Closure $writer = null,
    ) {
        $this->writer = $writer ?? $this->defaultWriter();
    }

    /**
     * Print the boot report of the registered packages and exit non-zero when one is broken.
     *
     * ## OPTIONS
     *
     * [--expect=<names>]
     * : Comma-separated package names that must be registered.
     *
     * [--strict]
     * : Treat warnings as failures.
     *
     * [--format=<format>]
     * : Output format.
     * ---
     * default: text
     * options:
     *   - text
     *   - json
     * ---
     *
     * @param array<int, string>         $args      positional arguments (unused)
     * @param array<string, bool|string> $assocArgs options
     */
    public function __invoke(array $args, array $assocArgs): void
    {
        $code = $this->run($assocArgs);

        if (class_exists(WP_CLI::class)) {
            WP_CLI::halt($code);
        }
    }

    /**
     * Collect, print and grade the report.
     *
     * @param array<string, bool|string> $assocArgs `expect`, `strict` and `format` options
     */
    public function run(array $assocArgs = []): int
    {
        $format = $assocArgs['format'] ?? 'text';

        if (!is_string($format) || !in_array($format, self::FORMATS, true)) {
            $this->write(sprintf('Invalid --format; use one of: %s.', implode(', ', self::FORMATS)));

            return self::EXIT_USAGE;
        }

        $report = $this->registry->report($this->expectedNames($assocArgs['expect'] ?? null));
        $strict = $this->isFlagSet($assocArgs['strict'] ?? false);

        return $this->print($report, $format) ? $report->exitCode($strict) : BootReport::EXIT_BROKEN;
    }

    private function print(BootReport $report, string $format): bool
    {
        $renderer = new BootReportRenderer();

        if ($format === 'text') {
            foreach ($renderer->text($report) as $line) {
                $this->write($line);
            }

            return true;
        }

        try {
            $this->write($renderer->json($report));
        } catch (JsonException $jsonException) {
            $this->write('Could not encode the report as JSON: ' . $jsonException->getMessage());

            return false;
        }

        return true;
    }

    /**
     * @return list<string>
     */
    private function expectedNames(bool|string|null $option): array
    {
        $fromOption = is_string($option) ? explode(',', $option) : [];
        $names = array_map(trim(...), [...$this->expected, ...$fromOption]);

        return array_values(array_unique(array_filter($names, static fn (string $name): bool => $name !== '')));
    }

    private function isFlagSet(bool|string $value): bool
    {
        return $value === true || in_array(strtolower((string) $value), ['1', 'true', 'yes'], true);
    }

    private function write(string $line): void
    {
        ($this->writer)($line);
    }

    /**
     * @return Closure(string): void
     */
    private function defaultWriter(): Closure
    {
        return static function (string $line): void {
            if (class_exists(WP_CLI::class)) {
                WP_CLI::line($line);

                return;
            }

            fwrite(STDOUT, $line . PHP_EOL);
        };
    }
}
