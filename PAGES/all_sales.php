<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: ../../PAGES/login.php");
    exit();
}

require_once __DIR__ . '../../project-test1/BACKEND/db_inventory.php';

// Anti-Back Button Cache Control
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Fix for ONLY_FULL_GROUP_BY error
$conn->query("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

$current_page = basename($_SERVER['PHP_SELF']);
$active_tab = isset($_GET['tab']) ? $_GET['tab'] : 'analytics';

// ==========================================
// AJAX REQUEST HANDLER FOR LIVE SALES REPORT
// ==========================================
if (isset($_GET['ajax']) && $_GET['ajax'] == '1' && $active_tab == 'live') {
    $view = isset($_GET['view']) && $_GET['view'] === 'month' ? 'month' : 'today';
    
    if ($view === 'month') {
        $live_query = "SELECT DATE_FORMAT(created_at, '%b %d') as label_name, SUM(total_amount) as total_earnings, COUNT(id) as total_transactions FROM sales WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE()) GROUP BY DATE(created_at) ORDER BY created_at ASC";
        $total_query = "SELECT SUM(total_amount) as grand_total, COUNT(id) as grand_trans FROM sales WHERE MONTH(created_at) = MONTH(CURRENT_DATE()) AND YEAR(created_at) = YEAR(CURRENT_DATE())";
    } else {
        $live_query = "SELECT TIME_FORMAT(created_at, '%h:00 %p') as label_name, SUM(total_amount) as total_earnings, COUNT(id) as total_transactions FROM sales WHERE DATE(created_at) = CURDATE() GROUP BY HOUR(created_at) ORDER BY created_at ASC";
        $total_query = "SELECT SUM(total_amount) as grand_total, COUNT(id) as grand_trans FROM sales WHERE DATE(created_at) = CURDATE()";
    }

    $res_live = $conn->query($live_query);
    $res_tot = $conn->query($total_query);
    $grand_total = 0;
    $grand_trans = 0;
    
    if ($res_tot && $row_tot = $res_tot->fetch_assoc()) {
        $grand_total = $row_tot['grand_total'] ?? 0;
        $grand_trans = $row_tot['grand_trans'] ?? 0;
    }

    $labels = [];
    $values = [];
    $table_rows = [];
    
    if ($res_live) {
        while ($row = $res_live->fetch_assoc()) {
            $labels[] = $row['label_name'];
            $values[] = (float)$row['total_earnings'];
            $table_rows[] = $row;
        }
    }
    ?>
    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
        <div class="bg-white/90 backdrop-blur-2xl p-6 rounded-3xl shadow-xl shadow-purple-900/5 border border-purple-100 flex items-center justify-between transition-all duration-300 hover:scale-[1.01] hover:shadow-2xl hover:border-purple-300 animate-fade-in">
            <div>
                <span class="text-xs font-extrabold text-black uppercase tracking-wider block mb-1">Total Revenue (<?php echo ucfirst($view); ?>)</span>
                <h3 class="text-2xl lg:text-3xl font-extrabold text-black">₱<?php echo number_format($grand_total, 2); ?></h3>
            </div>
            <div class="w-14 h-14 bg-purple-100 text-purple-700 rounded-2xl flex items-center justify-center text-2xl shadow-inner transition-transform duration-300 hover:rotate-6">
                <i class="bi bi-cash-stack"></i>
            </div>
        </div>
        <div class="bg-white/90 backdrop-blur-2xl p-6 rounded-3xl shadow-xl shadow-purple-900/5 border border-purple-100 flex items-center justify-between transition-all duration-300 hover:scale-[1.01] hover:shadow-2xl hover:border-purple-300 animate-fade-in">
            <div>
                <span class="text-xs font-extrabold text-black uppercase tracking-wider block mb-1">Total Transactions (<?php echo ucfirst($view); ?>)</span>
                <h3 class="text-2xl lg:text-3xl font-extrabold text-black"><?php echo number_format($grand_trans); ?> Orders</h3>
            </div>
            <div class="w-14 h-14 bg-purple-100 text-purple-700 rounded-2xl flex items-center justify-center text-2xl shadow-inner transition-transform duration-300 hover:rotate-6">
                <i class="bi bi-receipt"></i>
            </div>
        </div>
    </div>

    <div class="bg-white/90 backdrop-blur-2xl p-6 lg:p-8 rounded-3xl shadow-xl shadow-purple-900/5 border border-purple-100 mb-6 transition-all duration-300 hover:shadow-2xl animate-fade-in">
        <h3 class="text-sm font-extrabold text-black uppercase tracking-wider mb-6 flex items-center gap-2">
            <i class="bi bi-graph-up text-purple-700 text-lg"></i> Live Revenue Chart
        </h3>
        <div class="h-80 w-full">
            <canvas id="salesChart" 
                    data-labels='<?php echo json_encode($labels); ?>' 
                    data-values='<?php echo json_encode($values); ?>'>
            </canvas>
        </div>
    </div>

    <div class="bg-white/90 backdrop-blur-2xl rounded-3xl shadow-xl shadow-purple-900/5 border border-purple-100 overflow-hidden animate-fade-in">
        <div class="p-6 lg:p-8 border-b border-purple-100">
            <h3 class="text-sm font-extrabold text-black uppercase tracking-wider flex items-center gap-2">
                <i class="bi bi-table text-purple-700 text-lg"></i> Breakdown Summary Table
            </h3>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-left border-collapse">
                <thead>
                    <tr class="bg-purple-50/70 border-b border-purple-100 text-black text-xs uppercase font-extrabold tracking-wider">
                        <th class="p-4 pl-8">Timeframe / Period</th>
                        <th class="p-4 text-center">Transactions Count</th>
                        <th class="p-4 text-right pr-8">Subtotal Earnings</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-purple-50 text-black">
                    <?php if (!empty($table_rows)): ?>
                        <?php foreach ($table_rows as $tr): ?>
                            <tr class="hover:bg-purple-50/60 transition-all duration-200">
                                <td class="p-4 pl-8 font-bold text-black"><?php echo htmlspecialchars($tr['label_name']); ?></td>
                                <td class="p-4 text-center font-extrabold text-purple-700"><?php echo $tr['total_transactions']; ?></td>
                                <td class="p-4 text-right pr-8 font-extrabold text-purple-700">₱<?php echo number_format($tr['total_earnings'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="3" class="text-center py-12 text-black font-medium">No sales recorded for this timeframe yet.</td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    <?php
    exit();
}

// ==========================================
// DATA LOGIC FOR TAB 1: SALES HISTORY
// ==========================================
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

// ==========================================
// DATA LOGIC FOR TAB 3: DASHBOARD ANALYTICS
// ==========================================
$filter_year = isset($_GET['filter_year']) && !empty($_GET['filter_year']) ? $conn->real_escape_string($_GET['filter_year']) : date('Y');
$filter_month = isset($_GET['filter_month']) && !empty($_GET['filter_month']) ? $conn->real_escape_string($_GET['filter_month']) : '';

$dash_where_clauses = [];
if (!empty($filter_year)) { $dash_where_clauses[] = "YEAR(created_at) = '$filter_year'"; }
if (!empty($filter_month)) { $dash_where_clauses[] = "MONTH(created_at) = '$filter_month'"; }
$dash_where_sql = count($dash_where_clauses) > 0 ? "WHERE " . implode(' AND ', $dash_where_clauses) : "";

$day_query = "SELECT DATE_FORMAT(created_at, '%M %d, %Y') as period_name, SUM(total_amount) as total_earnings, COUNT(id) as total_transactions FROM sales $dash_where_sql GROUP BY DATE(created_at) ORDER BY DATE(created_at) DESC";
$day_result = $conn->query($day_query);

$week_query = "SELECT CONCAT('Week ', WEEK(created_at, 1), ' (', DATE_FORMAT(created_at, '%b %Y'), ')') as period_name, SUM(total_amount) as total_earnings, COUNT(id) as total_transactions FROM sales $dash_where_sql GROUP BY WEEK(created_at, 1), YEAR(created_at) ORDER BY YEAR(created_at) DESC, WEEK(created_at, 1) DESC";
$week_result = $conn->query($week_query);

$month_query = "SELECT DATE_FORMAT(created_at, '%M %Y') as period_name, SUM(total_amount) as total_earnings, COUNT(id) as total_transactions FROM sales $dash_where_sql GROUP BY MONTH(created_at), YEAR(created_at) ORDER BY YEAR(created_at) DESC, MONTH(created_at) DESC";
$month_result = $conn->query($month_query);

$where_year_only = !empty($filter_year) ? "WHERE YEAR(created_at) = '$filter_year'" : "";
$year_query = "SELECT DATE_FORMAT(created_at, '%Y') as period_name, SUM(total_amount) as total_earnings, COUNT(id) as total_transactions FROM sales $where_year_only GROUP BY YEAR(created_at) ORDER BY YEAR(created_at) DESC";
$year_result = $conn->query($year_query);

$graph_weekly_data = [];
$wk_res = $conn->query($week_query);
if($wk_res) { while($row = $wk_res->fetch_assoc()) { $graph_weekly_data[] = $row; } }

$graph_monthly_data = [];
$mo_res = $conn->query($month_query);
if($mo_res) { while($row = $mo_res->fetch_assoc()) { $graph_monthly_data[] = $row; } }

$graph_yearly_data = [];
$yr_res = $conn->query($year_query);
if($yr_res) { while($row = $yr_res->fetch_assoc()) { $graph_yearly_data[] = $row; } }

$overall_daily = 0; $overall_daily_trans = 0;
$res_tot_day = $conn->query("SELECT SUM(total_amount) as total, COUNT(id) as trans_count FROM sales $dash_where_sql");
if($res_tot_day && $row = $res_tot_day->fetch_assoc()) { 
    $overall_daily = $row['total'] ?? 0; 
    $overall_daily_trans = $row['trans_count'] ?? 0; 
}

$overall_weekly = 0; $overall_weekly_trans = 0;
foreach($graph_weekly_data as $w) { 
    $overall_weekly += $w['total_earnings']; 
    $overall_weekly_trans += $w['total_transactions']; 
}

$overall_monthly = 0; $overall_monthly_trans = 0;
foreach($graph_monthly_data as $m) { 
    $overall_monthly += $m['total_earnings']; 
    $overall_monthly_trans += $m['total_transactions']; 
}

$overall_yearly = 0; $overall_yearly_trans = 0;
foreach($graph_yearly_data as $y) { 
    $overall_yearly += $y['total_earnings']; 
    $overall_yearly_trans += $y['total_transactions']; 
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-purple-950/10">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PannaKoda - Combined System Reports</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Inter', sans-serif; }
        
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 15px rgba(147, 51, 234, 0.15); }
            50% { box-shadow: 0 0 25px rgba(147, 51, 234, 0.35); }
        }
        .animate-fade-in {
            animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        .animate-pulse-glow {
            animation: pulseGlow 3s infinite ease-in-out;
        }
        .hover-lift {
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease;
        }
        .hover-lift:hover {
            transform: translateY(-4px);
        }
    </style>
</head>
<body class="h-full flex overflow-hidden text-black antialiased selection:bg-purple-600 selection:text-white">
    <div class="flex h-screen w-full overflow-hidden">
        <?php include 'sidebar.php'; ?>
        
        <div id="sidebarOverlay" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-40 hidden md:hidden transition-all duration-300"></div>

        <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden bg-purple-50/30">
            <header class="h-20 px-6 md:hidden flex items-center bg-white/90 backdrop-blur-2xl border-b border-purple-100 shrink-0 shadow-sm">
                <button id="burgerToggle" class="p-2 -ml-2 rounded-2xl text-black hover:bg-purple-100 focus:outline-none transition-colors">
                    <i class="fa-solid fa-bars text-xl"></i>
                </button>
                <span class="ml-4 font-extrabold text-black tracking-tight">PannaKoda</span>
            </header>
             
            <main class="flex-1 p-6 lg:p-8 space-y-6 overflow-y-auto">
                
                <!-- NAVIGATION BUTTON TABS FOR SWITCHING VIEWS -->
                <div class="bg-white/90 backdrop-blur-2xl p-3 rounded-3xl shadow-lg shadow-purple-900/5 border border-purple-100 flex flex-wrap gap-2 items-center justify-between animate-fade-in">
                    <div class="flex items-center gap-2 flex-wrap">
                        <a href="?tab=analytics" class="px-5 py-3 rounded-2xl text-xs font-extrabold uppercase tracking-wider transition-all duration-300 flex items-center gap-2 hover-lift <?php echo $active_tab == 'analytics' ? 'bg-purple-700 text-white shadow-lg shadow-purple-700/30' : 'bg-purple-50 text-black hover:bg-purple-100'; ?>">
                            <i class="bi bi-grid-1x2-fill"></i> Analytics Dashboard
                        </a>
                        <a href="?tab=history" class="px-5 py-3 rounded-2xl text-xs font-extrabold uppercase tracking-wider transition-all duration-300 flex items-center gap-2 hover-lift <?php echo $active_tab == 'history' ? 'bg-purple-700 text-white shadow-lg shadow-purple-700/30' : 'bg-purple-50 text-black hover:bg-purple-100'; ?>">
                            <i class="fa-solid fa-clock-rotate-left"></i> Sales History Logs
                        </a>
                        <a href="?tab=live" class="px-5 py-3 rounded-2xl text-xs font-extrabold uppercase tracking-wider transition-all duration-300 flex items-center gap-2 hover-lift <?php echo $active_tab == 'live' ? 'bg-purple-700 text-white shadow-lg shadow-purple-700/30' : 'bg-purple-50 text-black hover:bg-purple-100'; ?>">
                            <i class="bi bi-activity"></i> Live Reports (Today/Month)
                        </a>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- TAB CONTENT 1: SALES HISTORY (File 1)      -->
                <!-- ========================================== -->
                <div id="tab-content-history" class="space-y-6 animate-fade-in <?php echo $active_tab != 'history' ? 'hidden' : ''; ?>">
                    <div class="bg-gradient-to-r from-purple-900 via-purple-800 to-indigo-950 p-8 lg:p-10 rounded-3xl shadow-2xl shadow-purple-900/20 text-white flex flex-col md:flex-row md:items-center justify-between gap-6 animate-pulse-glow">
                        <div>
                            <span class="bg-white/10 backdrop-blur-md text-purple-200 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider border border-purple-300/20">
                                Sales Logs Overview
                            </span>
                            <h1 class="text-2xl lg:text-3xl font-extrabold tracking-tight mt-3 text-white">Sales History & Filters</h1>
                            <p class="text-purple-200/90 text-sm mt-1">Monitor real-time revenue streams, earnings, and system reports seamlessly.</p>
                        </div>
                    </div>

                    <div class="bg-white/90 backdrop-blur-2xl p-6 lg:p-8 rounded-3xl shadow-xl shadow-purple-900/5 border border-purple-100 hover-lift">
                        <h3 class="text-xs font-extrabold text-black uppercase tracking-wider mb-6 flex items-center gap-2">
                            <i class="fa-solid fa-filter text-purple-700"></i> Search Filters
                        </h3>
                        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                            <input type="hidden" name="tab" value="history">
                            <div>
                                <label class="block text-xs font-extrabold text-black uppercase mb-2 tracking-wider">Item Name</label>
                                <input type="text" name="search_item" placeholder="Search item..." value="<?php echo htmlspecialchars($search_item); ?>"
                                    class="w-full px-4 py-3 rounded-2xl border border-purple-200 focus:outline-none focus:border-purple-700 focus:ring-4 focus:ring-purple-700/10 text-sm bg-purple-50/40 transition-all font-medium text-black">
                            </div>
                            <div>
                                <label class="block text-xs font-extrabold text-black uppercase mb-2 tracking-wider">Category</label>
                                <select name="search_category" class="w-full px-4 py-3 rounded-2xl border border-purple-200 focus:outline-none focus:border-purple-700 focus:ring-4 focus:ring-purple-700/10 text-sm bg-purple-50/40 transition-all font-medium text-black">
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
                                <label class="block text-xs font-extrabold text-black uppercase mb-2 tracking-wider">Transaction Date</label>
                                <input type="date" name="search_date" value="<?php echo htmlspecialchars($search_date); ?>"
                                    class="w-full px-4 py-3 rounded-2xl border border-purple-200 focus:outline-none focus:border-purple-700 focus:ring-4 focus:ring-purple-700/10 text-sm bg-purple-50/40 transition-all font-medium text-black">
                            </div>
                            <div class="md:col-span-3 flex gap-3 justify-end pt-2">
                                <button type="submit" class="bg-purple-700 hover:bg-purple-800 active:scale-[0.98] text-white px-6 py-3 rounded-2xl text-xs font-extrabold uppercase tracking-wider flex items-center gap-2 shadow-lg shadow-purple-700/25 transition-all duration-300 hover:shadow-xl">
                                    <i class="fa-solid fa-magnifying-glass"></i> Filter Income
                                </button>
                                <a href="?tab=history" class="bg-purple-100 hover:bg-purple-200 active:scale-[0.98] text-black px-6 py-3 rounded-2xl text-xs font-extrabold uppercase tracking-wider transition-all duration-300 flex items-center">
                                    Reset
                                </a>
                            </div>
                        </form>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white/90 backdrop-blur-2xl p-6 lg:p-8 rounded-3xl shadow-xl shadow-purple-900/5 border border-purple-100 flex items-center justify-between relative overflow-hidden hover-lift">
                            <div class="absolute left-0 top-0 bottom-0 w-2 bg-purple-700"></div>
                            <div>
                                <p class="text-xs font-extrabold text-purple-800 uppercase tracking-wider mb-1 flex items-center gap-1.5"><i class="fa-solid fa-money-bill-wave"></i>Cash Transactions</p>
                                <h4 class="text-xl font-bold text-black mt-2">
                                    <span class="text-black"><?php echo $cash_count ? $cash_count : 0; ?></span> orders collected
                                </h4>
                                <p class="text-xs font-semibold text-black mt-1">Total: <span class="font-extrabold text-purple-700 text-base">₱<?php echo number_format($cash_total, 2); ?></span></p>
                            </div>
                            <div class="w-14 h-14 bg-purple-100 rounded-2xl flex items-center justify-center text-purple-700 text-2xl shadow-inner transition-transform duration-300 hover:scale-110">
                                <i class="fa-solid fa-cash-register"></i>
                            </div>
                        </div>

                        <div class="bg-white/90 backdrop-blur-2xl p-6 lg:p-8 rounded-3xl shadow-xl shadow-purple-900/5 border border-purple-100 flex items-center justify-between relative overflow-hidden hover-lift">
                            <div class="absolute left-0 top-0 bottom-0 w-2 bg-purple-900"></div>
                            <div>
                                <p class="text-xs font-extrabold text-purple-900 uppercase tracking-wider mb-1 flex items-center gap-1.5"><i class="fa-solid fa-credit-card"></i>Card / Digital Transactions</p>
                                <h4 class="text-xl font-bold text-black mt-2">
                                    <span class="text-black"><?php echo $card_count ? $card_count : 0; ?></span> orders collected
                                </h4>
                                <p class="text-xs font-semibold text-black mt-1">Total: <span class="font-extrabold text-purple-800 text-base">₱<?php echo number_format($card_total, 2); ?></span></p>
                            </div>
                            <div class="w-14 h-14 bg-purple-100 rounded-2xl flex items-center justify-center text-purple-800 text-2xl shadow-inner transition-transform duration-300 hover:scale-110">
                                <i class="fa-solid fa-wallet"></i>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="bg-white/90 backdrop-blur-2xl p-6 lg:p-8 rounded-3xl shadow-xl shadow-purple-900/5 border border-purple-100 lg:col-span-2 hover-lift">
                            <h3 class="text-sm font-extrabold text-black uppercase tracking-wider mb-6 flex items-center gap-2">
                                <i class="fa-solid fa-chart-area text-purple-700 text-lg"></i> Sales Performance Graph
                            </h3>
                            <div class="h-72 w-full">
                                <canvas id="monthlySalesChart" 
                                        data-labels='<?php echo json_encode($months); ?>' 
                                        data-totals='<?php echo json_encode($sales_totals); ?>'>
                                </canvas>
                            </div>
                        </div>

                        <div class="bg-white/90 backdrop-blur-2xl p-6 lg:p-8 rounded-3xl shadow-xl shadow-purple-900/5 border border-purple-100 hover-lift">
                            <h3 class="text-sm font-extrabold text-black uppercase tracking-wider mb-6 flex items-center gap-2">
                                <i class="fa-solid fa-fire text-purple-700 text-lg"></i> Top 6 Best Sellers
                            </h3>
                            <div class="divide-y divide-purple-100">
                                <?php if ($top_products_result && $top_products_result->num_rows > 0): $rank = 1; ?>
                                    <?php while ($prod = $top_products_result->fetch_assoc()): ?>
                                        <div class="flex items-center justify-between py-3.5 first:pt-0 last:pb-0 transition-all duration-200 hover:px-2 rounded-xl hover:bg-purple-50/50">
                                            <div class="flex items-center gap-3">
                                                <span class="w-7 h-7 rounded-xl bg-purple-100 text-xs font-extrabold text-purple-800 flex items-center justify-center shadow-sm">
                                                    <?php echo $rank++; ?>
                                                </span>
                                                <span class="text-sm font-bold text-black"><?php echo htmlspecialchars($prod['item_name']); ?></span>
                                            </div>
                                            <span class="text-xs bg-purple-100 text-purple-800 font-extrabold px-3 py-1 rounded-xl tracking-wide">
                                                <?php echo $prod['total_qty']; ?> sold
                                            </span>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <p class="text-sm text-black text-center py-12 font-medium">No sales records yet.</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white/90 backdrop-blur-2xl rounded-3xl shadow-xl shadow-purple-900/5 border border-purple-100 overflow-hidden">
                        <div class="p-6 lg:p-8 border-b border-purple-100">
                            <h3 class="text-sm font-extrabold text-black uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-list-check text-purple-700 text-lg"></i> Purchase Breakdown Logs
                            </h3>
                        </div>
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-purple-50/70 border-b border-purple-100 text-black text-xs uppercase font-extrabold tracking-wider">
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
                                <tbody class="text-sm divide-y divide-purple-50 text-black">
                                    <?php if ($history_result && $history_result->num_rows > 0): ?>
                                        <?php while ($row = $history_result->fetch_assoc()): ?>
                                            <tr class="hover:bg-purple-50/60 transition-colors duration-200">
                                                <td class="p-4 pl-8 font-bold text-purple-700 whitespace-nowrap">
                                                    <i class="bi bi-clock me-1.5 text-black font-normal"></i>
                                                    <?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?>
                                                </td>
                                                <td class="p-4"><span class="font-mono text-xs bg-purple-100 text-purple-900 px-3 py-1 rounded-xl font-bold">#TXN-<?php echo str_pad($row['sale_id'], 5, '0', STR_PAD_LEFT); ?></span></td>
                                                <td class="p-4 font-extrabold text-black"><?php echo htmlspecialchars($row['item_name']); ?></td>
                                                <td class="p-4"><span class="text-xs bg-purple-100 text-purple-900 px-3 py-1 rounded-xl font-semibold"><?php echo htmlspecialchars($row['category_name']); ?></span></td>
                                                <td class="p-4 text-center font-extrabold text-black"><?php echo $row['quantity']; ?></td>
                                                <td class="p-4 text-right font-semibold text-black">₱<?php echo number_format($row['price_at_sale'], 2); ?></td>
                                                <td class="p-4 text-right font-extrabold text-purple-700">₱<?php echo number_format($row['subtotal'], 2); ?></td>
                                                <td class="p-4 text-center">
                                                    <span class="text-xs px-3 py-1 rounded-xl font-extrabold tracking-wide bg-purple-100 text-purple-900">
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
                                                    )" class="bg-purple-100 hover:bg-purple-200 text-purple-800 px-3 py-1.5 rounded-xl text-xs font-bold transition-all duration-200 flex items-center gap-1.5 mx-auto hover:scale-105">
                                                        <i class="fa-solid fa-receipt"></i> Receipt
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr>
                                            <td colspan="9" class="text-center py-16 text-black font-medium">No purchase history records found matching your query metrics.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- TAB CONTENT 2: ANALYTICS DASHBOARD (File 3)-->
                <!-- ========================================== -->
                <div id="tab-content-analytics" class="space-y-6 animate-fade-in <?php echo $active_tab != 'analytics' ? 'hidden' : ''; ?>">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-purple-900 via-purple-800 to-indigo-950 rounded-3xl p-6 lg:p-8 text-white shadow-2xl shadow-purple-900/20 animate-pulse-glow">
                        <div>
                            <span class="bg-white/10 text-purple-200 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider backdrop-blur-md border border-purple-300/20">Analytics Overview</span>
                            <h1 class="text-2xl lg:text-3xl font-extrabold tracking-tight mt-2 text-white">Dashboard Analytics</h1>
                            <p class="text-purple-200/90 text-sm mt-1">Monitor real-time revenue streams, earnings, and system reports seamlessly.</p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
                        <div class="bg-white/90 backdrop-blur-2xl rounded-3xl p-5 border border-purple-100 shadow-xl shadow-purple-900/5 flex items-center gap-4 hover-lift">
                            <div class="w-12 h-12 rounded-2xl bg-purple-100 text-purple-700 flex items-center justify-center text-xl shrink-0 shadow-inner">
                                <i class="bi bi-wallet2"></i>
                            </div>
                            <div>
                                <span class="text-black text-xs font-bold uppercase tracking-wider block">Total Daily Earnings</span>
                                <h4 class="text-black font-extrabold text-lg mt-0.5">₱<?php echo number_format($overall_daily, 2); ?></h4>
                                <span class="text-black text-[11px] font-medium flex items-center gap-1 mt-0.5">
                                    <i class="bi bi-receipt"></i> <?php echo number_format($overall_daily_trans); ?> Transactions
                                </span>
                            </div>
                        </div>

                        <div class="bg-white/90 backdrop-blur-2xl rounded-3xl p-5 border border-purple-100 shadow-xl shadow-purple-900/5 flex items-center gap-4 hover-lift">
                            <div class="w-12 h-12 rounded-2xl bg-purple-100 text-purple-700 flex items-center justify-center text-xl shrink-0 shadow-inner">
                                <i class="bi bi-graph-up-arrow"></i>
                            </div>
                            <div>
                                <span class="text-black text-xs font-bold uppercase tracking-wider block">Total Weekly Earnings</span>
                                <h4 class="text-black font-extrabold text-lg mt-0.5">₱<?php echo number_format($overall_weekly, 2); ?></h4>
                                <span class="text-black text-[11px] font-medium flex items-center gap-1 mt-0.5">
                                    <i class="bi bi-receipt"></i> <?php echo number_format($overall_weekly_trans); ?> Transactions
                                </span>
                            </div>
                        </div>

                        <div class="bg-white/90 backdrop-blur-2xl rounded-3xl p-5 border border-purple-100 shadow-xl shadow-purple-900/5 flex items-center gap-4 hover-lift">
                            <div class="w-12 h-12 rounded-2xl bg-purple-100 text-purple-700 flex items-center justify-center text-xl shrink-0 shadow-inner">
                                <i class="bi bi-cash-stack"></i>
                            </div>
                            <div>
                                <span class="text-black text-xs font-bold uppercase tracking-wider block">Total Monthly Earnings</span>
                                <h4 class="text-black font-extrabold text-lg mt-0.5">₱<?php echo number_format($overall_monthly, 2); ?></h4>
                                <span class="text-black text-[11px] font-medium flex items-center gap-1 mt-0.5">
                                    <i class="bi bi-receipt"></i> <?php echo number_format($overall_monthly_trans); ?> Transactions
                                </span>
                            </div>
                        </div>

                        <div class="bg-white/90 backdrop-blur-2xl rounded-3xl p-5 border border-purple-100 shadow-xl shadow-purple-900/5 flex items-center gap-4 hover-lift">
                            <div class="w-12 h-12 rounded-2xl bg-purple-100 text-purple-700 flex items-center justify-center text-xl shrink-0 shadow-inner">
                                <i class="bi bi-piggy-bank"></i>
                            </div>
                            <div>
                                <span class="text-black text-xs font-bold uppercase tracking-wider block">Total Yearly Earnings</span>
                                <h4 class="text-black font-extrabold text-lg mt-0.5">₱<?php echo number_format($overall_yearly, 2); ?></h4>
                                <span class="text-black text-[11px] font-medium flex items-center gap-1 mt-0.5">
                                    <i class="bi bi-receipt"></i> <?php echo number_format($overall_yearly_trans); ?> Transactions
                                </span>
                            </div>
                        </div>
                    </div>

                    <div class="bg-white/90 backdrop-blur-2xl rounded-3xl shadow-xl shadow-purple-900/5 border border-purple-100 p-6 hover-lift">
                        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-5 items-end">
                            <input type="hidden" name="tab" value="analytics">
                            <div>
                                <label class="block text-xs font-bold text-black uppercase tracking-wider mb-2">Filter Month</label>
                                <div class="relative">
                                    <select name="filter_month" class="w-full bg-purple-50/50 border border-purple-200 rounded-2xl px-4 py-3 text-black text-sm focus:outline-none focus:ring-2 focus:ring-purple-700/20 focus:border-purple-700 transition-all appearance-none font-medium">
                                        <option value="">-- All Months --</option>
                                        <?php
                                        for ($m = 1; $m <= 12; $m++) {
                                            $month_num = str_pad($m, 2, '0', STR_PAD_LEFT);
                                            $month_name = date('F', mktime(0, 0, 0, $m, 1));
                                            $selected = ($filter_month == $month_num) ? 'selected' : '';
                                            echo "<option value='$month_num' $selected>$month_name</option>";
                                        }
                                        ?>
                                    </select>
                                    <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-black">
                                        <i class="bi bi-chevron-down text-xs"></i>
                                    </div>
                                </div>
                            </div>
                            <div>
                                <label class="block text-xs font-bold text-black uppercase tracking-wider mb-2">Filter Year</label>
                                <div class="relative">
                                    <select name="filter_year" class="w-full bg-purple-50/50 border border-purple-200 rounded-2xl px-4 py-3 text-black text-sm focus:outline-none focus:ring-2 focus:ring-purple-700/20 focus:border-purple-700 transition-all appearance-none font-medium">
                                        <?php
                                        $start_year = date('Y') - 5;
                                        $end_year = date('Y');
                                        for ($y = $end_year; $y >= $start_year; $y--) {
                                            $selected = ($filter_year == $y) ? 'selected' : '';
                                            echo "<option value='$y' $selected>$y</option>";
                                        }
                                        ?>
                                    </select>
                                    <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-black">
                                        <i class="bi bi-chevron-down text-xs"></i>
                                    </div>
                                </div>
                            </div>
                            <div class="flex gap-3">
                                <button type="submit" class="flex-1 bg-purple-700 hover:bg-purple-800 active:scale-[0.98] text-white font-semibold py-3 px-5 rounded-2xl flex items-center justify-center gap-2 transition-all duration-300 shadow-lg shadow-purple-700/25 text-sm hover:shadow-xl">
                                    <i class="bi bi-funnel-fill"></i> Apply Filter
                                </button>
                            </div>
                        </form>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">
                        <div class="bg-white/90 backdrop-blur-2xl rounded-3xl shadow-xl shadow-purple-900/5 border border-purple-100 p-6 flex flex-col h-[420px] hover-lift">
                            <div class="flex items-center justify-between mb-4">
                                <h6 class="text-black font-bold text-sm tracking-wide">Daily Income</h6>
                                <span class="w-10 h-10 rounded-2xl bg-purple-100 text-purple-700 flex items-center justify-center text-base"><i class="bi bi-calendar-event"></i></span>
                            </div>
                            <div class="overflow-y-auto flex-1 pr-1 space-y-2">
                                <?php if ($day_result && $day_result->num_rows > 0): ?>
                                    <?php while($row = $day_result->fetch_assoc()): ?>
                                        <div class="flex justify-between items-center p-3 rounded-2xl hover:bg-purple-50 transition-all border border-transparent hover:border-purple-100 gap-2">
                                            <div class="min-w-0">
                                                <span class="text-black font-semibold text-xs block truncate"><?php echo $row['period_name']; ?></span>
                                                <span class="text-black text-[11px]"><i class="bi bi-receipt"></i> <?php echo $row['total_transactions']; ?> Trans.</span>
                                            </div>
                                            <span class="font-extrabold text-purple-700 text-xs shrink-0 bg-purple-100 px-2.5 py-1 rounded-xl">₱<?php echo number_format($row['total_earnings'], 2); ?></span>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="h-full flex items-center justify-center text-black text-xs font-medium">No daily records</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="bg-white/90 backdrop-blur-2xl rounded-3xl shadow-xl shadow-purple-900/5 border border-purple-100 p-6 flex flex-col h-[420px] hover-lift">
                            <div class="flex items-center justify-between mb-4">
                                <h6 class="text-black font-bold text-sm tracking-wide">Weekly Income</h6>
                                <span class="w-10 h-10 rounded-2xl bg-purple-100 text-purple-700 flex items-center justify-center text-base"><i class="bi bi-calendar-range"></i></span>
                            </div>
                            <div class="overflow-y-auto flex-1 pr-1 space-y-2">
                                <?php if (!empty($graph_weekly_data)): ?>
                                    <?php foreach($graph_weekly_data as $row): ?>
                                        <div class="flex justify-between items-center p-3 rounded-2xl hover:bg-purple-50 transition-all border border-transparent hover:border-purple-100 gap-2">
                                            <div class="min-w-0">
                                                <span class="text-black font-semibold text-xs block truncate"><?php echo $row['period_name']; ?></span>
                                                <span class="text-black text-[11px]"><i class="bi bi-receipt"></i> <?php echo $row['total_transactions']; ?> Trans.</span>
                                            </div>
                                            <span class="font-extrabold text-purple-700 text-xs shrink-0 bg-purple-100 px-2.5 py-1 rounded-xl">₱<?php echo number_format($row['total_earnings'], 2); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="h-full flex items-center justify-center text-black text-xs font-medium">No weekly records</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="bg-white/90 backdrop-blur-2xl rounded-3xl shadow-xl shadow-purple-900/5 border border-purple-100 p-6 flex flex-col h-[420px] hover-lift">
                            <div class="flex items-center justify-between mb-4">
                                <h6 class="text-black font-bold text-sm tracking-wide">Monthly Income</h6>
                                <span class="w-10 h-10 rounded-2xl bg-purple-100 text-purple-700 flex items-center justify-center text-base"><i class="bi bi-calendar3"></i></span>
                            </div>
                            <div class="overflow-y-auto flex-1 pr-1 space-y-2">
                                <?php if (!empty($graph_monthly_data)): ?>
                                    <?php foreach($graph_monthly_data as $row): ?>
                                        <div class="flex justify-between items-center p-3 rounded-2xl hover:bg-purple-50 transition-all border border-transparent hover:border-purple-100 gap-2">
                                            <div class="min-w-0">
                                                <span class="text-black font-semibold text-xs block truncate"><?php echo $row['period_name']; ?></span>
                                                <span class="text-black text-[11px]"><i class="bi bi-receipt"></i> <?php echo $row['total_transactions']; ?> Trans.</span>
                                            </div>
                                            <span class="font-extrabold text-purple-700 text-xs shrink-0 bg-purple-100 px-2.5 py-1 rounded-xl">₱<?php echo number_format($row['total_earnings'], 2); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="h-full flex items-center justify-center text-black text-xs font-medium">No monthly records</div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <div class="bg-white/90 backdrop-blur-2xl rounded-3xl shadow-xl shadow-purple-900/5 border border-purple-100 p-6 flex flex-col h-[420px] hover-lift">
                            <div class="flex items-center justify-between mb-4">
                                <h6 class="text-black font-bold text-sm tracking-wide">Yearly Income</h6>
                                <span class="w-10 h-10 rounded-2xl bg-purple-100 text-purple-700 flex items-center justify-center text-base"><i class="bi bi-calendar4-years"></i></span>
                            </div>
                            <div class="overflow-y-auto flex-1 pr-1 space-y-2">
                                <?php if (!empty($graph_yearly_data)): ?>
                                    <?php foreach($graph_yearly_data as $row): ?>
                                        <div class="flex justify-between items-center p-3 rounded-2xl hover:bg-purple-50 transition-all border border-transparent hover:border-purple-100 gap-2">
                                            <div class="min-w-0">
                                                <span class="text-black font-semibold text-xs block truncate"><?php echo $row['period_name']; ?></span>
                                                <span class="text-black text-[11px]"><i class="bi bi-receipt"></i> <?php echo $row['total_transactions']; ?> Trans.</span>
                                            </div>
                                            <span class="font-extrabold text-purple-700 text-xs shrink-0 bg-purple-100 px-2.5 py-1 rounded-xl">₱<?php echo number_format($row['total_earnings'], 2); ?></span>
                                        </div>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <div class="h-full flex items-center justify-center text-black text-xs font-medium">No yearly records</div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- TAB CONTENT 3: LIVE SALES REPORT (File 2)  -->
                <!-- ========================================== -->
                <div id="tab-content-live" class="space-y-6 animate-fade-in <?php echo $active_tab != 'live' ? 'hidden' : ''; ?>">
                    <div class="bg-gradient-to-r from-purple-900 via-purple-800 to-indigo-950 p-8 lg:p-10 rounded-3xl shadow-2xl shadow-purple-900/20 text-white flex flex-col md:flex-row md:items-center justify-between gap-6 animate-pulse-glow">
                        <div>
                            <span class="bg-white/10 backdrop-blur-md text-purple-200 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider border border-purple-300/20">
                                Live Analytics Overview
                            </span>
                            <h1 class="text-2xl lg:text-3xl font-extrabold tracking-tight mt-3 text-white" id="report-title">Today's Live Sales Report</h1>
                            <p class="text-purple-200/90 text-sm mt-1">Monitor real-time revenue streams, earnings, and system reports seamlessly.</p>
                        </div>
                        <div class="inline-flex bg-white/10 backdrop-blur-md p-1.5 rounded-2xl border border-white/10 shrink-0">
                            <button onclick="switchLiveView('today')" id="btn-today" class="px-5 py-2.5 rounded-xl text-xs font-extrabold tracking-wider uppercase shadow-lg shadow-black/5 bg-white text-black transition-all duration-300 flex items-center gap-2">
                                <i class="bi bi-calendar-event"></i>Today Only
                            </button>
                            <button onclick="switchLiveView('month')" id="btn-month" class="px-5 py-2.5 rounded-xl text-xs font-extrabold tracking-wider uppercase text-purple-200 hover:text-white transition-all duration-300 flex items-center gap-2">
                                <i class="bi bi-calendar-month"></i>This Month
                            </button>
                        </div>
                    </div>

                    <!-- Sidebar removed from here as requested (alsin mo ung sidebar inside main sa live reports) -->
                    <div id="live-sales-container">
                        <div class="flex flex-col items-center justify-center py-24 bg-white/90 backdrop-blur-2xl rounded-3xl shadow-xl shadow-purple-900/5 border border-purple-100">
                            <div class="animate-spin rounded-full h-10 w-10 border-3 border-purple-700 border-t-transparent mb-4"></div>
                            <p class="text-sm text-black font-bold uppercase tracking-wider">Loading live dashboard updates...</p>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- RECEIPT MODAL -->
    <div id="receiptModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 transition-all duration-300">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden border border-purple-100 transform transition-all duration-300 scale-95 animate-fade-in">
            <div class="p-6 text-center font-mono text-black">
                <h3 class="font-bold text-lg tracking-wider text-black">PANNAKODA</h3>
                <p class="text-xs text-black mt-0.5 font-medium">Official Receipt</p>
                <p class="text-xs text-black mt-2">OR # : <span id="modal-or" class="font-bold"></span></p>
                <p class="text-xs text-black font-medium" id="modal-date"></p>

                <div class="border-t border-dashed border-purple-300 my-4"></div>

                <div class="flex justify-between text-xs font-bold text-black mb-2">
                    <span>Item</span>
                    <span>Qty</span>
                    <span>Total</span>
                </div>
                <div class="flex justify-between text-xs text-black items-center font-medium">
                    <span id="modal-item" class="text-left truncate max-w-[140px]"></span>
                    <span id="modal-qty"></span>
                    <span id="modal-total"></span>
                </div>

                <div class="border-t border-dashed border-purple-300 my-4"></div>

                <div class="space-y-1 text-xs text-left text-black">
                    <div class="flex justify-between font-bold">
                        <span>TOTAL AMOUNT:</span>
                        <span id="modal-grand-total" class="text-purple-700"></span>
                    </div>
                    <div class="flex justify-between font-medium">
                        <span>Payment Mode:</span>
                        <span id="modal-payment"></span>
                    </div>
                    <div class="flex justify-between font-medium">
                        <span>Amount Paid:</span>
                        <span id="modal-paid"></span>
                    </div>
                    <div class="flex justify-between font-medium">
                        <span>Change Due:</span>
                        <span>₱0.00</span>
                    </div>
                </div>
            </div>

            <div class="bg-purple-50/50 p-4 border-t border-purple-100 flex gap-3">
                <button onclick="closeReceiptModal()" class="w-full bg-purple-700 hover:bg-purple-800 text-white py-2.5 rounded-2xl text-xs font-extrabold uppercase transition-all duration-200 shadow-md">
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

        // Initialize Main Sales History Chart
        const canvas = document.getElementById('monthlySalesChart');
        if (canvas) {
            let chartLabels = JSON.parse(canvas.getAttribute('data-labels') || '[]');
            let chartData = JSON.parse(canvas.getAttribute('data-totals') || '[]');
            const ctx = canvas.getContext('2d');
            new Chart(ctx, {
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
                            gradient.addColorStop(0, 'rgba(126, 34, 206, 0.3)');
                            gradient.addColorStop(1, 'rgba(126, 34, 206, 0.0)');
                            return gradient;
                        },
                        borderColor: '#7e22ce',                     
                        borderWidth: 3,
                        pointBackgroundColor: '#7e22ce',            
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
                    plugins: { legend: { display: false } },
                    scales: {
                        x: { grid: { display: false }, ticks: { color: '#000000', font: { size: 11, weight: '600' } } },
                        y: { beginAtZero: true, ticks: { color: '#000000', font: { size: 11, weight: '600' }, callback: function(value) { return '₱' + value.toLocaleString(); } }, grid: { color: '#f3e8ff' } }
                    }
                }
            });
        }

        // Live View Logic for Tab 3
        let currentLiveView = "today";
        let salesChartInstance = null;

        function initLiveChart() {
            const chartCanvas = document.getElementById('salesChart');
            if (!chartCanvas) return;

            const labels = JSON.parse(chartCanvas.getAttribute('data-labels') || '[]');
            const values = JSON.parse(chartCanvas.getAttribute('data-values') || '[]');
            const ctx = chartCanvas.getContext('2d');
            
            if (salesChartInstance) { salesChartInstance.destroy(); }

            salesChartInstance = new Chart(ctx, {
                type: 'line', 
                data: {
                    labels: labels, 
                    datasets: [{
                        label: 'Total Revenue (₱)',
                        data: values,
                        borderColor: '#7e22ce',
                        backgroundColor: (context) => {
                            const chart = context.chart;
                            const {ctx, chartArea} = chart;
                            if (!chartArea) return null;
                            const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                            gradient.addColorStop(0, 'rgba(126, 34, 206, 0.3)');
                            gradient.addColorStop(1, 'rgba(126, 34, 206, 0.0)');
                            return gradient;
                        },
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#7e22ce',
                        pointBorderColor: '#ffffff',
                        pointBorderWidth: 2,
                        pointRadius: 4,
                        pointHoverRadius: 6
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: { legend: { display: false } },
                    scales: {
                        y: { beginAtZero: true, grid: { color: '#f3e8ff' }, ticks: { color: '#000000', font: { size: 11, weight: '600' }, callback: function(value) { return '₱' + value.toLocaleString(); } } },
                        x: { grid: { display: false }, ticks: { color: '#000000', font: { size: 11, weight: '600' } } }
                    }
                }
            });
        }

        function fetchLiveSalesData() {
            if ("<?php echo $active_tab; ?>" !== 'live') return;
            fetch(`?tab=live&view=${currentLiveView}&ajax=1`)
                .then(response => response.text())
                .then(htmlContent => {
                    const container = document.getElementById('live-sales-container');
                    if(container) {
                        container.innerHTML = htmlContent;
                        initLiveChart();
                    }
                })
                .catch(error => console.error('Error fetching layout updates:', error));
        }

        function switchLiveView(viewType) {
            currentLiveView = viewType;
            const btnToday = document.getElementById('btn-today');
            const btnMonth = document.getElementById('btn-month');
            
            if(viewType === 'month') {
                if(btnMonth) btnMonth.className = 'px-5 py-2.5 rounded-xl text-xs font-extrabold tracking-wider uppercase shadow-lg bg-white text-black transition-all duration-300 flex items-center gap-2';
                if(btnToday) btnToday.className = 'px-5 py-2.5 rounded-xl text-xs font-extrabold tracking-wider uppercase text-purple-200 hover:text-white transition-all duration-300 flex items-center gap-2';
            } else {
                if(btnToday) btnToday.className = 'px-5 py-2.5 rounded-xl text-xs font-extrabold tracking-wider uppercase shadow-lg bg-white text-black transition-all duration-300 flex items-center gap-2';
                if(btnMonth) btnMonth.className = 'px-5 py-2.5 rounded-xl text-xs font-extrabold tracking-wider uppercase text-purple-200 hover:text-white transition-all duration-300 flex items-center gap-2';
            }
            fetchLiveSalesData();
        }

        document.addEventListener("DOMContentLoaded", function() {
            if ("<?php echo $active_tab; ?>" === 'live') {
                switchLiveView(currentLiveView);
                setInterval(fetchLiveSalesData, 4000);
            }
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>