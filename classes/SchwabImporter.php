<?php

namespace PhpFinancialsExtract;

class SchwabImporter
{
    private string $filePath;

    public function __construct(string $filePath)
    {
        $this->filePath = $filePath;
    }

    /**
     * Import symbols and shares from Schwab CSV export
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
        $currentAccount = '';

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

            // Check for account name line (e.g., "Designated_Bene_Individual ...553")
            if (count($row) === 1 && preg_match('/^[A-Za-z_]+.*\.\.\.\d+$/', $row[0])) {
                $currentAccount = trim($row[0]);
                $headers = null; // Reset headers for new account section
                continue;
            }

            // Skip the title line
            if (count($row) === 1 && strpos($row[0], 'Positions for') === 0) {
                continue;
            }

            // Check for header row
            if ($row[0] === 'Symbol') {
                // Remove BOM from first header if present
                $row[0] = preg_replace('/^\xEF\xBB\xBF/', '', $row[0]);
                $headers = $row;
                continue;
            }

            // Skip if we don't have headers yet
            if ($headers === null) {
                continue;
            }

            // Skip rows that don't match header count
            if (count($row) !== count($headers)) {
                continue;
            }

            // Map row to associative array
            $data = array_combine($headers, $row);

            $symbol = trim($data['Symbol'] ?? '');
            $quantity = $data['Qty (Quantity)'] ?? '';

            // Skip non-holding rows (Cash, Account Total, etc.)
            if (empty($symbol) || $symbol === 'Cash & Cash Investments' || $symbol === 'Account Total') {
                continue;
            }

            // Skip rows without valid quantity
            if (empty($quantity) || $quantity === '--') {
                continue;
            }

            // Parse quantity as float (remove commas)
            $shares = (float) str_replace(',', '', $quantity);

            // Skip zero share positions
            if ($shares <= 0) {
                continue;
            }

            $holdings[] = [
                'symbol' => $symbol,
                'shares' => $shares,
                'account_number' => $currentAccount,
                'account_name' => $currentAccount,
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
