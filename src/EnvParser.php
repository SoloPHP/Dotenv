<?php

declare(strict_types=1);

namespace Solo\Dotenv;

use SensitiveParameter;
use Solo\Dotenv\Exception\ParseException;

/** Parses one assignment per line without interpolation. */
final class EnvParser
{
    private const string UTF8_BOM = "\xEF\xBB\xBF";

    /**
     * Arguments that carry file text are hidden from stack traces because they may contain secrets.
     *
     * @return array<string, string>
     * @throws ParseException When an assignment is malformed.
     */
    public static function parse(#[SensitiveParameter] string $contents, ?string $source = null): array
    {
        if (str_starts_with($contents, self::UTF8_BOM)) {
            $contents = substr($contents, strlen(self::UTF8_BOM));
        }

        $result = [];
        $lines = explode("\n", str_replace(["\r\n", "\r"], "\n", $contents));

        foreach ($lines as $index => $line) {
            $line = ltrim($line, " \t");
            if ($line === '' || $line[0] === '#') {
                continue;
            }

            if (preg_match('/^(?:export[ \t]+)?([A-Za-z_][A-Za-z0-9_]*)[ \t]*=/', $line, $matches) !== 1) {
                throw new ParseException('Expected a valid variable name followed by =.', $index + 1, $source);
            }

            $rawValue = substr($line, strlen($matches[0]));
            $result[$matches[1]] = self::parseValue($rawValue, $index + 1, $source);
        }

        return $result;
    }

    private static function parseValue(
        #[SensitiveParameter] string $rawValue,
        int $lineNumber,
        ?string $source
    ): string {
        $value = ltrim($rawValue, " \t");
        if (str_starts_with($value, '"') || str_starts_with($value, "'")) {
            return self::parseQuotedValue($value, $lineNumber, $source);
        }

        return self::stripComment($rawValue);
    }

    private static function parseQuotedValue(
        #[SensitiveParameter] string $value,
        int $lineNumber,
        ?string $source
    ): string {
        $quote = $value[0];
        // Text between stops is copied in one piece; only double quotes stop at a backslash.
        $stops = $quote === '"' ? '"\\' : "'";
        $length = strlen($value);
        $parsed = '';
        $index = 1;

        while ($index < $length) {
            $span = strcspn($value, $stops, $index);
            $parsed .= substr($value, $index, $span);
            $index += $span;

            if ($index < $length && $value[$index] === $quote) {
                if (self::stripComment(substr($value, $index + 1)) !== '') {
                    throw new ParseException('Unexpected text after a quoted value.', $lineNumber, $source);
                }

                return $parsed;
            }

            // Otherwise $index is at a backslash in double quotes, or past the end.
            if ($index + 1 < $length) {
                $escaped = $value[$index + 1];
                $parsed .= match ($escaped) {
                    'n' => "\n",
                    'r' => "\r",
                    't' => "\t",
                    '"' => '"',
                    '\\' => '\\',
                    default => '\\' . $escaped,
                };
            }

            $index += 2;
        }

        throw new ParseException('Unterminated quoted value.', $lineNumber, $source);
    }

    /** A comment starts at a # that follows a space or a tab. */
    private static function stripComment(#[SensitiveParameter] string $text): string
    {
        if (preg_match('/[ \t]#/', $text, $comment, PREG_OFFSET_CAPTURE) === 1) {
            $text = substr($text, 0, $comment[0][1]);
        }

        return trim($text, " \t");
    }
}
