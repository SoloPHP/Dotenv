<?php

declare(strict_types=1);

namespace Solo\Dotenv\Tests;

use PHPUnit\Framework\TestCase;
use Solo\Dotenv\Dotenv;

final class DotenvTest extends TestCase
{
    private string $tmpDir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->tmpDir = sys_get_temp_dir().'/dotenv-tests-'.bin2hex(random_bytes(4));
        mkdir($this->tmpDir);
        $_ENV = [];
        $_SERVER = [];
    }

    protected function tearDown(): void
    {
        $files = glob($this->tmpDir.'/*') ?: [];
        foreach ($files as $file) {
            @unlink($file);
        }
        @rmdir($this->tmpDir);
        parent::tearDown();
    }

    public function testLoadsEnvFilesInOrderWithoutOverwritingPreExistingEnv(): void
    {
        $_ENV['FROM_SYSTEM'] = 'keep';
        file_put_contents($this->tmpDir.'/.env', "FOO=one\nBAR=two");
        file_put_contents($this->tmpDir.'/.env.local', "FOO=local\nBAZ=three");

        Dotenv::load($this->tmpDir, ['.env', '.env.local']);

        $this->assertSame('keep', $_ENV['FROM_SYSTEM']);
        // later file overrides earlier within same call
        $this->assertSame('local', $_ENV['FOO']);
        $this->assertSame('two', $_ENV['BAR']);
        $this->assertSame('three', $_ENV['BAZ']);
    }

    public function testPopulateServerOptional(): void
    {
        file_put_contents($this->tmpDir.'/.env', "FOO=bar");
        Dotenv::load($this->tmpDir, ['.env'], overwrite: true, populateServer: true);

        $this->assertSame('bar', $_ENV['FOO']);
        $this->assertSame('bar', $_SERVER['FOO']);
    }

    public function testGetters(): void
    {
        $_ENV['S'] = 'hello';
        $_ENV['N'] = '42';
        $_ENV['B1'] = 'true';
        $_ENV['B2'] = 'no';

        $this->assertSame('hello', Dotenv::string('S'));
        $this->assertSame(42, Dotenv::int('N'));
        $this->assertTrue(Dotenv::bool('B1'));
        $this->assertFalse(Dotenv::bool('B2'));
        $this->assertSame('def', Dotenv::string('MISSING', 'def'));
        $this->assertSame(7, Dotenv::int('MISSING', 7));
        $this->assertTrue(Dotenv::bool('MISSING', true));
    }

    public function testRequired(): void
    {
        $_ENV['REQ'] = 'x';
        $this->assertSame('x', Dotenv::required('REQ'));

        $this->expectException(\InvalidArgumentException::class);
        Dotenv::required('NOPE');
    }
}


