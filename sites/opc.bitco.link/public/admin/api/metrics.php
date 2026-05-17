<?php
/**
 * Returns the rolling 7-day server metrics JSON written by
 * scripts/collect_metrics.sh. Behind the admin session so the data isn't
 * publicly browsable (replaces the old fetch of /admin/data/metrics.json
 * which is now denied at the nginx layer).
 */
require __DIR__ . '/_bootstrap.php';

$metricsFile = dirname(__DIR__) . '/data/metrics.json';

if (!file_exists($metricsFile)) {
    echo json_encode([]);
    exit;
}

// Stream the file as-is. The format is whatever collect_metrics.sh writes.
header('Cache-Control: no-store');
readfile($metricsFile);
