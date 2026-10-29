<?php

declare(strict_types=1);

namespace Commerce\Hub\Http;

final class Response
{
    public function __construct(public int $status = 200, public array $headers = [], public string $body = '')
    {
    }

    public static function html(string $body, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'text/html; charset=utf-8'], $body);
    }

    public static function redirect(string $to, int $status = 303): self
    {
        // Only same-site paths, so a crafted "next" parameter cannot redirect off-site.
        if (!str_starts_with($to, '/') || str_starts_with($to, '//')) {
            $to = '/';
        }
        return new self($status, ['Location' => $to]);
    }

    public static function json(array $data, int $status = 200): self
    {
        return new self($status, ['Content-Type' => 'application/json'], json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE));
    }

    public static function csv(string $filename, string $body): self
    {
        return new self(200, ['Content-Type' => 'text/csv; charset=utf-8', 'Content-Disposition' => 'attachment; filename="' . preg_replace('/[^A-Za-z0-9._-]/', '', $filename) . '"'], "\xEF\xBB\xBF" . $body);
    }

    public function send(): void
    {
        http_response_code($this->status);
        foreach ($this->headers as $k => $v) {
            header("{$k}: {$v}");
        }
        echo $this->body;
    }
}
