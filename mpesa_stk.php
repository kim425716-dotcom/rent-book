<?php
/**
 * Rent Book Kenya - Safaricom Daraja STK Push Proxy & Status Query API
 * Enables secure, live Lipa na M-Pesa STK Push from the web client.
 */

header('Content-Type: application/json; charset=UTF-8');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');

// Respond to preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit;
}

// Helper: Safaricom HTTP Request via cURL
function safaricomCurl($url, $method = 'GET', $headers = [], $data = null) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);

    if ($method === 'POST') {
        curl_setopt($ch, CURLOPT_POST, true);
        if ($data !== null) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, is_array($data) ? json_encode($data) : $data);
        }
    }

    if (!empty($headers)) {
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    }

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curlError = curl_error($ch);
    curl_close($ch);

    return [
        'code' => $httpCode,
        'body' => $response,
        'error' => $curlError
    ];
}

// Helper: Format phone to 254XXXXXXXXX
function sanitizeKenyaPhone($phone) {
    $clean = preg_replace('/[^0-9]/', '', (string)$phone);
    if (strlen($clean) === 9 && ($clean[0] === '7' || $clean[0] === '1')) {
        return '254' . $clean;
    }
    if (strlen($clean) === 10 && $clean[0] === '0') {
        return '254' . substr($clean, 1);
    }
    if (strlen($clean) === 12 && substr($clean, 0, 3) === '254') {
        return $clean;
    }
    return $clean;
}

// Read JSON input
$rawInput = file_get_contents('php://input');
$input = json_decode($rawInput, true);
if (!is_array($input)) {
    $input = $_POST;
}

$action = isset($input['action']) ? trim($input['action']) : (isset($_GET['action']) ? trim($_GET['action']) : 'health');

// Health Check
if ($action === 'health') {
    echo json_encode([
        'status' => 'online',
        'service' => 'Rent Book Kenya Safaricom Daraja Gateway',
        'version' => '1.0.0',
        'time' => date('Y-m-d H:i:s T')
    ]);
    exit;
}

// Extract configuration
$env = isset($input['env']) && strtolower($input['env']) === 'production' ? 'production' : 'sandbox';
$consumerKey = isset($input['consumerKey']) ? trim($input['consumerKey']) : '';
$consumerSecret = isset($input['consumerSecret']) ? trim($input['consumerSecret']) : '';
$shortcode = isset($input['shortcode']) ? trim($input['shortcode']) : '';
$passkey = isset($input['passkey']) ? trim($input['passkey']) : '';

// Base URL based on environment
$baseUrl = ($env === 'production')
    ? 'https://api.safaricom.co.ke'
    : 'https://sandbox.safaricom.co.ke';

// 1. Test Authentication & Token Generation
if ($action === 'test_auth') {
    if (empty($consumerKey) || empty($consumerSecret)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Consumer Key and Consumer Secret are required.'
        ]);
        exit;
    }

    $authUrl = $baseUrl . '/oauth/v1/generate?grant_type=client_credentials';
    $authHeader = 'Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret);

    $res = safaricomCurl($authUrl, 'GET', [$authHeader]);
    $data = json_decode($res['body'], true);

    if ($res['code'] === 200 && isset($data['access_token'])) {
        echo json_encode([
            'success' => true,
            'message' => 'Authentication successful! Daraja credentials are valid.',
            'expires_in' => $data['expires_in'] ?? '3599',
            'env' => $env
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Authentication failed. Please verify Consumer Key and Secret for ' . ucfirst($env) . ' mode.',
            'safaricom_response' => $data ?? $res['body'],
            'curl_error' => $res['error']
        ]);
    }
    exit;
}

// 2. Dispatch STK Push
if ($action === 'push') {
    $phone = isset($input['phone']) ? sanitizeKenyaPhone($input['phone']) : '';
    $amount = isset($input['amount']) ? (int)$input['amount'] : 0;
    $accountRef = isset($input['accountRef']) ? trim($input['accountRef']) : 'Rent';
    $transactionDesc = isset($input['transactionDesc']) ? trim($input['transactionDesc']) : 'Rent Payment';
    $transactionType = isset($input['transactionType']) ? trim($input['transactionType']) : 'CustomerPayBillOnline';
    $callbackUrl = isset($input['callbackUrl']) && !empty($input['callbackUrl']) 
        ? trim($input['callbackUrl']) 
        : 'https://logictech.co.ke/rentbook/mpesa_callback.php';

    // Validation
    if (empty($consumerKey) || empty($consumerSecret) || empty($shortcode) || empty($passkey)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing required Daraja credentials (Key, Secret, Shortcode, or Passkey).'
        ]);
        exit;
    }

    if (empty($phone) || strlen($phone) !== 12 || substr($phone, 0, 3) !== '254') {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Invalid Kenyan phone number. Expected format: 07XXXXXXXX or 2547XXXXXXXX.'
        ]);
        exit;
    }

    if ($amount <= 0) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Amount must be greater than zero.'
        ]);
        exit;
    }

    // Step A: Generate OAuth token
    $authUrl = $baseUrl . '/oauth/v1/generate?grant_type=client_credentials';
    $authHeader = 'Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret);
    $authRes = safaricomCurl($authUrl, 'GET', [$authHeader]);
    $authData = json_decode($authRes['body'], true);

    if ($authRes['code'] !== 200 || !isset($authData['access_token'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to obtain access token from Safaricom. Check credentials.',
            'details' => $authData ?? $authRes['body']
        ]);
        exit;
    }

    $accessToken = $authData['access_token'];

    // Step B: Generate Timestamp & Password
    $timestamp = date('YmdHis');
    $password = base64_encode($shortcode . $passkey . $timestamp);

    // Step C: Send STK Push Request
    $stkUrl = $baseUrl . '/mpesa/stkpush/v1/processrequest';
    $stkPayload = [
        'BusinessShortCode' => (int)$shortcode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'TransactionType' => $transactionType,
        'Amount' => $amount,
        'PartyA' => (int)$phone,
        'PartyB' => (int)$shortcode,
        'PhoneNumber' => (int)$phone,
        'CallBackURL' => $callbackUrl,
        'AccountReference' => substr($accountRef, 0, 12),
        'TransactionDesc' => substr($transactionDesc, 0, 12)
    ];

    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    $stkRes = safaricomCurl($stkUrl, 'POST', $headers, $stkPayload);
    $stkData = json_decode($stkRes['body'], true);

    if ($stkRes['code'] === 200 && isset($stkData['ResponseCode']) && $stkData['ResponseCode'] === '0') {
        echo json_encode([
            'success' => true,
            'MerchantRequestID' => $stkData['MerchantRequestID'] ?? '',
            'CheckoutRequestID' => $stkData['CheckoutRequestID'] ?? '',
            'ResponseCode' => $stkData['ResponseCode'],
            'ResponseDescription' => $stkData['ResponseDescription'] ?? 'STK push sent.',
            'CustomerMessage' => $stkData['CustomerMessage'] ?? 'Please enter your M-Pesa PIN on your phone.',
            'timestamp' => $timestamp,
            'shortcode' => $shortcode,
            'amount' => $amount,
            'phone' => $phone
        ]);
    } else {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => $stkData['errorMessage'] ?? ($stkData['ResponseDescription'] ?? 'Safaricom rejected the STK push request.'),
            'safaricom_response' => $stkData ?? $stkRes['body'],
            'curl_error' => $stkRes['error']
        ]);
    }
    exit;
}

// 3. Query STK Push Status
if ($action === 'query') {
    $checkoutRequestId = isset($input['checkoutRequestId']) ? trim($input['checkoutRequestId']) : '';

    if (empty($checkoutRequestId) || empty($shortcode) || empty($passkey) || empty($consumerKey) || empty($consumerSecret)) {
        http_response_code(400);
        echo json_encode([
            'success' => false,
            'message' => 'Missing CheckoutRequestID or credentials for status query.'
        ]);
        exit;
    }

    // Step A: Generate OAuth token
    $authUrl = $baseUrl . '/oauth/v1/generate?grant_type=client_credentials';
    $authHeader = 'Authorization: Basic ' . base64_encode($consumerKey . ':' . $consumerSecret);
    $authRes = safaricomCurl($authUrl, 'GET', [$authHeader]);
    $authData = json_decode($authRes['body'], true);

    if (!isset($authData['access_token'])) {
        http_response_code(401);
        echo json_encode([
            'success' => false,
            'message' => 'Failed to obtain access token for status query.',
            'details' => $authData
        ]);
        exit;
    }

    $accessToken = $authData['access_token'];

    // Step B: Build Query Payload
    $timestamp = date('YmdHis');
    $password = base64_encode($shortcode . $passkey . $timestamp);

    $queryUrl = $baseUrl . '/mpesa/stkpushquery/v1/query';
    $queryPayload = [
        'BusinessShortCode' => (int)$shortcode,
        'Password' => $password,
        'Timestamp' => $timestamp,
        'CheckoutRequestID' => $checkoutRequestId
    ];

    $headers = [
        'Authorization: Bearer ' . $accessToken,
        'Content-Type: application/json'
    ];

    $queryRes = safaricomCurl($queryUrl, 'POST', $headers, $queryPayload);
    $queryData = json_decode($queryRes['body'], true);

    // Parse status
    $resultCode = isset($queryData['ResultCode']) ? (string)$queryData['ResultCode'] : '';
    $resultDesc = isset($queryData['ResultDesc']) ? $queryData['ResultDesc'] : ($queryData['errorMessage'] ?? '');

    $status = 'PENDING';
    if ($resultCode === '0') {
        $status = 'COMPLETED';
    } elseif ($resultCode === '1032') {
        $status = 'CANCELLED'; // Request cancelled by user
    } elseif ($resultCode === '1037') {
        $status = 'TIMEOUT'; // User failed to enter PIN
    } elseif ($resultCode !== '' && $resultCode !== '0') {
        $status = 'FAILED';
    }

    echo json_encode([
        'success' => true,
        'status' => $status,
        'ResultCode' => $resultCode,
        'ResultDesc' => $resultDesc,
        'raw' => $queryData
    ]);
    exit;
}

// Fallback
http_response_code(404);
echo json_encode(['error' => 'Unknown action: ' . $action]);
