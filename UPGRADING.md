# Upgrading from 1.0 to 2.0

Version 2.0 requires PHP 8.3 or newer. Existing calls to `Dotenv::load()` and the accessors keep their signatures and named arguments. The behaviour changes below can affect configuration at startup. Before deploying, run the following against each set of files your application loads. It checks the syntax and returns the merged file values without modifying the environment.

```php
use Solo\Dotenv\Dotenv;

Dotenv::read(__DIR__, ['.env', '.env.local']);
```

| Check | Change in 2.0 |
| --- | --- |
| Malformed assignments | A stray line such as `BROKEN`, an invalid name such as `APP-NAME`, or an unterminated quote now raises `ParseException`. Correct the line reported in the exception. |
| Hashes in unquoted values | `TOKEN=abc#123` now stores `abc#123`. Write `TOKEN=abc # comment` if the hash begins a comment. |
| Quoted paths and escapes | In double quotes, `\n`, `\r`, `\t`, `\"` and `\\` are decoded. Use single quotes for literal paths such as `DIR='C:\new\tools'`. After a closing quote only whitespace and an optional whitespace-separated comment are valid. |
| File errors | Optional files may be absent. Invalid directories and unreadable files now raise `RuntimeException`. An empty filename or one containing a null byte raises `InvalidArgumentException`. If any file fails to load or parse, no values from the call are applied. |
| Server-provided variables | A key present only in `$_SERVER` now takes precedence over the files by default, unless its name starts with `HTTP_`. Use `overwrite: true` if file values should win. Use `populateServer: true` as well to mirror applied values into `$_SERVER`. |
| Request headers | `$_SERVER` entries named `HTTP_*` come from client request headers and are now ignored. `get()` and the typed accessors no longer return them, and `populateServer` no longer overwrites them. Read request headers from your HTTP layer. |
| Integer settings | Fractions, exponent notation and values outside the PHP integer range now return the supplied default. Replace values such as `TIMEOUT=1e2` with `TIMEOUT=100`. |
| Non-scalar settings | `string()` returns its default for arrays, objects and resources. `required()` throws for these types and for a value that converts to an empty string, including native `false`. Use `get()` if you need a raw value from a superglobal. |
| Error messages | The `required()` exception message has changed. Match on the exception type rather than its text. |
