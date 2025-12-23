<?php
require_once 'session_check.php';
require_once 'config.php';

try {
    // Get the 10 most frequent categories from the past 2 months
    $sql = "SELECT ec.category_id, ec.category_name, COUNT(e.expense_id) as frequency
            FROM expense_categories ec
            LEFT JOIN expenses e ON ec.category_id = e.category_id
            WHERE e.created_at >= DATE_SUB(NOW(), INTERVAL 3 MONTH) OR e.created_at IS NULL
            GROUP BY ec.category_id, ec.category_name
            ORDER BY frequency DESC, ec.category_name
            LIMIT 12";
    
    $stmt = $pdo->query($sql);
    $topCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Get all categories
    $stmt = $pdo->query("SELECT * FROM expense_categories ORDER BY category_name");
    $allCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Check if there are any expense records
    $checkRecordsStmt = $pdo->query("SELECT COUNT(*) as count FROM expenses");
    $recordCount = $checkRecordsStmt->fetch(PDO::FETCH_ASSOC)['count'];
    
    // If no records exist, use all categories; otherwise use the top 10
    if ($recordCount == 0) {
        $defaultCategories = array_column($allCategories, 'category_id');
    } else {
        $defaultCategories = array_column($topCategories, 'category_id');
    }
    
    // Filter for initial display
    $showAll = isset($_GET['show_all']) && $_GET['show_all'] == 1;
    
    if ($showAll) {
        $displayCategories = $allCategories;
    } else {
        // Create a lookup array to preserve the frequency order from topCategories
        $categoryOrder = array_flip($defaultCategories);
        
        // Filter and sort the categories based on frequency order
        $displayCategories = array_filter($allCategories, function($cat) use ($defaultCategories) {
            return in_array($cat['category_id'], $defaultCategories);
        });
        
        // Sort the filtered categories by the frequency order
        usort($displayCategories, function($a, $b) use ($categoryOrder) {
            return $categoryOrder[$a['category_id']] - $categoryOrder[$b['category_id']];
        });
    }
} catch(PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Expense - Select Category</title>
  <link href="https://unpkg.com/flowbite@latest/dist/flowbite.min.css" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-4 sm:py-8 max-w-2xl">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800">Select Category</h1>
            <a href="index.php" 
               class="text-blue-500 hover:text-blue-700 text-sm sm:text-base">
                Back to List
            </a>
        </div>
        
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 sm:gap-4">
                <?php foreach($displayCategories as $category) { ?>
                    <a href="add_expense_details.php?category=<?php echo $category['category_id']; ?>"
                       class="block p-2 sm:p-4 border rounded-lg hover:bg-blue-50 text-center transition-colors">
                        <span class="text-sm sm:text-base font-medium text-gray-700">
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </span>
                    </a>
                <?php } ?>
            </div>

            <?php if (!$showAll) { ?>
                <div class="mt-6 text-center">
                    <a href="?show_all=1" 
                       class="inline-block bg-gray-500 hover:bg-gray-600 text-white text-sm font-bold py-2 px-4 rounded">
                        Expand Categories
                    </a>
                </div>
            <?php } else { ?>
                <div class="mt-6 text-center">
                    <a href="?" 
                       class="inline-block bg-gray-500 hover:bg-gray-600 text-white text-sm font-bold py-2 px-4 rounded">
                        Show Less
                    </a>
                </div>
            <?php } ?>
        </div>
    </div>
  <script src="https://unpkg.com/flowbite@latest/dist/flowbite.bundle.js"></script>

</body>
</html>
