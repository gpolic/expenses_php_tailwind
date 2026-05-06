<?php
require_once 'session_check.php';
require_once 'config.php';

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = $_POST['action'] ?? '';

    if ($action === 'delete') {
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
    <title>Categories</title>
    <link href="https://unpkg.com/flowbite@latest/dist/flowbite.min.css" rel="stylesheet" />
    <link href="styles.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
  
</head>
<body class="bg-gray-50">
    <?php require_once 'nav.php'; ?>

    <!-- Mobile sticky mini-header -->
    <div id="sticky-header" class="sm:hidden fixed top-0 left-0 right-0 z-40 bg-white/75 backdrop-blur-lg backdrop-saturate-150 border-b border-gray-200/60 -translate-y-full transition-transform duration-300 ease-out">
        <div class="container mx-auto px-4 h-12 flex items-center">
            <span class="text-base font-semibold text-gray-900">Categories</span>
        </div>
    </div>

    <main class="container mx-auto px-4 py-8 pb-20 sm:pb-6 max-w-2xl">

        <!-- Header -->
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <h1 id="page-title" class="text-2xl sm:text-3xl font-bold text-gray-800">Categories</h1>
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

        <!-- Categories Table -->
        <div class="bg-white rounded-lg shadow-sm p-4 sm:p-6">
            <h2 class="text-base font-semibold text-gray-800 mb-4">
                Existing Categories
                <span class="text-gray-400 font-normal text-sm">(<?php echo count($categories); ?>)</span>
            </h2>

            <?php if (count($categories) > 0): ?>
                <ul class="flex flex-col gap-2">
                    <?php foreach ($categories as $cat): ?>
                    <li>
                        <a href="edit_category.php?id=<?php echo $cat['category_id']; ?>"
                           class="flex items-center bg-gray-50 hover:bg-blue-50 active:bg-blue-100 rounded-lg px-4 py-3 min-h-[48px] transition-colors">
                            <span class="font-medium text-gray-700 text-sm">
                                <?php echo htmlspecialchars($cat['category_name']); ?>
                            </span>
                        </a>
                    </li>
                    <?php endforeach; ?>
                </ul>
            <?php else: ?>
                <p class="text-gray-500 text-center py-6 text-sm">No categories found.</p>
            <?php endif; ?>
        </div>

    </main>

    <script src="https://unpkg.com/flowbite@latest/dist/flowbite.bundle.js"></script>
    <script>
    const pageTitle = document.getElementById('page-title');
    const stickyHeader = document.getElementById('sticky-header');
    if (pageTitle && stickyHeader) {
        const titleObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    stickyHeader.classList.add('-translate-y-full');
                } else {
                    stickyHeader.classList.remove('-translate-y-full');
                }
            });
        }, { threshold: 0 });
        titleObserver.observe(pageTitle);
    }
    </script>
</body>
</html>
