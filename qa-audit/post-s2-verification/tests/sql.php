<?php

if (getenv('DB_PORT') !== '13318' || ! str_starts_with(getenv('DB_DATABASE'), 'clinicos_post_s2_')) {
    throw new RuntimeException('Dedicated test database guard');
}
$db = new PDO('mysql:host=127.0.0.1;port=13318;dbname='.getenv('DB_DATABASE'), 'root', '', [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$request = json_decode(stream_get_contents(STDIN), true, flags: JSON_THROW_ON_ERROR);
$query = $db->prepare($request['query']);
$query->execute($request['parameters'] ?? []);
$result = match ($request['mode']) {
    'get' => $query->fetch(PDO::FETCH_ASSOC) ?: null,
    'all' => $query->fetchAll(PDO::FETCH_ASSOC),
    'run' => ['changes' => $query->rowCount(), 'lastInsertRowid' => $db->lastInsertId()],
};
echo json_encode($result, JSON_THROW_ON_ERROR);
