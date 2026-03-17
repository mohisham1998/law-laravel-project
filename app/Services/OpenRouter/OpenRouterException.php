<?php

namespace App\Services\OpenRouter;

use Exception;

class OpenRouterException extends Exception
{
    public function __construct(
        string $message = '',
        int $code = 0,
        ?\Throwable $previous = null,
        public ?int $httpStatus = null,
        public ?array $response = null,
    ) {
        parent::__construct($message, $code, $previous);
    }

    public static function fromResponse(int $httpStatus, array $response): self
    {
        $msg = $response['error']['message'] ?? $response['message'] ?? "OpenRouter API error (HTTP {$httpStatus})";
        return new self($msg, 0, null, $httpStatus, $response);
    }
}
