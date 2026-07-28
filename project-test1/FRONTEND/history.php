<?php
session_start();

// 1. Siguraduhin muna na may naka-login na user
if (!isset($_SESSION['role'])) {
    header("Location: ../../PAGES/login.php");
    exit();
}

include('../BACKEND/db_inventory.php');

// Itakda ang kasalukuyang page para sa sidebar active highlight logic
$current_page = basename($_SERVER['PHP_SELF']);

// 1. Pagproseso ng Filters (Search Logic)
$search_item = isset($_GET['search_item']) ? $conn->real_escape_string($_GET['search_item']) : '';
$search_category = isset($_GET['search_category']) ? $conn->real_escape_string($_GET['search_category']) : '';
$search_date = isset($_GET['search_date']) ? $conn->real_escape_string($_GET['search_date']) : '';

$where_clauses = [];
if (!empty($search_item)) { $where_clauses[] = "items.item_name LIKE '%$search_item%'"; }
if (!empty($search_category)) { $where_clauses[] = "categories.id = '$search_category'"; }
if (!empty($search_date)) { $where_clauses[] = "DATE(sales.created_at) = '$search_date'"; }

$where_sql = "";
if (count($where_clauses) > 0) { $where_sql = "WHERE " . implode(' AND ', $where_clauses); }

// 2. Pangunahing Query para sa Breakdown Report
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

// 3. Query para sa Top 6 Best Selling Products
$top_products_query = "
    SELECT items.item_name, SUM(sales_items.quantity) as total_qty
    FROM sales_items
    JOIN items ON sales_items.item_id = items.id
    GROUP BY sales_items.item_id
    ORDER BY total_qty DESC
    LIMIT 6";
$top_products_result = $conn->query($top_products_query);

// 4. Query para sa Monthly Purchase / Sales Graph (Huling 6 na buwan)
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
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PannaKoda - Sales History</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&display=swap');
        body { font-family: 'Inter', sans-serif; }
    </style>
</head>

<body class="bg-[#f0f4f9] text-[#333333] flex h-screen overflow-hidden">
    <div class="flex h-screen w-full overflow-hidden">
        <?php include '../../PAGES/sidebar.php'; ?>
        
        <div id="sidebarOverlay" class="fixed inset-0 bg-black/50 z-40 hidden md:hidden"></div>

        <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden">
            
            <header class="p-4 md:hidden flex items-center bg-white border-b border-gray-100 shrink-0">
                <button id="burgerToggle" class="text-gray-500 hover:text-gray-700 focus:outline-none">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <span class="ml-4 font-bold text-gray-700">PannaKoda</span>
            </header>
             
            <main class="flex-1 p-6 space-y-6 overflow-y-auto">
                
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-4 flex items-center gap-2">
                        <i class="fa-solid fa-filter"></i> Search Filters
                    </h3>
                    <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2 tracking-wide">Item Name</label>
                            <input type="text" name="search_item" placeholder="Search item..." value="<?php echo htmlspecialchars($search_item); ?>"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#4a6cf7] focus:ring-1 focus:ring-[#4a6cf7] text-sm bg-gray-50">
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2 tracking-wide">Category</label>
                            <select name="search_category" class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#4a6cf7] text-sm bg-gray-50 appearance-none">
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
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2 tracking-wide">Transaction Date</label>
                            <input type="date" name="search_date" value="<?php echo htmlspecialchars($search_date); ?>"
                                class="w-full px-4 py-2.5 rounded-xl border border-gray-200 focus:outline-none focus:border-[#4a6cf7] text-sm bg-gray-50">
                        </div>
                        <div class="md:col-span-3 flex gap-3 justify-end mt-2">
                            <button type="submit" class="bg-[#4a6cf7] hover:bg-[#3b5ad9] text-white px-6 py-2.5 rounded-xl text-sm font-semibold flex items-center gap-2 shadow-sm transition">
                                <i class="fa-solid fa-magnifying-glass"></i> Filter Income
                            </button>
                            <a href="history.php" class="bg-gray-100 hover:bg-gray-200 text-gray-600 px-6 py-2.5 rounded-xl text-sm font-semibold transition">
                                Reset
                            </a>
                        </div>
                    </form>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Cash Transactions</p>
                            <h4 class="text-xl font-bold text-gray-800">
                                <span id="cash-count-display" class="text-[#4a6cf7]"><?php echo $cash_count ? $cash_count : 0; ?></span> orders collected
                            </h4>
                            <p class="text-sm text-gray-500 mt-1">Total Cash Amount: <span class="font-semibold text-green-600">₱<?php echo number_format($cash_total, 2); ?></span></p>
                        </div>
                        <div class="w-12 h-12 bg-emerald-50 rounded-xl flex items-center justify-center text-emerald-500 text-xl">
                            <i class="fa-solid fa-money-bill-wave"></i>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Card / Digital Transactions</p>
                            <h4 class="text-xl font-bold text-gray-800">
                                <span id="card-count-display" class="text-[#4a6cf7]"><?php echo $card_count ? $card_count : 0; ?></span> orders collected
                            </h4>
                            <p class="text-sm text-gray-500 mt-1">Total Card Amount: <span class="font-semibold text-[#4a6cf7]">₱<?php echo number_format($card_total, 2); ?></span></p>
                        </div>
                        <div class="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-[#4a6cf7] text-xl">
                            <i class="fa-solid fa-credit-card"></i>
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 lg:col-span-2">
                        <h3 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-chart-area text-blue-500"></i> Sales Performance Graph
                        </h3>
                        <div class="h-64">
                            <canvas id="monthlySalesChart" 
                                    data-labels='<?php echo json_encode($months); ?>' 
                                    data-totals='<?php echo json_encode($sales_totals); ?>'>
                            </canvas>
                        </div>
                    </div>

                    <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                        <h3 class="text-sm font-bold text-gray-800 mb-4 flex items-center gap-2">
                            <i class="fa-solid fa-fire text-orange-500"></i> Top 6 Best Sellers
                        </h3>
                        <div id="top-sellers-list" class="divide-y divide-gray-100">
                            <?php if ($top_products_result && $top_products_result->num_rows > 0): $rank = 1; ?>
                                <?php while ($prod = $top_products_result->fetch_assoc()): ?>
                                    <div class="flex items-center justify-between py-3">
                                        <div class="flex items-center gap-3">
                                            <span class="w-6 h-6 rounded-full bg-gray-100 text-xs font-bold text-gray-500 flex items-center justify-center">
                                                <?php echo $rank++; ?>
                                            </span>
                                            <span class="text-sm font-medium text-gray-700"><?php echo htmlspecialchars($prod['item_name']); ?></span>
                                        </div>
                                        <span class="text-xs bg-red-50 text-red-600 font-semibold px-2.5 py-1 rounded-full">
                                            <?php echo $prod['total_qty']; ?> sold
                                        </span>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-sm text-gray-400 text-center py-8">No sales records yet.</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-100">
                        <h3 class="text-sm font-bold text-gray-800 flex items-center gap-2">
                            <i class="fa-solid fa-list-check text-indigo-500"></i> Purchase Breakdown Logs
                        </h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-gray-50 text-gray-400 text-xs font-bold uppercase tracking-wider border-b border-gray-100">
                                    <th class="p-4">Date & Time</th>
                                    <th class="p-4">TXN ID</th>
                                    <th class="p-4">Item Name</th>
                                    <th class="p-4">Category</th>
                                    <th class="p-4 text-center">Qty</th>
                                    <th class="p-4 text-right">Rate Price</th>
                                    <th class="p-4 text-right">Subtotal</th>
                                    <th class="p-4 text-center">Method</th>
                                </tr>
                            </thead>
                            <tbody id="history-table-body" class="text-sm divide-y divide-gray-100 text-gray-600">
                                <?php if ($history_result && $history_result->num_rows > 0): ?>
                                    <?php while ($row = $history_result->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50 transition">
                                            <td class="p-4 whitespace-nowrap"><?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?></td>
                                            <td class="p-4"><span class="font-mono text-xs bg-gray-100 px-2 py-1 rounded">#TXN-<?php echo str_pad($row['sale_id'], 5, '0', STR_PAD_LEFT); ?></span></td>
                                            <td class="p-4 font-semibold text-gray-800"><?php echo htmlspecialchars($row['item_name']); ?></td>
                                            <td class="p-4"><span class="text-xs bg-gray-100 text-gray-500 px-2 py-0.5 rounded-full"><?php echo htmlspecialchars($row['category_name']); ?></span></td>
                                            <td class="p-4 text-center font-medium"><?php echo $row['quantity']; ?></td>
                                            <td class="p-4 text-right">₱<?php echo number_format($row['price_at_sale'], 2); ?></td>
                                            <td class="p-4 text-right font-semibold text-emerald-600">₱<?php echo number_format($row['subtotal'], 2); ?></td>
                                            <td class="p-4 text-center">
                                                <span class="text-xs px-2.5 py-1 rounded-full font-semibold <?php echo strcasecmp($row['payment_method'], 'Cash') === 0 ? 'bg-emerald-50 text-emerald-600' : 'bg-blue-50 text-[#4a6cf7]'; ?>">
                                                    <?php echo $row['payment_method']; ?>
                                                </span>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="8" class="text-center py-12 text-gray-400">No purchase history records found matching your query metrics.</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <script>
        const sidebar = document.getElementById('sidebar');
        const overlay = document.getElementById('sidebarOverlay');
        const burgerToggle = document.getElementById('burgerToggle');

        // Toggle Sidebar para sa Small Screen Layouts
        if(burgerToggle) {
            burgerToggle.addEventListener('click', function() {
                if(sidebar) sidebar.classList.toggle('-translate-x-full');
                if(overlay) overlay.classList.toggle('hidden');
            });
        }

        // Isara ang sidebar kapag kinlik ang overlay
        if(overlay) {
            overlay.addEventListener('click', function() {
                if(sidebar) sidebar.classList.add('-translate-x-full');
                if(overlay) overlay.classList.add('hidden');
            });
        }

        // Chart.js Configuration at Design Customization
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
                    backgroundColor: 'rgba(79, 112, 221, 0.08)', 
                    borderColor: '#4f70dd',                     
                    borderWidth: 2.5,
                    pointBackgroundColor: '#4f70dd',            
                    pointBorderColor: '#fff',
                    pointBorderWidth: 2,
                    pointRadius: 4,                                 
                    pointHoverRadius: 6,
                    tension: 0.2,                                
                    fill: true                                  
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                return 'Revenue: ₱' + context.parsed.y.toLocaleString();
                            }
                        }
                    }
                },
                scales: {
                    x: { grid: { display: false } },
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) { return '₱' + value.toLocaleString(); }
                        },
                        grid: { color: '#f3f4f6' }
                    }
                }
            }
        });

        // AJAX POLLING MECHANISM (Bawat 5 segundo)
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