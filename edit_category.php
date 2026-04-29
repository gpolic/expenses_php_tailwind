<?php
require_once 'session_check.php';
require_once 'config.php';

$category_id = (int)($_GET['id'] ?? 0);
if (!$category_id) {
    header("Location: manage_category.php");
    exit();
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
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
    <?php require_once 'nav.php'; ?>
    <main class="container mx-auto px-4 py-8 pb-20 sm:pb-6 max-w-2xl">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Edit Category</h1>
        </div>

        <?php if (isset($error_message)): ?>
            <div class="mb-4 p-3 bg-red-100 text-red-700 rounded-lg text-sm">
                <?php echo $error_message; ?>
            </div>
        <?php endif; ?>

        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Category Name</label>
                    <input type="text"
                           name="category_name"
                           value="<?php echo htmlspecialchars($category['category_name']); ?>"
                           required
                           maxlength="25"
                           class="shadow border rounded w-full py-2 px-3 text-gray-700 focus:outline-none focus:ring-2 focus:ring-blue-500">
                </div>

                <div class="flex items-center justify-between gap-3 pt-4">
                    <button type="submit"
                            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-8 rounded">
                        Save Changes
                    </button>
                    <a href="manage_category.php"
                       class="text-center bg-gray-200 hover:bg-gray-300 text-gray-700 font-bold py-2 px-8 rounded">
                        Cancel
                    </a>
                </div>

                <div class="pt-8 mt-4 border-t border-gray-200 flex justify-center">
                    <button type="button"
                            onclick="confirmDelete()"
                            class="border border-red-500 text-red-600 hover:bg-red-50 font-bold py-2 px-8 rounded">
                        Delete
                    </button>
                </div>
            </form>

            <form id="deleteForm" method="POST" class="hidden">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="delete" value="1">
            </form>
        </div>
    </main>

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
