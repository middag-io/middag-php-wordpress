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

use Middag\WordPress\Diagnostics\Contract\DiagnosticCheckInterface;
use Middag\WordPress\Runtime\Kernel;
use ReflectionClass;
use Throwable;

/**
 * Runs the built-in and the declared checks of one {@see PackageProbe}.
 *
 * Every check is isolated: an exception or error becomes a failed result for
 * that check and the next one still runs, so one broken package never hides
 * the state of the others.
 *
 * @internal
 */
final readonly class PackageInspector
{
    public function inspect(PackageProbe $probe): PackageReport
    {
        $results = [
            $this->guard('autoload', fn (): CheckResult => $this->autoload($probe)),
            $this->guard('kernel', fn (): CheckResult => $this->kernel($probe)),
            $this->version($probe),
        ];

        foreach ($probe->checks as $check) {
            $results[] = $this->runDeclared($check);
        }

        return new PackageReport($probe->name, $probe->version, $results);
    }

    private function autoload(PackageProbe $probe): CheckResult
    {
        $class = $probe->autoloadClass();

        if ($class === null) {
            return CheckResult::warn('autoload', 'no entry class declared; autoloader not verified');
        }

        if (!class_exists($class) && !interface_exists($class)) {
            return CheckResult::fail('autoload', sprintf('%s does not resolve through the autoloader', $class));
        }

        $file = (new ReflectionClass($class))->getFileName();

        return CheckResult::pass('autoload', sprintf('%s resolved from %s', $class, $file === false ? '(internal)' : $file));
    }

    private function kernel(PackageProbe $probe): CheckResult
    {
        $class = $probe->kernelClass;

        if ($class === null) {
            return CheckResult::pass('kernel', 'no kernel declared');
        }

        if (!class_exists($class)) {
            return CheckResult::fail('kernel', sprintf('%s does not resolve through the autoloader', $class));
        }

        if (!is_subclass_of($class, Kernel::class)) {
            return CheckResult::fail('kernel', sprintf('%s does not extend %s', $class, Kernel::class));
        }

        return $class::isBooted()
            ? CheckResult::pass('kernel', sprintf('%s booted', $class))
            : CheckResult::fail('kernel', sprintf('%s is not booted', $class));
    }

    private function version(PackageProbe $probe): CheckResult
    {
        return trim($probe->version) === ''
            ? CheckResult::warn('version', 'no version declared')
            : CheckResult::pass('version', $probe->version);
    }

    private function runDeclared(DiagnosticCheckInterface $check): CheckResult
    {
        try {
            return $check->run();
        } catch (Throwable $throwable) {
            return CheckResult::fail($this->safeName($check), $this->describe($throwable), $check->scope());
        }
    }

    /**
     * @param callable(): CheckResult $check
     */
    private function guard(string $name, callable $check): CheckResult
    {
        try {
            return $check();
        } catch (Throwable $throwable) {
            return CheckResult::fail($name, $this->describe($throwable));
        }
    }

    private function safeName(DiagnosticCheckInterface $check): string
    {
        try {
            return $check->name();
        } catch (Throwable) {
            return $check::class;
        }
    }

    private function describe(Throwable $throwable): string
    {
        return sprintf('%s: %s', $throwable::class, $throwable->getMessage());
    }
}
