<?php
session_start();

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
    $cash_total = 0; $cash_count = 0; $card_total = 0; $card_count = 0;

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
    $chart_labels = []; $chart_data = [];

    if ($graph_result && $graph_result->num_rows > 0) {
        while($g_row = $graph_result->fetch_assoc()) {
            $chart_labels[] = ($view === 'month') ? date('M d', strtotime($g_row['label'])) : $g_row['label'];
            $chart_data[] = $g_row['total'];
        }
    }
    ?>
    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
        <div class="bg-white/80 backdrop-blur-xl p-6 rounded-3xl shadow-sm border border-slate-100 relative overflow-hidden group hover:shadow-md transition-all">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-blue-50 rounded-full group-hover:scale-125 transition-transform duration-500 pointer-events-none"></div>
            <div class="text-slate-400 text-xs uppercase font-extrabold tracking-wider relative z-10">Total Transactions</div>
            <div class="text-3xl font-black text-slate-800 mt-2 relative z-10"><?php echo isset($summary['total_transactions']) ? $summary['total_transactions'] : '0'; ?></div>
        </div>
        <div class="bg-white/80 backdrop-blur-xl p-6 rounded-3xl shadow-sm border border-slate-100 relative overflow-hidden group hover:shadow-md transition-all">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-blue-50 rounded-full group-hover:scale-125 transition-transform duration-500 pointer-events-none"></div>
            <div class="text-slate-400 text-xs uppercase font-extrabold tracking-wider relative z-10">Total Items Sold</div>
            <div class="text-3xl font-black text-slate-800 mt-2 relative z-10"><?php echo isset($summary['total_items_sold']) ? number_format($summary['total_items_sold']) : '0'; ?> <span class="text-sm font-semibold text-slate-400">pcs</span></div>
        </div>
        <div class="bg-white/80 backdrop-blur-xl p-6 rounded-3xl shadow-sm border border-slate-100 relative overflow-hidden group hover:shadow-md transition-all">
            <div class="absolute -right-6 -bottom-6 w-24 h-24 bg-emerald-50 rounded-full group-hover:scale-125 transition-transform duration-500 pointer-events-none"></div>
            <div class="text-slate-400 text-xs uppercase font-extrabold tracking-wider relative z-10">Total Gross Revenue</div>
            <div class="text-3xl font-black text-blue-600 mt-2 relative z-10">₱<?php echo isset($summary['total_revenue']) ? number_format($summary['total_revenue'], 2) : '0.00'; ?></div>
        </div>
    </div>

    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white/80 backdrop-blur-xl p-6 rounded-3xl shadow-sm border border-slate-100 flex items-center justify-between relative overflow-hidden">
            <div class="absolute left-0 top-0 bottom-0 w-2 bg-emerald-500"></div>
            <div>
                <div class="text-emerald-600 text-xs uppercase font-extrabold tracking-wider mb-1 flex items-center gap-1.5"><i class="bi bi-cash-stack"></i>Cash Payments</div>
                <h2 class="text-xl font-bold text-slate-800"><?php echo $cash_count; ?> Transactions</h2>
            </div>
            <div class="text-2xl lg:text-3xl font-black text-emerald-600">₱<?php echo number_format($cash_total, 2); ?></div>
        </div>
        <div class="bg-white/80 backdrop-blur-xl p-6 rounded-3xl shadow-sm border border-slate-100 flex items-center justify-between relative overflow-hidden">
            <div class="absolute left-0 top-0 bottom-0 w-2 bg-blue-600"></div>
            <div>
                <div class="text-blue-600 text-xs uppercase font-extrabold tracking-wider mb-1 flex items-center gap-1.5"><i class="bi bi-credit-card-2-front"></i>Card / Other Payments</div>
                <h2 class="text-xl font-bold text-slate-800"><?php echo $card_count; ?> Transactions</h2>
            </div>
            <div class="text-2xl lg:text-3xl font-black text-blue-600">₱<?php echo number_format($card_total, 2); ?></div>
        </div>
    </div>

    <div class="bg-white/80 backdrop-blur-xl p-6 lg:p-8 rounded-3xl shadow-sm border border-slate-100 mb-6">
        <h5 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider mb-6 flex items-center gap-2"><i class="bi bi-graph-up text-blue-600 text-lg"></i>Sales Performance Graph</h5>
        <div class="relative h-[300px] w-full">
            <canvas id="salesChart" 
                    data-labels="<?php echo htmlspecialchars(json_encode($chart_labels)); ?>" 
                    data-values="<?php echo htmlspecialchars(json_encode($chart_data)); ?>"></canvas>
        </div>
    </div>

    <div class="bg-white/80 backdrop-blur-xl p-6 lg:p-8 rounded-3xl shadow-sm border border-slate-100">
        <h5 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider mb-6 flex items-center gap-2"><i class="bi bi-receipt text-blue-600 text-lg"></i>Itemized Purchase Records</h5>
        <div class="overflow-x-auto rounded-2xl border border-slate-100">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-slate-50/70 border-b border-slate-100 text-slate-400 text-xs uppercase font-extrabold tracking-wider">
                        <th class="p-4 pl-6">Date & Time</th>
                        <th class="p-4">Transaction ID</th>
                        <th class="p-4">Item Name</th>
                        <th class="p-4">Category</th>
                        <th class="p-4 text-center">Qty</th>
                        <th class="p-4 text-end">Rate Price</th>
                        <th class="p-4 text-end">Subtotal</th>
                        <th class="p-4 text-center">Method</th>
                        <th class="p-4 text-center pr-6">Action</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100 text-sm text-slate-600">
                    <?php if ($items_result && $items_result->num_rows > 0): ?>
                        <?php while ($row = $items_result->fetch_assoc()): ?>
                            <tr class="hover:bg-slate-50/50 transition-colors">
                                <td class="p-4 pl-6 font-bold text-blue-600 whitespace-nowrap">
                                    <i class="bi bi-clock me-1.5 text-slate-400 font-normal"></i>
                                    <?php echo ($view === 'month') ? date('M d, h:i A', strtotime($row['sale_datetime'])) : date('h:i A', strtotime($row['sale_datetime'])); ?>
                                </td>
                                <td class="p-4"><span class="px-3 py-1 bg-slate-100 text-slate-600 rounded-xl text-xs font-bold font-mono">#TXN-<?php echo str_pad($row['sale_id'], 5, '0', STR_PAD_LEFT); ?></span></td>
                                <td class="p-4 font-extrabold text-slate-800"><?php echo htmlspecialchars($row['item_name']); ?></td>
                                <td class="p-4"><span class="text-xs font-semibold text-slate-500"><?php echo htmlspecialchars($row['category_name']); ?></span></td>
                                <td class="p-4 text-center font-extrabold text-slate-800"><?php echo $row['quantity']; ?></td>
                                <td class="p-4 text-end font-semibold text-slate-600">₱<?php echo number_format($row['price_at_sale'], 2); ?></td>
                                <td class="p-4 text-end text-emerald-600 font-extrabold">₱<?php echo number_format($row['subtotal'], 2); ?></td>
                                <td class="p-4 text-center">
                                    <span class="px-3 py-1 rounded-xl text-xs font-extrabold tracking-wide <?php echo strcasecmp($row['payment_method'], 'Cash') === 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700'; ?>">
                                        <?php echo $row['payment_method']; ?>
                                    </span>
                                </td>
                                <td class="p-4 text-center pr-6">
                                    <button onclick="openReceiptModal(
                                        '<?php echo $row['sale_id']; ?>', 
                                        '<?php echo date('F d, Y h:i A', strtotime($row['sale_datetime'])); ?>', 
                                        '<?php echo addslashes($row['item_name']); ?>', 
                                        '<?php echo $row['quantity']; ?>', 
                                        '<?php echo number_format($row['subtotal'], 2); ?>', 
                                        '<?php echo $row['payment_method']; ?>'
                                    )" class="bg-blue-50 hover:bg-blue-100 text-blue-600 px-3 py-1.5 rounded-xl text-xs font-bold transition-all inline-flex items-center gap-1.5">
                                        <i class="bi bi-receipt"></i> Receipt
                                    </button>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="9" class="text-center py-12 text-slate-400 font-medium">No purchase records found for this active time scope.</td>
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
<html lang="en" class="h-full bg-slate-900/5">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple POS - Sales Report</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="h-full flex overflow-hidden font-sans text-slate-600 antialiased selection:bg-blue-500 selection:text-white">
   <div class="flex h-screen w-full overflow-hidden">
     <?php include '../../PAGES/sidebar.php'; ?>
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-slate-50">
        <header class="bg-white/80 backdrop-blur-xl border-b border-slate-100 h-20 flex items-center px-6 shrink-0 md:hidden">
            <button class="p-2 -ml-2 rounded-2xl text-slate-600 hover:bg-slate-100 transition-colors" type="button" id="burgerToggle">
                <i class="bi bi-list text-2xl"></i>
            </button>
        </header>

        <div class="flex-1 overflow-y-auto p-6 lg:p-8">
            <div class="max-w-7xl mx-auto space-y-6">
                <div class="bg-gradient-to-r from-blue-900 via-blue-800 to-indigo-900 p-8 lg:p-10 rounded-3xl shadow-xl shadow-blue-900/10 text-white flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <span class="bg-white/10 backdrop-blur-md text-blue-200 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider">
                            Analytics Overview
                        </span>
                        <h1 class="text-2xl lg:text-3xl font-extrabold tracking-tight mt-3" id="report-title"><?php echo $report_title; ?></h1>
                        <p class="text-blue-200/80 text-sm mt-1">Monitor real-time revenue streams, earnings, and system reports seamlessly.</p>
                    </div>
                    <div class="inline-flex bg-white/10 backdrop-blur-md p-1.5 rounded-2xl border border-white/10 shrink-0">
                        <button onclick="switchView('today')" id="btn-today" class="px-5 py-2.5 rounded-xl text-xs font-extrabold tracking-wider uppercase transition-all duration-300 flex items-center gap-2">
                            <i class="bi bi-calendar-event"></i>Today Only
                        </button>
                        <button onclick="switchView('month')" id="btn-month" class="px-5 py-2.5 rounded-xl text-xs font-extrabold tracking-wider uppercase transition-all duration-300 flex items-center gap-2">
                            <i class="bi bi-calendar-month"></i>This Month
                        </button>
                    </div>
                </div>

                <div id="live-sales-container">
                    <div class="flex flex-col items-center justify-center py-24 bg-white/80 backdrop-blur-xl rounded-3xl shadow-sm border border-slate-100">
                        <div class="animate-spin rounded-full h-10 w-10 border-3 border-blue-600 border-t-transparent mb-4"></div>
                        <p class="text-sm text-slate-400 font-bold uppercase tracking-wider">Loading live dashboard updates...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- RECEIPT MODAL -->
    <div id="receiptModal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden border border-slate-100 transform transition-all">
            <div class="p-6 text-center font-mono text-slate-800">
                <h3 class="font-bold text-lg tracking-wider">PANNAKODA</h3>
                <p class="text-xs text-slate-500 mt-0.5">Official Receipt</p>
                <p class="text-xs text-slate-600 mt-2">OR # : <span id="modal-or"></span></p>
                <p class="text-xs text-slate-600" id="modal-date"></p>

                <div class="border-t border-dashed border-slate-300 my-4"></div>

                <div class="flex justify-between text-xs font-bold text-slate-700 mb-2">
                    <span>Item</span>
                    <span>Qty</span>
                    <span>Total</span>
                </div>
                <div class="flex justify-between text-xs text-slate-600 items-center">
                    <span id="modal-item" class="text-left truncate max-w-[140px]"></span>
                    <span id="modal-qty"></span>
                    <span id="modal-total"></span>
                </div>

                <div class="border-t border-dashed border-slate-300 my-4"></div>

                <div class="space-y-1 text-xs text-left text-slate-700">
                    <div class="flex justify-between font-bold">
                        <span>TOTAL AMOUNT:</span>
                        <span id="modal-grand-total"></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Payment Mode:</span>
                        <span id="modal-payment"></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Amount Paid:</span>
                        <span id="modal-paid"></span>
                    </div>
                    <div class="flex justify-between">
                        <span>Change Due:</span>
                        <span>₱0.00</span>
                    </div>
                </div>
            </div>

            <div class="bg-slate-50 p-4 border-t border-slate-100 flex gap-3">
                <button onclick="closeReceiptModal()" class="w-full bg-slate-200 hover:bg-slate-300 text-slate-700 py-2.5 rounded-2xl text-xs font-extrabold uppercase transition-all">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        const burger = document.getElementById('burgerToggle');
        if (burger) {
            burger.addEventListener('click', function() {
                document.getElementById('sidebar').classList.toggle('-translate-x-full');
            });
        }

        let currentView = "<?php echo $view; ?>";
        let salesChartInstance = null;

        function openReceiptModal(saleId, date, itemName, qty, subtotal, paymentMethod) {
            document.getElementById('modal-or').innerText = saleId;
            document.getElementById('modal-date').innerText = date;
            document.getElementById('modal-item').innerText = itemName;
            document.getElementById('modal-qty').innerText = qty;
            document.getElementById('modal-total').innerText = '₱' + subtotal;
            document.getElementById('modal-grand-total').innerText = '₱' + subtotal;
            document.getElementById('modal-payment').innerText = paymentMethod;
            document.getElementById('modal-paid').innerText = '₱' + subtotal;

            document.getElementById('receiptModal').classList.remove('hidden');
        }

        function closeReceiptModal() {
            document.getElementById('receiptModal').classList.add('hidden');
        }

        function initChart() {
            const chartCanvas = document.getElementById('salesChart');
            if (!chartCanvas) return;

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
                        borderColor: '#2563eb',
                        backgroundColor: (context) => {
                            const chart = context.chart;
                            const {ctx, chartArea} = chart;
                            if (!chartArea) return null;
                            const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                            gradient.addColorStop(0, 'rgba(37, 99, 235, 0.25)');
                            gradient.addColorStop(1, 'rgba(37, 99, 235, 0.0)');
                            return gradient;
                        },
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#2563eb',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    animation: false, 
                    plugins: { 
                        legend: { display: false },
                        tooltip: {
                            backgroundColor: '#0f172a',
                            titleFont: { size: 12, weight: 'bold' },
                            bodyFont: { size: 13, weight: 'bold' },
                            padding: 12,
                            cornerRadius: 12,
                            callbacks: {
                                label: function(context) {
                                    return ' Total Revenue: ₱' + context.parsed.y.toLocaleString();
                                }
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: { color: '#f8fafc', drawBorder: false },
                            ticks: {
                                color: '#94a3b8',
                                font: { size: 11, weight: '600' },
                                callback: function(value) { return '₱' + value.toLocaleString(); }
                            }
                        },
                        x: { 
                            grid: { display: false, drawBorder: false }, 
                            ticks: { 
                                color: '#94a3b8',
                                font: { size: 11, weight: '600' }
                            } 
                        }
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
                .catch(error => console.error('Error fetching layout updates:', error));
        }

        function switchView(viewType) {
            currentView = viewType;
            const btnToday = document.getElementById('btn-today');
            const btnMonth = document.getElementById('btn-month');
            
            if(viewType === 'month') {
                btnMonth.className = 'px-5 py-2.5 rounded-xl text-xs font-extrabold tracking-wider uppercase shadow-lg shadow-black/5 bg-white text-slate-900 transition-all duration-300 flex items-center gap-2';
                btnToday.className = 'px-5 py-2.5 rounded-xl text-xs font-extrabold tracking-wider uppercase text-blue-200 hover:text-white transition-all duration-300 flex items-center gap-2';
            } else {
                btnToday.className = 'px-5 py-2.5 rounded-xl text-xs font-extrabold tracking-wider uppercase shadow-lg shadow-black/5 bg-white text-slate-900 transition-all duration-300 flex items-center gap-2';
                btnMonth.className = 'px-5 py-2.5 rounded-xl text-xs font-extrabold tracking-wider uppercase text-blue-200 hover:text-white transition-all duration-300 flex items-center gap-2';
            }
            fetchSalesData();
        }

        document.addEventListener("DOMContentLoaded", function() {
            switchView(currentView);
            setInterval(fetchSalesData, 4000);
        });
    </script>
</body>
</html>