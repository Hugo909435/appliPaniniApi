<?php
// Simple pack drop simulation (no Laravel bootstrap required).

function loadEnv(string $path): array
{
    if (!file_exists($path)) return [];
    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $env = [];
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#')) continue;
        $pos = strpos($line, '=');
        if ($pos === false) continue;
        $key = trim(substr($line, 0, $pos));
        $val = trim(substr($line, $pos + 1));
        if ($val !== '' && ($val[0] === '"' || $val[0] === "'")) {
            $val = trim($val, "\"'");
        }
        $env[$key] = $val;
    }
    return $env;
}

function pickRandomRarity(array $rarities): string
{
    $totalWeight = array_sum($rarities);
    if ($totalWeight <= 0) return array_key_first($rarities);
    $random = mt_rand(1, (int) ($totalWeight * 100)) / 100;
    $current = 0;
    foreach ($rarities as $rarity => $probability) {
        $current += $probability;
        if ($random <= $current) return $rarity;
    }
    return array_key_first($rarities);
}

$packsToSim = isset($argv[1]) ? max(1, (int) $argv[1]) : 10000;
$packId = isset($argv[2]) ? (int) $argv[2] : null;

$env = loadEnv(__DIR__ . '/../.env');
$dbHost = $env['DB_HOST'] ?? '127.0.0.1';
$dbPort = $env['DB_PORT'] ?? '3306';
$dbName = $env['DB_DATABASE'] ?? '';
$dbUser = $env['DB_USERNAME'] ?? '';
$dbPass = $env['DB_PASSWORD'] ?? '';

if ($dbName === '' || $dbUser === '') {
    fwrite(STDERR, "Missing DB credentials in .env\n");
    exit(1);
}

$dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";
$pdo = new PDO($dsn, $dbUser, $dbPass, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);

$pack = null;
if ($packId) {
    $stmt = $pdo->prepare("SELECT * FROM packs WHERE id = :id LIMIT 1");
    $stmt->execute(['id' => $packId]);
    $pack = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$pack) {
    $stmt = $pdo->query("SELECT * FROM packs WHERE is_active = 1 ORDER BY price ASC LIMIT 1");
    $pack = $stmt->fetch(PDO::FETCH_ASSOC);
}
if (!$pack) {
    fwrite(STDERR, "No pack found.\n");
    exit(1);
}

$defaults = [
    'common'    => 38.0,
    'uncommon'  => 28.0,
    'rare'      => 16.0,
    'epic'      => 8.0,
    'legendary' => 3.5,
    'icone'     => 1.5,
    'special'   => 5.0,
];

$boosts = [];
if (!empty($pack['rarity_boosts'])) {
    $decoded = json_decode($pack['rarity_boosts'], true);
    if (is_array($decoded)) $boosts = $decoded;
}
$rarities = $defaults;
foreach ($boosts as $k => $v) $rarities[$k] = (float) $v;

// Available counts per rarity (non-exclusive)
$stmt = $pdo->query(
    "SELECT r.slug, COUNT(*) AS total
     FROM cards c
     JOIN rarities r ON r.id = c.rarities_id
     WHERE c.is_exclusive = 0
     GROUP BY r.slug"
);
$availableCounts = [];
foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
    $availableCounts[$row['slug']] = (int) $row['total'];
}

// Special count (rarity = special)
$stmt = $pdo->query(
    "SELECT COUNT(*) AS total
     FROM cards c
     JOIN rarities r ON r.id = c.rarities_id
     WHERE r.slug = 'special'"
);
$specialCount = (int) $stmt->fetchColumn();

$filtered = [];
foreach ($rarities as $slug => $weight) {
    if ($weight <= 0) continue;
    if ($slug === 'special') {
        if ($specialCount > 0) $filtered[$slug] = $weight;
    } else {
        if (array_key_exists($slug, $availableCounts)) $filtered[$slug] = $weight;
    }
}
if (empty($filtered)) $filtered = $rarities;

$cardCount = (int) ($pack['card_count'] ?? 5);
$totalCards = $packsToSim * $cardCount;
$counts = [];
foreach ($filtered as $slug => $_) $counts[$slug] = 0;

for ($i = 0; $i < $totalCards; $i++) {
    $slug = pickRandomRarity($filtered);
    $counts[$slug] = ($counts[$slug] ?? 0) + 1;
}

$totalWeight = array_sum($filtered);

echo "Pack: {$pack['name']} (id {$pack['id']})\n";
echo "Simulated packs: {$packsToSim}\n";
echo "Cards per pack: {$cardCount}\n";
echo "Total cards: {$totalCards}\n\n";

echo "Available cards (non-exclusive):\n";
foreach ($availableCounts as $slug => $cnt) {
    echo "  - {$slug}: {$cnt}\n";
}
echo "Special cards: {$specialCount}\n\n";

echo "Weights used (filtered):\n";
foreach ($filtered as $slug => $w) {
    echo "  - {$slug}: {$w}\n";
}
echo "\nObserved distribution:\n";
foreach ($counts as $slug => $cnt) {
    $pct = $totalCards > 0 ? ($cnt / $totalCards) * 100 : 0;
    $expected = $totalWeight > 0 ? ($filtered[$slug] / $totalWeight) * 100 : 0;
    printf("  - %-9s: %6d (%.2f%%) | expected %.2f%%\n", $slug, $cnt, $pct, $expected);
}

echo "\n";
echo "Tip: run with a higher sample size to stabilize results (e.g. 100000).\n";
