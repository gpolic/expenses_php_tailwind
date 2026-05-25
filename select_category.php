<?php
require_once 'session_check.php';
require_once 'config.php';

try {
    // All categories sorted by all-time record count (most used first, unused last)
    $sql = "SELECT ec.category_id, ec.category_name, COUNT(e.expense_id) as frequency
            FROM expense_categories ec
            LEFT JOIN expenses e ON ec.category_id = e.category_id
            GROUP BY ec.category_id, ec.category_name
            ORDER BY frequency DESC, ec.category_name";

    $stmt = $pdo->query($sql);
    $displayCategories = $stmt->fetchAll(PDO::FETCH_ASSOC);
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
  <link href="styles.css" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>

</head>
<body class="bg-gray-50">
    <?php require_once 'nav.php'; ?>
    <main class="container mx-auto px-4 py-8 pb-20 sm:pb-6 max-w-2xl">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Select Category</h1>
        </div>
        
        <div class="bg-white/60 rounded-2xl p-3 sm:p-6">
            <div class="grid grid-cols-2 sm:grid-cols-3 gap-3 sm:gap-4">
                <?php foreach($displayCategories as $category) { ?>
                    <a href="add_expense_details.php?category=<?php echo $category['category_id']; ?>"
                       class="flex items-center justify-center text-center
                              bg-white hover:bg-blue-50
                              border border-gray-100
                              rounded-2xl shadow-sm
                              active:scale-95
                              p-3 sm:p-4
                              transition-colors duration-150">
                        <span class="text-sm sm:text-base font-medium text-gray-700 leading-tight">
                            <?php echo htmlspecialchars($category['category_name']); ?>
                        </span>
                    </a>
                <?php } ?>
            </div>

        </div>
    </main>
  <script src="https://unpkg.com/flowbite@latest/dist/flowbite.bundle.js"></script>

</body>
</html>
