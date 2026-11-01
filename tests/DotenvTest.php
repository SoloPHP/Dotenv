<?php

declare(strict_types=1);

namespace Solo\Dotenv\Tests;

use FilesystemIterator;
use InvalidArgumentException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Solo\Dotenv\Dotenv;
use Solo\Dotenv\Exception\ParseException;
use stdClass;

final class DotenvTest extends TestCase
{
    private string $tmpDir;
    private array $savedEnv;
    private array $savedServer;

    #[Override]
    protected function setUp(): void
    {
        $this->tmpDir = sys_get_temp_dir() . '/dotenv-tests-' . bin2hex(random_bytes(8));
        mkdir($this->tmpDir);
        $this->savedEnv = $_ENV;
        $this->savedServer = $_SERVER;
        $_ENV = [];
        $_SERVER = [];
    }

    #[Override]
    protected function tearDown(): void
    {
        $_ENV = $this->savedEnv;
        $_SERVER = $this->savedServer;
        foreach (new FilesystemIterator($this->tmpDir) as $file) {
            if ($file->isDir()) {
                rmdir($file->getPathname());
            } else {
                unlink($file->getPathname());
            }
        }
        rmdir($this->tmpDir);
    }

    public function testReadMergesAvailableFilesInOrderWithoutTouchingGlobals(): void
    {
        $_ENV = ['PORT' => 'system'];
        $_SERVER = ['HOST' => 'server'];
        file_put_contents($this->tmpDir . '/first', "PORT=1\nHOST=host");
        file_put_contents($this->tmpDir . '/second', "PORT=2\nEMPTY=");

        $values = Dotenv::read($this->tmpDir, ['first', 'missing', 'second']);

        self::assertSame(['PORT' => '2', 'HOST' => 'host', 'EMPTY' => ''], $values);
        self::assertSame(['PORT' => 'system'], $_ENV);
        self::assertSame(['HOST' => 'server'], $_SERVER);
    }

    public function testOptionalFilesCanAllBeAbsent(): void
    {
        self::assertSame([], Dotenv::read($this->tmpDir));
    }

    public function testMissingBaseDirectoryIsAnError(): void
    {
        $this->expectException(RuntimeException::class);
        Dotenv::read($this->tmpDir . '/missing');
    }

    public function testAReadFailureLeavesEarlierFilesUnapplied(): void
    {
        file_put_contents($this->tmpDir . '/.env', 'PORT=9000');
        mkdir($this->tmpDir . '/.env.local');

        try {
            Dotenv::load($this->tmpDir, populateServer: true);
            self::fail('Expected a directory in place of a file to abort the load.');
        } catch (RuntimeException $exception) {
            self::assertStringContainsString('.env.local', $exception->getMessage());
        }

        self::assertSame([], $_ENV);
        self::assertSame([], $_SERVER);
    }

    public function testAPathWithAnInvalidDirectoryIsNotAnOptionalFile(): void
    {
        $this->expectException(RuntimeException::class);
        Dotenv::read($this->tmpDir, ['missing/config.env']);
    }

    public function testABrokenSymlinkIsNotAnOptionalFile(): void
    {
        if (!@symlink($this->tmpDir . '/missing-target', $this->tmpDir . '/.env')) {
            self::markTestSkipped('Symbolic links are not available.');
        }

        $this->expectException(RuntimeException::class);
        Dotenv::read($this->tmpDir);
    }

    public function testAnUnreadableFileIsAnError(): void
    {
        $path = $this->tmpDir . '/.env';
        file_put_contents($path, 'PORT=8000');
        chmod($path, 0);

        try {
            // Windows only marks the file read-only, and root reads it anyway.
            if (is_readable($path)) {
                self::markTestSkipped('The current user can read files without permission bits.');
            }

            $this->expectException(RuntimeException::class);
            Dotenv::read($this->tmpDir);
        } finally {
            // A read-only file cannot be deleted on Windows, so restore the bits for tearDown().
            chmod($path, 0644);
        }
    }

    public function testANullByteInTheFilenameIsNotSilentlyTreatedAsAMissingFile(): void
    {
        $this->expectException(InvalidArgumentException::class);
        Dotenv::read($this->tmpDir, ["config\0.env"]);
    }

    public function testLoadLeavesBothGlobalsIntactIfALaterFileIsMalformed(): void
    {
        $_ENV = ['EXISTING' => 'env'];
        $_SERVER = ['EXISTING' => 'server'];
        file_put_contents($this->tmpDir . '/.env', "EXISTING=file\nADDED=one");
        file_put_contents($this->tmpDir . '/.env.local', "OK=two\nnot an assignment");

        try {
            Dotenv::load($this->tmpDir, overwrite: true, populateServer: true);
            self::fail('Expected an invalid .env.local to abort the load.');
        } catch (ParseException $exception) {
            self::assertStringContainsString('.env.local', $exception->getMessage());
        }

        self::assertSame(['EXISTING' => 'env'], $_ENV);
        self::assertSame(['EXISTING' => 'server'], $_SERVER);
    }

    public function testLoadProtectsKeysInEitherGlobalAndMirrorsOnlyAppliedValues(): void
    {
        $_ENV = ['ENV_ONLY' => '', 'BOTH' => 'env'];
        $_SERVER = ['SERVER_ONLY' => null, 'BOTH' => 'server'];
        file_put_contents($this->tmpDir . '/.env', "ENV_ONLY=file\nSERVER_ONLY=file\nBOTH=file\nADDED=first");
        file_put_contents($this->tmpDir . '/.env.local', 'ADDED=local');

        Dotenv::load($this->tmpDir, populateServer: true);

        self::assertSame(['ENV_ONLY' => '', 'BOTH' => 'env', 'ADDED' => 'local'], $_ENV);
        self::assertSame(['SERVER_ONLY' => null, 'BOTH' => 'server', 'ADDED' => 'local'], $_SERVER);
    }

    public function testOverwriteAndServerMirroringAreIndependent(): void
    {
        $_SERVER['PORT'] = '9000';
        file_put_contents($this->tmpDir . '/.env', 'PORT=8000');

        Dotenv::load($this->tmpDir);
        self::assertArrayNotHasKey('PORT', $_ENV);
        self::assertSame('9000', Dotenv::get('PORT'));

        Dotenv::load($this->tmpDir, overwrite: true);
        self::assertSame('8000', $_ENV['PORT']);
        self::assertSame('9000', $_SERVER['PORT']);

        file_put_contents($this->tmpDir . '/.env', 'PORT=7000');
        Dotenv::load($this->tmpDir, overwrite: true, populateServer: true);
        self::assertSame('7000', $_ENV['PORT']);
        self::assertSame('7000', $_SERVER['PORT']);
    }

    public function testPreviouslyLoadedValuesAreProtectedOnSubsequentLoads(): void
    {
        file_put_contents($this->tmpDir . '/.env', 'PORT=8000');
        Dotenv::load($this->tmpDir);
        self::assertSame([], $_SERVER);
        file_put_contents($this->tmpDir . '/.env', 'PORT=9000');

        Dotenv::load($this->tmpDir);

        self::assertSame('8000', $_ENV['PORT']);
    }

    public function testRequestHeadersInServerNeitherBlockNorReceiveFileValues(): void
    {
        $_ENV = ['HTTP_KEEP' => 'env'];
        $_SERVER = ['HTTP_PROXY' => 'header.example:8080', 'HTTP_KEEP' => 'header'];
        file_put_contents($this->tmpDir . '/.env', "HTTP_PROXY=proxy.internal:3128\nHTTP_KEEP=file");

        Dotenv::load($this->tmpDir, populateServer: true);

        self::assertSame(['HTTP_KEEP' => 'env', 'HTTP_PROXY' => 'proxy.internal:3128'], $_ENV);
        self::assertSame(['HTTP_PROXY' => 'header.example:8080', 'HTTP_KEEP' => 'header'], $_SERVER);
        self::assertSame('proxy.internal:3128', Dotenv::get('HTTP_PROXY'));

        Dotenv::load($this->tmpDir, overwrite: true, populateServer: true);

        self::assertSame(['HTTP_KEEP' => 'file', 'HTTP_PROXY' => 'proxy.internal:3128'], $_ENV);
        self::assertSame(['HTTP_PROXY' => 'header.example:8080', 'HTTP_KEEP' => 'header'], $_SERVER);
    }

    public function testAccessorsReadRequestHeaderNamesOnlyFromEnv(): void
    {
        $_SERVER = ['HTTP_PORT' => '8080', 'HTTP_DEBUG' => 'true', 'HTTP_TOKEN' => 'header', 'http_proxy' => 'proxy'];

        self::assertNull(Dotenv::get('HTTP_PORT'));
        self::assertSame(80, Dotenv::int('HTTP_PORT', 80));
        self::assertFalse(Dotenv::bool('HTTP_DEBUG'));
        self::assertSame('proxy', Dotenv::get('http_proxy'));

        $_ENV['HTTP_PORT'] = '9000';
        self::assertSame(9000, Dotenv::int('HTTP_PORT'));

        $this->expectException(InvalidArgumentException::class);
        Dotenv::required('HTTP_TOKEN');
    }

    public function testGetUsesEnvThenServerAndFallsBackForMissingOrEmptyValues(): void
    {
        $_ENV = ['BOTH' => 'env', 'NULL_VALUE' => null, 'EMPTY' => '', 'ZERO' => '0'];
        $_SERVER = ['BOTH' => 'server', 'NULL_VALUE' => 'server', 'EMPTY' => 'shadowed', 'ARGS' => ['a']];

        self::assertSame('env', Dotenv::get('BOTH'));
        self::assertSame('server', Dotenv::get('NULL_VALUE'));
        self::assertSame('default', Dotenv::get('EMPTY', 'default'));
        self::assertSame('0', Dotenv::get('ZERO'));
        self::assertSame(['a'], Dotenv::get('ARGS'));
        self::assertNull(Dotenv::get('MISSING'));
    }

    public function testStringAcceptsScalarValuesAndDefaultsForUnsupportedTypes(): void
    {
        $_SERVER = [
            'N' => 42,
            'F' => 1.5,
            'TRUE' => true,
            'BOOL' => false,
            'ARGS' => ['a'],
            'OBJECT' => new stdClass(),
        ];

        self::assertSame('42', Dotenv::string('N'));
        self::assertSame('1.5', Dotenv::string('F'));
        self::assertSame('1', Dotenv::string('TRUE'));
        self::assertSame('', Dotenv::string('BOOL', 'default'));
        self::assertSame('default', Dotenv::string('ARGS', 'default'));
        self::assertSame('default', Dotenv::string('OBJECT', 'default'));
        self::assertSame('default', Dotenv::string('MISSING', 'default'));
    }

    #[DataProvider('integerValues')]
    public function testIntAcceptsOnlyInRangeDecimalIntegers(mixed $value, ?int $expected): void
    {
        $_ENV['NUMBER'] = $value;

        self::assertSame($expected ?? 91, Dotenv::int('NUMBER', 91));
    }

    public static function integerValues(): array
    {
        $belowMinimum = PHP_INT_SIZE === 8 ? '-9223372036854775809' : '-2147483649';
        $aboveMaximum = PHP_INT_SIZE === 8 ? '9223372036854775808' : '2147483648';

        return [
            'native integer' => [42, 42],
            'padded and signed' => [' +00042 ', 42],
            'negative' => ['-0042', -42],
            'zero' => ['000', 0],
            'minimum' => [(string) PHP_INT_MIN, PHP_INT_MIN],
            'maximum' => [(string) PHP_INT_MAX, PHP_INT_MAX],
            'below minimum' => [$belowMinimum, null],
            'above maximum' => [$aboveMaximum, null],
            'fraction' => ['1.9', null],
            'exponent' => ['1e2', null],
            'native float' => [1.0, null],
            'native boolean' => [true, null],
            'empty' => ['', null],
            'missing' => [null, null],
        ];
    }

    public function testBoolRecognizesCommonTokensAndDefaultsForUnknownOrMissingValues(): void
    {
        foreach ([' YES ', 'On', 'true', '1', 1, true] as $value) {
            $_ENV['FLAG'] = $value;
            self::assertTrue(Dotenv::bool('FLAG', false));
        }
        foreach ([' NO ', 'Off', 'false', '0', 0, false] as $value) {
            $_ENV['FLAG'] = $value;
            self::assertFalse(Dotenv::bool('FLAG', true));
        }
        $_ENV['FLAG'] = 'unknown';
        self::assertTrue(Dotenv::bool('FLAG', true));
        self::assertFalse(Dotenv::bool('FLAG', false));
        self::assertTrue(Dotenv::bool('MISSING', true));
    }

    public function testRequiredReturnsNonEmptyScalarStringsIncludingZero(): void
    {
        $_ENV = ['ZERO' => 0, 'REQ' => 'value'];

        self::assertSame('0', Dotenv::required('ZERO'));
        self::assertSame('value', Dotenv::required('REQ'));
    }

    #[DataProvider('invalidRequiredValues')]
    public function testRequiredThrowsForMissingEmptyAndNonScalarValues(mixed $value): void
    {
        if ($value !== null) {
            $_SERVER['REQUIRED_KEY'] = $value;
        }

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('REQUIRED_KEY');
        Dotenv::required('REQUIRED_KEY');
    }

    public static function invalidRequiredValues(): array
    {
        return [[null], [''], [false], [['a']], [new stdClass()]];
    }
}
