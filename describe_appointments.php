<?php
require __DIR__ . '/../db.php';

try {
    $stmt = $pdo->query("DESCRIBE appointments");
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode($rows, JSON_PRETTY_PRINT);
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage();
}
