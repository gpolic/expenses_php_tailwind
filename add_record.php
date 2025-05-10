<?php
require_once 'session_check.php';
require_once 'config.php';

try {
    // Define default category IDs
    $defaultCategories = [2, 4, 8, 9, 10, 15, 23, 24, 30, 32];
    
    // Get all categories
    $stmt = $pdo->query("SELECT * FROM expensetype ORDER BY categDescr");
    $allCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Filter for initial display
    $showAll = isset($_GET['show_all']) && $_GET['show_all'] == 1;
    $displayCategories = $showAll ? $allCategories : array_filter($allCategories, function($cat) use ($defaultCategories) {
        return in_array($cat['categID'], $defaultCategories);
    });
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
                    <a href="add_expense_details.php?category=<?php echo $category['categID']; ?>" 
                       class="block p-2 sm:p-4 border rounded-lg hover:bg-blue-50 text-center transition-colors">
                        <span class="text-sm sm:text-base font-medium text-gray-700">
                            <?php echo htmlspecialchars($category['categDescr']); ?>
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
