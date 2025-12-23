<?php
require_once 'session_check.php';
require_once 'config.php';

// Handle form submission for adding a new category
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $category_name = trim($_POST['category_name']);
        
        if (!empty($category_name)) {
            // Check if category already exists
            $checkStmt = $pdo->prepare("SELECT category_id FROM expense_categories WHERE category_name = :category_name");
            $checkStmt->execute([':category_name' => $category_name]);
            
            if ($checkStmt->rowCount() == 0) {
                // Insert new category
                $stmt = $pdo->prepare("INSERT INTO expense_categories (category_name) VALUES (:category_name)");
                $stmt->execute([':category_name' => $category_name]);
                
                $success_message = "Category '" . htmlspecialchars($category_name) . "' added successfully!";
            } else {
                $error_message = "Category '" . htmlspecialchars($category_name) . "' already exists!";
            }
        } else {
            $error_message = "Category name cannot be empty!";
        }
    } catch(PDOException $e) {
        $error_message = "Error adding category: " . $e->getMessage();
    }
}

// Fetch all existing categories
try {
    $stmt = $pdo->query("SELECT * FROM expense_categories ORDER BY category_name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $error_message = "Error fetching categories: " . $e->getMessage();
    $categories = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Expense Category</title>
    <link href="https://unpkg.com/flowbite@latest/dist/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800">Manage Expense Categories</h1>
            <a href="index.php"
               class="text-blue-500 hover:text-blue-700 text-sm sm:text-base font-medium self-center">
                Back to List
            </a>
        </div>
        
        <!-- Add Category Form -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6 mb-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Add New Category</h2>
            
            <?php if (isset($error_message)): ?>
                <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg">
                    <?php echo $error_message; ?>
                </div>
            <?php endif; ?>
            
            <?php if (isset($success_message)): ?>
                <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-lg">
                    <?php echo $success_message; ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="mb-4">
                    <label for="category_name" class="block text-sm font-medium text-gray-700 mb-1">Category Name</label>
                    <input type="text" 
                           id="category_name" 
                           name="category_name" 
                           required 
                           maxlength="25"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="mt-1 text-xs text-gray-500">Maximum 25 characters</p>
                </div>
                
                <button type="submit" 
                        class="w-full sm:w-auto bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded focus:outline-none focus:ring-2 focus:ring-blue-300">
                    Add Category
                </button>
            </form>
        </div>
        
        <!-- Existing Categories List -->
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
            <h2 class="text-lg font-semibold text-gray-800 mb-4">Existing Categories</h2>
            
            <?php if (count($categories) > 0): ?>
                <div class="grid grid-cols-2 sm:grid-cols-3 gap-2 sm:gap-4">
                    <?php foreach($categories as $category): ?>
                        <div class="p-2 sm:p-4 border rounded-lg bg-gray-50 text-center">
                            <span class="text-sm sm:text-base font-medium text-gray-700">
                                <?php echo htmlspecialchars($category['category_name']); ?>
                            </span>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-4">No categories found.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <script src="https://unpkg.com/flowbite@latest/dist/flowbite.bundle.js"></script>
</body>
</html>