<?php

namespace App\Exceptions;

use Exception;

class NowigoApiException extends Exception
{
    protected ?int $httpStatus;

    public function __construct(string $message = '', ?int $httpStatus = null, ?\Throwable $previous = null)
    {
        $this->httpStatus = $httpStatus;
        parent::__construct($message, $httpStatus ?? 0, $previous);
    }

    public function getHttpStatus(): ?int
    {
        return $this->httpStatus;
    }

    public function isRateLimited(): bool
    {
        return in_array($this->httpStatus, [403, 429]);
    }
}
