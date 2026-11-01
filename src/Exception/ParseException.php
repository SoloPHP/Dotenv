<?php

declare(strict_types=1);

namespace Solo\Dotenv\Exception;

use InvalidArgumentException;

final class ParseException extends InvalidArgumentException
{
    public function __construct(
        string $reason,
        private readonly int $lineNumber,
        private readonly ?string $source = null
    ) {
        $location = $source === null ? 'line ' . $lineNumber : $source . ':' . $lineNumber;
        parent::__construct('Invalid .env syntax at ' . $location . ': ' . $reason);
    }

    public function getLineNumber(): int
    {
        return $this->lineNumber;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }
}
