<?php

declare(strict_types=1);

namespace Solo\Dotenv\Tests;

use PHPUnit\Framework\TestCase;
use Solo\Dotenv\EnvParser;

final class EnvParserTest extends TestCase
{
    public function testParsesSimplePairs(): void
    {
        $env = "FOO=bar\nBAZ=qux";
        $parsed = EnvParser::parse($env);
        $this->assertSame(['FOO' => 'bar', 'BAZ' => 'qux'], $parsed);
    }

    public function testIgnoresCommentsAndEmptyLines(): void
    {
        $env = "# comment\n\nFOO=bar # inline\n# another\nBAZ=qux";
        $parsed = EnvParser::parse($env);
        $this->assertSame(['FOO' => 'bar', 'BAZ' => 'qux'], $parsed);
    }

    public function testSupportsQuotesAndExport(): void
    {
        $env = "export FOO='bar'\nBAZ=\"qux\"\nNAME=John Doe";
        $parsed = EnvParser::parse($env);
        $this->assertSame(['FOO' => 'bar', 'BAZ' => 'qux', 'NAME' => 'John Doe'], $parsed);
    }
}


