<?php
require_once 'session_check.php';
require_once 'config.php';

header('Content-Type: application/json');

$offset = isset($_GET['offset']) ? (int)$_GET['offset'] : 0;
$limit = isset($_GET['limit']) ? (int)$_GET['limit'] : 20;

if ($offset < 0) $offset = 0;
if ($limit < 1) $limit = 20;
if ($limit > 50) $limit = 50;

try {
    $sql = "SELECT e.*, ec.category_name
            FROM expenses e
            JOIN expense_categories ec ON e.category_id = ec.category_id
            ORDER BY e.created_at DESC
            LIMIT :limit OFFSET :offset";
    $stmt = $pdo->prepare($sql);
    $stmt->bindValue(':limit', $limit, PDO::PARAM_INT);
    $stmt->bindValue(':offset', $offset, PDO::PARAM_INT);
    $stmt->execute();
    $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $data = array_map(function($row) {
        return [
            'expense_id' => (int)$row['expense_id'],
            'date' => date('d/m/Y', strtotime($row['created_at'])),
            'category_name' => htmlspecialchars($row['category_name']),
            'expense_amount' => number_format($row['expense_amount'], 2, '.', ''),
            'expense_description' => htmlspecialchars($row['expense_description']),
        ];
    }, $rows);

    echo json_encode($data);
} catch (PDOException $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Database error']);
}
