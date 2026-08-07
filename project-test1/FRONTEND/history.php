<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: ../../PAGES/login.php");
    exit();
}

include('../BACKEND/db_inventory.php');

$current_page = basename($_SERVER['PHP_SELF']);

$search_item = isset($_GET['search_item']) ? $conn->real_escape_string($_GET['search_item']) : '';
$search_category = isset($_GET['search_category']) ? $conn->real_escape_string($_GET['search_category']) : '';
$search_date = isset($_GET['search_date']) ? $conn->real_escape_string($_GET['search_date']) : '';

$where_clauses = [];
if (!empty($search_item)) { $where_clauses[] = "items.item_name LIKE '%$search_item%'"; }
if (!empty($search_category)) { $where_clauses[] = "categories.id = '$search_category'"; }
if (!empty($search_date)) { $where_clauses[] = "DATE(sales.created_at) = '$search_date'"; }

$where_sql = count($where_clauses) > 0 ? "WHERE " . implode(' AND ', $where_clauses) : "";

$history_query = "
    SELECT 
        sales.id as sale_id,
        sales.created_at,
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
    $where_sql
    ORDER BY sales.created_at DESC";

$history_result = $conn->query($history_query);
$categories_list = $conn->query("SELECT * FROM categories ORDER BY name ASC");

$top_products_query = "
    SELECT items.item_name, SUM(sales_items.quantity) as total_qty
    FROM sales_items
    JOIN items ON sales_items.item_id = items.id
    GROUP BY sales_items.item_id
    ORDER BY total_qty DESC
    LIMIT 6";
$top_products_result = $conn->query($top_products_query);

$monthly_sales_query = "
    SELECT 
        DATE_FORMAT(sales.created_at, '%b %Y') as month_name, 
        SUM(sales_items.quantity * sales_items.price_at_sale) as total_sales
    FROM sales_items
    JOIN sales ON sales_items.sale_id = sales.id
    GROUP BY DATE_FORMAT(sales.created_at, '%Y-%m'), DATE_FORMAT(sales.created_at, '%b %Y')
    ORDER BY MIN(sales.created_at) ASC
    LIMIT 6";
$monthly_sales_result = $conn->query($monthly_sales_query);

$months = [];
$sales_totals = [];
if ($monthly_sales_result) {
    while ($row = $monthly_sales_result->fetch_assoc()) {
        $months[] = $row['month_name'];
        $sales_totals[] = (float)$row['total_sales'];
    }
}

$payment_stats_query = "
    SELECT 
        sales.payment_method,
        COUNT(DISTINCT sales.id) as txn_count,
        SUM(sales_items.quantity * sales_items.price_at_sale) as total_amount
    FROM sales_items
    JOIN sales ON sales_items.sale_id = sales.id
    JOIN items ON sales_items.item_id = items.id
    JOIN categories ON items.category_id = categories.id
    $where_sql
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
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-slate-900/5">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PannaKoda - Sales History</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>
<body class="h-full flex overflow-hidden text-slate-600 antialiased selection:bg-blue-500 selection:text-white">
    <div class="flex h-screen w-full overflow-hidden">
        <?php include '../../PAGES/sidebar.php'; ?>
        
        <div id="sidebarOverlay" class="fixed inset-0 bg-slate-900/40 backdrop-blur-sm z-40 hidden md:hidden transition-all"></div>

        <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden bg-slate-50">
            <header class="h-20 px-6 md:hidden flex items-center bg-white/80 backdrop-blur-xl border-b border-slate-100 shrink-0">
                <button id="burgerToggle" class="p-2 -ml-2 rounded-2xl text-slate-600 hover:bg-slate-100 focus:outline-none transition-colors">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <span class="ml-4 font-extrabold text-slate-800 tracking-tight">PannaKoda</span>
            </header>
             
            <main class="flex-1 p-6 lg:p-8 space-y-6 overflow-y-auto">
                <div class="bg-gradient-to-r from-blue-900 via-blue-800 to-indigo-900 p-8 lg:p-10 rounded-3xl shadow-xl shadow-blue-900/10 text-white flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div>
                        <span class="bg-white/10 backdrop-blur-md text-blue-200 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider">
                            Analytics Overview
                        </span>
                        <h1 class="text-2xl lg:text-3xl font-extrabold tracking-tight mt-3">Sales History</h1>
                        <p class="text-blue-200/80 text-sm mt-1">Monitor real-time revenue streams, earnings, and system reports seamlessly.</p>
                    </div>
                </div>

                <div class="bg-white/80 backdrop-blur-xl p-6 lg:p-8 rounded-3xl shadow-sm border border-slate-100">
                    <h3 class="text-xs font-extrabold text-slate-400 uppercase tracking-wider mb-6 flex items-center gap-2">
                        <i class="fa-solid fa-filter text-blue-600"></i> Search Filters
                    </h3>
                    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                        <div>
                            <label class="block text-xs font-extrabold text-slate-400 uppercase mb-2 tracking-wider">Item Name</label>
                            <input type="text" name="search_item" placeholder="Search item..." value="<?php echo htmlspecialchars($search_item); ?>"
                                class="w-full px-4 py-3 rounded-2xl border border-slate-200 focus:outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10 text-sm bg-slate-50/50 transition-all font-medium">
                        </div>
                        <div>
                            <label class="block text-xs font-extrabold text-slate-400 uppercase mb-2 tracking-wider">Category</label>
                            <select name="search_category" class="w-full px-4 py-3 rounded-2xl border border-slate-200 focus:outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10 text-sm bg-slate-50/50 transition-all font-medium">
                                <option value="">-- All Categories --</option>
                                <?php if($categories_list): ?>
                                    <?php while($cat = $categories_list->fetch_assoc()): ?>
                                        <option value="<?php echo $cat['id']; ?>" <?php echo $search_category == $cat['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($cat['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-extrabold text-slate-400 uppercase mb-2 tracking-wider">Transaction Date</label>
                            <input type="date" name="search_date" value="<?php echo htmlspecialchars($search_date); ?>"
                                class="w-full px-4 py-3 rounded-2xl border border-slate-200 focus:outline-none focus:border-blue-600 focus:ring-4 focus:ring-blue-600/10 text-sm bg-slate-50/50 transition-all font-medium text-slate-600">
                        </div>
                        <div class="md:col-span-3 flex gap-3 justify-end pt-2">
                            <button type="submit" class="bg-blue-600 hover:bg-blue-700 active:scale-[0.98] text-white px-6 py-3 rounded-2xl text-xs font-extrabold uppercase tracking-wider flex items-center gap-2 shadow-lg shadow-blue-600/20 transition-all">
                                <i class="fa-solid fa-magnifying-glass"></i> Filter Income
                            </button>
                            <a href="history.php" class="bg-slate-100 hover:bg-slate-200 active:scale-[0.98] text-slate-600 px-6 py-3 rounded-2xl text-xs font-extrabold uppercase tracking-wider transition-all flex items-center">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white/80 backdrop-blur-xl p-6 lg:p-8 rounded-3xl shadow-sm border border-slate-100 flex items-center justify-between relative overflow-hidden">
                        <div class="absolute left-0 top-0 bottom-0 w-2 bg-emerald-500"></div>
                        <div>
                            <p class="text-xs font-extrabold text-emerald-600 uppercase tracking-wider mb-1 flex items-center gap-1.5"><i class="fa-solid fa-money-bill-wave"></i>Cash Transactions</p>
                            <h4 class="text-xl font-bold text-slate-800 mt-2">
                                <span id="cash-count-display" class="text-slate-900"><?php echo $cash_count ? $cash_count : 0; ?></span> orders collected
                            </h4>
                            <p class="text-xs font-semibold text-slate-400 mt-1">Total: <span class="font-extrabold text-emerald-600 text-base">₱<?php echo number_format($cash_total, 2); ?></span></p>
                        </div>
                        <div class="w-14 h-14 bg-emerald-50 rounded-2xl flex items-center justify-center text-emerald-500 text-2xl shadow-inner">
                            <i class="fa-solid fa-cash-register"></i>
                        </div>
                    </div>

                    <div class="bg-white/80 backdrop-blur-xl p-6 lg:p-8 rounded-3xl shadow-sm border border-slate-100 flex items-center justify-between relative overflow-hidden">
                        <div class="absolute left-0 top-0 bottom-0 w-2 bg-blue-600"></div>
                        <div>
                            <p class="text-xs font-extrabold text-blue-600 uppercase tracking-wider mb-1 flex items-center gap-1.5"><i class="fa-solid fa-credit-card"></i>Card / Digital Transactions</p>
                            <h4 class="text-xl font-bold text-slate-800 mt-2">
                                <span id="card-count-display" class="text-slate-900"><?php echo $card_count ? $card_count : 0; ?></span> orders collected
                            </h4>
                            <p class="text-xs font-semibold text-slate-400 mt-1">Total: <span class="font-extrabold text-blue-600 text-base">₱<?php echo number_format($card_total, 2); ?></span></p>
                        </div>
                        <div class="w-14 h-14 bg-blue-50 rounded-2xl flex items-center justify-center text-blue-600 text-2xl shadow-inner">
                            <i class="fa-solid fa-wallet"></i>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="bg-white/80 backdrop-blur-xl p-6 lg:p-8 rounded-3xl shadow-sm border border-slate-100 lg:col-span-2">
                        <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider mb-6 flex items-center gap-2">
                            <i class="fa-solid fa-chart-area text-blue-600 text-lg"></i> Sales Performance Graph
                        </h3>
                        <div class="h-72 w-full">
                            <canvas id="monthlySalesChart" 
                                    data-labels='<?php echo json_encode($months); ?>' 
                                    data-totals='<?php echo json_encode($sales_totals); ?>'>
                            </canvas>
                        </div>
                    </div>

                    <div class="bg-white/80 backdrop-blur-xl p-6 lg:p-8 rounded-3xl shadow-sm border border-slate-100">
                        <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider mb-6 flex items-center gap-2">
                            <i class="fa-solid fa-fire text-amber-500 text-lg"></i> Top 6 Best Sellers
                        </h3>
                        <div id="top-sellers-list" class="divide-y divide-slate-100">
                            <?php if ($top_products_result && $top_products_result->num_rows > 0): $rank = 1; ?>
                                <?php while ($prod = $top_products_result->fetch_assoc()): ?>
                                    <div class="flex items-center justify-between py-3.5 first:pt-0 last:pb-0">
                                        <div class="flex items-center gap-3">
                                            <span class="w-7 h-7 rounded-xl bg-slate-100 text-xs font-extrabold text-slate-500 flex items-center justify-center shadow-sm">
                                                <?php echo $rank++; ?>
                                            </span>
                                            <span class="text-sm font-bold text-slate-800"><?php echo htmlspecialchars($prod['item_name']); ?></span>
                                        </div>
                                        <span class="text-xs bg-rose-50 text-rose-600 font-extrabold px-3 py-1 rounded-xl tracking-wide">
                                            <?php echo $prod['total_qty']; ?> sold
                                        </span>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-sm text-slate-400 text-center py-12 font-medium">No sales records yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="bg-white/80 backdrop-blur-xl rounded-3xl shadow-sm border border-slate-100 overflow-hidden">
                    <div class="p-6 lg:p-8 border-b border-slate-100">
                        <h3 class="text-sm font-extrabold text-slate-800 uppercase tracking-wider flex items-center gap-2">
                            <i class="fa-solid fa-list-check text-blue-600 text-lg"></i> Purchase Breakdown Logs
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50/70 border-b border-slate-100 text-slate-400 text-xs uppercase font-extrabold tracking-wider">
                                    <th class="p-4 pl-8">Date & Time</th>
                                    <th class="p-4">TXN ID</th>
                                    <th class="p-4">Item Name</th>
                                    <th class="p-4">Category</th>
                                    <th class="p-4 text-center">Qty</th>
                                    <th class="p-4 text-right">Rate Price</th>
                                    <th class="p-4 text-right">Subtotal</th>
                                    <th class="p-4 text-center">Method</th>
                                    <th class="p-4 text-center pr-8">Action</th>
                                </tr>
                            </thead>
                            <tbody id="history-table-body" class="text-sm divide-y divide-slate-100 text-slate-600">
                                <?php if ($history_result && $history_result->num_rows > 0): ?>
                                    <?php while ($row = $history_result->fetch_assoc()): ?>
                                        <tr class="hover:bg-slate-50/50 transition-colors">
                                            <td class="p-4 pl-8 font-bold text-blue-600 whitespace-nowrap">
                                                <i class="bi bi-clock me-1.5 text-slate-400 font-normal"></i>
                                                <?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?>
                                            </td>
                                            <td class="p-4"><span class="font-mono text-xs bg-slate-100 text-slate-600 px-3 py-1 rounded-xl font-bold">#TXN-<?php echo str_pad($row['sale_id'], 5, '0', STR_PAD_LEFT); ?></span></td>
                                            <td class="p-4 font-extrabold text-slate-800"><?php echo htmlspecialchars($row['item_name']); ?></td>
                                            <td class="p-4"><span class="text-xs bg-slate-100 text-slate-500 px-3 py-1 rounded-xl font-semibold"><?php echo htmlspecialchars($row['category_name']); ?></span></td>
                                            <td class="p-4 text-center font-extrabold text-slate-800"><?php echo $row['quantity']; ?></td>
                                            <td class="p-4 text-right font-semibold text-slate-600">₱<?php echo number_format($row['price_at_sale'], 2); ?></td>
                                            <td class="p-4 text-right font-extrabold text-emerald-600">₱<?php echo number_format($row['subtotal'], 2); ?></td>
                                            <td class="p-4 text-center">
                                                <span class="text-xs px-3 py-1 rounded-xl font-extrabold tracking-wide <?php echo strcasecmp($row['payment_method'], 'Cash') === 0 ? 'bg-emerald-50 text-emerald-700' : 'bg-blue-50 text-blue-700'; ?>">
                                                    <?php echo $row['payment_method']; ?>
                                                </span>
                                            </td>
                                            <td class="p-4 text-center pr-8">
                                                <button onclick="openReceiptModal(
                                                    '<?php echo $row['sale_id']; ?>', 
                                                    '<?php echo date('F d, Y h:i A', strtotime($row['created_at'])); ?>', 
                                                    '<?php echo addslashes($row['item_name']); ?>', 
                                                    '<?php echo $row['quantity']; ?>', 
                                                    '<?php echo number_format($row['subtotal'], 2); ?>', 
                                                    '<?php echo $row['payment_method']; ?>'
                                                )" class="bg-blue-50 hover:bg-blue-100 text-blue-600 px-3 py-1.5 rounded-xl text-xs font-bold transition-all flex items-center gap-1.5 mx-auto">
                                                    <i class="fa-solid fa-receipt"></i> Receipt
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="9" class="text-center py-16 text-slate-400 font-medium">No purchase history records found matching your query metrics.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
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
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const burgerToggle = document.getElementById('burgerToggle');

        if(burgerToggle) {
            burgerToggle.addEventListener('click', function() {
                if(sidebar) sidebar.classList.toggle('-translate-x-full');
                if(overlay) overlay.classList.toggle('hidden');
            });
        }
        if(overlay) {
            overlay.addEventListener('click', function() {
                if(sidebar) sidebar.classList.add('-translate-x-full');
                if(overlay) overlay.classList.add('hidden');
            });
        }

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

        const canvas = document.getElementById('monthlySalesChart');
        let chartLabels = JSON.parse(canvas.getAttribute('data-labels') || '[]');
        let chartData = JSON.parse(canvas.getAttribute('data-totals') || '[]');

        const ctx = canvas.getContext('2d');
        let monthlySalesChart = new Chart(ctx, {
            type: 'line', 
            data: {
                labels: chartLabels, 
                datasets: [{
                    label: 'Total Revenue (₱)',
                    data: chartData,
                    backgroundColor: (context) => {
                        const chart = context.chart;
                        const {ctx, chartArea} = chart;
                        if (!chartArea) return null;
                        const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                        gradient.addColorStop(0, 'rgba(37, 99, 235, 0.25)');
                        gradient.addColorStop(1, 'rgba(37, 99, 235, 0.0)');
                        return gradient;
                    },
                    borderColor: '#2563eb',                     
                    borderWidth: 3,
                    pointBackgroundColor: '#2563eb',            
                    pointBorderColor: '#ffffff',
                    pointBorderWidth: 2,
                    pointRadius: 4,                                 
                    pointHoverRadius: 6,
                    tension: 0.4,                                
                    fill: true                                  
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
                                return ' Revenue: ₱' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: { 
                        grid: { display: false, drawBorder: false },
                        ticks: { 
                            color: '#94a3b8',
                            font: { size: 11, weight: '600' }
                        } 
                    },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            color: '#94a3b8',
                            font: { size: 11, weight: '600' },
                            callback: function(value) { return '₱' + value.toLocaleString(); }
                        },
                        grid: { color: '#f8fafc', drawBorder: false }
                    }
                }
            }
        });

        function updateElementSafe(doc, elementId) {
            const newElement = doc.getElementById(elementId);
            const oldElement = document.getElementById(elementId);
            if (newElement && oldElement) { oldElement.innerHTML = newElement.innerHTML; }
        }

        function fetchUpdates() {
            const currentUrl = window.location.pathname + window.location.search;
            fetch(currentUrl)
                .then(response => {
                    if (!response.ok) throw new Error("Network status invalid");
                    return response.text();
                })
                .then(html => {
                    const parser = new DOMParser();
                    const doc = parser.parseFromString(html, 'text/html');

                    updateElementSafe(doc, 'cash-count-display');
                    updateElementSafe(doc, 'card-count-display');
                    updateElementSafe(doc, 'top-sellers-list');
                    updateElementSafe(doc, 'history-table-body');

                    const newCanvas = doc.getElementById('monthlySalesChart');
                    if (newCanvas && monthlySalesChart) {
                        const newLabels = JSON.parse(newCanvas.getAttribute('data-labels') || '[]');
                        const newData = JSON.parse(newCanvas.getAttribute('data-totals') || '[]');
                        
                        monthlySalesChart.data.labels = newLabels;
                        monthlySalesChart.data.datasets[0].data = newData;
                        monthlySalesChart.update();
                    }
                })
                .catch(error => console.error("Refreshing log sync error:", error));
        }
        setInterval(fetchUpdates, 5000); 
    </script>
</body>
</html>
<?php $conn->close(); ?>