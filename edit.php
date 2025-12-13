<?php
require_once 'config.php';
require_once 'session_check.php';
  
if (isset($_POST['delete'])) {
    try {
        $stmt = $pdo->prepare("DELETE FROM expense WHERE ExpenseID = :id");
        $stmt->execute([':id' => $_POST['id']]);
        header("Location: index.php");
        exit();
    } catch(PDOException $e) {
        die("Delete failed: " . $e->getMessage());
    }
}

  
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $sql = "UPDATE expense SET 
                ExpenseAmount = :amount, 
                ExpenseDate = :date, 
                ExpenseDescr = :description,
                CategID = :category
                WHERE ExpenseID = :id";
                
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':amount' => $_POST['amount'],
            ':date' => $_POST['date'],
            ':description' => $_POST['description'],
            ':category' => $_POST['category'],
            ':id' => $_POST['id']
        ]);
        
        header("Location: index.php");
        exit();
    } catch(PDOException $e) {
        die("Update failed: " . $e->getMessage());
    }
}

try {
    // Fetch expense details
    $stmt = $pdo->prepare("SELECT e.*, c.categDescr FROM expense e
            JOIN expensetype c ON e.CategID = c.categID
            WHERE e.ExpenseID = :id");
    $stmt->execute([':id' => $_GET['id']]);
    $expense = $stmt->fetch(PDO::FETCH_ASSOC);

    // Fetch categories for dropdown
    $categories = $pdo->query("SELECT * FROM expensetype");
    
    // Fetch top 3 descriptions for this category
    $stmt = $pdo->prepare("
        SELECT ExpenseDescr, COUNT(*) as count
        FROM expense
        WHERE CategID = :category
        GROUP BY ExpenseDescr
        ORDER BY count DESC
        LIMIT 3
    ");
    $stmt->execute([':category' => $expense['CategID']]);
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
    <title>Edit Expense</title>
  <link href="https://unpkg.com/flowbite@latest/dist/flowbite.min.css" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-4 sm:py-8 max-w-2xl">
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <h1 class="text-xl sm:text-2xl font-bold text-gray-800">Edit Expense</h1>
            <a href="index.php" 
               class="text-blue-500 hover:text-blue-700 text-sm sm:text-base">
                Back to List
            </a>
        </div>
        
        <div class="bg-white rounded-lg shadow-lg p-4 sm:p-6">
            <form method="POST" class="space-y-4" id="expenseForm">
                <input type="hidden" name="id" value="<?php echo $expense['ExpenseID']; ?>">
                
                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Category</label>
                    <select name="category" class="shadow border rounded w-full py-2 px-3 text-gray-700">
                        <?php while($category = $categories->fetch(PDO::FETCH_ASSOC)) { ?>
                            <option value="<?php echo $category['categID']; ?>" 
                                    <?php echo ($category['categID'] == $expense['CategID']) ? 'selected' : ''; ?>>
                                <?php echo htmlspecialchars($category['categDescr']); ?>
                            </option>
                        <?php } ?>
                    </select>
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Amount</label>
                    <input type="number" step="0.01" name="amount" 
                        value="<?php echo htmlspecialchars($expense['ExpenseAmount']); ?>"
                        class="shadow border rounded w-full py-2 px-3 text-gray-700">
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Date</label>
                    <input type="datetime-local" name="date" 
                           value="<?php echo date('Y-m-d\TH:i', strtotime($expense['ExpenseDate'])); ?>"
                           class="shadow border rounded w-full py-2 px-3 text-gray-700">
                </div>

                <div>
                    <label class="block text-gray-700 text-sm font-bold mb-2">Description</label>
                    <input type="text" name="description"
                           value="<?php echo htmlspecialchars($expense['ExpenseDescr']); ?>"
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
                    <a href="index.php" 
                       class="w-full sm:w-auto text-center text-gray-500 hover:text-gray-700 font-bold py-2 px-4">
                        Cancel
                    </a>
                </div>
            </form>

            <!-- Hidden delete form -->
            <form id="deleteForm" method="POST" class="hidden">
                <input type="hidden" name="id" value="<?php echo $expense['ExpenseID']; ?>">
                <input type="hidden" name="delete" value="1">
            </form>
        </div>
    </div>

    <script>
    function confirmDelete() {
        if (confirm('Are you sure you want to delete this expense?')) {
            document.getElementById('deleteForm').submit();
        }
    }
    
    function setDescription(text) {
        document.getElementById('description').value = text;
    }
    </script>
  <script src="https://unpkg.com/flowbite@latest/dist/flowbite.bundle.js"></script>
</body>
</html>

