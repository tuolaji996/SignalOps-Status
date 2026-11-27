<?php
declare(strict_types=1);

namespace SignalOps\Status\Http;

final class Response
{
    /** @var array<string, string> */
    public array $headers = [];

    /** @var list<array{name: string, value: string, options: array<string, mixed>}> */
    public array $cookies = [];

    public function __construct(public string $body = '', public int $status = 200, array $headers = [])
    {
        // Caller headers win; text/html is only the default.
        $this->headers = $headers + ['Content-Type' => 'text/html; charset=utf-8'];
    }

    public static function redirect(string $location, int $status = 303): self
    {
        return new self('', $status, ['Location' => $location]);
    }

    public static function json(mixed $data, int $status = 200): self
    {
        $body = json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE);
        return new self($body === false ? '{}' : $body, $status, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public function withHeader(string $name, string $value): self
    {
        $this->headers[$name] = $value;
        return $this;
    }

    /** Private pages: never cached by the browser or CDN, never indexed. */
    public function private(): self
    {
        $this->headers['Cache-Control'] = 'no-store, private, max-age=0';
        $this->headers['Pragma'] = 'no-cache';
        $this->headers['X-Robots-Tag'] = 'noindex, nofollow';
        return $this;
    }

    public function withCookie(string $name, string $value, array $options): self
    {
        $this->cookies[] = ['name' => $name, 'value' => $value, 'options' => $options];
        return $this;
    }

    public function send(bool $headOnly = false): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            foreach ($this->headers as $name => $value) {
                header($name . ': ' . $value);
            }
            foreach ($this->cookies as $cookie) {
                setcookie($cookie['name'], $cookie['value'], $cookie['options']);
            }
        }
        if (!$headOnly) {
            echo $this->body;
        }
    }
}
