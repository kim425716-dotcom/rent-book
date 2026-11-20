<?php
/**
 * Rent Book Kenya - Safaricom Daraja STK Callback Listener
 * Captures Safaricom async transaction confirmations.
 */

header('Content-Type: application/json; charset=UTF-8');

$callbackJSON = file_get_contents('php://input');
$logFile = __DIR__ . '/mpesa_callbacks.log';

if (!empty($callbackJSON)) {
    $entry = "[" . date('Y-m-d H:i:s') . "] " . $callbackJSON . PHP_EOL;
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

// Safaricom expects a standard JSON acknowledgment
echo json_encode([
    'ResultCode' => 0,
    'ResultDesc' => 'Confirmation received successfully'
]);
