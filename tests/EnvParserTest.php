<?php

declare(strict_types=1);

namespace Solo\Dotenv\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Solo\Dotenv\EnvParser;
use Solo\Dotenv\Exception\ParseException;

final class EnvParserTest extends TestCase
{
    public function testParsesAssignmentsWithBomExportAndMixedLineEndings(): void
    {
        $contents = "\xEF\xBB\xBF \texport  FOO \t= first\r\n# comment\r \t\n"
            . "export\t_lower2=John Doe\nFOO=last=assignment\rZERO=0\nEMPTY=\nexport=ordinary";

        self::assertSame([
            'FOO' => 'last=assignment',
            '_lower2' => 'John Doe',
            'ZERO' => '0',
            'EMPTY' => '',
            'export' => 'ordinary',
        ], EnvParser::parse($contents));
        self::assertSame([], EnvParser::parse(''));
    }

    public function testCommentsDoNotTruncateLiteralHashes(): void
    {
        $contents = <<<'ENV'
URL=https://example.test/page#fragment
SECRET=abc#def  # comment
HASH=#abc
EMPTY= # comment
SINGLE='  literal # value  ' # comment
DOUBLE="quoted # value" # comment
ENV;

        self::assertSame([
            'URL' => 'https://example.test/page#fragment',
            'SECRET' => 'abc#def',
            'HASH' => '#abc',
            'EMPTY' => '',
            'SINGLE' => '  literal # value  ',
            'DOUBLE' => 'quoted # value',
        ], EnvParser::parse($contents));
    }

    public function testOnlyDoubleQuotedValuesDecodeEscapes(): void
    {
        $contents = <<<'ENV'
DOUBLE="first\nsecond\r\t\"quoted\"\\tail\q"
SINGLE='first\nsecond\t\\tail'
UNQUOTED=C:\new\test
EMPTY_SINGLE=''
EMPTY_DOUBLE=""
ENV;

        self::assertSame([
            'DOUBLE' => "first\nsecond\r\t\"quoted\"\\tail\\q",
            'SINGLE' => 'first\nsecond\t\\\\tail',
            'UNQUOTED' => 'C:\new\test',
            'EMPTY_SINGLE' => '',
            'EMPTY_DOUBLE' => '',
        ], EnvParser::parse($contents));
    }

    public function testDoesNotExpandVariablesOrCommands(): void
    {
        $contents = <<<'ENV'
BASE=actual
SINGLE='${BASE}'
DOUBLE="$BASE ${BASE} $(whoami)"
UNQUOTED=${BASE}
ENV;

        self::assertSame([
            'BASE' => 'actual',
            'SINGLE' => '${BASE}',
            'DOUBLE' => '$BASE ${BASE} $(whoami)',
            'UNQUOTED' => '${BASE}',
        ], EnvParser::parse($contents));
    }

    #[DataProvider('invalidAssignments')]
    public function testRejectsMalformedAssignments(string $assignment): void
    {
        $this->expectException(ParseException::class);

        EnvParser::parse($assignment);
    }

    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidAssignments(): iterable
    {
        yield 'missing equals' => ['BROKEN'];
        yield 'empty key' => ['=value'];
        yield 'numeric key' => ['123=value'];
        yield 'space in key' => ['BAD KEY=value'];
        yield 'dot in key' => ['BAD.KEY=value'];
        yield 'hyphen in key' => ['BAD-KEY=value'];
        yield 'non-ASCII key' => ['Ä=value'];
        yield 'unterminated double quote' => ['KEY="value'];
        yield 'unterminated single quote' => ["KEY='value"];
        yield 'escaped closing quote' => ['KEY="value\"'];
        yield 'only opening quote' => ['KEY="'];
        yield 'trailing quoted text' => ['KEY="value" trailing'];
        yield 'no space before quoted comment' => ['KEY="value"#comment'];
        yield 'concatenated quotes' => ["KEY='one'\"two\""];
        yield 'multiline quote' => ["KEY=\"first\nsecond\""];
    }

    public function testParseErrorsIdentifySourceAndLineWithoutExposingValues(): void
    {
        try {
            EnvParser::parse("# comment\r\nGOOD=value\rBAD=\"super-secret", '/app/.env');
            self::fail('Expected a parse error.');
        } catch (ParseException $exception) {
            self::assertSame(3, $exception->getLineNumber());
            self::assertSame('/app/.env', $exception->getSource());
            self::assertStringContainsString('/app/.env:3', $exception->getMessage());
            self::assertStringNotContainsString('super-secret', $exception->getMessage());
            self::assertStringNotContainsString('BAD', $exception->getMessage());
        }
    }

    public function testParseErrorTracesHideTheParsedText(): void
    {
        $ignoreArgs = ini_set('zend.exception_ignore_args', '0');

        try {
            EnvParser::parse("GOOD=value\nBAD=\"super-secret", '/app/.env');
            self::fail('Expected a parse error.');
        } catch (ParseException $exception) {
            $arguments = [];
            foreach ($exception->getTrace() as $frame) {
                if (($frame['class'] ?? null) === EnvParser::class) {
                    array_push($arguments, ...($frame['args'] ?? []));
                }
            }

            self::assertContains('/app/.env', $arguments);
            self::assertStringNotContainsString('super-secret', print_r($arguments, true));
        } finally {
            ini_set('zend.exception_ignore_args', (string) $ignoreArgs);
        }
    }

    public function testParseErrorsWorkWithoutSource(): void
    {
        try {
            EnvParser::parse("GOOD=value\nBROKEN");
            self::fail('Expected a parse error.');
        } catch (ParseException $exception) {
            self::assertSame(2, $exception->getLineNumber());
            self::assertNull($exception->getSource());
            self::assertStringContainsString('line 2', $exception->getMessage());
        }
    }
}
