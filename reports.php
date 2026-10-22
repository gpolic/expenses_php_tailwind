<?php
require_once 'session_check.php';
require_once 'config.php';

// Period: ?p=<preset> or ?from=YYYY-MM-DD&to=YYYY-MM-DD (inclusive); default last 12 completed months
$today = new DateTime('today');
$thisMonth = new DateTime('first day of this month 00:00');
$thisYear = (int)$today->format('Y');
$presets = [
    'mtd'  => ['This Month', clone $thisMonth, clone $today],
    'lm'   => ['Last Month', (clone $thisMonth)->modify('-1 month'), (clone $thisMonth)->modify('-1 day')],
    'ytd'  => ['Year to Date', new DateTime($thisYear . '-01-01'), clone $today],
    'ly'   => ['Last Year', new DateTime(($thisYear - 1) . '-01-01'), new DateTime(($thisYear - 1) . '-12-31')],
    'l12m' => ['Last 12 Months',(clone $thisMonth)->modify('-12 months'), (clone $thisMonth)->modify('-1 day')],
];

function date_param($name) {
    $value = filter_input(INPUT_GET, $name);
    if (!is_string($value)) {
        return null;
    }
    $date = DateTime::createFromFormat('!Y-m-d', $value);
    return ($date && $date->format('Y-m-d') === $value && $date->format('Y') >= 2000) ? $date : null;
}

$preset = filter_input(INPUT_GET, 'p');
$from = date_param('from');
$to = date_param('to');
if (is_string($preset) && isset($presets[$preset])) {
    [, $from, $to] = $presets[$preset];
} elseif ($from && $to) {
    if ($from > $to) {
        [$from, $to] = [$to, $from];
    }
    $to = min($to, $today);
    $from = min($from, $to);
} else {
    [, $from, $to] = $presets['l12m'];
}
// A custom range that equals a preset highlights that preset
$activePreset = null;
foreach ($presets as $key => [, $presetFrom, $presetTo]) {
    if ($presetFrom == $from && $presetTo == $to) {
        $activePreset = $key;
        break;
    }
}
$rangeEnd = (clone $to)->modify('+1 day');
$days = $from->diff($to)->days + 1;
$byDay = $days <= 62;
// Averages and the trend are per day or per average month, so partial months don't skew them
$unit = $byDay ? 1 : 365.25 / 12;

if ($from == $to) {
    $periodLabel = $from->format('M j, Y');
} elseif ($from->format('Y') === $to->format('Y')) {
    $periodLabel = $from->format('M j') . ' – ' . $to->format('M j, Y');
} else {
    $periodLabel = $from->format('M j, Y') . ' – ' . $to->format('M j, Y');
}

// Categories: ?cat=1,4,7 limits every figure on the page to those categories; absent = all
try {
    $allCategories = $pdo->query("SELECT category_id, category_name FROM expense_categories ORDER BY category_name")
                         ->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $allCategories = [];
}
$selectedCats = [];
$catParam = filter_input(INPUT_GET, 'cat');
if (is_string($catParam) && $catParam !== '') {
    // Only known ids pass, so the list is safe to put into the SQL
    $knownIds = array_map('intval', array_column($allCategories, 'category_id'));
    $selectedCats = array_values(array_intersect($knownIds, array_map('intval', explode(',', $catParam))));
}
if (count($selectedCats) === count($allCategories)) {
    $selectedCats = [];
}
$catFilter = $selectedCats ? ' AND e.category_id IN (' . implode(',', $selectedCats) . ')' : '';
$catQuery = $selectedCats ? '&cat=' . implode(',', $selectedCats) : '';
if (!$selectedCats) {
    $catLabel = 'All categories';
} elseif (count($selectedCats) === 1) {
    $catLabel = array_column($allCategories, 'category_name', 'category_id')[$selectedCats[0]];
} else {
    $catLabel = count($selectedCats) . ' of ' . count($allCategories) . ' categories';
}

$slope = 0;
$totalAmount = 0;
$average = 0;
$txCount = 0;

// Expenses per day (ranges up to 62 days) or per month within the range
try {
    $buckets = [];
    if ($byDay) {
        $sql = "SELECT DATE(created_at) as bucket, SUM(expense_amount) as total, COUNT(*) as cnt
                FROM expenses e
                WHERE created_at >= :startDate AND created_at < :endDate$catFilter
                GROUP BY DATE(created_at)";
        for ($d = clone $from; $d <= $to; $d->modify('+1 day')) {
            $buckets[$d->format('Y-m-d')] = ['label' => $d->format('M j'), 'amount' => 0.0, 'days' => 1];
        }
    } else {
        // Single query instead of one per month — range filter uses created_at index
        $sql = "SELECT YEAR(created_at) as yr, MONTH(created_at) as mo, SUM(expense_amount) as total, COUNT(*) as cnt
                FROM expenses e
                WHERE created_at >= :startDate AND created_at < :endDate$catFilter
                GROUP BY YEAR(created_at), MONTH(created_at)";
        for ($d = new DateTime($from->format('Y-m-01')); $d <= $to; $d->modify('+1 month')) {
            $bucketStart = max($d, $from);
            $bucketEnd = min((clone $d)->modify('last day of this month'), $to);
            $buckets[$d->format('Y-n')] = [
                'label' => $d->format('M Y'),
                'amount' => 0.0,
                'days' => $bucketStart->diff($bucketEnd)->days + 1
            ];
        }
    }

    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':startDate' => $from->format('Y-m-d'),
        ':endDate' => $rangeEnd->format('Y-m-d')
    ]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $key = $byDay ? $row['bucket'] : $row['yr'] . '-' . (int)$row['mo'];
        if (isset($buckets[$key])) {
            $buckets[$key]['amount'] = floatval($row['total']);
            $txCount += (int)$row['cnt'];
        }
    }

    $periodData = [];
    foreach ($buckets as $bucket) {
        $periodData[] = ['label' => $bucket['label'], 'amount' => $bucket['amount']];
    }

    $totalAmount = array_sum(array_column($periodData, 'amount'));
    $average = $totalAmount / $days * $unit;
    $txAverage = $txCount > 0 ? $totalAmount / $txCount : 0;

    // Trend (simple linear regression) on the per-day or per-month rate of each bucket
    $rates = [];
    foreach ($buckets as $bucket) {
        $rates[] = $bucket['amount'] / $bucket['days'] * $unit;
    }
    $n = count($rates);
    $sumX = 0;
    $sumY = 0;
    $sumXY = 0;
    $sumX2 = 0;

    for ($i = 0; $i < $n; $i++) {
        $x = $i + 1;
        $sumX += $x;
        $sumY += $rates[$i];
        $sumXY += $x * $rates[$i];
        $sumX2 += $x * $x;
    }

    if ($n > 1) {
        $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    }
    $intercept = ($sumY - $slope * $sumX) / $n;

    // Generate trend line data
    $trendData = [];
    for ($i = 0; $i < $n; $i++) {
        $trendData[] = $slope * ($i + 1) + $intercept;
    }

} catch(PDOException $e) {
    $periodData = [];
    $trendData = [];
}

// Trend card: change across the period relative to the average
$trendChange = $average > 0 ? $slope * (count($trendData) - 1) / $average : 0;
if (abs($trendChange) < 0.05) {
    [$trendArrow, $trendText, $trendColor, $trendBg] = ['→', 'Flat', 'text-gray-600', 'bg-gray-100'];
} else {
    $trendArrow = $trendChange > 0 ? '↗' : '↘';
    $trendText = abs($trendChange) > 0.2 ? 'Strong' : 'Moderate';
    $trendColor = $trendChange > 0 ? 'text-red-600' : 'text-green-600';
    $trendBg = $trendChange > 0 ? 'bg-red-100' : 'bg-green-100';
}

// Expense categories by total amount for the same period
try {
    $categorySql = "SELECT ec.category_name as category, SUM(e.expense_amount) as total, COUNT(*) as cnt
                    FROM expenses e
                    JOIN expense_categories ec ON e.category_id = ec.category_id
                    WHERE e.created_at >= :startDate
                    AND e.created_at < :endDate$catFilter
                    GROUP BY e.category_id, ec.category_name
                    ORDER BY total DESC";

    $stmt = $pdo->prepare($categorySql);
    $stmt->execute([
        ':startDate' => $from->format('Y-m-d'),
        ':endDate'   => $rangeEnd->format('Y-m-d')
    ]);

    $categoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $categoryMax = $categoryData ? (float)$categoryData[0]['total'] : 0;

} catch(PDOException $e) {
    $categoryData = [];
    $categoryMax = 0;
}

// The 10 largest single expenses in the period
try {
    $stmt = $pdo->prepare("SELECT e.expense_id, e.created_at, e.expense_amount, e.expense_description, ec.category_name
                           FROM expenses e
                           JOIN expense_categories ec ON e.category_id = ec.category_id
                           WHERE e.created_at >= :startDate
                           AND e.created_at < :endDate$catFilter
                           ORDER BY e.expense_amount DESC, e.created_at DESC
                           LIMIT 10");
    $stmt->execute([
        ':startDate' => $from->format('Y-m-d'),
        ':endDate'   => $rangeEnd->format('Y-m-d')
    ]);
    $largestExpenses = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    $largestExpenses = [];
}

// Year over year: the range's end year and the two years before, year-to-date up to the same day
$yoyYear = (int)$to->format('Y');
$yoyYears = [$yoyYear, $yoyYear - 1, $yoyYear - 2];
$ytdLabel = 'Jan 1 – ' . $to->format('M j');
$yoySeries = [];
$ytdTotals = [];
foreach ($yoyYears as $k => $year) {
    // Past years show every month; the end year stops after the range's end month
    $yoySeries[$year] = array_fill(0, 12, $k === 0 ? null : 0.0);
    $ytdTotals[$year] = 0.0;
}
for ($i = 0; $i < (int)$to->format('n'); $i++) {
    $yoySeries[$yoyYear][$i] = 0.0;
}
try {
    $sql = "SELECT YEAR(created_at) as yr, MONTH(created_at) as mo,
                   SUM(expense_amount) as total
            FROM expenses e
            WHERE created_at >= :startDate AND created_at < :endDate$catFilter
            GROUP BY YEAR(created_at), MONTH(created_at)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([
        ':startDate' => end($yoyYears) . '-01-01',
        ':endDate'   => $rangeEnd->format('Y-m-d')
    ]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $yoySeries[(int)$row['yr']][$row['mo'] - 1] = floatval($row['total']);
    }

    // Year-to-date per year: Jan 1 up to the range's end day of that year
    $sums = [];
    $params = [':minDate' => end($yoyYears) . '-01-01', ':maxDate' => $rangeEnd->format('Y-m-d')];
    foreach ($yoyYears as $k => $year) {
        $sums[] = "SUM(CASE WHEN created_at >= :s$k AND created_at < :e$k THEN expense_amount ELSE 0 END) as y$k";
        $params[":s$k"] = $year . '-01-01';
        $params[":e$k"] = (clone $rangeEnd)->modify("-$k year")->format('Y-m-d');
    }
    $sql = "SELECT " . implode(', ', $sums) . "
            FROM expenses e
            WHERE created_at >= :minDate AND created_at < :maxDate$catFilter";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $ytd = $stmt->fetch(PDO::FETCH_ASSOC);
    foreach ($yoyYears as $k => $year) {
        $ytdTotals[$year] = floatval($ytd["y$k"] ?? 0);
    }
} catch(PDOException $e) {
    // Charts and cards stay at their empty defaults
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Expense Tracker</title>
    <link href="https://unpkg.com/flowbite@4.0.2/dist/flowbite.min.css" rel="stylesheet" />
    <link href="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.css" rel="stylesheet" />
    <link href="styles.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.5.1"></script>
    <script src="https://cdn.jsdelivr.net/npm/flatpickr@4.6.13/dist/flatpickr.min.js"></script>
    <style>
        /* Range calendar in the app's blue */
        #rangePanel .flatpickr-calendar { box-shadow: none; border: 0; margin: 0 auto; }
        /* Tailwind's .hidden (display: none) would drop flatpickr's other-month days and shift the weekdays */
        #rangePanel .flatpickr-day.hidden { display: block; visibility: hidden; }
        .flatpickr-day.selected, .flatpickr-day.startRange, .flatpickr-day.endRange,
        .flatpickr-day.selected:hover, .flatpickr-day.startRange:hover, .flatpickr-day.endRange:hover {
            background: #2563eb; border-color: #2563eb;
        }
        .flatpickr-day.inRange {
            background: #dbeafe; border-color: #dbeafe;
            box-shadow: -5px 0 0 #dbeafe, 5px 0 0 #dbeafe;
        }
        .flatpickr-day.selected.startRange + .endRange:not(:nth-child(7n+1)),
        .flatpickr-day.startRange.startRange + .endRange:not(:nth-child(7n+1)),
        .flatpickr-day.endRange.startRange + .endRange:not(:nth-child(7n+1)) {
            box-shadow: -10px 0 0 #2563eb;
        }
        .flatpickr-day.today { border-color: #93c5fd; }
    </style>
</head>
<body class="bg-gray-50">
    <?php require_once 'nav.php'; ?>
    <main class="container mx-auto px-4 py-8 pb-20 sm:pb-6">

        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Reports</h1>

            <div class="flex flex-col sm:flex-row gap-2 w-full sm:w-auto">
            <!-- Category filter: searchable checkbox list -->
            <div class="relative w-full sm:w-auto" id="catPicker">
                <button type="button" id="catButton" aria-haspopup="dialog" aria-expanded="false"
                        class="w-full sm:w-auto sm:min-w-[12rem] flex items-center gap-3 bg-white border border-gray-300 rounded-lg px-4 py-2 text-left shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-blue-100">
                    <svg class="w-5 h-5 <?= $selectedCats ? 'text-blue-600' : 'text-gray-500' ?> shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 4h18l-7 8v6l-4 2v-8L3 4z"></path>
                    </svg>
                    <span class="flex-1 min-w-0">
                        <span class="block text-xs text-gray-500">Categories</span>
                        <span class="block text-sm font-semibold text-gray-800 truncate max-w-[14rem]"><?= htmlspecialchars($catLabel) ?></span>
                    </span>
                    <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <div id="catPanel" role="dialog" aria-label="Choose categories"
                     class="hidden absolute right-0 z-30 mt-2 w-full sm:w-72 bg-white border border-gray-200 rounded-xl shadow-xl">
                    <div class="p-3 border-b border-gray-100">
                        <input type="search" id="catSearch" placeholder="Search categories" autocomplete="off"
                               class="w-full rounded-lg border border-gray-300 px-3 py-2 text-sm focus:border-blue-500 focus:ring-2 focus:ring-blue-100 focus:outline-none">
                        <div class="flex gap-3 mt-2 text-sm">
                            <button type="button" id="catAll" class="text-blue-600 hover:underline">Select all</button>
                            <button type="button" id="catNone" class="text-blue-600 hover:underline">Clear</button>
                        </div>
                    </div>
                    <ul id="catList" class="max-h-72 overflow-y-auto py-1">
                        <?php foreach ($allCategories as $cat): ?>
                            <li>
                                <label class="flex items-center gap-3 px-3 py-2 text-sm text-gray-800 hover:bg-gray-50 cursor-pointer">
                                    <input type="checkbox" value="<?= (int)$cat['category_id'] ?>"
                                           <?= !$selectedCats || in_array((int)$cat['category_id'], $selectedCats, true) ? 'checked' : '' ?>
                                           class="w-4 h-4 rounded border border-gray-300 text-blue-600 focus:ring-blue-500">
                                    <span class="truncate"><?= htmlspecialchars($cat['category_name']) ?></span>
                                </label>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                    <p id="catEmpty" class="hidden px-3 py-4 text-sm text-gray-500">No matching categories.</p>
                    <div class="p-3 border-t border-gray-100">
                        <span id="catCount" class="text-sm text-gray-600"></span>
                    </div>
                </div>
            </div>

            <!-- Date range picker: presets plus a range calendar -->
            <div class="relative w-full sm:w-auto" id="rangePicker">
                <button type="button" id="rangeButton" aria-haspopup="dialog" aria-expanded="false"
                        class="w-full sm:w-auto sm:min-w-[16rem] flex items-center gap-3 bg-white border border-gray-300 rounded-lg px-4 py-2 text-left shadow-sm hover:bg-gray-50 focus:outline-none focus:ring-4 focus:ring-blue-100">
                    <svg class="w-5 h-5 text-gray-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                    </svg>
                    <span class="flex-1 min-w-0">
                        <span class="block text-xs text-gray-500"><?= $activePreset ? $presets[$activePreset][0] : 'Custom Range' ?></span>
                        <span class="block text-sm font-semibold text-gray-800 truncate"><?= htmlspecialchars($periodLabel) ?></span>
                    </span>
                    <svg class="w-4 h-4 text-gray-500 shrink-0" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" aria-hidden="true">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M19 9l-7 7-7-7"></path>
                    </svg>
                </button>

                <div id="rangePanel" role="dialog" aria-label="Choose date range"
                     class="hidden absolute right-0 z-30 mt-2 w-full sm:w-max bg-white border border-gray-200 rounded-xl shadow-xl">
                    <div class="flex flex-col sm:flex-row">
                        <div class="flex sm:flex-col gap-2 sm:gap-1 p-3 overflow-x-auto [scrollbar-width:none] sm:overflow-visible border-b sm:border-b-0 sm:border-r border-gray-100 sm:w-40">
                            <?php foreach ($presets as $key => [$label]): ?>
                                <a href="?p=<?= $key ?><?= $catQuery ?>"
                                   class="shrink-0 whitespace-nowrap rounded-full sm:rounded-lg px-3 py-1.5 text-sm <?= $key === $activePreset ? 'bg-blue-600 text-white font-medium' : 'bg-gray-100 sm:bg-transparent text-gray-700 hover:bg-gray-100' ?>"><?= $label ?></a>
                            <?php endforeach; ?>
                        </div>
                        <div class="p-3">
                            <div id="rangeCalendar"></div>
                            <div class="flex flex-wrap items-center justify-between gap-3 pt-3 mt-3 border-t border-gray-100">
                                <span id="rangeText" class="text-sm text-gray-600"></span>
                                <div class="flex gap-2 ml-auto">
                                    <button type="button" id="rangeCancel" class="px-3 py-2 text-sm rounded-lg text-gray-700 hover:bg-gray-100">Cancel</button>
                                    <button type="button" id="rangeApply" class="px-4 py-2 text-sm font-medium rounded-lg bg-blue-600 text-white hover:bg-blue-700 disabled:opacity-50">Apply</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-2 md:grid-cols-3 xl:grid-cols-5 gap-3 md:gap-6 mb-6 md:mb-8">
            <div class="bg-white rounded-lg shadow-sm p-4 md:p-6">
                <div class="flex items-center">
                    <div class="flex-1">
                        <h2 class="text-xs md:text-sm font-medium text-gray-500"><?= $byDay ? 'Daily' : 'Monthly' ?> Average</h2>
                        <p class="text-lg md:text-2xl font-bold text-blue-600"><?= number_format($average, 2) ?>€</p>
                    </div>
                    <div class="w-6 h-6 md:w-8 md:h-8 bg-blue-100 rounded-full flex items-center justify-center">
                        <svg class="w-3 h-3 md:w-4 md:h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z"></path>
                            <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-4 md:p-6">
                <div class="flex items-center">
                    <div class="flex-1">
                        <h2 class="text-xs md:text-sm font-medium text-gray-500">Total (<?= $days ?> day<?= $days == 1 ? '' : 's' ?>)</h2>
                        <p class="text-lg md:text-2xl font-bold text-green-600"><?= number_format($totalAmount, 2) ?>€</p>
                    </div>
                    <div class="w-6 h-6 md:w-8 md:h-8 bg-green-100 rounded-full flex items-center justify-center">
                        <svg class="w-3 h-3 md:w-4 md:h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-4 md:p-6">
                <div class="flex items-center">
                    <div class="flex-1">
                        <h2 class="text-xs md:text-sm font-medium text-gray-500">Transactions</h2>
                        <p class="text-lg md:text-2xl font-bold text-purple-600"><?= number_format($txCount) ?></p>
                    </div>
                    <div class="w-6 h-6 md:w-8 md:h-8 bg-purple-100 rounded-full flex items-center justify-center">
                        <svg class="w-3 h-3 md:w-4 md:h-4 text-purple-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M3 4a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 6a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1zm0 6a1 1 0 011-1h12a1 1 0 110 2H4a1 1 0 01-1-1z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="bg-white rounded-lg shadow-sm p-4 md:p-6">
                <div class="flex items-center">
                    <div class="flex-1">
                        <h2 class="text-xs md:text-sm font-medium text-gray-500">Avg Transaction</h2>
                        <p class="text-lg md:text-2xl font-bold text-amber-600"><?= number_format($txAverage, 2) ?>€</p>
                    </div>
                    <div class="w-6 h-6 md:w-8 md:h-8 bg-amber-100 rounded-full flex items-center justify-center">
                        <svg class="w-3 h-3 md:w-4 md:h-4 text-amber-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M5 2a2 2 0 00-2 2v14l3.5-2 3.5 2 3.5-2 3.5 2V4a2 2 0 00-2-2H5zm2.5 3a1.5 1.5 0 100 3 1.5 1.5 0 000-3zm6.207.293a1 1 0 00-1.414 0l-6 6a1 1 0 101.414 1.414l6-6a1 1 0 000-1.414zM12.5 10a1.5 1.5 0 100 3 1.5 1.5 0 000-3z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
            </div>

            <div class="col-span-2 md:col-span-1 bg-white rounded-lg shadow-sm p-4 md:p-6">
                <div class="flex items-center">
                    <div class="flex-1">
                        <h2 class="text-xs md:text-sm font-medium text-gray-500">Trend</h2>
                        <p class="text-lg md:text-2xl font-bold <?= $trendColor ?>">
                            <?= $trendArrow ?> <?= $trendText ?>
                        </p>
                    </div>
                    <div class="w-6 h-6 md:w-8 md:h-8 <?= $trendBg ?> rounded-full flex items-center justify-center">
                        <svg class="w-3 h-3 md:w-4 md:h-4 <?= $trendColor ?>" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.414 14.586 7H12z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Line Chart -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4"><?= $byDay ? 'Daily' : 'Monthly' ?> Expenses</h2>
            <div class="relative h-64 sm:h-80 lg:h-96">
                <canvas id="expenseChart"></canvas>
            </div>
        </div>

        <!-- Year over Year -->
        <div class="bg-white rounded-lg shadow-lg p-3 sm:p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Year over Year - <?= $yoyYear - 2 ?> to <?= $yoyYear ?></h2>
            <div class="grid grid-cols-3 gap-2 sm:gap-4 mb-4">
                <?php foreach ($yoyYears as $k => $year): ?>
                    <div class="rounded-lg bg-gray-50 p-2 sm:p-4">
                        <h3 class="text-xs md:text-sm font-medium text-gray-500"><?= $year ?> <span class="hidden sm:inline">YTD</span></h3>
                        <p class="text-base md:text-2xl font-bold <?= $k === 0 ? 'text-blue-600' : 'text-gray-600' ?>"><?= number_format($ytdTotals[$year], 0) ?>€</p>
                        <?php if ($k > 0): ?>
                            <?php $change = $ytdTotals[$year] > 0 ? round(($ytdTotals[$yoyYear] - $ytdTotals[$year]) / $ytdTotals[$year] * 100, 1) : null; ?>
                            <p class="text-xs md:text-sm font-medium <?= $change === null ? 'text-gray-400' : ($change > 0 ? 'text-red-600' : ($change < 0 ? 'text-green-600' : 'text-gray-600')) ?>">
                                <?= $yoyYear ?>: <?= $change === null ? '—' : ($change > 0 ? '+' : '') . number_format($change, 1) . '%' ?>
                            </p>
                        <?php endif; ?>
                    </div>
                <?php endforeach; ?>
            </div>
            <p class="text-xs text-gray-500 mb-3">Year to date: <?= htmlspecialchars($ytdLabel) ?></p>
            <div class="relative h-64 sm:h-80">
                <canvas id="yoyChart"></canvas>
            </div>
        </div>

        <!-- Expense Categories -->
        <div class="bg-white rounded-lg shadow-lg p-3 sm:p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-3">Expense Categories</h2>
            <?php if (!$categoryData): ?>
                <p class="text-sm text-gray-500">No expenses in this period.</p>
            <?php else: ?>
                <div class="overflow-auto max-h-[28rem]">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 z-20 bg-white">
                            <tr class="border-b text-xs text-gray-500">
                                <th class="py-2 pr-2 text-left font-medium">Category</th>
                                <th class="py-2 px-2 text-center font-medium w-1/2">Total</th>
                                <th class="py-2 px-2 text-right font-medium">% of Total</th>
                                <th class="py-2 px-2 text-right font-medium"><span class="sm:hidden">#</span><span class="hidden sm:inline">Transactions</span></th>
                                <th class="py-2 pl-2 text-right font-medium">Average</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($categoryData as $row): ?>
                                <?php
                                $catTotal = (float)$row['total'];
                                $catCount = (int)$row['cnt'];
                                $barWidth = $categoryMax > 0 ? max(1, round($catTotal / $categoryMax * 100, 1)) : 0;
                                ?>
                                <tr class="border-b last:border-0">
                                    <td class="py-2 pr-2 text-gray-800 font-medium max-w-[8rem] sm:max-w-none truncate" title="<?= htmlspecialchars($row['category']) ?>"><?= htmlspecialchars($row['category']) ?></td>
                                    <td class="py-2 px-2">
                                        <div class="relative h-6 rounded bg-gray-100">
                                            <div class="absolute inset-y-0 left-0 rounded bg-blue-300" style="width: <?= $barWidth ?>%"></div>
                                            <span class="relative z-10 flex h-full items-center justify-center px-2 text-xs font-bold text-gray-900 whitespace-nowrap"><?= number_format($catTotal, 0) ?>€</span>
                                        </div>
                                    </td>
                                    <td class="py-2 px-2 text-right text-gray-700 tabular-nums"><?= $totalAmount > 0 ? number_format($catTotal / $totalAmount * 100, 1) : '0.0' ?>%</td>
                                    <td class="py-2 px-2 text-right text-gray-700 tabular-nums"><?= number_format($catCount) ?></td>
                                    <td class="py-2 pl-2 text-right text-gray-700 tabular-nums"><?= number_format($catCount > 0 ? $catTotal / $catCount : 0, 2) ?>€</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

        <!-- Largest Expenses -->
        <div class="bg-white rounded-lg shadow-lg p-3 sm:p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-3">10 Largest Expenses</h2>
            <?php if (!$largestExpenses): ?>
                <p class="text-sm text-gray-500">No expenses in this period.</p>
            <?php else: ?>
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b text-xs text-gray-500">
                                <th class="py-2 pr-2 text-left font-medium">Date</th>
                                <th class="py-2 px-2 text-left font-medium">Category</th>
                                <th class="py-2 px-2 text-left font-medium">Description</th>
                                <th class="py-2 pl-2 text-right font-medium">Amount</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($largestExpenses as $row): ?>
                                <tr class="border-b last:border-0 cursor-pointer hover:bg-gray-50" onclick="window.location.href='edit.php?id=<?= (int)$row['expense_id'] ?>'">
                                    <td class="py-2 pr-2 text-gray-700 whitespace-nowrap"><?= htmlspecialchars(date('M j, Y', strtotime($row['created_at']))) ?></td>
                                    <td class="py-2 px-2 text-gray-800 font-medium max-w-[7rem] sm:max-w-none truncate" title="<?= htmlspecialchars($row['category_name']) ?>"><?= htmlspecialchars($row['category_name']) ?></td>
                                    <td class="py-2 px-2 text-gray-600 max-w-[8rem] sm:max-w-none truncate" title="<?= htmlspecialchars($row['expense_description'] ?? '') ?>"><?= htmlspecialchars($row['expense_description'] ?? '') ?></td>
                                    <td class="py-2 pl-2 text-right text-gray-900 font-bold tabular-nums whitespace-nowrap"><?= number_format((float)$row['expense_amount'], 2) ?>€</td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php endif; ?>
        </div>

    </main>

    <script src="https://unpkg.com/flowbite@4.0.2/dist/flowbite.bundle.js"></script>
    <script>
        // Date range picker
        (function () {
            const rangeFrom = '<?= $from->format('Y-m-d') ?>';
            const rangeTo = '<?= $to->format('Y-m-d') ?>';
            const wrapper = document.getElementById('rangePicker');
            const button = document.getElementById('rangeButton');
            const panel = document.getElementById('rangePanel');
            const applyButton = document.getElementById('rangeApply');
            const text = document.getElementById('rangeText');
            const twoMonths = window.matchMedia('(min-width: 768px)').matches;

            function showSelection(dates) {
                applyButton.disabled = dates.length === 0;
                text.textContent = dates.length === 0 ? 'Pick a start date'
                    : dates.length === 1 ? flatpickr.formatDate(dates[0], 'M j, Y') + ' – pick an end date'
                    : dates.map(d => flatpickr.formatDate(d, 'M j, Y')).join(' – ');
            }

            function showRange() {
                picker.setDate([rangeFrom, rangeTo]);
                // With two months shown, put the range's end month on the right
                const end = flatpickr.parseDate(rangeTo, 'Y-m-d');
                picker.jumpToDate(twoMonths ? new Date(end.getFullYear(), end.getMonth() - 1, 1) : end);
                showSelection(picker.selectedDates);
            }

            // Created on first open: flatpickr measures its months, which fails while the panel is hidden
            let picker = null;

            function setOpen(open) {
                panel.classList.toggle('hidden', !open);
                button.setAttribute('aria-expanded', open);
                if (open && !picker) {
                    picker = flatpickr('#rangeCalendar', {
                        inline: true,
                        mode: 'range',
                        showMonths: twoMonths ? 2 : 1,
                        maxDate: 'today',
                        locale: { firstDayOfWeek: 1 },
                        onChange: showSelection
                    });
                    showRange();
                }
            }

            button.addEventListener('click', () => setOpen(panel.classList.contains('hidden')));
            document.getElementById('rangeCancel').addEventListener('click', () => {
                showRange();
                setOpen(false);
            });
            applyButton.addEventListener('click', () => {
                const dates = picker.selectedDates;
                const from = flatpickr.formatDate(dates[0], 'Y-m-d');
                const to = flatpickr.formatDate(dates[dates.length - 1], 'Y-m-d');
                window.location.search = '?from=' + from + '&to=' + to + '<?= $catQuery ?>';
            });
            document.addEventListener('mousedown', (event) => {
                if (!wrapper.contains(event.target)) setOpen(false);
            });
            document.addEventListener('keydown', (event) => {
                if (event.key === 'Escape') setOpen(false);
            });
        })();

        // Category filter
        (function () {
            const periodQuery = '<?= $activePreset ? 'p=' . $activePreset : 'from=' . $from->format('Y-m-d') . '&to=' . $to->format('Y-m-d') ?>';
            const wrapper = document.getElementById('catPicker');
            const button = document.getElementById('catButton');
            const panel = document.getElementById('catPanel');
            const search = document.getElementById('catSearch');
            const count = document.getElementById('catCount');
            const empty = document.getElementById('catEmpty');
            const boxes = Array.from(document.querySelectorAll('#catList input[type=checkbox]'));
            const initial = boxes.map(box => box.checked);

            // Case- and accent-insensitive, so "φαγητο" finds "Φαγητό"
            const fold = s => s.normalize('NFD').replace(/[̀-ͯ]/g, '').toLowerCase();

            function showCount() {
                const checked = boxes.filter(box => box.checked).length;
                count.textContent = checked === 0 ? 'None selected – closing keeps the current filter'
                    : checked === boxes.length ? 'All selected' : checked + ' of ' + boxes.length;
            }

            function filterList() {
                const term = fold(search.value.trim());
                let shown = 0;
                boxes.forEach(box => {
                    const match = fold(box.parentElement.textContent).includes(term);
                    box.closest('li').classList.toggle('hidden', !match);
                    if (match) shown++;
                });
                empty.classList.toggle('hidden', shown > 0);
            }

            // Select all / Clear act on the categories the search currently shows
            function setVisible(checked) {
                boxes.forEach(box => {
                    if (!box.closest('li').classList.contains('hidden')) box.checked = checked;
                });
                showCount();
            }

            function setOpen(open) {
                panel.classList.toggle('hidden', !open);
                button.setAttribute('aria-expanded', open);
                if (open) search.focus();
            }

            function reset() {
                boxes.forEach((box, i) => { box.checked = initial[i]; });
                search.value = '';
                filterList();
                showCount();
            }

            // Closing the panel applies the selection: reload only if it changed, keep the old one if none is checked
            function close() {
                const ids = boxes.filter(box => box.checked).map(box => box.value);
                const changed = boxes.some((box, i) => box.checked !== initial[i]);
                if (changed && ids.length > 0) {
                    window.location.search = '?' + periodQuery + (ids.length === boxes.length ? '' : '&cat=' + ids.join(','));
                    return;
                }
                reset();
                setOpen(false);
            }

            button.addEventListener('click', () => {
                if (panel.classList.contains('hidden')) setOpen(true);
                else close();
            });
            search.addEventListener('input', filterList);
            boxes.forEach(box => box.addEventListener('change', showCount));
            document.getElementById('catAll').addEventListener('click', () => setVisible(true));
            document.getElementById('catNone').addEventListener('click', () => setVisible(false));
            document.addEventListener('mousedown', (event) => {
                if (!wrapper.contains(event.target) && !panel.classList.contains('hidden')) close();
            });
            document.addEventListener('keydown', (event) => {
                // Escape discards the changes
                if (event.key === 'Escape' && !panel.classList.contains('hidden')) {
                    reset();
                    setOpen(false);
                }
            });
            showCount();
        })();

        // Chart data from PHP
        const periodData = <?= json_encode($periodData) ?>;
        const trendData = <?= json_encode($trendData) ?>;

        // Prepare line chart data
        const labels = periodData.map(item => item.label);
        const amounts = periodData.map(item => item.amount);
        const pointRadius = amounts.length > 24 ? 2 : 6;

        // Line Chart configuration
        const lineConfig = {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: '<?= $byDay ? 'Daily' : 'Monthly' ?> Expenses',
                        data: amounts,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: pointRadius,
                        pointHoverRadius: pointRadius + 2,
                        pointBackgroundColor: 'rgb(59, 130, 246)',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2
                    },
                    {
                        label: 'Trend Line',
                        data: trendData,
                        borderColor: 'rgb(239, 68, 68)',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [5, 5],
                        fill: false,
                        pointRadius: 0,
                        pointHoverRadius: 0
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: 'rgba(59, 130, 246, 0.5)',
                        borderWidth: 1,
                        filter: function(tooltipItem) {
                            // Only show tooltip for the expenses dataset (index 0)
                            return tooltipItem.datasetIndex === 0;
                        },
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': €' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        title: {
                            display: false
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        title: {
                            display: false
                        },
                        ticks: {
                            callback: function(value) {
                                return '€' + value.toFixed(0);
                            }
                        }
                    }
                }
            }
        };

        // Create charts
        const lineCtx = document.getElementById('expenseChart').getContext('2d');
        const expenseChart = new Chart(lineCtx, lineConfig);

        // Year over Year line chart
        const yoyYear = <?= $yoyYear ?>;
        const yoyConfig = {
            type: 'line',
            data: {
                labels: ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'],
                datasets: [
                    {
                        label: String(yoyYear - 2),
                        data: <?= json_encode($yoySeries[$yoyYear - 2]) ?>,
                        borderColor: 'rgb(209, 213, 219)',
                        backgroundColor: 'rgb(209, 213, 219)',
                        borderWidth: 2,
                        borderDash: [2, 3],
                        tension: 0.3,
                        pointRadius: 2,
                        pointHoverRadius: 4
                    },
                    {
                        label: String(yoyYear - 1),
                        data: <?= json_encode($yoySeries[$yoyYear - 1]) ?>,
                        borderColor: 'rgb(107, 114, 128)',
                        backgroundColor: 'rgb(107, 114, 128)',
                        borderWidth: 2,
                        borderDash: [6, 4],
                        tension: 0.3,
                        pointRadius: 3,
                        pointHoverRadius: 5
                    },
                    {
                        label: String(yoyYear),
                        data: <?= json_encode($yoySeries[$yoyYear]) ?>,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgb(59, 130, 246)',
                        borderWidth: 3,
                        tension: 0.3,
                        pointRadius: 4,
                        pointHoverRadius: 6,
                        pointBorderColor: 'white',
                        pointBorderWidth: 2
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: {
                    intersect: false,
                    mode: 'index'
                },
                plugins: {
                    legend: {
                        position: 'top',
                        labels: {
                            usePointStyle: true,
                            padding: 20
                        }
                    },
                    tooltip: {
                        filter: function(tooltipItem) {
                            return tooltipItem.parsed.y !== null;
                        },
                        callbacks: {
                            label: function(context) {
                                return context.dataset.label + ': €' + context.parsed.y.toFixed(2);
                            }
                        }
                    }
                },
                scales: {
                    x: {
                        grid: { display: false }
                    },
                    y: {
                        beginAtZero: true,
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        ticks: {
                            callback: function(value) {
                                return '€' + value.toFixed(0);
                            }
                        }
                    }
                }
            }
        };
        const yoyCtx = document.getElementById('yoyChart').getContext('2d');
        const yoyChart = new Chart(yoyCtx, yoyConfig);
    </script>
</body>
</html>
