<!-- budget_list.php -->
<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '../../project-test1/BACKEND/db_inventory.php';

$view = isset($_GET['view']) ? $_GET['view'] : 'today';

if ($view === 'year') {
    $date_condition = "YEAR(sales.created_at) = YEAR(CURDATE())";
    $report_title = "This Year's Budget & Revenue Report (" . date('Y') . ")";
} elseif ($view === 'month') {
    $date_condition = "MONTH(sales.created_at) = MONTH(CURDATE()) AND YEAR(sales.created_at) = YEAR(CURDATE())";
    $report_title = "This Month's Budget & Revenue Report (" . date('F Y') . ")";
} else {
    $date_condition = "DATE(sales.created_at) = CURDATE()";
    $report_title = "Today's Budget & Revenue Report (" . date('F d, Y') . ")";
}

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    
    $daily_rev_query = "SELECT SUM(sales_items.quantity * sales_items.price_at_sale) as total FROM sales_items JOIN sales ON sales_items.sale_id = sales.id WHERE DATE(sales.created_at) = CURDATE()";
    $daily_orders_query = "SELECT COUNT(DISTINCT sales.id) as total_orders FROM sales WHERE DATE(sales.created_at) = CURDATE()";
    $daily_cust_query = "SELECT COUNT(DISTINCT sales.id) as total_cust FROM sales WHERE DATE(sales.created_at) = CURDATE()";

    $monthly_rev_query = "SELECT SUM(sales_items.quantity * sales_items.price_at_sale) as total FROM sales_items JOIN sales ON sales_items.sale_id = sales.id WHERE MONTH(sales.created_at) = MONTH(CURDATE()) AND YEAR(sales.created_at) = YEAR(CURDATE())";
    $monthly_orders_query = "SELECT COUNT(DISTINCT sales.id) as total_orders FROM sales WHERE MONTH(sales.created_at) = MONTH(CURDATE()) AND YEAR(sales.created_at) = YEAR(CURDATE())";
    $monthly_cust_query = "SELECT COUNT(DISTINCT sales.id) as total_cust FROM sales WHERE MONTH(sales.created_at) = MONTH(CURDATE()) AND YEAR(sales.created_at) = YEAR(CURDATE())";

    $yearly_rev_query = "SELECT SUM(sales_items.quantity * sales_items.price_at_sale) as total FROM sales_items JOIN sales ON sales_items.sale_id = sales.id WHERE YEAR(sales.created_at) = YEAR(CURDATE())";
    $yearly_orders_query = "SELECT COUNT(DISTINCT sales.id) as total_orders FROM sales WHERE YEAR(sales.created_at) = YEAR(CURDATE())";
    $yearly_cust_query = "SELECT COUNT(DISTINCT sales.id) as total_cust FROM sales WHERE YEAR(sales.created_at) = YEAR(CURDATE())";

    $total_budget_query = "SELECT SUM(sales_items.quantity * sales_items.price_at_sale) as total_company_budget FROM sales_items JOIN sales ON sales_items.sale_id = sales.id WHERE YEAR(sales.created_at) = YEAR(CURDATE())";
    $budget_result = $conn->query($total_budget_query);
    $raw_company_budget = ($budget_result && $row = $budget_result->fetch_assoc()) ? ($row['total_company_budget'] ?? 0) : 0;

    $deducted_query = "SELECT SUM(amount) as total_deducted FROM budget_requests WHERE status = 'Fully Approved (Admin)' AND YEAR(created_at) = YEAR(CURDATE())";
    $deducted_res = $conn->query($deducted_query);
    $total_deducted = ($deducted_res && $d_row = $deducted_res->fetch_assoc()) ? ($d_row['total_deducted'] ?? 0) : 0;

    $total_company_budget = $raw_company_budget - $total_deducted;

    $daily_total = $conn->query($daily_rev_query)->fetch_assoc()['total'] ?? 0;
    $daily_orders = $conn->query($daily_orders_query)->fetch_assoc()['total_orders'] ?? 0;
    $daily_cust = $conn->query($daily_cust_query)->fetch_assoc()['total_cust'] ?? 0;

    $monthly_total = $conn->query($monthly_rev_query)->fetch_assoc()['total'] ?? 0;
    $monthly_orders = $conn->query($monthly_orders_query)->fetch_assoc()['total_orders'] ?? 0;
    $monthly_cust = $conn->query($monthly_cust_query)->fetch_assoc()['total_cust'] ?? 0;

    $yearly_total = $conn->query($yearly_rev_query)->fetch_assoc()['total'] ?? 0;
    $yearly_orders = $conn->query($yearly_orders_query)->fetch_assoc()['total_orders'] ?? 0;
    $yearly_cust = $conn->query($yearly_cust_query)->fetch_assoc()['total_cust'] ?? 0;

    if ($view === 'year') {
        $graph_query = "
            SELECT DATE_FORMAT(sales.created_at, '%Y-%m') as label, SUM(sales_items.quantity * sales_items.price_at_sale) as total
            FROM sales_items
            JOIN sales ON sales_items.sale_id = sales.id
            WHERE $date_condition
            GROUP BY DATE_FORMAT(sales.created_at, '%Y-%m')
            ORDER BY DATE_FORMAT(sales.created_at, '%Y-%m') ASC";
    } elseif ($view === 'month') {
        $graph_query = "
            SELECT DATE(sales.created_at) as label, SUM(sales_items.quantity * sales_items.price_at_sale) as total
            FROM sales_items
            JOIN sales ON sales_items.sale_id = sales.id
            WHERE $date_condition
            GROUP BY DATE(sales.created_at)
            ORDER BY DATE(sales.created_at) ASC";
    } else {
        $graph_query = "
            SELECT 
                DATE_FORMAT(sales.created_at, '%h:00 %p') as label, 
                SUM(sales_items.quantity * sales_items.price_at_sale) as total
            FROM sales_items
            JOIN sales ON sales_items.sale_id = sales.id
            WHERE $date_condition
            GROUP BY HOUR(sales.created_at), DATE_FORMAT(sales.created_at, '%h:00 %p')
            ORDER BY HOUR(sales.created_at) ASC";
    }

    $graph_result = $conn->query($graph_query);
    $chart_labels = [];
    $chart_data = [];

    if ($graph_result && $graph_result->num_rows > 0) {
        while($g_row = $graph_result->fetch_assoc()) {
            if ($view === 'year') {
                $chart_labels[] = date('M Y', strtotime($g_row['label'] . '-01'));
            } elseif ($view === 'month') {
                $chart_labels[] = date('M d', strtotime($g_row['label']));
            } else {
                $chart_labels[] = $g_row['label'];
            }
            $chart_data[] = $g_row['total'];
        }
    }
    ?>

    <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
        <div class="bg-white p-5 rounded-2xl shadow-sm border-l-4 border-amber-500">
            <div class="flex items-center justify-between">
                <div class="text-slate-400 text-xs uppercase font-bold tracking-wider">Total Company Budget (Net)</div>
                <div class="h-3 w-3 rounded-full bg-amber-500/20 flex items-center justify-center">
                    <div class="h-1.5 w-1.5 rounded-full bg-amber-500 animate-pulse"></div>
                </div>
            </div>
            <div class="text-3xl font-bold text-slate-800 mt-1">₱<?php echo number_format($total_company_budget, 2); ?></div>
            <div class="flex justify-between text-xs text-slate-500 mt-3 pt-3 border-t border-slate-100">
                <span>Deducted Expenses: <strong class="text-slate-700">₱<?php echo number_format($total_deducted, 2); ?></strong></span>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border-l-4 border-blue-500">
            <div class="flex items-center justify-between">
                <div class="text-slate-400 text-xs uppercase font-bold tracking-wider">Daily Revenue (Today)</div>
                <div class="h-3 w-3 rounded-full bg-blue-500/20 flex items-center justify-center">
                    <div class="h-1.5 w-1.5 rounded-full bg-blue-500 animate-pulse"></div>
                </div>
            </div>
            <div class="text-3xl font-bold text-slate-800 mt-1">₱<?php echo number_format($daily_total, 2); ?></div>
            <div class="flex justify-between text-xs text-slate-500 mt-3 pt-3 border-t border-slate-100">
                <span>Orders: <strong class="text-slate-700"><?php echo number_format($daily_orders); ?></strong></span>
                <span>Customers: <strong class="text-slate-700"><?php echo number_format($daily_cust); ?></strong></span>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border-l-4 border-indigo-500">
            <div class="flex items-center justify-between">
                <div class="text-slate-400 text-xs uppercase font-bold tracking-wider">Monthly Revenue</div>
                <div class="h-3 w-3 rounded-full bg-indigo-500/20 flex items-center justify-center">
                    <div class="h-1.5 w-1.5 rounded-full bg-indigo-500 animate-pulse"></div>
                </div>
            </div>
            <div class="text-3xl font-bold text-slate-800 mt-1">₱<?php echo number_format($monthly_total, 2); ?></div>
            <div class="flex justify-between text-xs text-slate-500 mt-3 pt-3 border-t border-slate-100">
                <span>Orders: <strong class="text-slate-700"><?php echo number_format($monthly_orders); ?></strong></span>
                <span>Customers: <strong class="text-slate-700"><?php echo number_format($monthly_cust); ?></strong></span>
            </div>
        </div>

        <div class="bg-white p-5 rounded-2xl shadow-sm border-l-4 border-emerald-500">
            <div class="flex items-center justify-between">
                <div class="text-slate-400 text-xs uppercase font-bold tracking-wider">Yearly Revenue</div>
                <div class="h-3 w-3 rounded-full bg-emerald-500/20 flex items-center justify-center">
                    <div class="h-1.5 w-1.5 rounded-full bg-emerald-500 animate-pulse"></div>
                </div>
            </div>
            <div class="text-3xl font-bold text-emerald-600 mt-1">₱<?php echo number_format($yearly_total, 2); ?></div>
            <div class="flex justify-between text-xs text-slate-500 mt-3 pt-3 border-t border-slate-100">
                <span>Orders: <strong class="text-slate-700"><?php echo number_format($yearly_orders); ?></strong></span>
                <span>Customers: <strong class="text-slate-700"><?php echo number_format($yearly_cust); ?></strong></span>
            </div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm mb-6">
        <h5 class="text-md font-bold text-slate-700 mb-4 flex items-center"><i class="bi bi-graph-up text-indigo-500 me-2"></i>Budget Wave Performance Graph</h5>
        <div class="relative h-[280px] w-full">
            <canvas id="salesChart" 
                    data-labels="<?php echo htmlspecialchars(json_encode($chart_labels)); ?>" 
                    data-values="<?php echo htmlspecialchars(json_encode($chart_data)); ?>"></canvas>
        </div>
    </div>

    <?php
    $conn->close();
    exit; 
}
?>

<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Management</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="h-full flex overflow-hidden font-sans text-slate-800 antialiased">
   <div class="flex h-screen w-full overflow-hidden">
     <?php include 'sidebar.php'; ?>
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden">
        <header class="bg-white border-b border-slate-100 h-16 flex items-center px-6 shrink-0 md:hidden">
            <button class="p-2 -ml-2 rounded-xl text-slate-600 hover:bg-slate-100" type="button" id="burgerToggle">
                <i class="bi bi-list text-2xl"></i>
            </button>
        </header>

        <div class="flex-1 overflow-y-auto p-6 md:p-8">
            <div class="max-w-7xl mx-auto">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4 mb-8">
                    <div>
                        <h2 class="text-2xl font-bold text-slate-800 tracking-tight flex items-center gap-3">
                            <i class="bi bi-wallet2 text-[#4e73df]"></i>
                            <span id="report-title"><?php echo $report_title; ?></span>
                        </h2>
                    </div>
                    <div class="inline-flex bg-slate-200/60 p-1 rounded-xl self-start md:self-auto">
                        <button onclick="switchView('today')" id="btn-today" class="px-4 py-2 rounded-lg text-sm font-bold transition-all duration-200 flex items-center gap-2">
                            <i class="bi bi-calendar-event"></i>Today
                        </button>
                        <button onclick="switchView('month')" id="btn-month" class="px-4 py-2 rounded-lg text-sm font-bold transition-all duration-200 flex items-center gap-2">
                            <i class="bi bi-calendar-month"></i>Month
                        </button>
                        <button onclick="switchView('year')" id="btn-year" class="px-4 py-2 rounded-lg text-sm font-bold transition-all duration-200 flex items-center gap-2">
                            <i class="bi bi-calendar-check"></i>Year
                        </button>
                    </div>
                </div>

                <div id="live-sales-container">
                    <div class="flex flex-col items-center justify-center py-20 bg-white rounded-2xl shadow-sm border border-slate-100">
                        <div class="animate-spin rounded-full h-8 w-8 border-2 border-indigo-600 border-t-transparent mb-3"></div>
                        <p class="text-sm text-slate-400 font-medium">Loading budget data...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script>
        let currentView = "<?php echo $view; ?>";
        let salesChartInstance = null;

        function initChart() {
            const chartCanvas = document.getElementById('salesChart');
            if (!chartCanvas) return;

            const labels = JSON.parse(chartCanvas.getAttribute('data-labels') || '[]');
            const values = JSON.parse(chartCanvas.getAttribute('data-values') || '[]');
            const ctx = chartCanvas.getContext('2d');
            
            if (salesChartInstance) {
                salesChartInstance.destroy();
            }

            let gradient = ctx.createLinearGradient(0, 0, 0, 280);
            gradient.addColorStop(0, 'rgba(79, 70, 229, 0.4)');
            gradient.addColorStop(1, 'rgba(79, 70, 229, 0.0)');

            salesChartInstance = new Chart(ctx, {
                type: 'line', 
                data: {
                    labels: labels, 
                    datasets: [{
                        label: 'Revenue (₱)',
                        data: values,
                        borderColor: '#4f46e5',
                        backgroundColor: gradient,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#4f46e5',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: {
                        duration: 1200,
                        easing: 'easeOutQuart'
                    }, 
                    plugins: { legend: { display: false } },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#f1f5f9' },
                            ticks: {
                                color: '#94a3b8',
                                callback: function(value) { return '₱' + value.toLocaleString(); }
                            }
                        },
                        x: { grid: { display: false }, ticks: { color: '#94a3b8' } }
                    }
                }
            });
        }

        function fetchSalesData() {
            fetch(`?view=${currentView}&ajax=1`)
                .then(response => response.text())
                .then(htmlContent => {
                    document.getElementById('live-sales-container').innerHTML = htmlContent;
                    initChart();
                })
                .catch(error => console.error('Error fetching data:', error));
        }

        function switchView(viewType) {
            currentView = viewType;
            const btnToday = document.getElementById('btn-today');
            const btnMonth = document.getElementById('btn-month');
            const btnYear = document.getElementById('btn-year');
            const titleSpan = document.getElementById('report-title');
            
            const now = new Date();
            const todayTitle = "Today's Budget & Revenue Report (" + now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) + ")";
            const monthTitle = "This Month's Budget & Revenue Report (" + now.toLocaleDateString('en-US', { month: 'long', year: 'numeric' }) + ")";
            const yearTitle = "This Year's Budget & Revenue Report (" + now.getFullYear() + ")";

            [btnToday, btnMonth, btnYear].forEach(btn => {
                btn.className = 'px-4 py-2 rounded-lg text-sm font-bold text-slate-500 hover:text-slate-800 transition-all flex items-center gap-2';
            });

            if(viewType === 'year') {
                titleSpan.innerText = yearTitle;
                btnYear.className = 'px-4 py-2 rounded-lg text-sm font-bold shadow-sm bg-white text-slate-800 transition-all flex items-center gap-2';
            } else if(viewType === 'month') {
                titleSpan.innerText = monthTitle;
                btnMonth.className = 'px-4 py-2 rounded-lg text-sm font-bold shadow-sm bg-white text-slate-800 transition-all flex items-center gap-2';
            } else {
                titleSpan.innerText = todayTitle;
                btnToday.className = 'px-4 py-2 rounded-lg text-sm font-bold shadow-sm bg-white text-slate-800 transition-all flex items-center gap-2';
            }
            fetchSalesData();
        }

        document.addEventListener("DOMContentLoaded", function() {
            switchView(currentView);
            setInterval(fetchSalesData, 5000);
        });
    </script>
</body>
</html>