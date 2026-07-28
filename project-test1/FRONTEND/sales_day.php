<?php
session_start();

// 1. Siguraduhin muna na may naka-login na user
if (!isset($_SESSION['role'])) {
    header("Location: ../../PAGES/login.php");
    exit();
}

require_once __DIR__ . '/../BACKEND/db_inventory.php';

$view = isset($_GET['view']) ? $_GET['view'] : 'today';

if ($view === 'month') {
    $date_condition = "MONTH(sales.created_at) = MONTH(CURDATE()) AND YEAR(sales.created_at) = YEAR(CURDATE())";
    $report_title = "This Month's Sales Report (" . date('F Y') . ")";
} else {
    $date_condition = "DATE(sales.created_at) = CURDATE()";
    $report_title = "Today's Live Sales Report (" . date('F d, Y') . ")";
}

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    
    // 1. SUMMARY QUERY
    $summary_query = "
        SELECT 
            COUNT(DISTINCT sales.id) as total_transactions,
            SUM(sales_items.quantity) as total_items_sold,
            SUM(sales_items.quantity * sales_items.price_at_sale) as total_revenue
        FROM sales_items
        JOIN sales ON sales_items.sale_id = sales.id
        WHERE $date_condition";

    $summary_result = $conn->query($summary_query);
    $summary = ($summary_result && $summary_result->num_rows > 0) ? $summary_result->fetch_assoc() : null;

    // 2. PAYMENT STATS QUERY
    $payment_stats_query = "
        SELECT 
            sales.payment_method,
            COUNT(DISTINCT sales.id) as txn_count,
            SUM(sales_items.quantity * sales_items.price_at_sale) as total_amount
        FROM sales_items
        JOIN sales ON sales_items.sale_id = sales.id
        WHERE $date_condition
        GROUP BY sales.payment_method";

    $payment_stats_result = $conn->query($payment_stats_query);
    $cash_total = 0; $cash_count = 0;
    $card_total = 0; $card_count = 0;

    if ($payment_stats_result) {
        while ($ps = $payment_stats_result->fetch_assoc()) {
            if (strcasecmp($ps['payment_method'], 'Cash') == 0) {
                $cash_total += $ps['total_amount'];
                $cash_count += $ps['txn_count'];
            } else {
                $card_total += $ps['total_amount'];
                $card_count += $ps['txn_count'];
            }
        }
    }

    // 3. ITEMS QUERY
    $items_query = "
        SELECT 
            sales.created_at as sale_datetime,
            sales.id as sale_id,
            items.item_name,
            categories.name as category_name,
            sales_items.quantity,
            sales_items.price_at_sale,
            (sales_items.quantity * sales_items.price_at_sale) as subtotal,
            sales.payment_method
        FROM sales_items
        JOIN sales ON sales_items.sale_id = sales.id
        JOIN items ON sales_items.item_id = items.id
        JOIN categories ON items.category_id = categories.id
        WHERE $date_condition
        ORDER BY sales.created_at DESC";

    $items_result = $conn->query($items_query);

    // 4. GRAPH QUERY
    if ($view === 'month') {
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
            $chart_labels[] = ($view === 'month') ? date('M d', strtotime($g_row['label'])) : $g_row['label'];
            $chart_data[] = $g_row['total'];
        }
    }
    ?>

    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white p-5 rounded-2xl shadow-sm border-l-4 border-indigo-500">
            <div class="text-slate-400 text-xs uppercase font-bold tracking-wider">Total Transactions</div>
            <div class="text-3xl font-bold text-slate-800 mt-1"><?php echo isset($summary['total_transactions']) ? $summary['total_transactions'] : '0'; ?></div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border-l-4 border-indigo-500">
            <div class="text-slate-400 text-xs uppercase font-bold tracking-wider">Total Items Sold</div>
            <div class="text-3xl font-bold text-slate-800 mt-1"><?php echo isset($summary['total_items_sold']) ? number_format($summary['total_items_sold']) : '0'; ?> <span class="text-sm font-medium text-slate-500">pcs</span></div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border-l-4 border-emerald-500">
            <div class="text-slate-400 text-xs uppercase font-bold tracking-wider">Total Gross Revenue</div>
            <div class="text-3xl font-bold text-indigo-600 mt-1">₱<?php echo isset($summary['total_revenue']) ? number_format($summary['total_revenue'], 2) : '0.00'; ?></div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white p-5 rounded-2xl shadow-sm border-l-4 border-emerald-500 flex items-center justify-between">
            <div>
                <div class="text-emerald-600 text-xs uppercase font-bold tracking-wider mb-1"><i class="bi bi-cash me-2"></i>Cash Payments</div>
                <h2 class="text-2xl font-bold text-slate-800"><?php echo $cash_count; ?> Transactions</h2>
            </div>
            <div class="text-3xl font-bold text-emerald-600">₱<?php echo number_format($cash_total, 2); ?></div>
        </div>
        <div class="bg-white p-5 rounded-2xl shadow-sm border-l-4 border-indigo-500 flex items-center justify-between">
            <div>
                <div class="text-indigo-600 text-xs uppercase font-bold tracking-wider mb-1"><i class="bi bi-credit-card me-2"></i>Card / Other Payments</div>
                <h2 class="text-2xl font-bold text-slate-800"><?php echo $card_count; ?> Transactions</h2>
            </div>
            <div class="text-3xl font-bold text-indigo-600">₱<?php echo number_format($card_total, 2); ?></div>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm mb-6">
        <h5 class="text-md font-bold text-slate-700 mb-4 flex items-center"><i class="bi bi-pie-chart-fill text-indigo-500 me-2"></i>Sales Performance Graph</h5>
        <div class="relative h-[280px] w-full">
            <canvas id="salesChart" 
                    data-labels="<?php echo htmlspecialchars(json_encode($chart_labels)); ?>" 
                    data-values="<?php echo htmlspecialchars(json_encode($chart_data)); ?>"></canvas>
        </div>
    </div>

    <div class="bg-white p-6 rounded-2xl shadow-sm">
        <h5 class="text-md font-bold text-slate-700 mb-4 flex items-center"><i class="bi bi-list-stars text-indigo-500 me-2"></i>Itemized Purchase Records</h5>
        <div class="overflow-x-auto rounded-xl border border-slate-100">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50 border-b border-slate-100 text-slate-600 text-xs uppercase font-bold">
                        <th class="p-4">Date & Time</th>
                        <th class="p-4">Transaction ID</th>
                        <th class="p-4">Item Name</th>
                        <th class="p-4">Category</th>
                        <th class="p-4 text-center">Qty</th>
                        <th class="p-4 text-end">Rate Price</th>
                        <th class="p-4 text-end">Subtotal</th>
                        <th class="p-4 text-center">Method</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-700">
                    <?php if ($items_result && $items_result->num_rows > 0): ?>
                        <?php while ($row = $items_result->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50/80 transition-colors">
                                <td class="p-4 font-semibold text-indigo-600 whitespace-nowrap">
                                    <i class="bi bi-clock me-1 text-slate-400"></i>
                                    <?php echo ($view === 'month') ? date('M d, h:i A', strtotime($row['sale_datetime'])) : date('h:i A', strtotime($row['sale_datetime'])); ?>
                                </td>
                                <td class="p-4"><span class="px-2.5 py-1 bg-slate-100 text-slate-600 rounded-lg text-xs font-medium">#TXN-<?php echo str_pad($row['sale_id'], 5, '0', STR_PAD_LEFT); ?></span></td>
                                <td class="p-4 font-bold text-slate-800"><?php echo htmlspecialchars($row['item_name']); ?></td>
                                <td class="p-4"><span class="text-xs font-semibold text-slate-500"><?php echo htmlspecialchars($row['category_name']); ?></span></td>
                                <td class="p-4 text-center font-bold text-slate-800"><?php echo $row['quantity']; ?></td>
                                <td class="p-4 text-end">₱<?php echo number_format($row['price_at_sale'], 2); ?></td>
                                <td class="p-4 text-end text-emerald-600 font-bold">₱<?php echo number_format($row['subtotal'], 2); ?></td>
                                <td class="p-4 text-center">
                                    <span class="px-2.5 py-1 rounded-full text-xs font-bold <?php echo strcasecmp($row['payment_method'], 'Cash') === 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-indigo-50 text-indigo-700'; ?>">
                                        <?php echo $row['payment_method']; ?>
                                    </span>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8" class="text-center py-12 text-slate-400 font-medium">No purchase records found for this active time scope.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    $conn->close();
    exit; 
}

$current_page = basename($_SERVER['PHP_SELF']);
?>

<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple POS - Sales Report</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="h-full flex overflow-hidden font-sans text-slate-800 antialiased">

   <div class="flex h-screen w-full overflow-hidden">
     <?php include '../../PAGES/sidebar.php'; ?>
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
                            <i class="bi bi-graph-up-arrow text-[#4e73df]"></i>
                            <span id="report-title"><?php echo $report_title; ?></span>
                        </h2>
                    </div>
                    <div class="inline-flex bg-slate-200/60 p-1 rounded-xl self-start md:self-auto">
                        <button onclick="switchView('today')" id="btn-today" class="px-5 py-2 rounded-lg text-sm font-bold transition-all duration-200 flex items-center gap-2">
                            <i class="bi bi-calendar-event"></i>Today Only
                        </button>
                        <button onclick="switchView('month')" id="btn-month" class="px-5 py-2 rounded-lg text-sm font-bold transition-all duration-200 flex items-center gap-2">
                            <i class="bi bi-calendar-month"></i>This Month
                        </button>
                    </div>
                </div>

                <div id="live-sales-container">
                    <div class="flex flex-col items-center justify-center py-20 bg-white rounded-2xl shadow-sm border border-slate-100">
                        <div class="animate-spin rounded-full h-8 w-8 border-2 border-indigo-600 border-t-transparent mb-3"></div>
                        <p class="text-sm text-slate-400 font-medium">Loading live dashboard updates...</p>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script>
        document.getElementById('burgerToggle').addEventListener('click', function() {
            document.getElementById('sidebar').classList.toggle('-translate-x-full');
        });

        let currentView = "<?php echo $view; ?>";
        let salesChartInstance = null; // Dito ise-save ang chart para hindi magduplika

        function initChart() {
            const chartCanvas = document.getElementById('salesChart');
            if (!chartCanvas) return;

            // Kunin ang mga data mula sa data-attribute ng HTML canvas
            const labels = JSON.parse(chartCanvas.getAttribute('data-labels') || '[]');
            const values = JSON.parse(chartCanvas.getAttribute('data-values') || '[]');

            const ctx = chartCanvas.getContext('2d');
            
            if (salesChartInstance) {
                salesChartInstance.destroy();
            }

            salesChartInstance = new Chart(ctx, {
                type: 'line', 
                data: {
                    labels: labels, 
                    datasets: [{
                        label: 'Total Revenue (₱)',
                        data: values,
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79, 70, 229, 0.05)',
                        borderWidth: 2.5,
                        fill: true,
                        tension: 0.3,
                        pointBackgroundColor: '#4f46e5'
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false, 
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
                    initChart(); // I-initialize ang chart pagkatapos mailagay ang HTML
                })
                .catch(error => console.error('Error fetching layout updates:', error));
        }

        function switchView(viewType) {
            currentView = viewType;
            const btnToday = document.getElementById('btn-today');
            const btnMonth = document.getElementById('btn-month');
            const titleSpan = document.getElementById('report-title');
            
            // I-format ang buwan at araw sa JS para sa malinis na interface
            const now = new Date();
            const todayTitle = "Today's Live Sales Report (" + now.toLocaleDateString('en-US', { month: 'long', day: 'numeric', year: 'numeric' }) + ")";
            const monthTitle = "This Month's Sales Report (" + now.toLocaleDateString('en-US', { month: 'long', year: 'numeric' }) + ")";

            if(viewType === 'month') {
                titleSpan.innerText = monthTitle;
                btnMonth.className = 'px-5 py-2 rounded-lg text-sm font-bold shadow-sm bg-white text-slate-800 transition-all flex items-center gap-2';
                btnToday.className = 'px-5 py-2 rounded-lg text-sm font-bold text-slate-500 hover:text-slate-800 transition-all flex items-center gap-2';
            } else {
                titleSpan.innerText = todayTitle;
                btnToday.className = 'px-5 py-2 rounded-lg text-sm font-bold shadow-sm bg-white text-slate-800 transition-all flex items-center gap-2';
                btnMonth.className = 'px-5 py-2 rounded-lg text-sm font-bold text-slate-500 hover:text-slate-800 transition-all flex items-center gap-2';
            }
            fetchSalesData();
        }

        document.addEventListener("DOMContentLoaded", function() {
            switchView(currentView);
            setInterval(fetchSalesData, 4000); // Tumaas nang kaunti ang delay para makahinga ang database (4 seconds)
        });
    </script>
</body>
</html>