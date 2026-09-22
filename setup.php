<?php
require __DIR__ . '/db.php';
$db = get_db();
$sql = file_get_contents(__DIR__ . '/schema.sql');
header('Content-Type: text/plain; charset=utf-8');
if ($db->multi_query($sql)) {
    do {
        if ($res = $db->store_result()) $res->free();
    } while ($db->more_results() && $db->next_result());
}
if ($db->errno) {
    echo "ERROR: " . $db->error . "\n";
} else {
    echo "OK: schema applied.\n";
}
