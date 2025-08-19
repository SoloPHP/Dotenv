<?php

declare(strict_types=1);

namespace Solo\Dotenv;

final class Dotenv
{
    /**
     * Load .env files into $_ENV only (no putenv), optionally preserving existing keys.
     * @param string $baseDir Directory containing .env files
     * @param array<int,string> $filenames Files to load in order (first wins unless $overwrite=true)
     */
    public static function load(
        string $baseDir,
        array $filenames = ['.env', '.env.local'],
        bool $overwrite = false,
        bool $populateServer = false
    ): void
    {
        $setInThisCall = [];
        foreach ($filenames as $filename) {
            $path = rtrim($baseDir, '/').'/'.$filename;
            if (!is_file($path) || !is_readable($path)) {
                continue;
            }
            $vars = EnvParser::parse(file_get_contents($path) ?: '');
            foreach ($vars as $key => $value) {
                $alreadyExists = array_key_exists($key, $_ENV);
                $wasSetDuringThisCall = array_key_exists($key, $setInThisCall);
                if (!$overwrite && $alreadyExists && !$wasSetDuringThisCall) {
                    continue;
                }
                $_ENV[$key] = $value;
                $setInThisCall[$key] = true;
                if ($populateServer) {
                    $_SERVER[$key] = $value;
                }
            }
        }
    }

    public static function get(string $key, mixed $default = null): mixed
    {
        $value = $_ENV[$key] ?? $_SERVER[$key] ?? null;
        return $value === null || $value === '' ? $default : $value;
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::get($key);
        return $value === null ? $default : (string)$value;
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            return $default;
        }
        return is_numeric($value) ? (int)$value : $default;
    }

    public static function bool(string $key, bool $default = false): bool
    {
        $value = self::get($key);
        if ($value === null) {
            return $default;
        }
        $normalized = is_string($value) ? strtolower(trim($value)) : $value;
        return match ($normalized) {
            true, 1, '1', 'true', 'yes', 'on' => true,
            false, 0, '0', 'false', 'no', 'off' => false,
            default => $default,
        };
    }

    public static function required(string $key): string
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            throw new \InvalidArgumentException("Missing required env: $key");
        }
        return (string)$value;
    }

    /**
     * Very small .env parser: supports lines like KEY=VALUE, quotes, and leading 'export '.
     * Lines starting with # are comments; empty lines ignored.
     * No variable expansion is performed.
     * @return array<string,string>
     */
    private static function parse(string $contents): array
    {
        return EnvParser::parse($contents);
    }
}


