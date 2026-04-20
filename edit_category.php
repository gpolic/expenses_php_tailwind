<?php
require_once 'session_check.php';
require_once 'config.php';

$category_id = (int)($_GET['id'] ?? 0);
if (!$category_id) {
    header("Location: manage_category.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete'])) {
        try {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) as cnt FROM expenses WHERE category_id = :id");
            $checkStmt->execute([':id' => $category_id]);
            $cnt = $checkStmt->fetch(PDO::FETCH_ASSOC)['cnt'];
            if ($cnt > 0) {
                $error_message = "Cannot delete: this category has {$cnt} expense(s) linked to it.";
            } else {
                $stmt = $pdo->prepare("DELETE FROM expense_categories WHERE category_id = :id");
                $stmt->execute([':id' => $category_id]);
                header("Location: manage_category.php");
                exit();
            }
        } catch (PDOException $e) {
            $error_message = "Error deleting category: " . $e->getMessage();
        }
    } else {
        try {
            $category_name = trim($_POST['category_name']);
            if (empty($category_name)) {
                $error_message = "Category name cannot be empty.";
            } else {
                $checkStmt = $pdo->prepare("SELECT category_id FROM expense_categories WHERE category_name = :name AND category_id != :id");
                $checkStmt->execute([':name' => $category_name, ':id' => $category_id]);
                if ($checkStmt->rowCount() > 0) {
                    $error_message = "Category '" . htmlspecialchars($category_name) . "' already exists.";
                } else {
                    $stmt = $pdo->prepare("UPDATE expense_categories SET category_name = :name WHERE category_id = :id");
                    $stmt->execute([':name' => $category_name, ':id' => $category_id]);
                    header("Location: manage_category.php");
                    exit();
                }
            }
        } catch (PDOException $e) {
            $error_message = "Error updating category: " . $e->getMessage();
        }
    }
}

try {
    $stmt = $pdo->prepare("SELECT * FROM expense_categories WHERE category_id = :id");
    $stmt->execute([':id' => $category_id]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$category) {
        header("Location: manage_category.php");
        exit();
    }
} catch (PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Category</title>
    <link href="https://unpkg.com/flowbite@latest/dist/flowbite.min.css" rel="stylesheet" />
    <link href="styles.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8 max-w-2xl">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800">Edit Category</h1>
            <a href="manage_category.php" class="text-blue-500 hover:text-blue-700 text-sm font-medium">
                &larr; Back to Categories
            </a>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
            <form method="POST" class="space-y-4">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Category Name</label>
                    <input type="text"
                           name="category_name"
                           value="<?php echo htmlspecialchars($category['category_name']); ?>"
                           required
                           maxlength="25"
                           class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="flex flex-col sm:flex-row items-center justify-between gap-3 pt-4">
                    <div class="flex gap-3 w-full sm:w-auto">
                        <button type="submit"
                                class="flex-1 sm:flex-none bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-8 rounded">
                            Save Changes
                        </button>
                        <button type="button"
                                onclick="confirmDelete()"
                                class="flex-1 sm:flex-none bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-8 rounded">
                            Delete
                        </button>
                    </div>
                    <a href="manage_category.php"
                       class="w-full sm:w-auto text-center text-gray-500 hover:text-gray-700 font-bold py-2 px-4">
                        Cancel
                    </a>
                </div>
            </form>

            <form id="deleteForm" method="POST" class="hidden">
                <input type="hidden" name="delete" value="1">
            </form>
        </div>
    </div>

    <script>
    function confirmDelete() {
        if (confirm('Delete this category? This cannot be undone.')) {
            document.getElementById('deleteForm').submit();
        }
    }
    </script>
    <script src="https://unpkg.com/flowbite@latest/dist/flowbite.bundle.js"></script>
</body>
</html>
