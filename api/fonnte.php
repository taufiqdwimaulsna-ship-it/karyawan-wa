<?php
require_once __DIR__ . '/../config/database.php';

function sendWhatsAppNotification($target, $message) {
    if (!defined('FONNTE_TOKEN') || empty(FONNTE_TOKEN) || FONNTE_TOKEN === 'ISI_TOKEN_FONNTE_ANDA_DISINI') {
        return json_encode([
            'status'  => false,
            'message' => 'Token Fonnte belum dikonfigurasi di config/database.php'
        ]);
    }

    // Normalisasi nomor target
    $cleanTarget = preg_replace('/[^0-9]/', '', (string)$target);
    if (empty($cleanTarget)) {
        return json_encode([
            'status'  => false,
            'message' => 'Nomor WhatsApp target kosong atau tidak valid'
        ]);
    }

    if (substr($cleanTarget, 0, 1) === '0') {
        $cleanTarget = '62' . substr($cleanTarget, 1);
    } elseif (substr($cleanTarget, 0, 1) === '8') {
        $cleanTarget = '62' . $cleanTarget;
    }

    $payloadData = [
        'target'      => $cleanTarget,
        'message'     => $message,
        'countryCode' => '62',
    ];
    $jsonPayload = json_encode($payloadData, JSON_UNESCAPED_UNICODE);

    $response   = null;
    $error      = null;
    $methodUsed = 'none';

    // 1. Metode Utama: PHP cURL (JSON Payload, paksa IPv4)
    if (function_exists('curl_init')) {
        try {
            $curl = curl_init();
            curl_setopt_array($curl, [
                CURLOPT_URL            => 'https://api.fonnte.com/send',
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_CUSTOMREQUEST  => 'POST',
                CURLOPT_POSTFIELDS     => $jsonPayload,
                CURLOPT_HTTPHEADER     => [
                    'Authorization: ' . trim(FONNTE_TOKEN),
                    'Content-Type: application/json'
                ],
                CURLOPT_TIMEOUT        => 15,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_IPRESOLVE      => defined('CURL_IPRESOLVE_V4') ? CURL_IPRESOLVE_V4 : 1,
                CURLOPT_SSL_VERIFYPEER => false,
                CURLOPT_SSL_VERIFYHOST => false,
            ]);

            $res = curl_exec($curl);
            $err = curl_error($curl);
            curl_close($curl);

            if ($res && !$err && strpos($res, '"status"') !== false) {
                $response = $res;
                $methodUsed = 'php_curl_json_v4';
            } else {
                $error = $err ?: 'Empty response';
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        }
    }

    // 2. Fallback Utama untuk Apache: Windows CLI curl.exe dengan JSON file (mencegah teks terpotong oleh cmd.exe)
    if (empty($response)) {
        $tempJson = null;
        try {
            $tempJson = tempnam(sys_get_temp_dir(), 'fwa_');
            if ($tempJson && file_put_contents($tempJson, $jsonPayload) !== false) {
                $token = trim(FONNTE_TOKEN);
                $cmd = "curl.exe -4 -s -X POST https://api.fonnte.com/send " .
                       "-H \"Authorization: {$token}\" " .
                       "-H \"Content-Type: application/json\" " .
                       "-d \"@{$tempJson}\"";
                
                $cliOutput = @shell_exec($cmd);
                if (!empty($cliOutput) && strpos($cliOutput, '"status"') !== false) {
                    $response = $cliOutput;
                    $methodUsed = 'cli_curl_json_file';
                }
            }
        } catch (Throwable $e) {
            $error = $e->getMessage();
        } finally {
            if ($tempJson && file_exists($tempJson)) {
                @unlink($tempJson);
            }
        }
    }

    // 3. Fallback Cadangan: stream_context_create
    if (empty($response)) {
        try {
            $options = [
                'http' => [
                    'method'  => 'POST',
                    'header'  => "Authorization: " . trim(FONNTE_TOKEN) . "\r\n" .
                                 "Content-Type: application/json\r\n" .
                                 "Content-Length: " . strlen($jsonPayload) . "\r\n",
                    'content' => $jsonPayload,
                    'timeout' => 15,
                    'ignore_errors' => true
                ],
                'ssl' => [
                    'verify_peer'      => false,
                    'verify_peer_name' => false,
                ]
            ];

            $context = stream_context_create($options);
            $streamRes = @file_get_contents('https://api.fonnte.com/send', false, $context);
            if (!empty($streamRes) && strpos($streamRes, '"status"') !== false) {
                $response = $streamRes;
                $methodUsed = 'stream_context_json';
            }
        } catch (Throwable $e) {}
    }

    // Catat ke file log notifikasi
    try {
        $logDir = __DIR__ . '/../logs';
        if (!is_dir($logDir)) {
            @mkdir($logDir, 0777, true);
        }
        $logFile = $logDir . '/wa_notifications.log';
        $logEntry = "[" . date('Y-m-d H:i:s') . "] [Method: {$methodUsed}] Target: {$cleanTarget} | Response: " . ($response ?: ("Error: " . $error)) . "\n";
        @file_put_contents($logFile, $logEntry, FILE_APPEND);
    } catch (Throwable $e) {}

    return $response ?: json_encode(['status' => false, 'error' => $error ?: 'Gagal menghubungi server']);
}
