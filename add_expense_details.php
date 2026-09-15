<?php
require_once 'session_check.php';
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    try {
        $sql = "INSERT INTO expenses (category_id, expense_amount, created_at, expense_description)
                VALUES (:category, :amount, NOW(), :description)";
        
        $description = !empty($_POST['description']) ? $_POST['description'] : '';
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':category' => $_POST['category'],
            ':amount' => $_POST['amount'],
            ':description' => $description
        ]);
        
        header("Location: index.php");
        exit();
    } catch(PDOException $e) {
        die("Save failed: " . $e->getMessage());
    }
}

try {
    // Fetch category details
    $stmt = $pdo->prepare("SELECT * FROM expense_categories WHERE category_id = ?");
    $stmt->execute([$_GET['category']]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        header("Location: select_category.php");
        exit();
    }

    // Fetch top 3 descriptions for this category
    $stmt = $pdo->prepare("
        SELECT expense_description, COUNT(*) as count
        FROM expenses
        WHERE category_id = :category AND expense_description <> ''
        GROUP BY expense_description
        ORDER BY count DESC
        LIMIT 3
    ");
    $stmt->execute([':category' => $_GET['category']]);
    $topDescriptions = $stmt->fetchAll(PDO::FETCH_ASSOC);

} catch(PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Expense - Details</title>
  <link href="https://unpkg.com/flowbite@latest/dist/flowbite.min.css" rel="stylesheet" />
  <link href="styles.css" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>

</head>
<body class="bg-gray-50">
    <?php require_once 'nav.php'; ?>
    <main class="container mx-auto px-4 py-8 pb-20 sm:pb-6 max-w-2xl">
        <div class="flex items-center gap-3 mb-2">
            <a href="select_category.php" class="text-gray-500 hover:text-gray-700" aria-label="Back to categories">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
                </svg>
            </a>
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">New Expense</h1>
        </div>
        <p class="ml-8 mb-6 text-sm text-gray-500">
            Category: <span class="font-medium text-gray-700"><?php echo htmlspecialchars($category['category_name']); ?></span>
        </p>
        
        <div class="bg-white rounded-lg shadow-sm p-4 sm:p-6">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?php echo htmlspecialchars(csrf_token()); ?>">
                <input type="hidden" name="category" value="<?php echo $category['category_id']; ?>">
                
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Amount</label>
                    <div class="flex items-center gap-2 shadow border rounded w-full py-3 px-3 bg-white focus-within:ring-2 focus-within:ring-blue-500">
                        <span class="text-2xl font-semibold leading-none text-gray-400 select-none">&euro;</span>
                        <input type="number"
                               step="0.01"
                               name="amount"
                               required
                               inputmode="decimal"
                               enterkeyhint="done"
                               class="flex-1 min-w-0 border-0 bg-transparent p-0 text-2xl font-semibold leading-none text-gray-800 focus:outline-none focus:ring-0"
                               autofocus>
                    </div>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                    <input type="text" 
                           name="description" 
                           id="description" 
                           class="shadow border rounded w-full py-2 px-3 text-gray-700">
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Quick Select Description</label>
                    <div class="flex flex-wrap gap-2">
                        <?php foreach($topDescriptions as $desc) { ?>
                            <button type="button"
                                    data-desc="<?php echo htmlspecialchars($desc['expense_description'], ENT_QUOTES); ?>"
                                    class="desc-chip border border-blue-200 bg-blue-50 hover:bg-blue-100 active:scale-95 text-blue-700 font-medium py-2 px-4 rounded-full text-sm transition-transform">
                                <?php echo htmlspecialchars($desc['expense_description']); ?>
                            </button>
                        <?php } ?>
                    </div>
                </div>              
              
                <div class="flex items-center justify-between pt-4">
                    <button type="submit" 
                            class="w-full sm:w-auto bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-8 rounded text-sm sm:text-base">
                        Save
                    </button>
                </div>
            </form>
        </div>
    </main>

    <script>
    document.querySelectorAll('.desc-chip').forEach(function (chip) {
        chip.addEventListener('click', function () {
            document.getElementById('description').value = chip.dataset.desc;
        });
    });
    </script>
  
  <script src="https://unpkg.com/flowbite@latest/dist/flowbite.bundle.js"></script>
  
</body>
</html>
