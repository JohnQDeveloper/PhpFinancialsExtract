<?php

namespace PhpFinancialsExtract;

class StockDataFetcher
{
    private string $apiKey;
    private string $baseUrl = 'https://api.massive.com';
    private array $requestTimestamps = [];
    private int $maxRequestsPerMinute = 4;

    public function __construct(string $apiKey)
    {
        $this->apiKey = $apiKey;
    }

    /**
     * Get dividend history for a ticker symbol
     *
     * @param string $ticker The stock ticker symbol
     * @param int $limit Maximum number of results to return
     * @return array Array of dividend records
     */
    public function getDividendHistory(string $ticker, int $limit = 100): array
    {
        $url = sprintf(
            '%s/stocks/v1/dividends?ticker=%s&limit=%d&sort=ex_dividend_date.desc&apiKey=%s',
            $this->baseUrl,
            urlencode($ticker),
            $limit,
            $this->apiKey
        );

        return $this->fetchJson($url);
    }

    /**
     * Get current/EOD price data for a ticker symbol
     *
     * @param string $ticker The stock ticker symbol
     * @param string|null $date Date in YYYY-MM-DD format (defaults to today)
     * @param bool $adjusted Whether to return adjusted prices
     * @return array Price data including open, high, low, close
     */
    public function getCurrentPrice(string $ticker, ?string $date = null, bool $adjusted = true): array
    {
        if ($date === null) {
            $date = date('Y-m-d');
        }

        $url = sprintf(
            '%s/v1/open-close/%s/%s?adjusted=%s&apiKey=%s',
            $this->baseUrl,
            urlencode($ticker),
            $date,
            $adjusted ? 'true' : 'false',
            $this->apiKey
        );

        return $this->fetchJson($url);
    }

    /**
     * Get stock split history for a ticker symbol
     *
     * @param string $ticker The stock ticker symbol
     * @param int $limit Maximum number of results to return
     * @return array Array of stock split records
     */
    public function getStockSplits(string $ticker, int $limit = 100): array
    {
        $url = sprintf(
            '%s/stocks/v1/splits?ticker=%s&limit=%d&sort=execution_date.desc&apiKey=%s',
            $this->baseUrl,
            urlencode($ticker),
            $limit,
            $this->apiKey
        );

        return $this->fetchJson($url);
    }

    /**
     * Get all stock data (dividends, current price, splits) for a ticker
     *
     * @param string $ticker The stock ticker symbol
     * @return array Combined data with dividends, price, and splits
     */
    public function getAllData(string $ticker): array
    {
        return [
            'ticker' => $ticker,
            'price' => $this->getCurrentPrice($ticker),
            'dividends' => $this->getDividendHistory($ticker),
            'splits' => $this->getStockSplits($ticker),
        ];
    }

    /**
     * Enforce rate limit of requests per minute
     * Sleeps if necessary to stay within the limit
     */
    private function enforceRateLimit(): void
    {
        $now = microtime(true);
        $oneMinuteAgo = $now - 60.0;

        // Remove timestamps older than 1 minute
        $this->requestTimestamps = array_filter(
            $this->requestTimestamps,
            fn($timestamp) => $timestamp > $oneMinuteAgo
        );

        // If we've hit the limit, wait until the oldest request is more than 1 minute old
        if (count($this->requestTimestamps) >= $this->maxRequestsPerMinute) {
            $oldestTimestamp = min($this->requestTimestamps);
            $waitTime = 60.0 - ($now - $oldestTimestamp);

            if ($waitTime > 0) {
                usleep((int) ($waitTime * 1000000));
            }

            // Clean up again after waiting
            $now = microtime(true);
            $oneMinuteAgo = $now - 60.0;
            $this->requestTimestamps = array_filter(
                $this->requestTimestamps,
                fn($timestamp) => $timestamp > $oneMinuteAgo
            );
        }

        // Record this request
        $this->requestTimestamps[] = microtime(true);
    }

    /**
     * Fetch JSON data from a URL
     *
     * @param string $url The URL to fetch
     * @return array Decoded JSON response
     * @throws \RuntimeException If the request fails
     */
    private function fetchJson(string $url): array
    {
        $this->enforceRateLimit();

        $ch = curl_init();

        curl_setopt_array($ch, [
            CURLOPT_URL => $url,
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_TIMEOUT => 30,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_HTTPHEADER => [
                'Accept: application/json',
            ],
        ]);

        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $error = curl_error($ch);

        curl_close($ch);

        if ($response === false) {
            throw new \RuntimeException("cURL error: {$error}");
        }

        if ($httpCode >= 400) {
            throw new \RuntimeException("HTTP error {$httpCode}: {$response}");
        }

        $data = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException("JSON decode error: " . json_last_error_msg());
        }

        return $data;
    }
}
