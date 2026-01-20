#!/usr/bin/php
<?php

require_once __DIR__ . '/classes/FidelityImporter.php';
require_once __DIR__ . '/classes/VanguardImporter.php';
require_once __DIR__ . '/classes/SchwabImporter.php';

use PhpFinancialsExtract\FidelityImporter;
use PhpFinancialsExtract\VanguardImporter;
use PhpFinancialsExtract\SchwabImporter;

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
