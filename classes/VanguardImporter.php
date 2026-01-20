<?php

namespace PhpFinancialsExtract;

class VanguardImporter
{
    private string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Import symbols and shares from Vanguard CSV export
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

            // Skip if row is now empty after trimming
            if (empty($row)) {
                continue;
            }

            // First valid row is the header
            if ($headers === null) {
                // Remove BOM from first header if present
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
                $headers = $row;
                continue;
            }

            // Stop if we hit a new section (different header row)
            if ($row[0] === 'Account Number' && count($row) !== count($headers)) {
                break;
            }

            // Skip rows that don't match header count (malformed or section headers)
            if (count($row) !== count($headers)) {
                continue;
            }

            // Map row to associative array
            $data = array_combine($headers, $row);

            $symbol = trim($data['Symbol'] ?? '');
            $shares = $data['Shares'] ?? '';

            // Skip rows without a valid symbol or shares
            if (empty($symbol) || empty($shares)) {
                continue;
            }

            // Parse shares as float
            $sharesFloat = (float) str_replace(',', '', $shares);

            // Skip zero share positions
            if ($sharesFloat <= 0) {
                continue;
            }

            $holdings[] = [
                'symbol' => $symbol,
                'shares' => $sharesFloat,
                'account_number' => $data['Account Number'] ?? '',
                'account_name' => '', // Vanguard doesn't have account names in this format
                'description' => $data['Investment Name'] ?? '',
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
