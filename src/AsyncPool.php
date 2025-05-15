<?php declare(strict_types=1);

namespace AP\HttpClient;

use AP\HttpClient\Exception\CurlError;
use AP\HttpClient\Exception\DuplicateFinish;
use AP\HttpClient\Exception\NameNotFound;
use AP\HttpClient\Exception\NoStart;
use CurlMultiHandle;
use Throwable;

class AsyncPool
{
    private CurlMultiHandle $multiHandle;

    private float $wait_total_time = 0;

    /**
     * @var Request[]
     */
    private array $requests = [];

    private array $curly = [];

    private array $curly_ids = [];

    readonly public array $options;

    /**
     * @param int $data_transfer_start_waiting help to push request forward if wait no on the loop
     * @param int $runtime_stock_seconds
     * @param array $options
     * @param bool $destruct_curl_handle
     */
    public function __construct(
        readonly public int  $data_transfer_start_waiting = 0,
        readonly public int  $runtime_stock_seconds = 0,
        array                $options = [],
        readonly public bool $destruct_curl_handle = false,
    )
    {
        $this->multiHandle = curl_multi_init();
        $this->options     = $options + [
                CURLMOPT_MAX_TOTAL_CONNECTIONS => 12,
                CURLMOPT_MAX_HOST_CONNECTIONS  => 12,
                CURLMOPT_MAXCONNECTS           => 12,
                CURLMOPT_PIPELINING            => CURLPIPE_NOTHING,
            ];
        foreach ($this->options as $option => $value) {
            curl_multi_setopt($this->multiHandle, $option, $value);
        }
    }

    public function __destruct()
    {
        if ($this->destruct_curl_handle) {
            curl_multi_close($this->multiHandle);
        }
    }

    /**
     * @param Request $request
     * @param string|null $name
     * @param bool $safeStart
     * @return string
     * @throws Exception\DuplicateName
     * @throws Exception\DuplicateStart
     * @throws Exception\InvalidName
     */
    public function addRequest(
        Request $request,
        ?string $name = null,
        bool    $safeStart = true,
    ): string
    {
        $name = $this->addRequestAddToRequestsArray($request, $name);

        $this->curly[$name] = $this->requests[$name]->getCurlHandler(false);

        $this->curly_ids[(int)$this->curly[$name]] = $name;

        $this->requests[$name]->triggerStarted($name);

        curl_multi_add_handle($this->multiHandle, $this->curly[$name]);
        curl_multi_exec($this->multiHandle, $running);

        if ($safeStart && $this->data_transfer_start_waiting) {
            // an attempt to fix a bug that without curl_multi_exec in a loop, the process does not move
            // if the process of connecting to a remote server is slow
            usleep($this->data_transfer_start_waiting);
            curl_multi_exec($this->multiHandle, $running);
        }

        return $name;
    }

    public function exec(): int
    {
        curl_multi_exec(
            $this->multiHandle,
            $running
        );
        return $running;
    }

    public function getAllRequests(): array
    {
        return $this->requests;
    }

    /**
     * @param array $names
     * @param bool $curlErrorException
     * @param bool $wait
     * @param ?float $wait_seconds_limit
     * @return Request[]
     * @throws Exception\CurlError
     * @throws Exception\DuplicateFinish
     * @throws Exception\NameNotFound
     * @throws Exception\NoStart
     */
    public function checkOne(
        array  $names,
        bool   $curlErrorException = false,
        bool   $wait = true,
        ?float $wait_seconds_limit = null
    ): array
    {
        return $this->checkFew(
            names: $names,
            waitLimit: 1,
            curlErrorException: $curlErrorException,
            wait: $wait,
            wait_seconds_limit: $wait_seconds_limit
        );
    }

    /**
     * @param array $names
     * @param int $waitLimit
     * @param bool $curlErrorException
     * @param bool $wait
     * @param float|null $wait_seconds_limit
     * @return array
     * @throws CurlError
     * @throws DuplicateFinish
     * @throws NameNotFound
     * @throws NoStart
     */
    public function checkFew(
        array  $names,
        int    $waitLimit,
        bool   $curlErrorException = false,
        bool   $wait = true,
        ?float $wait_seconds_limit = null
    ): array
    {
        $res   = [];

        // make hashmap
        $names = array_combine(
            $names,
            $names
        );

        // check names
        foreach ($names as $name) {
            if (!isset($this->requests[$name])) {
                throw (new Exception\NameNotFound("name `$name` not found"));
            }
        }

        // check finished
        foreach ($names as $k => $name) {
            if ($this->requests[$name]->isFinished()) {
                unset($names[$k]);
                $res[$name] = $this->requests[$name];

                if ($curlErrorException) {
                    $res[$name] = $res[$name]->getObjetOrException();
                }

                if (count($res) == $waitLimit) {
                    return $res;
                }
            }
        }

        $start = microtime(true);
        do {
            curl_multi_exec($this->multiHandle, $running);
            do {
                $info = curl_multi_info_read(
                    $this->multiHandle,
                    $nextInfo
                );
                if (isset($info['msg'], $info['result'], $info['handle'])
                    && $info['msg'] == CURLMSG_DONE
                ) {
                    $name = $this->curly_ids[(int)$info['handle']];
                    if (!$this->requests[$name]->isFinished()) {
                        $aRes = $this->requests[$name]->triggerFinished(
                            isset($names[$name])
                                ? microtime(true) - $start
                                : 0,
                            (int)$info['result']
                        );

                        if (isset($names[$name])) {
                            $res[$name] = $aRes;
                            unset($names[$name]);
                        }

                        if (count($res) == $waitLimit) {
                            return $res;
                        }
                    }
                }
            } while ($nextInfo > 0);

            foreach ($names as $k => $name) {
                $requestRuntime = $this->requests[$name]->getRuntime();
                if ($requestRuntime + $this->runtime_stock_seconds >= $this->requests[$name]->getTimeout()) {
                    $obj = $this->requests[$name]->triggerFinished(
                        microtime(true) - $start,
                        CURLE_OPERATION_TIMEDOUT
                    );

                    if ($curlErrorException) {
                        $obj = $obj->getObjetOrException();
                    }

                    unset($names[$k]);

                    $res[$name] = $obj;
                    if (count($res) == $waitLimit) {
                        return $res;
                    }
                }
            }

            if (!$wait) {
                return $res;
            }

            $next_wait_limit_seconds = null;
            if (is_float($wait_seconds_limit)) {
                $next_wait_limit_seconds = microtime(true) - $start;
                if ($next_wait_limit_seconds > $wait_seconds_limit) {
                    return $res;
                }
            }

            if ($running > 0) {
                $timeout = 1;
                // update if there is wait limit, and wait limit end before max loop delay
                if (is_float($next_wait_limit_seconds) && $next_wait_limit_seconds < $timeout) {
                    $timeout = $next_wait_limit_seconds;
                }

                // update if any request's timeout less than max loop limit
                foreach ($names as $name) {
                    $t = $this->requests[$name]->getTimeout() - $this->requests[$name]->getRuntime();
                    if ($t < $timeout) {
                        $timeout = $t;
                    }
                }

                $waitStart = microtime(true);
                curl_multi_select($this->multiHandle, $timeout);
                $waitTime              = microtime(true) - $waitStart;
                $this->wait_total_time += $waitTime;
            }
        } while ($running > 0);

        return $res;
    }

    /**
     * @param array $names
     * @return Request[]
     * @throws Exception\CurlError
     * @throws Exception\DuplicateFinish
     * @throws Exception\NameNotFound
     * @throws Exception\NoStart
     */
    public function waitAny(array $names): array
    {
        return $this->checkFew(
            names: $names,
            waitLimit: 1
        );
    }

    /**
     * @return Request[]
     * @throws Throwable
     */
    public function waitAll(array $names): array
    {
        return $this->checkFew(
            names: $names,
            waitLimit: count($names)
        );
    }

    /**
     * @param string $name
     * @param bool $curlErrorException
     * @param ?float $wait_seconds_limit
     * @return Request
     * @throws Exception\CurlError
     * @throws Exception\DuplicateFinish
     * @throws Exception\NameNotFound
     * @throws Exception\NoStart
     */
    public function wait(
        string $name,
        bool   $curlErrorException = false,
        ?float $wait_seconds_limit = null,
    ): Request
    {
        return $this->checkFew(
            names: [$name],
            waitLimit: 1,
            curlErrorException: $curlErrorException,
            wait_seconds_limit: $wait_seconds_limit
        )[$name];
    }

    private int $requestIterator = 0;

    /**
     * @param Request $request
     * @param string|null $name
     * @return string
     * @throws Exception\DuplicateName
     * @throws Exception\InvalidName
     */
    private function addRequestAddToRequestsArray(Request $request, ?string $name = null): string
    {
        if (is_null($name)) {
            $name = "." . $this->requestIterator;
            $this->requestIterator++;
            $this->requests[$name] = $request;
            return $name;
        }

        if (str_starts_with($name, ".")) {
            throw (new Exception\InvalidName("name `$name` can not start as dot"));
        }

        if (isset($this->requests[$name])) {
            throw (new Exception\DuplicateName(
                "duplicate name `$name`, all names: " .
                implode(", ", array_keys($this->requests)))
            );
        }
        $this->requests[$name] = $request;
        return $name;
    }

    public function getWaitTotalTime(): float
    {
        return $this->wait_total_time;
    }
}
