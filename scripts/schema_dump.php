<?php

/**
 * Lightweight schema dumper for environments without `mysqldump`.
 * Generates `database/schema/mysql-schema.sql` containing DDL plus
 * the current contents of the `migrations` table so Laravel can
 * bootstrap from the dump, then run only new migrations.
 */

$env = parse_ini_file(__DIR__ . '/../.env', false, INI_SCANNER_RAW);

$host     = $env['DB_HOST'] ?? '127.0.0.1';
$port     = $env['DB_PORT'] ?? '3306';
$database = $env['DB_DATABASE'] ?? '';
$username = $env['DB_USERNAME'] ?? '';
$password = $env['DB_PASSWORD'] ?? '';

if (!$database) {
    fwrite(STDERR, "DB_DATABASE not set in .env\n");
    exit(1);
}

$dsn  = "mysql:host={$host};port={$port};dbname={$database}";
$pdo  = new PDO($dsn, $username, $password, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);

$tables = $pdo->query("
    SELECT table_name
    FROM information_schema.tables
    WHERE table_schema = {$pdo->quote($database)}
      AND table_type   = 'BASE TABLE'
    ORDER BY table_name
")->fetchAll(PDO::FETCH_COLUMN);

$lines = [];
$lines[] = '-- Schema dump generated ' . date('Y-m-d H:i:s');
$lines[] = 'SET FOREIGN_KEY_CHECKS=0;';

foreach ($tables as $table) {
    $createRow = $pdo->query("SHOW CREATE TABLE `{$table}`")->fetch();
    $createSql = $createRow['Create Table'] ?? null;
    if (!$createSql) {
        continue;
    }
    $lines[] = "DROP TABLE IF EXISTS `{$table}`;";
    $lines[] = $createSql . ';';
}

$lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

// Persist migration history so old migrations are marked as run.
$migrations = $pdo->query("
    SELECT id, migration, batch
    FROM migrations
    ORDER BY id
")->fetchAll();

if ($migrations) {
    $values = array_map(
        fn ($m) => sprintf("(%d, %s, %d)", $m['id'], $pdo->quote($m['migration']), $m['batch']),
        $migrations
    );
    $lines[] = 'INSERT INTO `migrations` (`id`, `migration`, `batch`) VALUES';
    $lines[] = '  ' . implode(",\n  ", $values) . ';';
}

$outputDir  = __DIR__ . '/../database/schema';
$outputFile = $outputDir . '/mysql-schema.sql';

if (!is_dir($outputDir)) {
    mkdir($outputDir, 0777, true);
}

file_put_contents($outputFile, implode("\n\n", $lines) . "\n");

echo "Schema written to {$outputFile}\n";
