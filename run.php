#!/usr/bin/php
<?php

require_once __DIR__ . '/classes/FidelityImporter.php';
require_once __DIR__ . '/classes/VanguardImporter.php';
require_once __DIR__ . '/classes/SchwabImporter.php';
require_once __DIR__ . '/classes/StockDataFetcher.php';

use PhpFinancialsExtract\FidelityImporter;
use PhpFinancialsExtract\VanguardImporter;
use PhpFinancialsExtract\SchwabImporter;
use PhpFinancialsExtract\StockDataFetcher;

// Load environment variables from .env
$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        if (strpos($line, '=') !== false && strpos($line, '#') !== 0) {
            putenv(trim($line));
        }
    }
}

echo "\n=== Fidelity holdings :: Aggregated Shares by Symbol ===\n\n";

$importer = new FidelityImporter(__DIR__ . '/exports/Fidelity.csv');

// Display aggregated totals
$aggregated = $importer->getAggregatedShares();
foreach ($aggregated as $symbol => $shares) {
    printf("%-10s %12.4f shares\n", $symbol, $shares);
}

# echo "\n=== Vanguard Holdings ===\n\n";

echo "\n=== Vanguard Holdings :: Aggregated Shares by Symbol ===\n\n";

$vanguardImporter = new VanguardImporter(__DIR__ . '/exports/Vanguard.csv');

$vanguardAggregated = $vanguardImporter->getAggregatedShares();
foreach ($vanguardAggregated as $symbol => $shares) {
    printf("%-10s %12.4f shares\n", $symbol, $shares);
}

echo "\n=== Schwab Holdings :: Aggregated Shares by Symbol ===\n\n";

$schwabImporter = new SchwabImporter(__DIR__ . '/exports/Schwab.csv');

$schwabAggregated = $schwabImporter->getAggregatedShares();
foreach ($schwabAggregated as $symbol => $shares) {
    printf("%-10s %12.4f shares\n", $symbol, $shares);
}

echo "\n=== All Holdings :: Aggregated Shares by Symbol ===\n\n";

$allAggregated = [];

// Combine all aggregated holdings
foreach ([$aggregated, $vanguardAggregated, $schwabAggregated] as $brokerageHoldings) {
    foreach ($brokerageHoldings as $symbol => $shares) {
        if (!isset($allAggregated[$symbol])) {
            $allAggregated[$symbol] = 0.0;
        }
        $allAggregated[$symbol] += $shares;
    }
}

ksort($allAggregated);

foreach ($allAggregated as $symbol => $shares) {
    printf("%-10s %12.4f shares\n", $symbol, $shares);
}

echo "\n=== Previous Day EOD Prices ===\n\n";

$apiKey = getenv('MASSIVE_API_KEY');
if (empty($apiKey)) {
    echo "Error: MASSIVE_API_KEY not found in .env\n";
    exit(1);
}

$fetcher = new StockDataFetcher($apiKey);

// Get previous trading day (skip weekends and find last valid trading day)
// Start from yesterday and work backwards to find a valid trading day
$previousDay = new DateTime('yesterday');

// Known US market holidays for 2026 (add more as needed)
$holidays = [
    '2026-01-01', // New Year's Day
    '2026-01-19', // Martin Luther King Jr. Day
    '2026-02-16', // Presidents Day
    '2026-04-03', // Good Friday
    '2026-05-25', // Memorial Day
    '2026-07-03', // Independence Day (observed)
    '2026-09-07', // Labor Day
    '2026-11-26', // Thanksgiving
    '2026-12-25', // Christmas
];

// Go back up to 7 days to find a valid trading day
for ($i = 0; $i < 7; $i++) {
    $dayOfWeek = (int) $previousDay->format('N');
    $dateStr = $previousDay->format('Y-m-d');

    // Skip weekends (Saturday = 6, Sunday = 7)
    if ($dayOfWeek >= 6 || in_array($dateStr, $holidays)) {
        $previousDay->modify('-1 day');
        continue;
    }
    break;
}
$previousDayStr = $previousDay->format('Y-m-d');

echo "Fetching EOD prices for {$previousDayStr}...\n\n";

printf("%-10s %12s %12s %15s\n", "Symbol", "Shares", "Price", "Value");
printf("%-10s %12s %12s %15s\n", "------", "------", "-----", "-----");

$totalValue = 0.0;

foreach ($allAggregated as $symbol => $shares) {
    try {
        $priceData = $fetcher->getCurrentPrice($symbol, $previousDayStr);
        $closePrice = $priceData['close'] ?? 0.0;
        $value = $shares * $closePrice;
        $totalValue += $value;
        printf("%-10s %12.4f %12.2f %15.2f\n", $symbol, $shares, $closePrice, $value);
    } catch (\Exception $e) {
        printf("%-10s %12.4f %12s %15s\n", $symbol, $shares, "N/A", "N/A");
    }
    #break; // Remove or comment out this line to process all symbols
}

printf("\n%-10s %12s %12s %15.2f\n", "TOTAL", "", "", $totalValue);

echo "\n=== Dividend History (Last 5 per Symbol) ===\n\n";

foreach ($allAggregated as $symbol => $shares) {
    echo "--- {$symbol} ---\n";
    try {
        $dividends = $fetcher->getDividendHistory($symbol, 5);
        $results = $dividends['results'] ?? [];
        if (empty($results)) {
            echo "  No dividend history found.\n";
        } else {
            printf("  %-12s %12s %12s\n", "Ex-Date", "Amount", "Pay Date");
            foreach ($results as $div) {
                $exDate = $div['ex_dividend_date'] ?? 'N/A';
                $amount = $div['cash_amount'] ?? 0.0;
                $payDate = $div['pay_date'] ?? 'N/A';
                printf("  %-12s %12.4f %12s\n", $exDate, $amount, $payDate);
            }
        }
    } catch (\Exception $e) {
        echo "  Error fetching dividends: " . $e->getMessage() . "\n";
    }
    echo "\n";
}

echo "\n=== Stock Split History ===\n\n";

foreach ($allAggregated as $symbol => $shares) {
    try {
        $splits = $fetcher->getStockSplits($symbol, 10);
        $results = $splits['results'] ?? [];
        if (!empty($results)) {
            echo "--- {$symbol} ---\n";
            printf("  %-12s %12s\n", "Date", "Ratio");
            foreach ($results as $split) {
                $execDate = $split['execution_date'] ?? 'N/A';
                $splitFrom = $split['split_from'] ?? 1;
                $splitTo = $split['split_to'] ?? 1;
                $ratio = "{$splitTo}:{$splitFrom}";
                printf("  %-12s %12s\n", $execDate, $ratio);
            }
            echo "\n";
        }
    } catch (\Exception $e) {
        // Silently skip errors for splits
    }
}

echo "(Only symbols with split history are shown)\n";
