<?php
/**
 * Server-Side Data Processor for jQuery DataTables
 * 
 * Securely processes requests:
 * 1. Strict input validation and whitelisting for ORDER BY clauses.
 * 2. Proper SQL parameterized queries using PDO to prevent SQL Injection.
 * 3. Output XSS sanitization (htmlspecialchars) for rendering in the DOM.
 */

header('Content-Type: application/json');

// Only allow AJAX POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method Not Allowed']);
    exit;
}

require_once __DIR__ . '/db.php';

try {
    $pdo = Database::getConnection();

    // 1. Parse and sanitize standard DataTables inputs
    $draw   = isset($_POST['draw']) ? (int)$_POST['draw'] : 1;
    $start  = isset($_POST['start']) ? (int)$_POST['start'] : 0;
    $length = isset($_POST['length']) ? (int)$_POST['length'] : 10;

    // Enforce limits and boundary checks
    if ($length < 1 || $length > 100) {
        $length = 10;
    }
    if ($start < 0) {
        $start = 0;
    }

    $searchValue = isset($_POST['search']['value']) ? trim($_POST['search']['value']) : '';

    // 2. Sorting Whitelist Setup
    // Map column indices sent by jQuery DataTables to actual database columns
    $columnMap = [
        0 => 'org_id',
        1 => 'org_name',
        2 => 'address',
        3 => 'category',
        4 => 'contact_person_name',
        5 => 'contact_person_email'
    ];

    $orderByCol = 'org_id'; // Fallback default
    if (isset($_POST['order'][0]['column'])) {
        $orderColIdx = (int)$_POST['order'][0]['column'];
        if (array_key_exists($orderColIdx, $columnMap)) {
            $orderByCol = $columnMap[$orderColIdx];
        }
    }

    $orderDir = 'ASC'; // Fallback default
    if (isset($_POST['order'][0]['dir'])) {
        $dir = strtolower($_POST['order'][0]['dir']);
        if ($dir === 'desc') {
            $orderDir = 'DESC';
        }
    }

    // 3. Count Total Records (No filter)
    $totalQuery = "SELECT COUNT(*) FROM `organizations`";
    $totalStmt = $pdo->query($totalQuery);
    $recordsTotal = (int)$totalStmt->fetchColumn();

    // 4. Build Search Query (Filtered count and data fetch)
    $searchSQL = "";
    $queryParams = [];

    if ($searchValue !== '') {
        // Parameterized search conditions to protect against SQL Injection
        $searchSQL = " WHERE `org_name` LIKE :search 
                       OR `address` LIKE :search 
                       OR `category` LIKE :search 
                       OR `contact_person_name` LIKE :search 
                       OR `contact_person_email` LIKE :search";
        $queryParams[':search'] = "%" . $searchValue . "%";
    }

    // Get Filtered count
    $filteredQuery = "SELECT COUNT(*) FROM `organizations`" . $searchSQL;
    $filteredStmt = $pdo->prepare($filteredQuery);
    $filteredStmt->execute($queryParams);
    $recordsFiltered = (int)$filteredStmt->fetchColumn();

    // 5. Fetch Actual Data
    // Column identifiers and direction are whitelisted and strictly controlled.
    // LIMIT and OFFSET are passed safely via numeric bindings.
    $dataQuery = "SELECT * FROM `organizations`" . $searchSQL . " ORDER BY `" . $orderByCol . "` " . $orderDir . " LIMIT :limit OFFSET :offset";
    
    $dataStmt = $pdo->prepare($dataQuery);

    // Bind search terms (if any)
    foreach ($queryParams as $param => $val) {
        $dataStmt->bindValue($param, $val, PDO::PARAM_STR);
    }

    // Bind Pagination limits strictly as integers
    $dataStmt->bindValue(':limit', $length, PDO::PARAM_INT);
    $dataStmt->bindValue(':offset', $start, PDO::PARAM_INT);
    
    $dataStmt->execute();
    $rows = $dataStmt->fetchAll();

    // 6. Format and Sanitize Output (Prevent XSS)
    $data = [];
    foreach ($rows as $row) {
        // Explicit sanitization before putting elements into response payload
        $data[] = [
            'org_id'              => (int)$row['org_id'],
            'org_name'            => htmlspecialchars($row['org_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'address'             => htmlspecialchars($row['address'] ?? '', ENT_QUOTES, 'UTF-8'),
            'category'            => htmlspecialchars($row['category'] ?? '', ENT_QUOTES, 'UTF-8'),
            'contact_person_name' => htmlspecialchars($row['contact_person_name'] ?? '', ENT_QUOTES, 'UTF-8'),
            'contact_person_email'=> htmlspecialchars($row['contact_person_email'] ?? '', ENT_QUOTES, 'UTF-8')
        ];
    }

    // Return DataTable standardized payload response
    echo json_encode([
        "draw"            => $draw,
        "recordsTotal"    => $recordsTotal,
        "recordsFiltered" => $recordsFiltered,
        "data"            => $data
    ]);

} catch (\Exception $e) {
    http_response_code(500);
    echo json_encode([
        'error' => 'An error occurred during search processing.'
    ]);
}
