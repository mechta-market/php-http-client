<?php

namespace MechtaMarket\HttpClient;

use GuzzleHttp\Cookie\CookieJar;
use LogicException;
use MechtaMarket\HttpClient\Exceptions\RequestException;

class Response
{
    protected \Psr\Http\Message\MessageInterface $response;

    /**
     * The decoded JSON response.
     */
    protected ?array $decoded = null;

    /**
     * The request cookies.
     */
    public CookieJar $cookies;

    /**
     * The transfer stats for the request.
     *
     * @var \GuzzleHttp\TransferStats|null
     */
    public ?\GuzzleHttp\TransferStats $transferStats;

    public function __construct(\Psr\Http\Message\MessageInterface $response)
    {
        $this->response = $response;
    }

    public function body(): string
    {
        return (string) $this->response->getBody();
    }

    public function json(string $key = null, mixed $default = null): mixed
    {
        if (! $this->decoded) {
            $this->decoded = json_decode($this->body(), true);
        }

        if (is_null($key)) {
            return $this->decoded;
        }

        return isset($this->decoded[$key]) ? $key : $default;
    }

    public function object(): ?object
    {
        return json_decode($this->body(), false);
    }

    public function header(string $header): string
    {
        return $this->response->getHeaderLine($header);
    }

    public function headers(): array
    {
        return $this->response->getHeaders();
    }

    public function status(): int
    {
        return (int) $this->response->getStatusCode();
    }

    /**
     * Get the reason phrase of the response.
     */
    public function reason(): string
    {
        return $this->response->getReasonPhrase();
    }

    /**
     * Get the effective URI of the response.
     *
     * @return \Psr\Http\Message\UriInterface|null
     */
    public function effectiveUri(): ?\Psr\Http\Message\UriInterface
    {
        return $this->transferStats?->getEffectiveUri();
    }

    public function successful(): bool
    {
        return $this->status() >= 200 && $this->status() < 300;
    }

    public function redirect(): bool
    {
        return $this->status() >= 300 && $this->status() < 400;
    }

    public function failed(): bool
    {
        return $this->serverError() || $this->clientError();
    }

    public function clientError(): bool
    {
        return $this->status() >= 400 && $this->status() < 500;
    }

    public function serverError(): bool
    {
        return $this->status() >= 500;
    }

    public function cookies(): CookieJar
    {
        return $this->cookies;
    }

    /**
     * Get the handler stats of the response.
     *
     * @return array
     */
    public function handlerStats(): array
    {
        return $this->transferStats?->getHandlerStats() ?? [];
    }

    /**
     * Close the stream and any underlying resources.
     */
    public function close(): static
    {
        $this->response->getBody()->close();

        return $this;
    }

    /**
     * Get the underlying PSR response for the response.
     */
    public function toPsrResponse(): \Psr\Http\Message\MessageInterface
    {
        return $this->response;
    }

    /**
     * Create an exception if a server or client error occurred.
     */
    public function toException(): ?RequestException
    {
        if ($this->failed()) {
            return new RequestException($this);
        }
        return null;
    }

    /**
     * Throw an exception if a server or client error occurred.
     *
     * @throws RequestException
     */
    public function throw(): static
    {
        $callback = func_get_args()[0] ?? null;

        if ($this->failed()) {
            $exception = $this->toException();

            if ($callback && is_callable($callback)) {
                $callback($this, $exception);
            }
        }

        return $this;
    }

    /**
     * Throw an exception if a server or client error occurred and the given condition evaluates to true.
     *
     * @throws RequestException
     */
    public function throwIf(\Closure|bool $condition): Response|static
    {
        return $condition ? $this->throw(func_get_args()[1] ?? null) : $this;
    }

    /**
     * Throw an exception if the response status code matches the given code.
     *
     * @throws RequestException
     */
    public function throwIfStatus(callable|int $statusCode): static
    {
        if (is_callable($statusCode) &&
            $statusCode($this->status(), $this)) {
            return $this->throw();
        }

        return $this->status() === $statusCode ? $this->throw() : $this;
    }

    /**
     * Throw an exception unless the response status code matches the given code.
     *
     * @throws RequestException
     */
    public function throwUnlessStatus(callable|int $statusCode): static
    {
        if (is_callable($statusCode)) {
            return $statusCode($this->status(), $this) ? $this : $this->throw();
        }

        return $this->status() === $statusCode ? $this : $this->throw();
    }

    /**
     * Throw an exception if the response status code is a 4xx level code.
     *
     * @throws RequestException
     */
    public function throwIfClientError(): static
    {
        return $this->clientError() ? $this->throw() : $this;
    }

    /**
     * Throw an exception if the response status code is a 5xx level code.
     *
     * @throws RequestException
     */
    public function throwIfServerError(): static
    {
        return $this->serverError() ? $this->throw() : $this;
    }

    /**
     * Determine if the given offset exists.
     * @return bool
     */
    public function offsetExists(string $offset): bool
    {
        return isset($this->json()[$offset]);
    }

    /**
     * Get the value for a given offset.
     * @return mixed
     */
    public function offsetGet(string $offset): mixed
    {
        return $this->json()[$offset];
    }

    /**
     * Set the value at the given offset.
     *
     * @throws \LogicException
     */
    public function offsetSet(string $offset, mixed $value): void
    {
        throw new LogicException('Response data may not be mutated using array access.');
    }

    /**
     * Unset the value at the given offset.
     *
     * @throws \LogicException
     */
    public function offsetUnset(string $offset): void
    {
        throw new LogicException('Response data may not be mutated using array access.');
    }

    /**
     * Get the body of the response.
     */
    public function __toString()
    {
        return $this->body();
    }

    /**
     * Dynamically proxy other methods to the underlying response.
     */
    public function __call(string $method, array $parameters)
    {
        return $this->response->{$method}(...$parameters);
    }
}