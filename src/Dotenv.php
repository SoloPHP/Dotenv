<?php

declare(strict_types=1);

namespace Solo\Dotenv;

use InvalidArgumentException;
use RuntimeException;

final class Dotenv
{
    private const array DEFAULT_FILES = ['.env', '.env.local'];

    /**
     * Read optional files in order. Later assignments win.
     *
     * @param list<string> $filenames
     * @return array<string, string>
     */
    public static function read(string $baseDir, array $filenames = self::DEFAULT_FILES): array
    {
        $prefix = rtrim($baseDir, '/') . '/';
        // Looking up "." inside a directory also checks permission to traverse it.
        if ($baseDir === '' || !is_dir($prefix . '.')) {
            throw new RuntimeException("Cannot access dotenv directory: $baseDir");
        }

        $values = [];
        foreach ($filenames as $filename) {
            if ($filename === '' || str_contains($filename, "\0")) {
                throw new InvalidArgumentException('Expected a non-empty dotenv filename without null bytes.');
            }

            $path = $prefix . $filename;
            if (!file_exists($path) && !is_link($path) && is_dir(dirname($path) . '/.')) {
                continue;
            }

            $contents = is_file($path) ? @file_get_contents($path) : false;
            if ($contents === false) {
                throw new RuntimeException("Cannot read dotenv file: $path");
            }

            $values = array_replace($values, EnvParser::parse($contents, $path));
        }

        return $values;
    }

    /**
     * Apply the merged files after every file has been read successfully.
     *
     * @param list<string> $filenames
     */
    public static function load(
        string $baseDir,
        array $filenames = self::DEFAULT_FILES,
        bool $overwrite = false,
        bool $populateServer = false
    ): void {
        foreach (self::read($baseDir, $filenames) as $key => $value) {
            $header = self::isRequestHeader($key);
            if (!$overwrite && (array_key_exists($key, $_ENV) || (!$header && array_key_exists($key, $_SERVER)))) {
                continue;
            }

            $_ENV[$key] = $value;
            if ($populateServer && !$header) {
                $_SERVER[$key] = $value;
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? (self::isRequestHeader($key) ? null : $_SERVER[$key] ?? null);
        return $value === null || $value === '' ? $default : $value;
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::get($key);
        return is_scalar($value) ? (string) $value : $default;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);
        if (is_int($value)) {
            return $value;
        }

        if (!is_string($value) || preg_match('/\A([+-]?)0*([0-9]+)\z/', trim($value, " \t\r\n"), $parts) !== 1) {
            return $default;
        }

        // Leading zeros are stripped above; FILTER_VALIDATE_INT rejects values outside the platform range.
        $integer = filter_var($parts[1] . $parts[2], FILTER_VALIDATE_INT);
        return $integer === false ? $default : $integer;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        return match (is_string($value) ? strtolower(trim($value)) : $value) {
            true, 1, '1', 'true', 'yes', 'on' => true,
            false, 0, '0', 'false', 'no', 'off' => false,
            default => $default,
        };
    }

    public static function required(string $key): string
    {
        $value = self::string($key);
        if ($value === '') {
            throw new InvalidArgumentException("Expected a non-empty scalar value for environment variable: $key");
        }

        return $value;
    }

    /** Web SAPIs copy client request headers into $_SERVER as HTTP_* entries, so those are not environment. */
    private static function isRequestHeader(string $key): bool
    {
        return str_starts_with($key, 'HTTP_');
    }
}
