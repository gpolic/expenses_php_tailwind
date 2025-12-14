<?php
require_once 'config.php';
require_once 'session_check.php';

// Get last 12 completed months of expense data (excluding current month)
try {
    $monthlyData = [];
    $currentDate = new DateTime();
    
    for ($i = 12; $i >= 1; $i--) {
        $date = clone $currentDate;
        $date->modify("-$i months");
        $month = $date->format('m');
        $year = $date->format('Y');
        $monthName = $date->format('M Y');
        
        $sql = "SELECT COALESCE(SUM(expense_amount), 0) as total
                FROM expenses
                WHERE MONTH(created_at) = :month
                AND YEAR(created_at) = :year";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':month' => $month,
            ':year' => $year
        ]);
        
        $result = $stmt->fetch(PDO::FETCH_ASSOC);
        $monthlyData[] = [
            'month' => $monthName,
            'amount' => floatval($result['total'])
        ];
    }
    
    // Calculate average
    $totalAmount = array_sum(array_column($monthlyData, 'amount'));
    $average = $totalAmount / 12;
    
    // Calculate trend (simple linear regression)
    $n = count($monthlyData);
    $sumX = 0;
    $sumY = 0;
    $sumXY = 0;
    $sumX2 = 0;
    
    for ($i = 0; $i < $n; $i++) {
        $x = $i + 1;
        $y = $monthlyData[$i]['amount'];
        $sumX += $x;
        $sumY += $y;
        $sumXY += $x * $y;
        $sumX2 += $x * $x;
    }
    
    $slope = ($n * $sumXY - $sumX * $sumY) / ($n * $sumX2 - $sumX * $sumX);
    $intercept = ($sumY - $slope * $sumX) / $n;
    
    // Generate trend line data
    $trendData = [];
    for ($i = 0; $i < $n; $i++) {
        $trendData[] = $slope * ($i + 1) + $intercept;
    }
    
} catch(PDOException $e) {
    $monthlyData = [];
    $average = 0;
    $trendData = [];
}

// Get top 10 expense categories by total amount for the same 12-month period
try {
    $currentDate = new DateTime();
    $startDate = clone $currentDate;
    $startDate->modify("-12 months");
    $endDate = clone $currentDate;
    $endDate->modify("-1 month")->modify("last day of this month");
    
    $categorySql = "SELECT ec.category_name as category, SUM(e.expense_amount) as total
                    FROM expenses e
                    JOIN expense_categories ec ON e.category_id = ec.category_id
                    WHERE e.created_at >= :startDate
                    AND e.created_at <= :endDate
                    GROUP BY e.category_id, ec.category_name
                    ORDER BY total DESC
                    LIMIT 10";
    
    $stmt = $pdo->prepare($categorySql);
    $stmt->execute([
        ':startDate' => $startDate->format('Y-m-01'),
        ':endDate' => $endDate->format('Y-m-t')
    ]);
    
    $categoryData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch(PDOException $e) {
    $categoryData = [];
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Expense Tracker</title>
    <link href="https://unpkg.com/flowbite@latest/dist/flowbite.min.css" rel="stylesheet" />
    <script src="https://cdn.tailwindcss.com"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-gray-50">
    <div class="container mx-auto px-4 py-8">
        
        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 mb-6">
            <h1 class="text-2xl sm:text-3xl font-bold text-gray-800">Expense Reports</h1>
            <div class="flex gap-2 w-full sm:w-auto">
                <a href="index.php"
                   class="flex-1 sm:flex-none bg-blue-500 hover:bg-blue-700 text-white font-bold py-2 px-4 rounded text-center text-sm sm:text-base">
                    Back to List
                </a>
                <a href="logout.php"
                   class="flex-1 sm:flex-none bg-red-500 hover:bg-red-700 text-white font-bold py-2 px-4 rounded text-center text-sm sm:text-base">
                    Logout
                </a>
            </div>
        </div>

        <!-- Summary Cards -->
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-8">
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-1">
                        <h2 class="text-sm font-medium text-gray-500">12-Month Average</h2>
                        <p class="text-2xl font-bold text-blue-600"><?= number_format($average, 2) ?>€</p>
                    </div>
                    <div class="w-8 h-8 bg-blue-100 rounded-full flex items-center justify-center">
                        <svg class="w-4 h-4 text-blue-600" fill="currentColor" viewBox="0 0 20 20">
                            <path d="M2 10a8 8 0 018-8v8h8a8 8 0 11-16 0z"></path>
                            <path d="M12 2.252A8.014 8.014 0 0117.748 8H12V2.252z"></path>
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-1">
                        <h2 class="text-sm font-medium text-gray-500">Total (12 months)</h2>
                        <p class="text-2xl font-bold text-green-600"><?= number_format($totalAmount, 2) ?>€</p>
                    </div>
                    <div class="w-8 h-8 bg-green-100 rounded-full flex items-center justify-center">
                        <svg class="w-4 h-4 text-green-600" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M4 4a2 2 0 00-2 2v4a2 2 0 002 2V6h10a2 2 0 00-2-2H4zm2 6a2 2 0 012-2h8a2 2 0 012 2v4a2 2 0 01-2 2H8a2 2 0 01-2-2v-4zm6 4a2 2 0 100-4 2 2 0 000 4z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
            </div>
            
            <div class="bg-white rounded-lg shadow-sm p-6">
                <div class="flex items-center">
                    <div class="flex-1">
                        <h2 class="text-sm font-medium text-gray-500">Trend</h2>
                        <p class="text-2xl font-bold <?= $slope > 0 ? 'text-red-600' : 'text-green-600' ?>">
                            <?= $slope > 0 ? '↗' : '↘' ?> <?= abs($slope) > 1 ? 'Strong' : 'Moderate' ?>
                        </p>
                    </div>
                    <div class="w-8 h-8 <?= $slope > 0 ? 'bg-red-100' : 'bg-green-100' ?> rounded-full flex items-center justify-center">
                        <svg class="w-4 h-4 <?= $slope > 0 ? 'text-red-600' : 'text-green-600' ?>" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M12 7a1 1 0 110-2h5a1 1 0 011 1v5a1 1 0 11-2 0V8.414l-4.293 4.293a1 1 0 01-1.414 0L8 10.414l-4.293 4.293a1 1 0 01-1.414-1.414l5-5a1 1 0 011.414 0L11 10.414 14.586 7H12z" clip-rule="evenodd"></path>
                        </svg>
                    </div>
                </div>
            </div>
        </div>

        <!-- Line Chart -->
        <div class="bg-white rounded-lg shadow-lg p-6 mb-8">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Monthly Expenses - Last 12 Months</h2>
            <div class="relative h-96">
                <canvas id="expenseChart"></canvas>
            </div>
        </div>

        <!-- Bar Chart -->
        <div class="bg-white rounded-lg shadow-lg p-6">
            <h2 class="text-xl font-bold text-gray-800 mb-4">Top 10 Expense Categories</h2>
            <div class="relative h-96">
                <canvas id="categoryChart"></canvas>
            </div>
        </div>

    </div>

    <script src="https://unpkg.com/flowbite@latest/dist/flowbite.bundle.js"></script>
    <script>
        // Chart data from PHP
        const monthlyData = <?= json_encode($monthlyData) ?>;
        const average = <?= $average ?>;
        const trendData = <?= json_encode($trendData) ?>;
        const categoryData = <?= json_encode($categoryData) ?>;
        
        // Prepare line chart data
        const labels = monthlyData.map(item => item.month);
        const amounts = monthlyData.map(item => item.amount);
        const averageData = new Array(labels.length).fill(average);
        
        // Prepare bar chart data
        const categoryLabels = categoryData.map(item => item.category);
        const categoryAmounts = categoryData.map(item => parseFloat(item.total));
        
        // Line Chart configuration
        const lineConfig = {
            type: 'line',
            data: {
                labels: labels,
                datasets: [
                    {
                        label: 'Monthly Expenses',
                        data: amounts,
                        borderColor: 'rgb(59, 130, 246)',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        pointBackgroundColor: 'rgb(59, 130, 246)',
                        pointBorderColor: 'white',
                        pointBorderWidth: 2
                    },
                    {
                        label: 'Average (' + average.toFixed(2) + '€)',
                        data: averageData,
                        borderColor: 'rgb(16, 185, 129)',
                        backgroundColor: 'transparent',
                        borderWidth: 2,
                        borderDash: [10, 5],
                        fill: false,
                        pointRadius: 0,
                        pointHoverRadius: 0
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
                        callbacks: {
                            label: function(context) {
                                if (context.datasetIndex === 0) {
                                    return context.dataset.label + ': €' + context.parsed.y.toFixed(2);
                                } else if (context.datasetIndex === 1) {
                                    return 'Average: €' + context.parsed.y.toFixed(2);
                                } else {
                                    return 'Trend: €' + context.parsed.y.toFixed(2);
                                }
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
                            display: true,
                            text: 'Month',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        title: {
                            display: true,
                            text: 'Amount (€)',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
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

        // Bar Chart configuration
        const barConfig = {
            type: 'bar',
            data: {
                labels: categoryLabels,
                datasets: [{
                    label: 'Total Amount (€)',
                    data: categoryAmounts,
                    backgroundColor: [
                        'rgba(59, 130, 246, 0.7)',   // Blue
                        'rgba(16, 185, 129, 0.7)',   // Green
                        'rgba(239, 68, 68, 0.7)',    // Red
                        'rgba(245, 158, 11, 0.7)',   // Yellow
                        'rgba(139, 92, 246, 0.7)',   // Purple
                        'rgba(236, 72, 153, 0.7)',   // Pink
                        'rgba(14, 165, 233, 0.7)',   // Sky
                        'rgba(34, 197, 94, 0.7)',    // Emerald
                        'rgba(251, 146, 60, 0.7)',   // Orange
                        'rgba(168, 85, 247, 0.7)'    // Violet
                    ],
                    borderColor: [
                        'rgb(59, 130, 246)',
                        'rgb(16, 185, 129)',
                        'rgb(239, 68, 68)',
                        'rgb(245, 158, 11)',
                        'rgb(139, 92, 246)',
                        'rgb(236, 72, 153)',
                        'rgb(14, 165, 233)',
                        'rgb(34, 197, 94)',
                        'rgb(251, 146, 60)',
                        'rgb(168, 85, 247)'
                    ],
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        display: false
                    },
                    tooltip: {
                        backgroundColor: 'rgba(0, 0, 0, 0.8)',
                        titleColor: 'white',
                        bodyColor: 'white',
                        borderColor: 'rgba(59, 130, 246, 0.5)',
                        borderWidth: 1,
                        callbacks: {
                            label: function(context) {
                                return 'Total: €' + context.parsed.y.toFixed(2);
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
                            display: true,
                            text: 'Expense Categories',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
                        },
                        ticks: {
                            maxRotation: 45,
                            minRotation: 45
                        }
                    },
                    y: {
                        grid: {
                            color: 'rgba(0, 0, 0, 0.1)'
                        },
                        title: {
                            display: true,
                            text: 'Total Amount (€)',
                            font: {
                                size: 14,
                                weight: 'bold'
                            }
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
        
        const barCtx = document.getElementById('categoryChart').getContext('2d');
        const categoryChart = new Chart(barCtx, barConfig);
    </script>
</body>
</html>