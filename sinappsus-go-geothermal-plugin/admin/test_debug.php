<?php
// Simple debug test file
$log_file = __DIR__ . '/debug.log';
$timestamp = date('Y-m-d H:i:s');
$log_entry = "[{$timestamp}] DIRECT TEST - Debug logging works!" . PHP_EOL;
file_put_contents($log_file, $log_entry, FILE_APPEND | LOCK_EX);

echo "Debug test written to: " . $log_file . "\n";
echo "Check if file exists: " . (file_exists($log_file) ? "YES" : "NO") . "\n";
if (file_exists($log_file)) {
    echo "File contents:\n";
    echo file_get_contents($log_file);
}
