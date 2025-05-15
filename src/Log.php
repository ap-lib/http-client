<?php declare(strict_types=1);

namespace AP\HttpClient;

readonly class Log
{
    public function __construct(
        public ?string   $url,
        public float|int $runtime,
        public float|int $wait_time,
        public int       $response_code,
        public ?string   $response_head,
        public ?string   $response_body,
        public ?string   $request_head,
        public ?string   $request_body,
        public int       $error_number,
        public ?string   $error_message,
    )
    {
    }
}
