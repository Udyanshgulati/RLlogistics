<?php

declare(strict_types=1);

namespace Commerce\Hub\Http;

final class Request
{
    /** @param array<string, string> $headers lower-case names */
    public function __construct(
        public readonly string $method,
        public readonly string $path,
        public readonly array $query = [],
        public readonly array $post = [],
        public readonly array $headers = [],
        public readonly string $body = '',
        public readonly string $ip = '127.0.0.1',
        public readonly array $files = [],
        public readonly bool $secure = false,
    ) {
    }

    public static function fromGlobals(): self
    {
        $headers = [];
        foreach ($_SERVER as $k => $v) {
            if (str_starts_with($k, 'HTTP_')) {
                $headers[strtolower(str_replace('_', '-', substr($k, 5)))] = (string) $v;
            }
        }
        if (isset($_SERVER['CONTENT_TYPE'])) {
            $headers['content-type'] = (string) $_SERVER['CONTENT_TYPE'];
        }
        $path = '/' . trim((string) parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH), '/');
        return new self(
            strtoupper($_SERVER['REQUEST_METHOD'] ?? 'GET'),
            $path === '/' ? '/' : rtrim($path, '/'),
            $_GET,
            $_POST,
            $headers,
            (string) file_get_contents('php://input'),
            (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
            $_FILES,
            (($_SERVER['HTTPS'] ?? '') !== '' && $_SERVER['HTTPS'] !== 'off') || ($_SERVER['SERVER_PORT'] ?? '') === '443',
        );
    }

    public function withPost(array $post): self
    {
        return new self($this->method, $this->path, $this->query, $post, $this->headers, $this->body, $this->ip, $this->files, $this->secure);
    }

    public function q(string $key, string $default = ''): string
    {
        $v = $this->query[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    public function p(string $key, string $default = ''): string
    {
        $v = $this->post[$key] ?? $default;
        return is_string($v) ? trim($v) : $default;
    }

    public function int(string $key, ?int $default = null, bool $fromPost = false): ?int
    {
        $v = $fromPost ? $this->p($key) : $this->q($key);
        return preg_match('/^-?\d{1,10}$/', $v) ? (int) $v : $default;
    }

    public function header(string $name): ?string
    {
        return $this->headers[strtolower($name)] ?? null;
    }
}
