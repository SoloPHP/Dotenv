---
layout: home

hero:
  name: Solo Dotenv
  text: Strict .env files. Predictable environment.
  tagline: Read layered .env files as an array or load them into $_ENV. A broken file fails at startup with its name and line, and the server's own variables keep precedence.
  image:
    src: /logo.svg
    alt: Solo Dotenv
  actions:
    - theme: brand
      text: Get started
      link: /guide/
    - theme: alt
      text: API reference
      link: /guide/api

features:
  - icon: 🧾
    title: Strict syntax
    details: A malformed line, an invalid name or an unterminated quote raises ParseException with the file and line instead of being skipped. The message never contains the value.
  - icon: 🗂️
    title: Layered files
    details: .env, then .env.local, or any list of files you name. Each file is optional, and the last assignment to a key wins.
  - icon: 🛡️
    title: Server variables first
    details: A variable already present in $_ENV or $_SERVER keeps its value unless you ask to overwrite it. Request headers under HTTP_* names never count as environment.
  - icon: ⚛️
    title: All or nothing
    details: load() reads and parses every file before it changes anything. A syntax or read error leaves $_ENV and $_SERVER exactly as they were.
  - icon: 🔢
    title: Strict accessors
    details: string(), int(), bool() and required() convert values strictly. 1.5, 1e2 and an overflowing number are not integers, and enabled is not a boolean.
  - icon: 🔒
    title: Nothing implicit
    details: 'No putenv(), no interpolation, no shell commands and no runtime dependencies. File contents stay out of stack traces through #[\SensitiveParameter].'
---

<style>
:root {
  --vp-home-hero-name-color: transparent;
  --vp-home-hero-name-background: linear-gradient(135deg, #10b981 0%, #3b82f6 100%);
  --vp-home-hero-image-background-image: linear-gradient(135deg, #10b98130 0%, #3b82f630 100%);
  --vp-home-hero-image-filter: blur(44px);
}

.VPHero .VPImage {
  max-width: 200px;
  max-height: 200px;
}
</style>

## Load and read

```dotenv
# .env
APP_ENV=production
APP_DEBUG=no
APP_PORT=8080
DB_DSN="pgsql:host=db;dbname=app"
```

```php
use Solo\Dotenv\Dotenv;

Dotenv::load(__DIR__); // .env, then .env.local; both optional

$env = Dotenv::string('APP_ENV', 'local'); // 'production'
$debug = Dotenv::bool('APP_DEBUG');        // false
$port = Dotenv::int('APP_PORT', 8000);     // 8080
$dsn = Dotenv::required('DB_DSN');         // 'pgsql:host=db;dbname=app'
```

A variable the server already provides, such as `APP_ENV` set in a container,
keeps its value. Read the [getting-started guide](./guide/index.md) and the
[API reference](./guide/api.md) for layered files, precedence and errors.

## Installation

PHP **8.3+** is required. The package has no runtime dependencies.

```sh
composer require solophp/dotenv
```

Continue with the [getting-started guide](./guide/index.md).
