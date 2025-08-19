<?php

declare(strict_types=1);

namespace Solo\Dotenv;

/**
 * Stateless .env file parser.
 * Supports: KEY=VALUE, single/double quotes, inline comments after unquoted values, and leading "export ".
 * Does not perform variable expansion.
 */
final class EnvParser
{
    /**
     * @return array<string,string>
     */
    public static function parse(string $contents): array
    {
        $result = [];
        $lines = preg_split('/\r?\n/', $contents) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) {
                continue;
            }
            if (str_starts_with($line, 'export ')) {
                $line = substr($line, 7);
            }
            $pos = strpos($line, '=');
            if ($pos === false) {
                continue;
            }
            $key = rtrim(substr($line, 0, $pos));
            $value = ltrim(substr($line, $pos + 1));
            // Strip inline comments after unquoted values
            if ($value !== '' && $value[0] !== '"' && $value[0] !== "'") {
                $hashPos = strpos($value, '#');
                if ($hashPos !== false) {
                    $value = rtrim(substr($value, 0, $hashPos));
                }
            }
            // Remove surrounding quotes
            if ((str_starts_with($value, '"') && str_ends_with($value, '"')) || (str_starts_with($value, "'") && str_ends_with($value, "'"))) {
                $value = substr($value, 1, -1);
            }
            $result[$key] = $value;
        }
        return $result;
    }
}


