<?php
/**
 * GET /api/readings.php
 *
 * Query params:
 *   range  = 1h | 6h | 24h | 7d | 30d  (default: 24h)
 *   limit  = int                         (default: 500, max: 5000)
 *   stats  = 1                           (include min/max/avg)
 */

require __DIR__ . '/../config.php';

header('Content-Type: application/json; charset=utf-8');
header('Access-Control-Allow-Origin: *');

$rangeMap = [
    '1h'  => '1 HOUR',
    '6h'  => '6 HOUR',
    '24h' => '24 HOUR',
    '7d'  => '7 DAY',
    '30d' => '30 DAY',
];

$range = $_GET['range'] ?? '24h';
$interval = $rangeMap[$range] ?? $rangeMap['24h'];

$limit = min(max((int)($_GET['limit'] ?? 500), 1), 5000);

$pdo = db();

$sql = "SELECT lux, recorded_at
        FROM light_readings
        WHERE recorded_at >= NOW() - INTERVAL {$interval}
        ORDER BY recorded_at ASC
        LIMIT :limit";

$stmt = $pdo->prepare($sql);
$stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
$stmt->execute();
$rows = $stmt->fetchAll();

$result = ['data' => $rows];

if (!empty($_GET['stats'])) {
    $sql = "SELECT
                MIN(lux) AS min_lux,
                MAX(lux) AS max_lux,
                ROUND(AVG(lux), 1) AS avg_lux,
                COUNT(*) AS total
            FROM light_readings
            WHERE recorded_at >= NOW() - INTERVAL {$interval}";
    $stats = $pdo->query($sql)->fetch();
    $result['stats'] = $stats;
}

$latest = $pdo->query("SELECT lux, recorded_at FROM light_readings ORDER BY id DESC LIMIT 1")->fetch();
$result['latest'] = $latest ?: null;

echo json_encode($result, JSON_UNESCAPED_UNICODE);
