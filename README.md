# Solo Dotenv

[![Latest Version on Packagist](https://img.shields.io/packagist/v/solophp/dotenv.svg)](https://packagist.org/packages/solophp/dotenv)
[![PHP Version](https://img.shields.io/badge/PHP-8.3%2B-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/packagist/l/solophp/dotenv.svg)](LICENSE)

Strict `.env` loader for PHP. Read layered files as an array, or load them into `$_ENV` and read typed values back. A malformed line fails with its file and line before anything is applied; variables the server already provides keep precedence, and `HTTP_*` request headers never count as environment. No `putenv()`, no interpolation and no runtime dependencies.

## Installation

```bash
composer require solophp/dotenv
```

Requires PHP 8.3+.

## Example

```dotenv
# .env
APP_DEBUG=no
APP_PORT=8080
DB_DSN="sqlite:/srv/app/data.db"
```

```php
use Solo\Dotenv\Dotenv;

Dotenv::load(__DIR__); // .env, then .env.local; both optional

$debug = Dotenv::bool('APP_DEBUG');    // false
$port = Dotenv::int('APP_PORT', 8000); // 8080
$dsn = Dotenv::required('DB_DSN');     // 'sqlite:/srv/app/data.db'
```

`Dotenv::read()` returns the same values as an array without touching the environment, and `EnvParser::parse()` parses a string.

## Documentation

**[Full Documentation](https://solophp.github.io/Dotenv/)**

- [Getting Started](https://solophp.github.io/Dotenv/guide/)
- [Layered Files](https://solophp.github.io/Dotenv/guide/#layer-files)
- [Precedence](https://solophp.github.io/Dotenv/guide/details#precedence)
- [File Syntax](https://solophp.github.io/Dotenv/guide/details#file-syntax)
- [Error Handling](https://solophp.github.io/Dotenv/guide/#handle-errors)
- [API Reference](https://solophp.github.io/Dotenv/guide/api)
- [Behavior in Detail](https://solophp.github.io/Dotenv/guide/details)
- [Upgrading from 1.x](UPGRADING.md)
- [Changelog](CHANGELOG.md)

## License

MIT. See [LICENSE](LICENSE).
