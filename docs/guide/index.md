# Getting started

Solo Dotenv reads configuration from `.env` files. `Dotenv::read()` returns the
values as an array, and `Dotenv::load()` puts them into `$_ENV`, where typed
accessors such as `Dotenv::int()` read them back. The parser is strict,
performs no interpolation and never calls `putenv()`.

## Install

PHP 8.3+ is required. The package has no runtime dependencies.

```sh
composer require solophp/dotenv
```

## Write a `.env` file

```dotenv
# Defaults committed with the application
APP_ENV=production
APP_DEBUG=no
APP_PORT=8080
APP_URL=https://example.test   # a comment starts after whitespace
MAIL_FROM="Solo App <app@example.test>"
```

Write one assignment per line. Names match `[A-Za-z_][A-Za-z0-9_]*`, and an
optional `export` prefix is ignored. [File syntax](details.md#file-syntax)
lists every rule.

## Load it at startup

Load the files once, early in the front controller or the console entry point:

```php
use Solo\Dotenv\Dotenv;

require __DIR__ . '/vendor/autoload.php';

Dotenv::load(__DIR__);
```

`load()` reads `.env` and then `.env.local` from the directory. Either file may
be missing. Every file is read and parsed before anything changes, so a broken
file leaves the environment as it was.

## Read values

```php
$env = Dotenv::string('APP_ENV', 'local');
$debug = Dotenv::bool('APP_DEBUG');
$port = Dotenv::int('APP_PORT', 8000);
$from = Dotenv::required('MAIL_FROM');
```

Each accessor looks in `$_ENV`, then in `$_SERVER` (except for `HTTP_*` keys),
and returns the default for a missing or empty value; `required()` throws
`InvalidArgumentException` instead. Conversions are strict: `int()` returns the default for `1.5` or
`1e2`, and `bool()` accepts `true/false`, `yes/no`, `on/off` and `1/0`. The
[API reference](api.md#value-accessors) gives the exact rules.

## Layer files

Later files override earlier ones, key by key. Commit `.env` with the defaults,
and keep the values of one machine in an uncommitted `.env.local`:

```php
Dotenv::load(__DIR__);                           // .env, then .env.local
Dotenv::load(__DIR__, ['.env', '.env.testing']); // a test configuration
Dotenv::load(__DIR__, ['config/app.env']);       // a file in a subdirectory
```

Filenames are relative to the directory. The directory, and any subdirectory a
filename names, must exist.

## Let the server win, or not

By default, a variable the server already provides keeps its value. A key
present in `$_ENV`, or in `$_SERVER` outside the `HTTP_*` request headers, is
skipped, so the environment of a container or an FPM pool overrides the files:

```php
Dotenv::load(__DIR__);                       // server values win
Dotenv::load(__DIR__, overwrite: true);      // file values win in $_ENV
Dotenv::load(__DIR__, populateServer: true); // also copy applied values to $_SERVER
```

Values applied by an earlier `load()` call are protected the same way.
[Precedence](details.md#precedence) explains the rules and why `HTTP_*` entries
are left out.

## Read without loading

`Dotenv::read()` returns the merged values without touching `$_ENV` or
`$_SERVER`:

```php
$values = Dotenv::read(__DIR__, ['.env', '.env.local']);
// ['APP_ENV' => 'production', 'APP_DEBUG' => 'no', ...]
```

Use it to build a configuration object, or to check every file in a deployment
step: it throws on the first invalid file and changes nothing.

## Handle errors

`read()` and `load()` throw before anything is applied:

| Problem | Exception |
| --- | --- |
| A malformed line, an invalid name or an unterminated quote | `ParseException`, an `InvalidArgumentException` |
| A missing or inaccessible directory, an unreadable file or a broken symlink | `RuntimeException` |
| An empty filename or one containing a null byte | `InvalidArgumentException` |

```text
Invalid .env syntax at /srv/app/.env.local:7: Unterminated quoted value.
```

A `ParseException` names the file and the line, never the assignment or its
value, and the parser keeps file contents out of stack traces. Let these
exceptions stop the application at startup: running with half a configuration
is rarely safer.

## Further reading

- [Public API reference](api.md)
- [Behavior in detail](details.md)
- [Upgrading from 1.x](https://github.com/SoloPHP/Dotenv/blob/main/UPGRADING.md)
