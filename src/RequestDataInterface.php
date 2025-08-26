<?php declare(strict_types=1);

namespace AP\HttpClient;

interface RequestDataInterface
{
    public function getFullInfo();

    public function getErrorMessage(): ?string;

    public function getErrorNumber(): int;

    public function getRuntime(): float;

    public function getResponseHttpCode(): int;

    public function getRequestBody(): ?string;

    public function getRequestHead(): string;

    public function getResponseHead(): string;

    public function getResponseBoby(): string;

    public function getResponseBodyJSONDecode(bool $throwOnError = false): mixed;

    public function getURL(): ?string;

    public function getLog(): Log;

    public function getStartedAt(): ?float;

    public function getFinishedAt(): ?float;

    public function getWaitTime(): float;

    public function getTimeout(): float;
}
