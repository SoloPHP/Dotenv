# Changelog

All notable changes to this project are documented in this file. The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and the project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [2.0.0] - 2026-11-01

This release contains breaking changes. Read the [upgrade guide](UPGRADING.md) before updating.

### Added

- `Dotenv::read()` returns the merged file values as an array without touching the environment.
- `ParseException` reports the source and one-based line number of malformed syntax.
- Double-quoted values decode `\n`, `\r`, `\t`, `\"` and `\\`.
- CR line endings and a leading UTF-8 BOM are supported.

### Changed

- PHP 8.3 or newer is required.
- Malformed lines, invalid names, unterminated quotes and text after a closing quote raise `ParseException` instead of being skipped or stored as written.
- `#` starts an inline comment only after a space or tab, so `TOKEN=abc#123` keeps the hash.
- A missing or inaccessible base directory, an unreadable file or a broken symlink raises `RuntimeException`. Absent files are still skipped.
- An empty filename or one containing a null byte raises `InvalidArgumentException`.
- `load()` applies no values unless every file is read and parsed successfully.
- `load()` keeps values already present in `$_SERVER` unless `overwrite` is set. `HTTP_*` entries in `$_SERVER` are ignored.
- `int()` accepts only decimal integers within the platform range. Fractions, exponents and overflowing values return the default.
- `string()` returns the default for non-scalar values. `required()` throws for non-scalar values and for values that convert to an empty string, with a new message.

### Security

- `get()` and the typed accessors no longer read `HTTP_*` keys from `$_SERVER`, which web servers fill from client request headers. `populateServer` no longer writes those keys to `$_SERVER`.
- Parser arguments are marked `#[\SensitiveParameter]`, so file contents and values stay out of exception stack traces.

## [1.0.0] - 2025-08-19

Initial release.

[2.0.0]: https://github.com/SoloPHP/Dotenv/compare/v1.0.0...v2.0.0
[1.0.0]: https://github.com/SoloPHP/Dotenv/releases/tag/v1.0.0
