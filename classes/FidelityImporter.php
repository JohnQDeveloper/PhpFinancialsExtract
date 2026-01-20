<?php

namespace PhpFinancialsExtract;

class FidelityImporter
{
    private string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Import symbols and shares from Fidelity CSV export
     *
     * @return array Array of holdings with symbol and shares
     */
    public function import(): array
    {
        if (!file_exists($this->filePath)) {
            throw new \RuntimeException("File not found: {$this->filePath}");
        }

        $handle = fopen($this->filePath, 'r');
        if ($handle === false) {
            throw new \RuntimeException("Unable to open file: {$this->filePath}");
        }

        $holdings = [];
        $headers = null;

        while (($row = fgetcsv($handle)) !== false) {
            // Skip empty rows
            if (empty($row) || (count($row) === 1 && empty($row[0]))) {
                continue;
            }

            // Remove trailing empty elements (from trailing commas)
            while (!empty($row) && $row[count($row) - 1] === '') {
                array_pop($row);
            }

            // First valid row is the header
            if ($headers === null) {
                // Remove BOM from first header if present
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
                $headers = $row;
                continue;
            }

            // Stop if we hit the disclaimer section (starts with quoted text)
            if (strpos($row[0], 'The data and information') === 0) {
                break;
            }

            // Skip rows that don't match header count (malformed or footer rows)
            if (count($row) !== count($headers)) {
                continue;
            }

            // Map row to associative array
            $data = array_combine($headers, $row);

            $symbol = trim($data['Symbol'] ?? '');
            $quantity = $data['Quantity'] ?? '';

            // Skip rows without a valid symbol or quantity (e.g., cash positions)
            if (empty($symbol) || empty($quantity)) {
                continue;
            }

            // Clean up symbol (remove ** suffix used for money market funds)
            $symbol = rtrim($symbol, '*');

            // Parse quantity as float
            $shares = (float) str_replace(',', '', $quantity);

            // Skip zero share positions
            if ($shares <= 0) {
                continue;
            }

            $holdings[] = [
                'symbol' => $symbol,
                'shares' => $shares,
                'account_number' => $data['Account Number'] ?? '',
                'account_name' => $data['Account Name'] ?? '',
                'description' => $data['Description'] ?? '',
            ];
        }

        fclose($handle);

        return $holdings;
    }

    /**
     * Get aggregated shares by symbol across all accounts
     *
     * @return array Associative array of symbol => total shares
     */
    public function getAggregatedShares(): array
    {
        $holdings = $this->import();
        $aggregated = [];

        foreach ($holdings as $holding) {
            $symbol = $holding['symbol'];
            if (!isset($aggregated[$symbol])) {
                $aggregated[$symbol] = 0.0;
            }
            $aggregated[$symbol] += $holding['shares'];
        }

        ksort($aggregated);

        return $aggregated;
    }

    /**
     * Get holdings grouped by account
     *
     * @return array Associative array of account_number => holdings
     */
    public function getHoldingsByAccount(): array
    {
        $holdings = $this->import();
        $byAccount = [];

        foreach ($holdings as $holding) {
            $accountKey = $holding['account_number'];
            if (!isset($byAccount[$accountKey])) {
                $byAccount[$accountKey] = [
                    'account_name' => $holding['account_name'],
                    'holdings' => [],
                ];
            }
            $byAccount[$accountKey]['holdings'][] = [
                'symbol' => $holding['symbol'],
                'shares' => $holding['shares'],
                'description' => $holding['description'],
            ];
        }

        return $byAccount;
    }
}
