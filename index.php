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
    // Fetch first 20 expense records with category descriptions
  $sql = "SELECT e.*, ec.category_name
    FROM expenses e
    JOIN expense_categories ec ON e.category_id = ec.category_id
    ORDER BY e.created_at DESC
    LIMIT 20";
            
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
    <?php require_once 'nav.php'; ?>

    <!-- Mobile sticky mini-header -->
    <div id="sticky-header" class="sm:hidden fixed top-0 left-0 right-0 z-40 bg-white/75 backdrop-blur-lg backdrop-saturate-150 border-b border-gray-200/60 -translate-y-full transition-transform duration-300 ease-out">
        <div class="container mx-auto px-4 h-12 flex items-center">
            <span class="text-base font-semibold text-gray-900">Expenses</span>
        </div>
    </div>

    <main class="container mx-auto px-4 py-8 pb-20 sm:pb-6">
      <h1 id="page-title" class="text-3xl font-bold tracking-tight text-gray-900 mb-4">Expenses</h1>

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
      <div id="mobile-list" class="sm:hidden flex flex-col gap-2">
        <?php
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($rows as $row):
        ?>
        <div class="odd:bg-white even:bg-gray-50 rounded-lg shadow-sm p-3 flex flex-col gap-1 cursor-pointer active:bg-blue-50"
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
        <?php endforeach; ?>
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
          <tbody id="desktop-list" class="divide-y divide-gray-200">
            <?php foreach($rows as $row): ?>
            <tr class="odd:bg-white even:bg-gray-50 hover:bg-blue-50 transition-colors cursor-pointer"
                onclick="editExpense(<?php echo $row['expense_id']; ?>)">
              <td class="px-6 py-4 text-sm"><?php echo htmlspecialchars(date('d/m/Y', strtotime($row['created_at']))); ?></td>
              <td class="px-6 py-4 text-sm"><?php echo htmlspecialchars($row['category_name']); ?></td>
              <td class="px-6 py-4 text-sm text-right"><?php echo number_format($row['expense_amount'], 2, '.', ''); ?></td>
              <td class="px-6 py-4 text-sm"><?php echo htmlspecialchars($row['expense_description']); ?></td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>

      <div id="sentinel" class="h-4 w-full"></div>
      <div id="loading-indicator" class="hidden text-center py-4 text-gray-500 text-sm">Loading more...</div>
      <div id="end-message" class="hidden text-center py-4 text-gray-500 text-sm">No more expenses</div>
      
    </main>

    <script>
    function editExpense(id) {
        window.location.href = `edit.php?id=${id}`;
    }

    let currentOffset = <?= count($rows) ?>;
    const limit = 20;
    let isLoading = false;
    let hasMore = true;

    const sentinel = document.getElementById('sentinel');
    const loadingIndicator = document.getElementById('loading-indicator');
    const endMessage = document.getElementById('end-message');
    const mobileList = document.getElementById('mobile-list');
    const desktopList = document.getElementById('desktop-list');

    async function loadMore() {
        if (isLoading || !hasMore) return;
        isLoading = true;
        loadingIndicator.classList.remove('hidden');
        try {
            const res = await fetch(`api_expenses.php?offset=${currentOffset}&limit=${limit}`);
            if (!res.ok) throw new Error('Network response was not ok');
            const data = await res.json();
            if (!Array.isArray(data)) {
                hasMore = false;
                console.error('Unexpected response', data);
                return;
            }
            if (data.length === 0) {
                hasMore = false;
                endMessage.classList.remove('hidden');
                observer.unobserve(sentinel);
            } else {
                appendRows(data);
                currentOffset += data.length;
            }
        } catch (e) {
            console.error('Failed to load more expenses:', e);
        } finally {
            isLoading = false;
            loadingIndicator.classList.add('hidden');
        }
    }

    function appendRows(rows) {
        rows.forEach(row => {
            // Mobile card
            const card = document.createElement('div');
            card.className = 'odd:bg-white even:bg-gray-50 rounded-lg shadow-sm p-3 flex flex-col gap-1 cursor-pointer active:bg-blue-50';
            card.onclick = () => editExpense(row.expense_id);
            card.innerHTML = `
                <div class="flex justify-between items-start">
                    <span class="text-sm font-bold text-gray-800">${row.date}</span>
                    <span class="text-xs text-gray-500">${row.expense_description}</span>
                </div>
                <div class="flex justify-between items-end">
                    <span class="text-sm text-gray-800">${row.category_name}</span>
                    <span class="text-sm font-bold text-blue-600">${row.expense_amount}€</span>
                </div>
            `;
            mobileList.appendChild(card);

            // Desktop row
            const tr = document.createElement('tr');
            tr.className = 'odd:bg-white even:bg-gray-50 hover:bg-blue-50 transition-colors cursor-pointer';
            tr.onclick = () => editExpense(row.expense_id);
            tr.innerHTML = `
                <td class="px-6 py-4 text-sm">${row.date}</td>
                <td class="px-6 py-4 text-sm">${row.category_name}</td>
                <td class="px-6 py-4 text-sm text-right">${row.expense_amount}</td>
                <td class="px-6 py-4 text-sm">${row.expense_description}</td>
            `;
            desktopList.appendChild(tr);
        });
    }

    const observer = new IntersectionObserver((entries) => {
        if (entries[0].isIntersecting) {
            loadMore();
        }
    }, { rootMargin: '200px' });

    if (sentinel) {
        observer.observe(sentinel);
    }

    // Sticky mini-header IntersectionObserver
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
  <script src="https://unpkg.com/flowbite@latest/dist/flowbite.bundle.js"></script>
</body>
</html>
