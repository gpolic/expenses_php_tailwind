<?php
require_once 'session_check.php';
require_once 'config.php';


// Get current month's total expenses
try {
    $currentMonth = date('m');
    $currentYear = date('Y');
    $currentDay = date('d');
    
    // Current month total up to current day (including today)
    $currentMonthTotalSql = "SELECT SUM(expense_amount) as monthTotal
                            FROM expenses
                            WHERE MONTH(created_at) = :month
                            AND YEAR(created_at) = :year
                            AND DAY(created_at) <= :day";
    
    $stmt = $pdo->prepare($currentMonthTotalSql);
    $stmt->execute([
        ':month' => $currentMonth,
        ':year' => $currentYear,
        ':day' => $currentDay
    ]);
    
    $currentMonthTotal = $stmt->fetch(PDO::FETCH_ASSOC);
    
    // Previous month total up to same day of previous month
    $previousMonth = $currentMonth - 1;
    $previousYear = $currentYear;
    
    if ($previousMonth == 0) {
        $previousMonth = 12;
        $previousYear = $currentYear - 1;
    }
    
    // Get the number of days in the previous month to handle months with different lengths
    $daysInPreviousMonth = cal_days_in_month(CAL_GREGORIAN, $previousMonth, $previousYear);
    $dayForPreviousMonth = min($currentDay, $daysInPreviousMonth);
    
    $previousMonthTotalSql = "SELECT SUM(expense_amount) as monthTotal
                             FROM expenses
                             WHERE MONTH(created_at) = :month
                             AND YEAR(created_at) = :year
                             AND DAY(created_at) <= :day";
    
    $stmtPrev = $pdo->prepare($previousMonthTotalSql);
    $stmtPrev->execute([
        ':month' => $previousMonth,
        ':year' => $previousYear,
        ':day' => $dayForPreviousMonth
    ]);
    
    $previousMonthTotal = $stmtPrev->fetch(PDO::FETCH_ASSOC);
    
    // Calculate percentage difference
    $currentValue = (float)$currentMonthTotal['monthTotal'];
    $previousValue = (float)$previousMonthTotal['monthTotal'];
    
    if ($previousValue != 0) {
        $percentageChange = (($currentValue - $previousValue) / abs($previousValue)) * 100;
    } else {
        $percentageChange = $currentValue > 0 ? 100 : 0; // If previous is 0 and current is positive, show 100% increase
    }
    
} catch(PDOException $e) {
    $currentMonthTotal = ['monthTotal' => 0];
    $previousMonthTotal = ['monthTotal' => 0];
    $percentageChange = 0;
}
  

try {
    // Fetch last 50 expense records with category descriptions
  $sql = "SELECT e.*, ec.category_name
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.category_id
    ORDER BY e.created_at DESC
    LIMIT 50";
            
    $stmt = $pdo->query($sql);
} catch(PDOException $e) {
    die("Query failed: " . $e->getMessage());
}
   
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Expense Tracker</title>
  <link href="https://unpkg.com/flowbite@latest/dist/flowbite.min.css" rel="stylesheet" />
  <link href="styles.css" rel="stylesheet" />
  <script src="https://cdn.tailwindcss.com"></script>
  <script src="https://cdn.jsdelivr.net/npm/simple-datatables@9.0.3"></script>

</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
  
        
      <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
        <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Expenses Tracker</h1>
        <div class="flex gap-2 w-full sm:w-auto">
          <!-- Desktop navigation: show 3 buttons -->
          <div class="hidden sm:flex gap-2 w-auto">
            <a href="add_record.php"
            class="bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-center text-sm sm:text-base flex items-center justify-center">
              Add Record
            </a>
            <a href="reports.php"
            class="bg-purple-500 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded text-center text-sm sm:text-base flex items-center justify-center">
              Reports
            </a>
            <a href="logout.php"
            class="bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded text-center text-sm sm:text-base flex items-center justify-center">
              Logout
            </a>
          </div>
          
          <!-- Mobile navigation: Add Record, Reports and Logout -->
          <div class="sm:hidden flex gap-2 w-full">
            <a href="add_record.php"
            class="flex-1 bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-center text-sm sm:text-base flex items-center justify-center">
              Add Record
            </a>
            <a href="reports.php"
            class="flex-1 bg-purple-500 hover:bg-purple-700 text-white font-bold py-2 px-4 rounded text-center text-sm sm:text-base flex items-center justify-center">
              Reports
            </a>
            <a href="logout.php"
            class="flex-1 bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded text-center text-sm sm:text-base flex items-center justify-center">
              Logout
            </a>
          </div>
        </div>
      </div>
      
      <div class="bg-white rounded-lg shadow-sm p-4 mb-6">
        <div class="flex items-center gap-3">
          <div>
            <h2 class="text-sm font-medium text-gray-500">Month Total</h2>
            <div class="flex items-baseline gap-2">
              <p class="text-lg font-bold text-blue-600">
                 <?= number_format($currentMonthTotal['monthTotal'], 2) ?>€
              </p>
              <?php if (isset($percentageChange)): ?>
              <span class="text-sm font-medium <?php echo $percentageChange >= 0 ? 'text-red-500' : 'text-green-500'; ?>">
                (<?= $percentageChange >= 0 ? '+' : '' ?><?= number_format($percentageChange, 2) ?>%)
              </span>
              <?php endif; ?>
            </div>
          </div>
        </div>
      </div>
      
      <!-- Mobile: card list -->
      <div class="sm:hidden flex flex-col gap-2">
        <?php
        $alt = false;
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as $row):
        ?>
        <div class="<?php echo $alt ? 'bg-gray-50' : 'bg-white'; ?> rounded-lg shadow-sm p-3 flex flex-col gap-1 cursor-pointer active:bg-blue-50"
             onclick="editExpense(<?php echo $row['expense_id']; ?>)">
          <div class="flex justify-between items-start">
            <span class="text-sm font-bold text-gray-800"><?php echo htmlspecialchars(date('d/m/Y', strtotime($row['created_at']))); ?></span>
            <span class="text-xs text-gray-500"><?php echo htmlspecialchars($row['expense_description']); ?></span>
          </div>
          <div class="flex justify-between items-end">
            <span class="text-sm text-gray-800"><?php echo htmlspecialchars($row['category_name']); ?></span>
            <span class="text-sm font-bold text-blue-600"><?php echo number_format($row['expense_amount'], 2, '.', ''); ?>€</span>
          </div>
        </div>
        <?php $alt = !$alt; endforeach; ?>
      </div>

      <!-- Desktop: table -->
      <div class="hidden sm:block relative overflow-x-auto shadow-md sm:rounded-lg">
        <table class="w-full">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Date</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Category</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Amount</th>
              <th class="px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Description</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php
            $alt = false;
            foreach($rows as $row):
            ?>
            <tr class="<?php echo $alt ? 'bg-gray-50' : 'bg-white'; ?> hover:bg-blue-50 transition-colors cursor-pointer"
                onclick="editExpense(<?php echo $row['expense_id']; ?>)">
              <td class="px-6 py-4 text-sm"><?php echo htmlspecialchars(date('d/m/Y', strtotime($row['created_at']))); ?></td>
              <td class="px-6 py-4 text-sm"><?php echo htmlspecialchars($row['category_name']); ?></td>
              <td class="px-6 py-4 text-sm text-right"><?php echo number_format($row['expense_amount'], 2, '.', ''); ?></td>
              <td class="px-6 py-4 text-sm"><?php echo htmlspecialchars($row['expense_description']); ?></td>
            </tr>
            <?php $alt = !$alt; endforeach; ?>
          </tbody>
        </table>
      </div>
      
    </div>

    <script>
    function editExpense(id) {
        window.location.href = `edit.php?id=${id}`;
    }
    </script>
  <script src="https://unpkg.com/flowbite@latest/dist/flowbite.bundle.js"></script>
</body>
</html>
