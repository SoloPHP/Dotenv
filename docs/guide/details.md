# Behavior in detail

Solo Dotenv turns `.env` files into configuration values and does nothing else. [Getting started](index.md) shows the setup, and the [public API](api.md) describes the contracts. This page explains the rules and how the package is tested.

## Design decisions

- **Fail at startup.** A malformed line raises an exception that names the file and line, before any value is applied. Skipping it would turn a typo into a missing setting that surfaces much later.
- **The server's environment wins.** Variables set by a container, an FPM pool or the web server describe the deployment, and `.env` files supply the defaults. `overwrite: true` reverses this where the files should win, as in tests.
- **Request headers are not environment.** Web servers copy request headers into `$_SERVER` as `HTTP_*` entries. Treating them as variables would let a client override configuration.
- **Superglobals only.** `putenv()` changes the environment of the whole process, which threads share and child processes inherit. Values stay in `$_ENV`, and in `$_SERVER` on request.
- **No interpolation.** `${VAR}`, `$VAR` and `$(command)` are literal text, so a value is exactly what its line says.
- **Strict conversions.** The accessors return the default rather than guess: `1.5` is not an integer, and `enabled` is not a boolean.

## Structure

```text
src/
├── Dotenv.php                 Files, precedence and typed accessors
├── EnvParser.php              Syntax of one file
└── Exception/
    └── ParseException.php     Malformed syntax, with source and line
```

## File syntax

```dotenv
# One assignment per line
export APP_NAME = Solo PHP
URL=https://example.test/page#details  # the fragment stays in the value
LITERAL='cost: ${PRICE}'               # no interpolation
GREETING="Hello\nworld"                # two lines in the value
EMPTY=
```

**Lines.** The text is split at LF, CRLF and CR, and a UTF-8 byte order mark at the start is removed. Leading spaces and tabs are ignored. A blank line or a line starting with `#` is skipped. Every other line is an assignment: an optional `export` followed by spaces or tabs, a name matching `[A-Za-z_][A-Za-z0-9_]*`, optional spaces or tabs, and `=`. The last assignment to a name wins.

**Unquoted values.** Spaces and tabs around the value are removed. A `#` starts a comment only after a space or a tab. Everything else is literal, including quotes inside the value and backslashes.

**Single quotes.** The text up to the next single quote is taken as it is. A single-quoted value cannot contain a single quote.

**Double quotes.** `\n`, `\r`, `\t`, `\"` and `\\` are decoded. Any other backslash sequence is kept as written, so `\q` stays `\q` and `\$` stays `\$`.

**After a closing quote**, only spaces or tabs may follow, optionally with a comment after them. A quoted value must close on the same line.

| Line | Value |
|---|---|
| `KEY=value` | `value` |
| `KEY = spaced value` | `spaced value` |
| `KEY=a#b` | `a#b` |
| `KEY=a #b` | `a` |
| `KEY=#tag` | `#tag` |
| `KEY= # comment` | An empty string |
| `KEY=C:\new\tools` | `C:\new\tools` |
| `KEY='${HOME}\n'` | `${HOME}\n`, as written |
| `KEY="C:\\new"` | `C:\new` |
| `KEY="say \"hi\""` | `say "hi"` |
| `KEY="value" # comment` | `value` |

These lines raise `ParseException`:

| Line | Reason |
|---|---|
| `BROKEN` | No `=` |
| `APP-NAME=x`, `1KEY=x`, `MY KEY=x` | Invalid name |
| `KEY="open` | Unterminated quote |
| `KEY="a"b`, `KEY="a"#c` | Text after the closing quote |

## Files and directories

- A path is `<baseDir>/<filename>`. A filename is never absolute, and it may contain subdirectories or `..`.
- The base directory must exist and allow traversal: the loader looks up `<baseDir>/.`, which needs search permission on the directory. An empty base directory is rejected rather than read as the filesystem root.
- A missing file is skipped when the directory that would contain it is accessible. Anything else is an error: a missing or inaccessible directory in the path, a directory or another non-regular file in place of the file, a broken symlink, or a file that cannot be read. A typo in a path therefore fails instead of loading nothing.
- Symlinks to regular files are followed.

## Precedence

PHP fills `$_ENV` and `$_SERVER` according to `variables_order`. With `EGPCS`, the process environment appears in both. With `GPCS`, the setting in `php.ini-production`, `$_ENV` starts empty and the process environment appears only in `$_SERVER`. Variables set by the web server, such as `fastcgi_param` in nginx or `SetEnv` in Apache, also arrive in `$_SERVER`. Because `load()` checks both superglobals, the server's variables win under either setting.

`load()` skips a key that is already in `$_ENV`, even with an empty or null value, or in `$_SERVER` under a name that does not start with `HTTP_`. `overwrite: true` writes every key to `$_ENV`. `populateServer: true` copies each written value to `$_SERVER`, except `HTTP_*` keys. The [API reference](api.md#load) shows every case.

### Request headers

Web servers expose each request header in `$_SERVER` under `HTTP_` and its upper-case name: a `Proxy: evil.example` header becomes `HTTP_PROXY`. A client could otherwise set those keys. The package therefore ignores `$_SERVER` entries whose name starts with `HTTP_`:

- they do not stop `load()` from applying a file value;
- `populateServer` never overwrites them;
- `get()` and the typed accessors read `HTTP_*` keys from `$_ENV` only.

The check is case-sensitive: a lower-case `http_proxy` from the process environment is an ordinary variable.

## All or nothing

`load()` calls `read()` first. `read()` merges the values in a local array and returns them only after every file has been read and parsed. Nothing is written before that point, so an exception leaves both superglobals unchanged, and another `load()` after a fix starts from a clean state.

## Security

- Exception messages contain a path, a line number or a key, never a value.
- `EnvParser` marks its text arguments with `#[\SensitiveParameter]`. When `zend.exception_ignore_args` is off, PHP records function arguments in stack traces, and these arguments appear as `SensitiveParameterValue` objects instead of the file contents.
- `HTTP_*` request headers never count as environment, as described [above](#request-headers).
- Keep secrets out of committed files: commit `.env` with safe defaults, and provide secrets through the server environment or an uncommitted `.env.local`.

## Testing

`composer check` validates `composer.json` and runs PHPUnit, PHPStan at its maximum level on `src/`, and the PSR-12 check on `src/` and `tests/`. `.github/workflows/ci.yml` runs it on PHP 8.3, 8.4 and 8.5 on Ubuntu, and on PHP 8.5 on Windows. `.github/workflows/docs.yml` builds this site.

## Verified behavior

The test suite checks, among other things:

- every syntax rule above, including the byte order mark, CR and CRLF line endings, `export`, comments after whitespace and literal hashes;
- decoding in double quotes, literal single quotes, backslashes in unquoted values, and the absence of interpolation;
- each rejected form, with the source and line in the exception and without the value in its message or stack trace;
- merging several files, absent files, a missing base directory, a missing subdirectory, a directory in place of a file and null bytes in filenames;
- an aborted load that leaves `$_ENV` and `$_SERVER` untouched;
- precedence between `$_ENV`, `$_SERVER` and the files, `overwrite`, `populateServer` and repeated loads;
- `HTTP_*` entries in `$_SERVER`, which neither block file values nor reach the accessors;
- integer conversion at `PHP_INT_MIN`, `PHP_INT_MAX` and beyond, with fractions, exponents and padding;
- boolean tokens, and `required()` with missing, empty and non-scalar values.
