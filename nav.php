<?php
$currentPage = basename($_SERVER['PHP_SELF']);

// Active state helpers
$isExpenses = in_array($currentPage, ['index.php', 'edit.php']);
$isAdd = in_array($currentPage, ['add_record.php', 'add_expense_details.php']);
$isReports = $currentPage === 'reports.php';
$isProfile = in_array($currentPage, ['profile.php', 'manage_category.php', 'edit_category.php']);
?>

<!-- Desktop top nav -->
<nav class="hidden sm:block bg-white shadow-sm w-full">
    <div class="container mx-auto px-4">
        <div class="flex items-center justify-between h-16">
            <a href="index.php" class="text-xl font-bold text-gray-800">Expenses Tracker</a>
            <div class="flex items-center gap-6">
                <a href="index.php" class="text-sm font-medium <?php echo $isExpenses ? 'text-blue-600' : 'text-gray-600 hover:text-gray-900'; ?>">
                    Expenses
                </a>
                <a href="add_record.php" class="text-sm font-medium <?php echo $isAdd ? 'text-blue-600' : 'text-gray-600 hover:text-gray-900'; ?>">
                    Add Record
                </a>
                <a href="reports.php" class="text-sm font-medium <?php echo $isReports ? 'text-blue-600' : 'text-gray-600 hover:text-gray-900'; ?>">
                    Reports
                </a>
                <a href="manage_category.php" class="text-sm font-medium <?php echo in_array($currentPage, ['manage_category.php', 'edit_category.php']) ? 'text-blue-600' : 'text-gray-600 hover:text-gray-900'; ?>">
                    Categories
                </a>
                <a href="logout.php" class="text-sm font-medium text-red-600 hover:text-red-800">
                    Logout
                </a>
            </div>
        </div>
    </div>
</nav>

<!-- Mobile bottom tab bar -->
<nav class="sm:hidden fixed inset-x-0 bottom-0 w-full bg-white border-t border-gray-200 z-50" style="padding-bottom: env(safe-area-inset-bottom);">
    <div class="grid grid-cols-4 w-full">
        <a href="index.php" class="flex flex-col items-center justify-center py-2 <?php echo $isExpenses ? 'text-blue-600' : 'text-gray-500'; ?>">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <span class="text-xs mt-1 <?php echo $isExpenses ? 'font-semibold' : ''; ?>">Expenses</span>
        </a>
        <a href="add_record.php" class="flex flex-col items-center justify-center py-2 <?php echo $isAdd ? 'text-blue-600' : 'text-gray-500'; ?>">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            <span class="text-xs mt-1 <?php echo $isAdd ? 'font-semibold' : ''; ?>">Add</span>
        </a>
        <a href="reports.php" class="flex flex-col items-center justify-center py-2 <?php echo $isReports ? 'text-blue-600' : 'text-gray-500'; ?>">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
            </svg>
            <span class="text-xs mt-1 <?php echo $isReports ? 'font-semibold' : ''; ?>">Reports</span>
        </a>
        <a href="profile.php" class="flex flex-col items-center justify-center py-2 <?php echo $isProfile ? 'text-blue-600' : 'text-gray-500'; ?>">
            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
            </svg>
            <span class="text-xs mt-1 <?php echo $isProfile ? 'font-semibold' : ''; ?>">Profile</span>
        </a>
    </div>
</nav>
