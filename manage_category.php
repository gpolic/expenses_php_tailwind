<?php
require_once 'session_check.php';
require_once 'config.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'add') {
        try {
            $category_name = trim($_POST['category_name']);
            if (empty($category_name)) {
                $error_message = "Category name cannot be empty.";
            } else {
                $checkStmt = $pdo->prepare("SELECT category_id FROM expense_categories WHERE category_name = :name");
                $checkStmt->execute([':name' => $category_name]);
                if ($checkStmt->rowCount() > 0) {
                    $error_message = "Category '" . htmlspecialchars($category_name) . "' already exists.";
                } else {
                    $stmt = $pdo->prepare("INSERT INTO expense_categories (category_name) VALUES (:name)");
                    $stmt->execute([':name' => $category_name]);
                    $success_message = "Category '" . htmlspecialchars($category_name) . "' added successfully.";
                }
            }
        } catch (PDOException $e) {
            $error_message = "Error adding category: " . $e->getMessage();
        }

    } elseif ($action === 'delete') {
        try {
            $category_id = (int)$_POST['category_id'];
            $checkStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM expenses WHERE category_id = :id");
            $checkStmt->execute([':id' => $category_id]);
            $cnt = $checkStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
            if ($cnt > 0) {
                $error_message = "Cannot delete: this category has {$cnt} expense(s) linked to it.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM expense_categories WHERE category_id = :id");
                $stmt->execute([':id' => $category_id]);
                $success_message = "Category deleted successfully.";
            }
        } catch (PDOException $e) {
            $error_message = "Error deleting category: " . $e->getMessage();
        }
    }
}

// Fetch all categories
try {
    $stmt = $pdo->query("SELECT * FROM expense_categories ORDER BY category_name");
    $categories = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    $error_message = "Error fetching categories: " . $e->getMessage();
    $categories = [];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Manage Categories</title>
    <link href="https://unpkg.com/flowbite@latest/dist/flowbite.min.css" rel="stylesheet" />
    <link href="styles.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
  
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-2xl">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800">Manage Categories</h1>
            <a href="index.php" class="text-blue-500 hover:text-blue-700 text-sm font-medium">
                &larr; Back to List
            </a>
        </div>

        <!-- Flash messages -->
        <?php if (isset($error_message)): ?>
            <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>
        <?php if (isset($success_message)): ?>
            <div class="mb-4 p-3 bg-green-100 text-green-700 rounded-lg text-sm">
                <?php echo $success_message; ?>
            </div>
        <?php endif; ?>

        <!-- Add Category -->
        <div class="bg-white rounded-lg shadow-sm p-4 sm:p-6 mb-6">
            <h2 class="text-base font-semibold text-gray-800 mb-4">Add New Category</h2>
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="action" value="add">
                <div class="flex gap-2">
                    <input type="text"
                           name="category_name"
                           required
                           maxlength="25"
                           placeholder="Category name (max 25 chars)"
                           class="flex-1 px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <button type="submit"
                            class="bg-blue-500 hover:bg-blue-600 text-white font-bold py-2 px-4 rounded text-sm">
                        Add
                    </button>
                </div>
            </form>
        </div>

        <!-- Categories Table -->
        <div class="bg-white rounded-lg shadow-sm p-4 sm:p-6">
            <h2 class="text-base font-semibold text-gray-800 mb-4">
                Existing Categories
                <span class="text-gray-400 font-normal text-sm">(<?php echo count($categories); ?>)</span>
            </h2>

            <?php if (count($categories) > 0): ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-gray-100">
                            <tr>
                                <th class="px-4 py-2 text-left text-xs font-medium text-gray-500 uppercase">Name</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100">
                            <?php foreach ($categories as $cat): ?>
                            <tr class="hover:bg-gray-50 cursor-pointer"
                                onclick="window.location='edit_category.php?id=<?php echo $cat['category_id']; ?>'">
                                <td class="px-4 py-3 font-medium text-gray-700">
                                    <?php echo htmlspecialchars($cat['category_name']); ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <p class="text-gray-500 text-center py-6 text-sm">No categories found.</p>
            <?php endif; ?>
        </div>

    </div>

    <script src="https://unpkg.com/flowbite@latest/dist/flowbite.bundle.js"></script>
</body>
</html>
