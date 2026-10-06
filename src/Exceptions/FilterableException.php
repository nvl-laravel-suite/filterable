<?php

declare(strict_types=1);

namespace Nvl\Filterable\Exceptions;

use Exception;
use Illuminate\Validation\ValidationException;
use Nvl\Filterable\Enums\FilterableResponseCode;
use Nvl\Support\Contracts\RespondableException;
use Nvl\Support\Exceptions\ExceptionResponse;
use Nvl\Support\Traits\InteractsWithPackageFailure;
use Throwable;

/**
 * @api
 * Raised when a filter contract or value is invalid.
 */
final class FilterableException extends Exception implements RespondableException
{
    use InteractsWithPackageFailure;

    /** Preserve the explicit legacy machine code supplied by the consumer. */
    public function responseCode(): string
    {
        return $this->errorCode;
    }

    /** Resolve enum-backed presentation or a safe legacy fallback. */
    protected function exceptionResponse(): ExceptionResponse
    {
        return new ExceptionResponse('filterable', FilterableResponseCode::tryFrom($this->errorCode) ?? FilterableResponseCode::OperationFailed, 422, ['path' => $this->path]);
    }

    /** Create a new failure using a declared package code. */
    public static function because(FilterableResponseCode $code, string $diagnosticMessage, string $path = 'filter', ?Throwable $previous = null): self
    {
        return new self($diagnosticMessage, $code->value, $path, $previous);
    }

    /**
     * Create a filter contract exception with machine-readable context.
     */
    public function __construct(
        string $message,
        public readonly string $errorCode = 'invalid_filter',
        public readonly string $path = 'filter',
        ?Throwable $previous = null,
    ) {
        parent::__construct($message, previous: $previous);
    }

    /**
     * Convert an HTTP-boundary failure into Laravel's standard 422 response.
     */
    public function toValidationException(): ValidationException
    {
        return ValidationException::withMessages([
            $this->path => [$this->getMessage()],
        ]);
    }
}
