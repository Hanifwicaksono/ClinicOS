<?php

if (getenv('DB_PORT') !== '13318' || ! str_starts_with(getenv('DB_DATABASE'), 'clinicos_post_s2_runtime_')) {
    throw new RuntimeException('Dedicated fixture import guard');
}
$source = new PDO('sqlite:'.(getenv('POST_SOURCE_SQLITE') ?: getenv('AUDIT_ROOT').'/audit.sqlite'));
$target = new PDO('mysql:host=127.0.0.1;port=13318;dbname='.getenv('DB_DATABASE'), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$target->exec('SET FOREIGN_KEY_CHECKS=0');
$counts = [];
foreach ($source->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'")->fetchAll(PDO::FETCH_COLUMN) as $table) {
    if (in_array($table, ['migrations', 'jobs', 'sessions', 'cache', 'cache_locks'], true) || ($table === 'failed_jobs' && ! getenv('POST_INCLUDE_FAILED'))) {
        continue;
    }
    $columns = $target->query('SHOW COLUMNS FROM `'.$table.'`')->fetchAll(PDO::FETCH_COLUMN);
    foreach ($source->query('SELECT * FROM "'.$table.'"')->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $row = array_intersect_key($row, array_flip($columns));
        $query = $target->prepare('INSERT INTO `'.$table.'` (`'.implode('`,`', array_keys($row)).'`) VALUES ('.implode(',', array_fill(0, count($row), '?')).')');
        $query->execute(array_values($row));
        $counts[$table] = ($counts[$table] ?? 0) + 1;
    }
}
$target->exec('SET FOREIGN_KEY_CHECKS=1');
echo json_encode(['tables' => count($counts), 'rows' => array_sum($counts)], JSON_THROW_ON_ERROR);
