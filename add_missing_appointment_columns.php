<?php
require __DIR__ . '/../db.php';

// Desired columns to ensure exist
$columns = [
    'guest_name' => "VARCHAR(255) DEFAULT NULL",
    'guest_contact' => "VARCHAR(100) DEFAULT NULL",
    'guest_room' => "VARCHAR(50) DEFAULT NULL"
];

try {
    $existing = [];
    $stmt = $pdo->query("DESCRIBE appointments");
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existing[$row['Field']] = $row;
    }

    $toAdd = [];
    foreach ($columns as $col => $def) {
        if (!isset($existing[$col])) {
            $toAdd[$col] = $def;
        }
    }

    if (empty($toAdd)) {
        echo "No columns to add.\n";
        exit(0);
    }

    $parts = [];
    foreach ($toAdd as $col => $def) {
        $parts[] = "ADD COLUMN `$col` $def";
    }
    $sql = "ALTER TABLE appointments " . implode(', ', $parts);
    $pdo->exec($sql);
    echo "Added columns: " . implode(', ', array_keys($toAdd)) . "\n";
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}
