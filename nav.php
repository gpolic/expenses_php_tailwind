<?php
$currentPage = basename($_SERVER['PHP_SELF']);

// Active state helpers
$isExpenses = in_array($currentPage, ['index.php', 'edit.php']);
$isAdd = in_array($currentPage, ['select_category.php', 'add_expense_details.php']);
$isReports = $currentPage === 'reports.php';
$isCategories = in_array($currentPage, ['manage_category.php', 'edit_category.php', 'add_category.php']);
$isProfile = $currentPage === 'profile.php';
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
                <a href="select_category.php" class="text-sm font-medium <?php echo $isAdd ? 'text-blue-600' : 'text-gray-600 hover:text-gray-900'; ?>">
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

<!-- Mobile FAB: Add new record (hidden on add pages, reports, and add category page) -->
<?php if (!$isAdd && !$isReports && $currentPage !== 'add_category.php' && $currentPage !== 'edit.php'): ?>
<a href="<?php echo $isCategories ? 'add_category.php' : 'select_category.php'; ?>" aria-label="<?php echo $isCategories ? 'Add new category' : 'Add new record'; ?>"
   class="sm:hidden fixed right-4 z-50 flex items-center justify-center w-14 h-14 rounded-full bg-blue-600 active:bg-blue-700 text-white shadow-lg"
   style="bottom: calc(env(safe-area-inset-bottom) + 72px);">
    <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
    </svg>
</a>
<?php endif; ?>

<!-- Mobile bottom tab bar -->
<nav class="sm:hidden fixed inset-x-0 bottom-0 w-full bg-white border-t border-gray-200 z-50" style="padding-bottom: env(safe-area-inset-bottom);">
    <div class="grid grid-cols-4 w-full">
        <a href="index.php" class="flex flex-col items-center justify-center py-2 <?php echo $isExpenses ? 'text-blue-600' : 'text-gray-500'; ?>">
            <span class="flex flex-col items-center px-3 py-1 rounded-full <?php echo $isExpenses ? 'bg-blue-50' : ''; ?>">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                </svg>
            </span>
            <span class="text-xs mt-1 <?php echo $isExpenses ? 'font-semibold' : ''; ?>">Expenses</span>
        </a>
        <a href="reports.php" class="flex flex-col items-center justify-center py-2 <?php echo $isReports ? 'text-blue-600' : 'text-gray-500'; ?>">
            <span class="flex flex-col items-center px-3 py-1 rounded-full <?php echo $isReports ? 'bg-blue-50' : ''; ?>">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/>
                </svg>
            </span>
            <span class="text-xs mt-1 <?php echo $isReports ? 'font-semibold' : ''; ?>">Reports</span>
        </a>
        <a href="manage_category.php" class="flex flex-col items-center justify-center py-2 <?php echo $isCategories ? 'text-blue-600' : 'text-gray-500'; ?>">
            <span class="flex flex-col items-center px-3 py-1 rounded-full <?php echo $isCategories ? 'bg-blue-50' : ''; ?>">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5a1.99 1.99 0 011.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.99 1.99 0 013 12V7a4 4 0 014-4z"/>
                </svg>
            </span>
            <span class="text-xs mt-1 <?php echo $isCategories ? 'font-semibold' : ''; ?>">Categories</span>
        </a>
        <a href="logout.php" class="flex flex-col items-center justify-center py-2 text-gray-500">
            <span class="flex flex-col items-center px-3 py-1 rounded-full">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24" xmlns="http://www.w3.org/2000/svg">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
                </svg>
            </span>
            <span class="text-xs mt-1">Logout</span>
        </a>
    </div>
</nav>
