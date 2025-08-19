# Solo Dotenv

[![Latest Version on Packagist](https://img.shields.io/packagist/v/solophp/dotenv.svg)](https://packagist.org/packages/solophp/dotenv)
[![License](https://img.shields.io/packagist/l/solophp/dotenv.svg)](https://github.com/solophp/dotenv/blob/main/LICENSE)
[![PHP Version](https://img.shields.io/packagist/php-v/solophp/dotenv.svg)](https://packagist.org/packages/solophp/dotenv)

Simple, zero-dependency .env loader for PHP. Loads environment variables from files into `$_ENV` (optionally also `$_SERVER`). No global `putenv` calls, no magic variable expansion.

### Features
- **Small and focused**: just parsing and loading
- **Zero dependencies**
- **Deterministic overrides**: later files in the same call override earlier ones
- **Typed accessors**: `string`, `int`, `bool`, and `required`

### Installation
```bash
composer require solophp/dotenv
```

### Quick start
```php
use Solo\Dotenv\Dotenv;

Dotenv::load(__DIR__, ['.env', '.env.local']);

$dbDsn = Dotenv::required('DB_DSN');
$isDebug = Dotenv::bool('APP_DEBUG', false);
```

### API
- `Dotenv::load(string $baseDir, array $filenames = ['.env', '.env.local'], bool $overwrite = false, bool $populateServer = false): void`
  - Loads files in order. Existing environment variables set before the call are preserved by default. Variables set by earlier files in the same call can be overridden by later files.
  - Set `$overwrite = true` to overwrite any existing `$_ENV` values.
  - Set `$populateServer = true` to mirror values into `$_SERVER`.
- `Dotenv::get(string $key, mixed $default = null): mixed`
- `Dotenv::string(string $key, string $default = ''): string`
- `Dotenv::int(string $key, int $default = 0): int`
- `Dotenv::bool(string $key, bool $default = false): bool`
- `Dotenv::required(string $key): string` — throws `InvalidArgumentException` if missing/empty.

### Parser
The parser is implemented in `Solo\Dotenv\EnvParser` and supports:
- Lines like `KEY=VALUE`
- Optional leading `export `
- Single and double quotes
- Inline comments for unquoted values (`FOO=bar # comment`)
- No variable expansion (by design)

### Testing
```bash
composer install
composer test
```

### License
MIT
