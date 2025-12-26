<?php
require_once 'config.php';
require_once 'session_check.php';


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
      
      <div class="relative overflow-x-auto shadow-md sm:rounded-lg">
        <table class="w-full">
          <thead class="bg-gray-100">
            <tr>
              <th class="px-2 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-200" onclick="sortTable(0)">Date</th>
              <th class="px-2 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-200" onclick="sortTable(1)">Category</th>
              <th class="px-2 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-200" onclick="sortTable(2)">Amount</th>
              <th class="px-2 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase cursor-pointer hover:bg-gray-200" onclick="sortTable(3)">Description</th>
              <th class="px-2 sm:px-6 py-3 text-left text-xs font-medium text-gray-500 uppercase">Actions</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-200">
            <?php while($row = $stmt->fetch(PDO::FETCH_ASSOC)) { ?>
            <tr class="hover:bg-gray-50">
              <td class="px-2 sm:px-6 py-4 text-sm">
                <?php echo htmlspecialchars(date('d/m/Y', strtotime($row['created_at']))); ?>
              </td>
              <td class="px-2 sm:px-6 py-4 text-sm">
                <?php echo htmlspecialchars($row['category_name']); ?>
              </td>
              <td class="px-2 sm:px-6 py-4 text-sm text-right">
                <?php echo number_format($row['expense_amount'], 2, '.', ''); ?>
              </td>
              <td class="px-2 sm:px-6 py-4 text-sm">
                <?php echo htmlspecialchars($row['expense_description']); ?>
              </td>
              <td class="px-2 sm:px-6 py-4 text-sm">
                <button onclick="editExpense(<?php echo $row['expense_id']; ?>)"
                class="text-blue-600 hover:text-blue-900">Edit</button>
              </td>
            </tr>
            <?php } ?>
          </tbody>
        </table>
      </div>
      
    </div>

    <script>
    function editExpense(id) {
        window.location.href = `edit.php?id=${id}`;
    }

    // Mobile dropdown functionality
    document.addEventListener('DOMContentLoaded', function() {
        const mobileDropdownButton = document.getElementById('mobileDropdownButton');
        const mobileDropdownMenu = document.getElementById('mobileDropdownMenu');

        mobileDropdownButton.addEventListener('click', function(e) {
            e.stopPropagation();
            mobileDropdownMenu.style.display = mobileDropdownMenu.style.display === 'block' ? 'none' : 'block';
        });

        // Close mobile dropdown when clicking outside
        document.addEventListener('click', function(e) {
            if (!mobileDropdownButton.contains(e.target) && !mobileDropdownMenu.contains(e.target)) {
                mobileDropdownMenu.style.display = 'none';
            }
        });
    });
    </script>
  <script src="https://unpkg.com/flowbite@latest/dist/flowbite.bundle.js"></script>
  <script>
    function sortTable(n) {
      var table, rows, switching, i, x, y, shouldSwitch, dir, switchcount = 0;
      table = document.querySelector("table");
      switching = true;
      dir = "asc";
      
      while (switching) {
        switching = false;
        rows = table.rows;
        
        for (i = 1; i < (rows.length - 1); i++) {
          shouldSwitch = false;
          x = rows[i].getElementsByTagName("TD")[n];
          y = rows[i + 1].getElementsByTagName("TD")[n];
          
          if (n === 0) { // Date column
            // Convert dd/mm/yyyy to Date objects
            let dateParts1 = x.innerHTML.split('/');
            let dateParts2 = y.innerHTML.split('/');
            let date1 = new Date(dateParts1[2], dateParts1[1] - 1, dateParts1[0]);
            let date2 = new Date(dateParts2[2], dateParts2[1] - 1, dateParts2[0]);
            
            if (dir === "asc") {
              if (date1 > date2) {
                shouldSwitch = true;
                break;
                  }
            } else {
              if (date1 < date2) {
                shouldSwitch = true;
                break;
                  }
            }
          } else if (n === 2) { // Amount column
            if (dir === "asc") {
              if (Number(x.innerHTML) > Number(y.innerHTML)) {
                shouldSwitch = true;
                break;
                  }
            } else {
              if (Number(x.innerHTML) < Number(y.innerHTML)) {
                shouldSwitch = true;
                break;
                  }
            }
          } else { // Text columns
            if (dir === "asc") {
              if (x.innerHTML.toLowerCase() > y.innerHTML.toLowerCase()) {
                shouldSwitch = true;
                break;
                  }
            } else {
              if (x.innerHTML.toLowerCase() < y.innerHTML.toLowerCase()) {
                shouldSwitch = true;
                break;
                  }
            }
          }
        }
        
        if (shouldSwitch) {
          rows[i].parentNode.insertBefore(rows[i + 1], rows[i]);
          switching = true;
          switchcount++;
        } else {
          if (switchcount == 0 && dir == "asc") {
            dir = "desc";
            switching = true;
          }
        }
      }
    }

  </script>
</body>
</html>
