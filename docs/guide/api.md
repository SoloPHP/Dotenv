# Public API reference

This reference describes Solo Dotenv 2.0: PHP 8.3+, no runtime dependencies and the `Solo\Dotenv` namespace. [Getting started](index.md) walks through the setup, and [Behavior in detail](details.md) explains the rules below.

The public API consists of three `final` classes. `Dotenv` and `EnvParser` have static methods only and keep no state: every call reads the superglobals as they are. Exception messages are not part of the compatibility promise.

| Type | Role |
|---|---|
| `Dotenv` | Reads files, loads them into `$_ENV` and reads typed values back |
| `EnvParser` | Parses the text of one file |
| `Exception\ParseException` | Thrown for malformed syntax |

## Dotenv

### read()

```php
/**
 * @param list<string> $filenames
 * @return array<string, string>
 */
public static function read(string $baseDir, array $filenames = ['.env', '.env.local']): array
```

Reads the files in order and returns their assignments merged: a later assignment to a key replaces an earlier one, within a file and across files. `$_ENV` and `$_SERVER` are neither read nor changed.

- `$baseDir` must be an existing directory that can be traversed. An empty string is rejected.
- Each filename is relative to `$baseDir`, even with a leading `/`, and may name subdirectories.
- A file that does not exist is skipped when its directory exists. Any other path that is not a readable regular file is an error.

| Exception | Thrown when |
|---|---|
| `RuntimeException` | `$baseDir` is empty, missing or inaccessible; a directory in a filename is missing; the path is a directory, a broken symlink or an unreadable file |
| `InvalidArgumentException` | A filename is empty or contains a null byte |
| `ParseException` | A file contains malformed syntax |

### load()

```php
/** @param list<string> $filenames */
public static function load(
    string $baseDir,
    array $filenames = ['.env', '.env.local'],
    bool $overwrite = false,
    bool $populateServer = false
): void
```

Calls `read()` with the same files, so it throws the same exceptions, before anything is written. Then, for each key:

| The key is | Default | `overwrite: true` |
|---|---|---|
| Not set | Written to `$_ENV` | Written to `$_ENV` |
| In `$_ENV`, even empty or null | Kept | Replaced |
| Only in `$_SERVER`, name not starting with `HTTP_` | Kept | Written to `$_ENV` |
| Only in `$_SERVER`, name starting with `HTTP_` | Written to `$_ENV` | Written to `$_ENV` |

With `populateServer: true`, each value written to `$_ENV` is also written to `$_SERVER`, except `HTTP_*` keys. `putenv()` is never called, so `getenv()` does not see the values.

### get()

```php
public static function get(string $key, mixed $default = null): mixed
```

Returns `$_ENV[$key]` unless it is null, otherwise `$_SERVER[$key]`, as stored. `$_SERVER` is not consulted for keys starting with `HTTP_`. A missing value, null or an empty string returns `$default`, and an empty string in `$_ENV` does so even when `$_SERVER` has a value. The string `'0'` is a value.

### Value accessors

```php
public static function string(string $key, string $default = ''): string
public static function int(string $key, int $default = 0): int
public static function bool(string $key, bool $default = false): bool
public static function required(string $key): string
```

Each accessor looks the value up with `get()`, so a missing, null or empty value takes the default.

| Method | Accepts | Otherwise |
|---|---|---|
| `string()` | Any scalar, converted with `(string)`: `true` gives `'1'`, `false` gives `''` | `$default` for arrays, objects and resources |
| `int()` | Native integers, and decimal strings with an optional sign, leading zeros and surrounding spaces, tabs or line breaks, from `PHP_INT_MIN` to `PHP_INT_MAX` | `$default`, for example for `1.5`, `1e2`, `0x1A`, an overflow, a float or a boolean |
| `bool()` | `true`, `false`, `1`, `0`, and the strings `true/false`, `yes/no`, `on/off` and `1/0` in any letter case, trimmed | `$default` |
| `required()` | A scalar whose string form is not empty, `'0'` included | Throws `InvalidArgumentException` naming the key |

## EnvParser

```php
/**
 * @return array<string, string>
 * @throws ParseException
 */
public static function parse(#[SensitiveParameter] string $contents, ?string $source = null): array
```

Parses the text of one file and returns its assignments; the last assignment to a key wins. `$source` labels errors, usually with a file path, and `Dotenv::read()` passes the path. [File syntax](details.md#file-syntax) lists the rules.

## ParseException

`Solo\Dotenv\Exception\ParseException` extends `InvalidArgumentException`.

```php
public function getLineNumber(): int  // one-based
public function getSource(): ?string  // the $source given to parse(), or null
```

The message reads `Invalid .env syntax at <source>:<line>: <reason>`, or `Invalid .env syntax at line <line>: <reason>` without a source. It never contains the assignment or its value.

| Reason | Cause |
|---|---|
| `Expected a valid variable name followed by =.` | A line that is neither blank nor a comment and does not start with a valid name and `=` |
| `Unterminated quoted value.` | A quote that does not close on the same line |
| `Unexpected text after a quoted value.` | Anything after the closing quote other than spaces or tabs, optionally followed by a comment |

## Errors and supported boundaries

- Values from files are always strings. Other types appear only when application code puts them into `$_ENV` or `$_SERVER`.
- There is no interpolation, no command substitution and no multi-line value.
- Values live in `$_ENV`, and in `$_SERVER` with `populateServer`. The process environment is never changed.
- Failures are reported as exceptions. PHP itself may still emit warnings, for example for paths outside `open_basedir`.
