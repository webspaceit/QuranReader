<?php
declare(strict_types=1);

namespace Quran\Http;

/**
 * An error that can safely be shown to the client as JSON.
 */
class ApiException extends \RuntimeException
{
    private int $status;

    private string $errorCode;

    /** @var array<string,mixed> */
    private array $details;

    /**
     * @param array<string,mixed> $details
     */
    public function __construct(string $message, int $status = 400, string $errorCode = 'bad_request', array $details = [])
    {
        parent::__construct($message);
        $this->status    = $status;
        $this->errorCode = $errorCode;
        $this->details   = $details;
    }

    public function status(): int
    {
        return $this->status;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    /** @return array<string,mixed> */
    public function details(): array
    {
        return $this->details;
    }

    public static function unauthorized(string $message = 'Please sign in to continue.'): self
    {
        return new self($message, 401, 'unauthorized');
    }

    public static function forbidden(string $message = 'You are not allowed to do that.'): self
    {
        return new self($message, 403, 'forbidden');
    }

    public static function notFound(string $message = 'Not found.'): self
    {
        return new self($message, 404, 'not_found');
    }

    public static function methodNotAllowed(): self
    {
        return new self('Method not allowed.', 405, 'method_not_allowed');
    }

    /** @param array<string,string> $errors */
    public static function invalid(array $errors, string $message = 'Please check the highlighted fields.'): self
    {
        return new self($message, 422, 'validation_error', ['fields' => $errors]);
    }

    public static function tooManyRequests(int $retryAfter): self
    {
        return new self(
            'Too many attempts. Please try again in ' . max(1, (int) ceil($retryAfter / 60)) . ' minute(s).',
            429,
            'too_many_requests',
            ['retry_after' => $retryAfter]
        );
    }
}
