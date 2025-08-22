<?php declare(strict_types=1);

namespace AP\HttpClient;

class MockedData implements RequestDataInterface
{
    public function __construct(

        public string  $responseHead = "HTTP/1.1 200 OK",
        public string  $responseBody = "",
        public string  $requestHead = "POST / HTTP/1.1",
        public ?string $requestBody = null,
        public int     $responseHttpCode = 200,
        public float   $runtime = 1.0,
        public int     $errorNumber = 0,
        public ?string $errorMessage = null,
        public array   $fullInfo = [],
        public ?string $url = null,
        public ?float  $startedAt = null,
        public ?float  $finishedAt = null,
        public float   $timeout = 0.0,
    )
    {
    }

    public function getFullInfo(): array
    {
        return $this->fullInfo;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function getErrorNumber(): int
    {
        return $this->errorNumber;
    }

    public function getRuntime(): float
    {
        return $this->runtime;
    }

    public function getResponseHttpCode(): int
    {
        return $this->responseHttpCode;
    }

    public function getRequestBody(): ?string
    {
        return $this->requestBody;
    }

    public function getRequestHead(): string
    {
        return $this->requestHead;
    }

    public function getResponseHead(): string
    {
        return $this->responseHead;
    }

    public function getResponseBoby(): string
    {
        return $this->responseBody;
    }

    public function getResponseBodyJSONDecode(): mixed
    {
        return json_decode($this->responseBody ?? '', true);
    }

    public function getURL(): ?string
    {
        return $this->url;
    }

    public function getLog(): Log
    {
        return new Log(
            url: $this->options[CURLOPT_URL] ?? null,
            started_at: $this->startedAt,
            finished_at: $this->finishedAt,
            runtime: $this->getRuntime(),
            wait_time: $this->getWaitTime(),
            response_code: $this->getResponseHttpCode(),
            response_head: $this->getResponseHead(),
            response_body: $this->getResponseBoby(),
            request_head: $this->getRequestHead(),
            request_body: $this->getRequestBody(),
            error_number: $this->getErrorNumber(),
            error_message: $this->getErrorMessage(),
        );
    }

    public function getStartedAt(): ?float
    {
        return $this->startedAt;
    }

    public function getFinishedAt(): ?float
    {
        return $this->finishedAt;
    }

    public function getWaitTime(): float
    {
        if ($this->startedAt !== null && $this->finishedAt !== null) {
            return max(0, $this->finishedAt - $this->startedAt - $this->runtime);
        }
        return 0.0;
    }

    public function getTimeout(): float
    {
        return $this->timeout;
    }
}