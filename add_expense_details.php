<?php
require_once 'session_check.php';
require_once 'config.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sql = "INSERT INTO expense (CategID, ExpenseAmount, ExpenseDate, ExpenseDescr)
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
    $stmt = $pdo->prepare("SELECT * FROM expensetype WHERE categID = ?");
    $stmt->execute([$_GET['category']]);
    $category = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$category) {
        header("Location: add_record.php");
        exit();
    }

    // Fetch top 3 descriptions for this category
    $stmt = $pdo->prepare("
        SELECT ExpenseDescr, COUNT(*) as count 
        FROM expense 
        WHERE CategID = :category 
        GROUP BY ExpenseDescr 
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
  <script src="https://cdn.tailwindcss.com"></script>

</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-4 sm:py-8 max-w-2xl">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800">
                New Expense: <?php echo htmlspecialchars($category['categDescr']); ?>
            </h1>
            <a href="add_record.php" 
               class="text-blue-500 hover:text-blue-700 text-sm sm:text-base">
                Back to Categories
            </a>
        </div>
        
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
            <form method="POST" class="space-y-4">
                <input type="hidden" name="category" value="<?php echo $category['categID']; ?>">
                
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Amount</label>
                    <input type="number" 
                           step="1" 
                           name="amount" 
                           required
                           class="shadow border rounded w-full py-2 px-3 text-gray-700"
                           autofocus>
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
                                    onclick="setDescription('<?php echo htmlspecialchars($desc['ExpenseDescr']); ?>')"
                                    class="bg-gray-100 hover:bg-gray-200 text-gray-700 font-medium py-2 px-4 rounded text-sm">
                                <?php echo htmlspecialchars($desc['ExpenseDescr']); ?>
                            </button>
                        <?php } ?>
                    </div>
                </div>              
              
                <div class="flex items-center justify-between pt-4">
                    <button type="submit" 
                            class="w-full sm:w-auto bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-8 rounded text-sm sm:text-base">
                        Save Expense
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    function setDescription(text) {
        document.getElementById('description').value = text;
    }
    </script>
  
  <script src="https://unpkg.com/flowbite@latest/dist/flowbite.bundle.js"></script>
  
</body>
</html>
