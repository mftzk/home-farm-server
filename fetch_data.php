<?php
/**
 * Cron script: pull lux data dari ESP8266 BH1750 dan simpan ke MySQL.
 *
 * Crontab (setiap 1 menit):
 *   * * * * * php /full/path/server/fetch_data.php
 */

require __DIR__ . '/config.php';

$url = 'http://' . ESP_IP . '/data';

$ctx = stream_context_create([
    'http' => [
        'timeout' => ESP_TIMEOUT,
    ],
]);

$response = @file_get_contents($url, false, $ctx);

if ($response === false) {
    $err = error_get_last();
    fwrite(STDERR, date('Y-m-d H:i:s') . " FETCH FAILED: {$err['message']}\n");
    exit(1);
}

$data = json_decode($response, true);

if (!is_array($data) || !isset($data['lux'])) {
    fwrite(STDERR, date('Y-m-d H:i:s') . " INVALID JSON: {$response}\n");
    exit(1);
}

if ($data['lux'] === 'err' || !is_numeric($data['lux']) || $data['lux'] < 0) {
    fwrite(STDERR, date('Y-m-d H:i:s') . " SENSOR ERROR: lux={$data['lux']}\n");
    exit(1);
}

$lux = (float) $data['lux'];

$stmt = db()->prepare('INSERT INTO light_readings (lux) VALUES (:lux)');
$stmt->execute(['lux' => $lux]);

echo date('Y-m-d H:i:s') . " OK lux={$lux}\n";
