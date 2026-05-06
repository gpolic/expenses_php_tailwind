<?php
require_once 'session_check.php';
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $category_name = trim($_POST['category_name'] ?? '');
    if (empty($category_name)) {
        $error_message = "Category name cannot be empty.";
    } else {
        try {
            $checkStmt = $pdo->prepare("SELECT category_id FROM expense_categories WHERE category_name = :name");
            $checkStmt->execute([':name' => $category_name]);
            if ($checkStmt->rowCount() > 0) {
                $error_message = "Category '" . htmlspecialchars($category_name) . "' already exists.";
            } else {
                $stmt = $pdo->prepare("INSERT INTO expense_categories (category_name) VALUES (:name)");
                $stmt->execute([':name' => $category_name]);
                header("Location: manage_category.php");
                exit();
            }
        } catch (PDOException $e) {
            $error_message = "Error adding category: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Category - Expense Tracker</title>
    <link href="https://unpkg.com/flowbite@latest/dist/flowbite.min.css" rel="stylesheet" />
    <link href="styles.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <?php require_once 'nav.php'; ?>
    <main class="container mx-auto px-4 py-8 pb-20 sm:pb-6 max-w-2xl">
        <div class="flex items-center gap-3 mb-6">
            <a href="manage_category.php" class="text-gray-500 hover:text-gray-700">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Add Category</h1>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow-sm p-4 sm:p-6">
            <form method="POST" action="">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <div class="mb-4">
                    <label for="category_name" class="block text-sm font-medium text-gray-700 mb-1">Category name</label>
                    <input type="text"
                           id="category_name"
                           name="category_name"
                           required
                           maxlength="25"
                           autofocus
                           placeholder="e.g. Groceries"
                           value="<?php echo htmlspecialchars($_POST['category_name'] ?? ''); ?>"
                           class="w-full px-3 py-2 border border-gray-300 rounded-md text-sm focus:outline-none focus:ring-2 focus:ring-blue-500">
                    <p class="text-xs text-gray-400 mt-1">Max 25 characters</p>
                </div>
                <button type="submit"
                        class="w-full bg-blue-600 hover:bg-blue-700 active:bg-blue-800 text-white font-semibold py-3 px-4 rounded-lg text-sm">
                    Add Category
                </button>
            </form>
        </div>
    </main>
    <script src="https://unpkg.com/flowbite@latest/dist/flowbite.bundle.js"></script>
</body>
</html>
