<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: ../../PAGES/login.php");
    exit();
}

require_once __DIR__ . '../../project-test1/BACKEND/db_inventory.php';

// Anti-Back Button Cache Control[cite: 1]
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// Fix for ONLY_FULL_GROUP_BY error[cite: 1]
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
        <div class="bg-white/90 backdrop-blur-2xl p-6 rounded-3xl shadow-xl shadow-orange-950/5 border border-orange-100 flex items-center justify-between transition-all duration-300 hover:scale-[1.01] hover:shadow-2xl hover:border-[#ff6b4a]/40 animate-fade-in">
            <div>
                <span class="text-xs font-extrabold text-black uppercase tracking-wider block mb-1">Total Revenue (<?php echo ucfirst($view); ?>)</span>
                <h3 class="text-2xl lg:text-3xl font-extrabold text-black">₱<?php echo number_format($grand_total, 2); ?></h3>
            </div>
            <div class="w-14 h-14 bg-orange-50 text-[#ff6b4a] rounded-2xl flex items-center justify-center text-2xl shadow-inner transition-transform duration-300 hover:rotate-6 border border-[#ff6b4a]/20">
                <i class="bi bi-cash-stack"></i>
            </div>
        </div>
        <div class="bg-white/90 backdrop-blur-2xl p-6 rounded-3xl shadow-xl shadow-orange-950/5 border border-orange-100 flex items-center justify-between transition-all duration-300 hover:scale-[1.01] hover:shadow-2xl hover:border-[#ff6b4a]/40 animate-fade-in">
            <div>
                <span class="text-xs font-extrabold text-black uppercase tracking-wider block mb-1">Total Transactions (<?php echo ucfirst($view); ?>)</span>
                <h3 class="text-2xl lg:text-3xl font-extrabold text-black"><?php echo number_format($grand_trans); ?> Orders</h3>
            </div>
            <div class="w-14 h-14 bg-orange-50 text-[#ff6b4a] rounded-2xl flex items-center justify-center text-2xl shadow-inner transition-transform duration-300 hover:rotate-6 border border-[#ff6b4a]/20">
                <i class="bi bi-receipt"></i>
            </div>
        </div>
    </div>

    <div class="bg-white/90 backdrop-blur-2xl p-6 lg:p-8 rounded-3xl shadow-xl shadow-orange-950/5 border border-orange-100 mb-6 transition-all duration-300 hover:shadow-2xl animate-fade-in">
        <h3 class="text-sm font-extrabold text-black uppercase tracking-wider mb-6 flex items-center gap-2">
            <i class="bi bi-graph-up text-[#ff6b4a] text-lg"></i> Live Revenue Chart
        </h3>
        <div class="h-80 w-full">
            <canvas id="salesChart" 
                    data-labels='<?php echo json_encode($labels); ?>' 
                    data-values='<?php echo json_encode($values); ?>'>
            </canvas>
        </div>
    </div>

    <div class="bg-white/90 backdrop-blur-2xl rounded-3xl shadow-xl shadow-orange-950/5 border border-orange-100 overflow-hidden animate-fade-in">
        <div class="p-6 lg:p-8 border-b border-orange-100">
            <h3 class="text-sm font-extrabold text-black uppercase tracking-wider flex items-center gap-2">
                <i class="bi bi-table text-[#ff6b4a] text-lg"></i> Breakdown Summary Table
            </h3>
        </div>
        <div class="overflow-x-auto max-h-[350px] overflow-y-auto">
            <table class="w-full text-left border-collapse">
                <thead class="sticky top-0 z-10">
                    <tr class="bg-orange-50/90 backdrop-blur-md border-b border-orange-100 text-black text-xs uppercase font-extrabold tracking-wider">
                        <th class="p-4 pl-8">Timeframe / Period</th>
                        <th class="p-4 text-center">Transactions Count</th>
                        <th class="p-4 text-right pr-8">Subtotal Earnings</th>
                    </tr>
                </thead>
                <tbody class="text-sm divide-y divide-orange-50 text-black">
                    <?php if (!empty($table_rows)): ?>
                        <?php foreach ($table_rows as $tr): ?>
                            <tr class="hover:bg-orange-50/60 transition-all duration-200">
                                <td class="p-4 pl-8 font-bold text-black"><?php echo htmlspecialchars($tr['label_name']); ?></td>
                                <td class="p-4 text-center font-extrabold text-[#ff6b4a]"><?php echo $tr['total_transactions']; ?></td>
                                <td class="p-4 text-right pr-8 font-extrabold text-[#ff6b4a]">₱<?php echo number_format($tr['total_earnings'], 2); ?></td>
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
<html lang="en" class="h-full bg-slate/50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PannaKoda - Combined System Reports & Tools</title>
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
        .animate-fade-in {
            animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        .hover-lift {
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s ease;
        }
        .hover-lift:hover {
            transform: translateY(-4px);
        }
    </style>
</head>
<body class="h-full flex overflow-hidden text-black antialiased selection:bg-[#ff6b4a] selection:text-white bg-slate/50">
    <div class="flex h-screen w-full overflow-hidden bg-slate/50" id="mainDashboardWrapper">
        <?php include 'sidebar.php'; ?>
        
        <div id="sidebarOverlay" class="fixed inset-0 bg-black/40 backdrop-blur-sm z-40 hidden md:hidden transition-all duration-300"></div>

        <div class="flex-1 flex flex-col min-w-0 h-full overflow-hidden bg-slate/50">
            <!-- TOP NAVBAR WITH FEATURE 1 (PH TIME CLOCK) POSITIONED AT THE TOP -->
            <header class="h-20 px-6 flex items-center justify-between bg-white/90 backdrop-blur-2xl border-b border-orange-100 shrink-0 shadow-sm">
                <div class="flex items-center gap-4">
                    <button id="burgerToggle" class="p-2 -ml-2 rounded-2xl text-black hover:bg-orange-100 focus:outline-none md:hidden transition-colors">
                        <i class="fa-solid fa-bars text-xl"></i>
                    </button>
                </div>
                
                <!-- FEATURE 1: Philippine Time Live Clock Widget -->
                <div class="flex items-center gap-3">
                    <div class="bg-orange-50 border border-[#ff6b4a]/20 px-4 py-2 rounded-2xl flex items-center gap-2.5 shadow-inner">
                        <i class="bi bi-clock-history text-[#ff6b4a] animate-pulse"></i>
                        <div>
                            <span id="ph-clock" class="text-xs font-bold text-black font-mono">Loading...</span>
                        </div>
                    </div>
                </div>
            </header>
             
            <main class="flex-1 p-6 lg:p-8 space-y-6 overflow-y-auto bg-slate/50">
                     
                <!-- FEATURE BAR: 10 WORKING MODERN UTILITY FEATURES -->
                <div class="grid grid-cols-2 sm:grid-cols-5 lg:grid-cols-5 gap-3">
                    <button onclick="switchTab('analytics')" class="p-3 bg-white/90 hover:bg-orange-50 rounded-2xl border border-orange-100 shadow-sm text-center transition-all hover-lift">
                        <i class="bi bi-grid-1x2-fill text-[#ff6b4a] text-lg block mb-1"></i>
                        <span class="text-[11px] font-extrabold uppercase tracking-wider block text-black">Analytics</span>
                    </button>
                    <button onclick="switchTab('history')" class="p-3 bg-white/90 hover:bg-orange-50 rounded-2xl border border-orange-100 shadow-sm text-center transition-all hover-lift">
                        <i class="fa-solid fa-clock-rotate-left text-[#ff6b4a] text-lg block mb-1"></i>
                        <span class="text-[11px] font-extrabold uppercase tracking-wider block text-black">History Logs</span>
                    </button>
                    <button onclick="switchTab('live')" class="p-3 bg-white/90 hover:bg-orange-50 rounded-2xl border border-orange-100 shadow-sm text-center transition-all hover-lift">
                        <i class="bi bi-activity text-[#ff6b4a] text-lg block mb-1"></i>
                        <span class="text-[11px] font-extrabold uppercase tracking-wider block text-black">Live Reports</span>
                    </button>
                    <button onclick="openCalcModal()" class="p-3 bg-white/90 hover:bg-orange-50 rounded-2xl border border-orange-100 shadow-sm text-center transition-all hover-lift">
                        <i class="bi bi-calculator-fill text-[#ff6b4a] text-lg block mb-1"></i>
                        <span class="text-[11px] font-extrabold uppercase tracking-wider block text-black">Quick Calc</span>
                    </button>
                    <button onclick="triggerQuickExport()" class="p-3 bg-white/90 hover:bg-orange-50 rounded-2xl border border-orange-100 shadow-sm text-center transition-all hover-lift col-span-2 sm:col-span-1">
                        <i class="bi bi-file-earmark-spreadsheet-fill text-[#ff6b4a] text-lg block mb-1"></i>
                        <span class="text-[11px] font-extrabold uppercase tracking-wider block text-black">CSV Export</span>
                    </button>
                </div>

                <!-- ========================================== -->
                <!-- TAB CONTENT 1: SALES HISTORY               -->
                <!-- ========================================== -->
                <div id="tab-content-history" class="space-y-6 animate-fade-in <?php echo $active_tab != 'history' ? 'hidden' : ''; ?>">
                    <div class="bg-gradient-to-br from-[#1a1010] via-[#1f1212] to-[#09090b] p-8 lg:p-10 rounded-3xl shadow-2xl text-white flex flex-col md:flex-row md:items-center justify-between gap-6 border border-[#ff6b4a]/30">
                        <div>
                            <span class="bg-[#ff6b4a]/20 backdrop-blur-md text-[#ff6b4a] text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider border border-[#ff6b4a]/30">
                                Sales Logs Overview
                            </span>
                            <h1 class="text-2xl lg:text-3xl font-extrabold tracking-tight mt-3 text-white">Sales History & Filters</h1>
                            <p class="text-slate-300 text-sm mt-1">Monitor real-time revenue streams, earnings, and system reports seamlessly.</p>
                        </div>
                    </div>

                    <div class="bg-white/90 backdrop-blur-2xl p-6 lg:p-8 rounded-3xl shadow-xl shadow-orange-950/5 border border-orange-100 hover-lift">
                        <h3 class="text-xs font-extrabold text-black uppercase tracking-wider mb-6 flex items-center gap-2">
                            <i class="fa-solid fa-filter text-[#ff6b4a]"></i> Search Filters
                        </h3>
                        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-6 items-end">
                            <input type="hidden" name="tab" value="history">
                            <div>
                                <label class="block text-xs font-extrabold text-black uppercase mb-2 tracking-wider">Item Name</label>
                                <input type="text" name="search_item" placeholder="Search item..." value="<?php echo htmlspecialchars($search_item); ?>"
                                    class="w-full px-4 py-3 rounded-2xl border border-orange-200 focus:outline-none focus:border-[#ff6b4a] focus:ring-4 focus:ring-[#ff6b4a]/10 text-sm bg-orange-50/30 transition-all font-medium text-black">
                            </div>
                            <div>
                                <label class="block text-xs font-extrabold text-black uppercase mb-2 tracking-wider">Category</label>
                                <select name="search_category" class="w-full px-4 py-3 rounded-2xl border border-orange-200 focus:outline-none focus:border-[#ff6b4a] focus:ring-4 focus:ring-[#ff6b4a]/10 text-sm bg-orange-50/30 transition-all font-medium text-black">
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
                                    class="w-full px-4 py-3 rounded-2xl border border-orange-200 focus:outline-none focus:border-[#ff6b4a] focus:ring-4 focus:ring-[#ff6b4a]/10 text-sm bg-orange-50/30 transition-all font-medium text-black">
                            </div>
                            <div class="md:col-span-3 flex gap-3 justify-end pt-2">
                                <button type="submit" class="bg-[#ff6b4a] hover:bg-[#fa4b2a] active:scale-[0.98] text-white px-6 py-3 rounded-2xl text-xs font-extrabold uppercase tracking-wider flex items-center gap-2 shadow-lg shadow-[#ff6b4a]/25 transition-all duration-300 hover:shadow-xl">
                                    <i class="fa-solid fa-magnifying-glass"></i> Filter Income
                                </button>
                                <a href="?tab=history" class="bg-orange-100 hover:bg-orange-200 active:scale-[0.98] text-black px-6 py-3 rounded-2xl text-xs font-extrabold uppercase tracking-wider transition-all duration-300 flex items-center">
                                    Reset
                                </a>
                            </div>
                        </form>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="bg-white/90 backdrop-blur-2xl p-6 lg:p-8 rounded-3xl shadow-xl shadow-orange-950/5 border border-orange-100 flex items-center justify-between relative overflow-hidden hover-lift">
                            <div class="absolute left-0 top-0 bottom-0 w-2 bg-[#ff6b4a]"></div>
                            <div>
                                <p class="text-xs font-extrabold text-[#ff6b4a] uppercase tracking-wider mb-1 flex items-center gap-1.5"><i class="fa-solid fa-money-bill-wave"></i>Cash Transactions</p>
                                <h4 class="text-xl font-bold text-black mt-2">
                                    <span class="text-black"><?php echo $cash_count ? $cash_count : 0; ?></span> orders collected
                                </h4>
                                <p class="text-xs font-semibold text-black mt-1">Total: <span class="font-extrabold text-[#ff6b4a] text-base">₱<?php echo number_format($cash_total, 2); ?></span></p>
                            </div>
                            <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center text-[#ff6b4a] text-2xl shadow-inner border border-[#ff6b4a]/20 transition-transform duration-300 hover:scale-110">
                                <i class="fa-solid fa-cash-register"></i>
                            </div>
                        </div>

                        <div class="bg-white/90 backdrop-blur-2xl p-6 lg:p-8 rounded-3xl shadow-xl shadow-orange-950/5 border border-orange-100 flex items-center justify-between relative overflow-hidden hover-lift">
                            <div class="absolute left-0 top-0 bottom-0 w-2 bg-[#1a1010]"></div>
                            <div>
                                <p class="text-xs font-extrabold text-slate-800 uppercase tracking-wider mb-1 flex items-center gap-1.5"><i class="fa-solid fa-credit-card"></i>Card / Digital Transactions</p>
                                <h4 class="text-xl font-bold text-black mt-2">
                                    <span class="text-black"><?php echo $card_count ? $card_count : 0; ?></span> orders collected
                                </h4>
                                <p class="text-xs font-semibold text-black mt-1">Total: <span class="font-extrabold text-slate-900 text-base">₱<?php echo number_format($card_total, 2); ?></span></p>
                            </div>
                            <div class="w-14 h-14 bg-orange-50 rounded-2xl flex items-center justify-center text-[#ff6b4a] text-2xl shadow-inner border border-[#ff6b4a]/20 transition-transform duration-300 hover:scale-110">
                                <i class="fa-solid fa-wallet"></i>
                            </div>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                        <div class="bg-white/90 backdrop-blur-2xl p-6 lg:p-8 rounded-3xl shadow-xl shadow-orange-950/5 border border-orange-100 lg:col-span-2 hover-lift">
                            <h3 class="text-sm font-extrabold text-black uppercase tracking-wider mb-6 flex items-center gap-2">
                                <i class="fa-solid fa-chart-area text-[#ff6b4a] text-lg"></i> Sales Performance Graph
                            </h3>
                            <div class="h-72 w-full">
                                <canvas id="monthlySalesChart" 
                                        data-labels='<?php echo json_encode($months); ?>' 
                                        data-totals='<?php echo json_encode($sales_totals); ?>'>
                                </canvas>
                            </div>
                        </div>

                        <div class="bg-white/90 backdrop-blur-2xl p-6 lg:p-8 rounded-3xl shadow-xl shadow-orange-950/5 border border-orange-100 hover-lift">
                            <h3 class="text-sm font-extrabold text-black uppercase tracking-wider mb-6 flex items-center gap-2">
                                <i class="fa-solid fa-fire text-[#ff6b4a] text-lg"></i> Top 6 Best Sellers
                            </h3>
                            <div class="divide-y divide-orange-100">
                                <?php if ($top_products_result && $top_products_result->num_rows > 0): $rank = 1; ?>
                                    <?php while ($prod = $top_products_result->fetch_assoc()): ?>
                                        <div class="flex items-center justify-between py-3.5 first:pt-0 last:pb-0 transition-all duration-200 hover:px-2 rounded-xl hover:bg-orange-50/50">
                                            <div class="flex items-center gap-3">
                                                <span class="w-7 h-7 rounded-xl bg-orange-100 text-xs font-extrabold text-[#ff6b4a] flex items-center justify-center shadow-sm">
                                                    <?php echo $rank++; ?>
                                                </span>
                                                <span class="text-sm font-bold text-black"><?php echo htmlspecialchars($prod['item_name']); ?></span>
                                            </div>
                                            <span class="text-xs bg-orange-100 text-[#ff6b4a] font-extrabold px-3 py-1 rounded-xl tracking-wide">
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

                    <!-- PURCHASE BREAKDOWN LOGS TABLE WITH ROWS SELECTOR & PAGINATION -->
                    <div class="bg-white/90 backdrop-blur-2xl rounded-3xl shadow-xl shadow-orange-950/5 border border-orange-100 overflow-hidden">
                        <div class="p-6 lg:p-8 border-b border-orange-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <h3 class="text-sm font-extrabold text-black uppercase tracking-wider flex items-center gap-2">
                                <i class="fa-solid fa-list-check text-[#ff6b4a] text-lg"></i> Purchase Breakdown Logs
                            </h3>
                            <!-- Show Rows Dropdown -->
                            <div class="flex items-center gap-2 text-xs font-bold text-black">
                                <span>Show:</span>
                                <select id="rowsPerPageSelect" onchange="changeRowsPerPage()" class="bg-orange-50 border border-orange-200 rounded-xl px-3 py-1.5 focus:outline-none focus:border-[#ff6b4a] text-black font-extrabold">
                                    <option value="5">5 rows</option>
                                    <option value="10">10 rows</option>
                                    <option value="20" selected>20 rows</option>
                                    <option value="25">25 rows</option>
                                    <option value="all">All rows</option>
                                </select>
                            </div>
                        </div>

                        <div class="overflow-x-auto">
                            <table id="purchaseLogsTable" class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="bg-orange-50/90 backdrop-blur-md border-b border-orange-100 text-black text-xs uppercase font-extrabold tracking-wider">
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
                                <tbody id="purchaseLogsTbody" class="text-sm divide-y divide-orange-50 text-black">
                                    <?php if ($history_result && $history_result->num_rows > 0): ?>
                                        <?php while ($row = $history_result->fetch_assoc()): ?>
                                            <tr class="log-row hover:bg-orange-50/60 transition-colors duration-200">
                                                <td class="p-4 pl-8 font-bold text-[#ff6b4a] whitespace-nowrap">
                                                    <i class="bi bi-clock me-1.5 text-black font-normal"></i>
                                                    <?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?>
                                                </td>
                                                <td class="p-4"><span class="font-mono text-xs bg-orange-100 text-orange-900 px-3 py-1 rounded-xl font-bold">#TXN-<?php echo str_pad($row['sale_id'], 5, '0', STR_PAD_LEFT); ?></span></td>
                                                <td class="p-4 font-extrabold text-black"><?php echo htmlspecialchars($row['item_name']); ?></td>
                                                <td class="p-4"><span class="text-xs bg-orange-100 text-orange-900 px-3 py-1 rounded-xl font-semibold"><?php echo htmlspecialchars($row['category_name']); ?></span></td>
                                                <td class="p-4 text-center font-extrabold text-black"><?php echo $row['quantity']; ?></td>
                                                <td class="p-4 text-right font-semibold text-black">₱<?php echo number_format($row['price_at_sale'], 2); ?></td>
                                                <td class="p-4 text-right font-extrabold text-[#ff6b4a]">₱<?php echo number_format($row['subtotal'], 2); ?></td>
                                                <td class="p-4 text-center">
                                                    <span class="text-xs px-3 py-1 rounded-xl font-extrabold tracking-wide bg-orange-100 text-orange-900">
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
                                                    )" class="bg-orange-100 hover:bg-orange-200 text-[#ff6b4a] px-3 py-1.5 rounded-xl text-xs font-bold transition-all duration-200 flex items-center gap-1.5 mx-auto hover:scale-105">
                                                        <i class="fa-solid fa-receipt"></i> Receipt
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <tr id="no-records-row">
                                            <td colspan="9" class="text-center py-16 text-black font-medium">No purchase history records found matching your query metrics.</td>
                                        </tr>
                                    <?php endif; ?>
                                </tbody>
                            </table>
                        </div>

                        <!-- PAGINATION FOOTER -->
                        <div class="p-6 border-t border-orange-100 flex flex-col sm:flex-row items-center justify-between gap-4">
                            <div id="tableInfo" class="text-xs font-bold text-black">
                                Showing 0 to 0 of 0 entries
                            </div>
                            <div id="paginationButtons" class="flex items-center gap-1">
                                <!-- Dynamically generated pagination buttons -->
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- TAB CONTENT 2: ANALYTICS DASHBOARD         -->
                <!-- ========================================== -->
                <div id="tab-content-analytics" class="space-y-6 animate-fade-in <?php echo $active_tab != 'analytics' ? 'hidden' : ''; ?>">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-br from-[#1a1010] via-[#1f1212] to-[#09090b] rounded-3xl p-6 lg:p-8 text-white shadow-2xl border border-[#ff6b4a]/30">
                        <div>
                            <span class="bg-[#ff6b4a]/20 text-[white] text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider backdrop-blur-md border border-[#ff6b4a]/30">Analytics Overview</span>
                            <h1 class="text-2xl lg:text-3xl font-extrabold tracking-tight mt-2 text-white">Dashboard Analytics</h1>
                            <p class="text-slate-300 text-sm mt-1">Monitor real-time revenue streams, earnings, and system reports seamlessly.</p>
                        </div>
                    </div>

                    <!-- AUTOMATIC EARNINGS PER DAY, MONTH, AND YEAR CARDS -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
                        <div class="bg-white/90 backdrop-blur-2xl rounded-3xl p-5 border border-orange-100 shadow-xl shadow-orange-950/5 flex items-center gap-4 hover-lift">
                            <div class="w-12 h-12 rounded-2xl bg-orange-50 text-[#ff6b4a] flex items-center justify-center text-xl shrink-0 shadow-inner border border-[#ff6b4a]/20">
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

                        <div class="bg-white/90 backdrop-blur-2xl rounded-3xl p-5 border border-orange-100 shadow-xl shadow-orange-950/5 flex items-center gap-4 hover-lift">
                            <div class="w-12 h-12 rounded-2xl bg-orange-50 text-[#ff6b4a] flex items-center justify-center text-xl shrink-0 shadow-inner border border-[#ff6b4a]/20">
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

                        <div class="bg-white/90 backdrop-blur-2xl rounded-3xl p-5 border border-orange-100 shadow-xl shadow-orange-950/5 flex items-center gap-4 hover-lift">
                            <div class="w-12 h-12 rounded-2xl bg-orange-50 text-[#ff6b4a] flex items-center justify-center text-xl shrink-0 shadow-inner border border-[#ff6b4a]/20">
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

                        <div class="bg-white/90 backdrop-blur-2xl rounded-3xl p-5 border border-orange-100 shadow-xl shadow-orange-950/5 flex items-center gap-4 hover-lift">
                            <div class="w-12 h-12 rounded-2xl bg-orange-50 text-[#ff6b4a] flex items-center justify-center text-xl shrink-0 shadow-inner border border-[#ff6b4a]/20">
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

                    <div class="bg-white/90 backdrop-blur-2xl rounded-3xl shadow-xl shadow-orange-950/5 border border-orange-100 p-6 hover-lift">
                        <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-5 items-end">
                            <input type="hidden" name="tab" value="analytics">
                            <div>
                                <label class="block text-xs font-bold text-black uppercase tracking-wider mb-2">Filter Month</label>
                                <div class="relative">
                                    <select name="filter_month" class="w-full bg-orange-50/40 border border-orange-200 rounded-2xl px-4 py-3 text-black text-sm focus:outline-none focus:ring-2 focus:ring-[#ff6b4a]/20 focus:border-[#ff6b4a] transition-all appearance-none font-medium">
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
                                    <select name="filter_year" class="w-full bg-orange-50/40 border border-orange-200 rounded-2xl px-4 py-3 text-black text-sm focus:outline-none focus:ring-2 focus:ring-[#ff6b4a]/20 focus:border-[#ff6b4a] transition-all appearance-none font-medium">
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
                                <button type="submit" class="flex-1 bg-[#ff6b4a] hover:bg-[#fa4b2a] active:scale-[0.98] text-white font-semibold py-3 px-5 rounded-2xl flex items-center justify-center gap-2 transition-all duration-300 shadow-lg shadow-[#ff6b4a]/25 text-sm hover:shadow-xl">
                                    <i class="bi bi-funnel-fill"></i> Apply Filter
                                </button>
                            </div>
                        </form>
                    </div>

                    <!-- UNIFIED INCOME TABLE WITH SWITCHING BUTTONS AND VERTICAL SCROLLING -->
                    <div class="bg-white/90 backdrop-blur-2xl rounded-3xl shadow-xl shadow-orange-950/5 border border-orange-100 overflow-hidden">
                        <div class="p-6 lg:p-8 border-b border-orange-100 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                            <h3 class="text-sm font-extrabold text-black uppercase tracking-wider flex items-center gap-2">
                                <i class="bi bi-table text-[#ff6b4a] text-lg"></i> Income Breakdown Records
                            </h3>
                            
                            <!-- Toggle Buttons -->
                            <div class="inline-flex bg-orange-50 p-1.5 rounded-2xl border border-orange-100 shrink-0">
                                <button onclick="switchIncomeTable('daily')" id="tab-btn-daily" class="px-4 py-2 rounded-xl text-xs font-extrabold tracking-wider uppercase bg-[#ff6b4a] text-white transition-all shadow-sm">
                                    Daily
                                </button>
                                <button onclick="switchIncomeTable('weekly')" id="tab-btn-weekly" class="px-4 py-2 rounded-xl text-xs font-extrabold tracking-wider uppercase text-black hover:text-[#ff6b4a] transition-all">
                                    Weekly
                                </button>
                                <button onclick="switchIncomeTable('monthly')" id="tab-btn-monthly" class="px-4 py-2 rounded-xl text-xs font-extrabold tracking-wider uppercase text-black hover:text-[#ff6b4a] transition-all">
                                    Monthly
                                </button>
                                <button onclick="switchIncomeTable('yearly')" id="tab-btn-yearly" class="px-4 py-2 rounded-xl text-xs font-extrabold tracking-wider uppercase text-black hover:text-[#ff6b4a] transition-all">
                                    Yearly
                                </button>
                            </div>
                        </div>

                        <!-- TABLES WRAPPER WITH FIXED MAX HEIGHT AND STICKY HEADERS -->
                        <div class="overflow-x-auto max-h-[380px] overflow-y-auto">
                            <!-- DAILY TABLE -->
                            <div id="table-container-daily" class="income-table-pane">
                                <table class="w-full text-left border-collapse">
                                    <thead class="sticky top-0 z-10">
                                        <tr class="bg-orange-50/90 backdrop-blur-md border-b border-orange-100 text-black text-xs uppercase font-extrabold tracking-wider">
                                            <th class="p-4 pl-8">Period (Daily)</th>
                                            <th class="p-4 text-center">Transactions Count</th>
                                            <th class="p-4 text-right pr-8">Total Earnings</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-sm divide-y divide-orange-50 text-black">
                                        <?php if ($day_result && $day_result->num_rows > 0): ?>
                                            <?php while($row = $day_result->fetch_assoc()): ?>
                                                <tr class="hover:bg-orange-50/50 transition-colors">
                                                    <td class="p-4 pl-8 font-bold text-black"><?php echo $row['period_name']; ?></td>
                                                    <td class="p-4 text-center font-extrabold text-[#ff6b4a]"><?php echo $row['total_transactions']; ?> Trans.</td>
                                                    <td class="p-4 text-right pr-8 font-extrabold text-[#ff6b4a]">₱<?php echo number_format($row['total_earnings'], 2); ?></td>
                                                </tr>
                                            <?php endwhile; ?>
                                        <?php else: ?>
                                            <tr><td colspan="3" class="text-center py-12 text-black font-medium">No daily records found.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- WEEKLY TABLE -->
                            <div id="table-container-weekly" class="income-table-pane hidden">
                                <table class="w-full text-left border-collapse">
                                    <thead class="sticky top-0 z-10">
                                        <tr class="bg-orange-50/90 backdrop-blur-md border-b border-orange-100 text-black text-xs uppercase font-extrabold tracking-wider">
                                            <th class="p-4 pl-8">Period (Weekly)</th>
                                            <th class="p-4 text-center">Transactions Count</th>
                                            <th class="p-4 text-right pr-8">Total Earnings</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-sm divide-y divide-orange-50 text-black">
                                        <?php if (!empty($graph_weekly_data)): ?>
                                            <?php foreach($graph_weekly_data as $row): ?>
                                                <tr class="hover:bg-orange-50/50 transition-colors">
                                                    <td class="p-4 pl-8 font-bold text-black"><?php echo $row['period_name']; ?></td>
                                                    <td class="p-4 text-center font-extrabold text-[#ff6b4a]"><?php echo $row['total_transactions']; ?> Trans.</td>
                                                    <td class="p-4 text-right pr-8 font-extrabold text-[#ff6b4a]">₱<?php echo number_format($row['total_earnings'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="3" class="text-center py-12 text-black font-medium">No weekly records found.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- MONTHLY TABLE -->
                            <div id="table-container-monthly" class="income-table-pane hidden">
                                <table class="w-full text-left border-collapse">
                                    <thead class="sticky top-0 z-10">
                                        <tr class="bg-orange-50/90 backdrop-blur-md border-b border-orange-100 text-black text-xs uppercase font-extrabold tracking-wider">
                                            <th class="p-4 pl-8">Period (Monthly)</th>
                                            <th class="p-4 text-center">Transactions Count</th>
                                            <th class="p-4 text-right pr-8">Total Earnings</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-sm divide-y divide-orange-50 text-black">
                                        <?php if (!empty($graph_monthly_data)): ?>
                                            <?php foreach($graph_monthly_data as $row): ?>
                                                <tr class="hover:bg-orange-50/50 transition-colors">
                                                    <td class="p-4 pl-8 font-bold text-black"><?php echo $row['period_name']; ?></td>
                                                    <td class="p-4 text-center font-extrabold text-[#ff6b4a]"><?php echo $row['total_transactions']; ?> Trans.</td>
                                                    <td class="p-4 text-right pr-8 font-extrabold text-[#ff6b4a]">₱<?php echo number_format($row['total_earnings'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="3" class="text-center py-12 text-black font-medium">No monthly records found.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>

                            <!-- YEARLY TABLE -->
                            <div id="table-container-yearly" class="income-table-pane hidden">
                                <table class="w-full text-left border-collapse">
                                    <thead class="sticky top-0 z-10">
                                        <tr class="bg-orange-50/90 backdrop-blur-md border-b border-orange-100 text-black text-xs uppercase font-extrabold tracking-wider">
                                            <th class="p-4 pl-8">Period (Yearly)</th>
                                            <th class="p-4 text-center">Transactions Count</th>
                                            <th class="p-4 text-right pr-8">Total Earnings</th>
                                        </tr>
                                    </thead>
                                    <tbody class="text-sm divide-y divide-orange-50 text-black">
                                        <?php if (!empty($graph_yearly_data)): ?>
                                            <?php foreach($graph_yearly_data as $row): ?>
                                                <tr class="hover:bg-orange-50/50 transition-colors">
                                                    <td class="p-4 pl-8 font-bold text-black"><?php echo $row['period_name']; ?></td>
                                                    <td class="p-4 text-center font-extrabold text-[#ff6b4a]"><?php echo $row['total_transactions']; ?> Trans.</td>
                                                    <td class="p-4 text-right pr-8 font-extrabold text-[#ff6b4a]">₱<?php echo number_format($row['total_earnings'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <tr><td colspan="3" class="text-center py-12 text-black font-medium">No yearly records found.</td></tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- ========================================== -->
                <!-- TAB CONTENT 3: LIVE SALES REPORT           -->
                <!-- ========================================== -->
                <div id="tab-content-live" class="space-y-6 animate-fade-in <?php echo $active_tab != 'live' ? 'hidden' : ''; ?>">
                    <div class="bg-gradient-to-br from-[#1a1010] via-[#1f1212] to-[#09090b] p-8 lg:p-10 rounded-3xl shadow-2xl text-white flex flex-col md:flex-row md:items-center justify-between gap-6 border border-[#ff6b4a]/30">
                        <div>
                            <span class="bg-[#ff6b4a]/20 backdrop-blur-md text-[#ff6b4a] text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider border border-[#ff6b4a]/30">
                                Live Analytics Overview
                            </span>
                            <h1 class="text-2xl lg:text-3xl font-extrabold tracking-tight mt-3 text-white" id="report-title">Today's Live Sales Report</h1>
                            <p class="text-slate-300 text-sm mt-1">Monitor real-time revenue streams, earnings, and system reports seamlessly.</p>
                        </div>
                        <div class="inline-flex bg-white/10 backdrop-blur-md p-1.5 rounded-2xl border border-white/10 shrink-0">
                            <button onclick="switchLiveView('today')" id="btn-today" class="px-5 py-2.5 rounded-xl text-xs font-extrabold tracking-wider uppercase shadow-lg shadow-black/5 bg-white text-black transition-all duration-300 flex items-center gap-2">
                                <i class="bi bi-calendar-event"></i>Today Only
                            </button>
                            <button onclick="switchLiveView('month')" id="btn-month" class="px-5 py-2.5 rounded-xl text-xs font-extrabold tracking-wider uppercase text-slate-300 hover:text-white transition-all duration-300 flex items-center gap-2">
                                <i class="bi bi-calendar-month"></i>This Month
                            </button>
                        </div>
                    </div>

                    <div id="live-sales-container">
                        <div class="flex flex-col items-center justify-center py-24 bg-white/90 backdrop-blur-2xl rounded-3xl shadow-xl shadow-orange-950/5 border border-orange-100">
                            <div class="animate-spin rounded-full h-10 w-10 border-3 border-[#ff6b4a] border-t-transparent mb-4"></div>
                            <p class="text-sm text-black font-bold uppercase tracking-wider">Loading live dashboard updates...</p>
                        </div>
                    </div>
                </div>

            </main>
        </div>
    </div>

    <!-- FEATURE 2: BUILT-IN MODERN CALCULATOR MODAL -->
    <div id="calcModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 transition-all duration-300">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-xs overflow-hidden border border-orange-100 transform transition-all duration-300 scale-95 animate-fade-in p-6">
            <div class="flex items-center justify-between mb-4">
                <h3 class="font-extrabold text-black text-sm uppercase tracking-wider flex items-center gap-2">
                    <i class="bi bi-calculator text-[#ff6b4a]"></i> Quick Calculator
                </h3>
                <button onclick="closeCalcModal()" class="text-black hover:text-[#ff6b4a] font-bold"><i class="bi bi-x-lg"></i></button>
            </div>
            <input type="text" id="calc-screen" readonly class="w-full bg-orange-50 border border-orange-200 rounded-2xl p-4 text-right text-2xl font-mono font-bold text-black mb-4 focus:outline-none" value="0">
            <div class="grid grid-cols-4 gap-2">
                <button onclick="calcClear()" class="bg-orange-100 hover:bg-orange-200 text-[#ff6b4a] font-extrabold py-3 rounded-xl text-sm">C</button>
                <button onclick="calcAppend('/')" class="bg-orange-100 hover:bg-orange-200 text-[#ff6b4a] font-extrabold py-3 rounded-xl text-sm">÷</button>
                <button onclick="calcAppend('*')" class="bg-orange-100 hover:bg-orange-200 text-[#ff6b4a] font-extrabold py-3 rounded-xl text-sm">×</button>
                <button onclick="calcAppend('-')" class="bg-orange-100 hover:bg-orange-200 text-[#ff6b4a] font-extrabold py-3 rounded-xl text-sm">-</button>
                
                <button onclick="calcAppend('7')" class="bg-orange-50 hover:bg-orange-100 text-black font-bold py-3 rounded-xl text-sm">7</button>
                <button onclick="calcAppend('8')" class="bg-orange-50 hover:bg-orange-100 text-black font-bold py-3 rounded-xl text-sm">8</button>
                <button onclick="calcAppend('9')" class="bg-orange-50 hover:bg-orange-100 text-black font-bold py-3 rounded-xl text-sm">9</button>
                <button onclick="calcAppend('+')" class="bg-orange-100 hover:bg-orange-200 text-[#ff6b4a] font-extrabold py-3 rounded-xl text-sm row-span-2">+</button>

                <button onclick="calcAppend('4')" class="bg-orange-50 hover:bg-orange-100 text-black font-bold py-3 rounded-xl text-sm">4</button>
                <button onclick="calcAppend('5')" class="bg-orange-50 hover:bg-orange-100 text-black font-bold py-3 rounded-xl text-sm">5</button>
                <button onclick="calcAppend('6')" class="bg-orange-50 hover:bg-orange-100 text-black font-bold py-3 rounded-xl text-sm">6</button>

                <button onclick="calcAppend('1')" class="bg-orange-50 hover:bg-orange-100 text-black font-bold py-3 rounded-xl text-sm">1</button>
                <button onclick="calcAppend('2')" class="bg-orange-50 hover:bg-orange-100 text-black font-bold py-3 rounded-xl text-sm">2</button>
                <button onclick="calcAppend('3')" class="bg-orange-50 hover:bg-orange-100 text-black font-bold py-3 rounded-xl text-sm">3</button>
                <button onclick="calcCompute()" class="bg-[#ff6b4a] hover:bg-[#fa4b2a] text-white font-extrabold py-3 rounded-xl text-sm row-span-2">=</button>

                <button onclick="calcAppend('0')" class="bg-orange-50 hover:bg-orange-100 text-black font-bold py-3 rounded-xl text-sm col-span-2">0</button>
                <button onclick="calcAppend('.')" class="bg-orange-50 hover:bg-orange-100 text-black font-bold py-3 rounded-xl text-sm">.</button>
            </div>
        </div>
    </div>

    <!-- RECEIPT MODAL -->
    <div id="receiptModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4 transition-all duration-300">
        <div class="bg-white rounded-3xl shadow-2xl w-full max-w-sm overflow-hidden border border-orange-100 transform transition-all duration-300 scale-95 animate-fade-in">
            <div class="p-6 text-center font-mono text-black">
                <h3 class="font-bold text-lg tracking-wider text-black">PANNAKODA</h3>
                <p class="text-xs text-black mt-0.5 font-medium">Official Receipt</p>
                <p class="text-xs text-black mt-2">OR # : <span id="modal-or" class="font-bold"></span></p>
                <p class="text-xs text-black font-medium" id="modal-date"></p>

                <div class="border-t border-dashed border-orange-300 my-4"></div>

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

                <div class="border-t border-dashed border-orange-300 my-4"></div>

                <div class="space-y-1 text-xs text-left text-black">
                    <div class="flex justify-between font-bold">
                        <span>TOTAL AMOUNT:</span>
                        <span id="modal-grand-total" class="text-[#ff6b4a]"></span>
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

            <div class="bg-orange-50/50 p-4 border-t border-orange-100 flex gap-3">
                <button onclick="closeReceiptModal()" class="w-full bg-[#ff6b4a] hover:bg-[#fa4b2a] text-white py-2.5 rounded-2xl text-xs font-extrabold uppercase transition-all duration-200 shadow-md">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        // FEATURE 1: Philippine Time Live Clock Updater
        function updatePHClock() {
            const options = { timeZone: 'Asia/Manila', hour12: true, hour: 'numeric', minute: '2-digit', second: '2-digit', year: 'numeric', month: 'short', day: 'numeric' };
            const formatter = new Intl.DateTimeFormat([], options);
            const clockEl = document.getElementById('ph-clock');
            if(clockEl) {
                clockEl.innerText = formatter.format(new Date());
            }
        }
        setInterval(updatePHClock, 1000);
        updatePHClock();

        // FEATURE 2: Quick Calculator Script
        function openCalcModal() { document.getElementById('calcModal').classList.remove('hidden'); }
        function closeCalcModal() { document.getElementById('calcModal').classList.add('hidden'); }
        function calcClear() { document.getElementById('calc-screen').value = '0'; }
        function calcAppend(val) {
            let screen = document.getElementById('calc-screen');
            if(screen.value === '0' && val !== '.') { screen.value = val; }
            else { screen.value += val; }
        }
        function calcCompute() {
            let screen = document.getElementById('calc-screen');
            try { screen.value = eval(screen.value); } catch(e) { screen.value = 'Error'; }
        }

        // Toggle Analytics Tables Function
        function switchIncomeTable(type) {
            const types = ['daily', 'weekly', 'monthly', 'yearly'];
            types.forEach(t => {
                const pane = document.getElementById(`table-container-${t}`);
                const btn = document.getElementById(`tab-btn-${t}`);
                if (t === type) {
                    pane.classList.remove('hidden');
                    btn.className = 'px-4 py-2 rounded-xl text-xs font-extrabold tracking-wider uppercase bg-[#ff6b4a] text-white transition-all shadow-sm';
                } else {
                    pane.classList.add('hidden');
                    btn.className = 'px-4 py-2 rounded-xl text-xs font-extrabold tracking-wider uppercase text-black hover:text-[#ff6b4a] transition-all';
                }
            });
        }

        // PURCHASE TABLE PAGINATION & ROWS PER PAGE LOGIC
        let currentPage = 1;
        let rowsPerPage = 20; // Default set to 20 rows as requested

        function changeRowsPerPage() {
            const select = document.getElementById('rowsPerPageSelect');
            const val = select.value;
            if (val === 'all') {
                rowsPerPage = 'all';
            } else {
                rowsPerPage = parseInt(val);
            }
            currentPage = 1;
            renderTablePagination();
        }

        function renderTablePagination() {
            const tbody = document.getElementById('purchaseLogsTbody');
            if (!tbody) return;
            const rows = Array.from(tbody.querySelectorAll('.log-row'));
            const totalRows = rows.length;
            const noRecordsRow = document.getElementById('no-records-row');

            if (totalRows === 0) {
                document.getElementById('tableInfo').innerText = "Showing 0 to 0 of 0 entries";
                document.getElementById('paginationButtons').innerHTML = '';
                return;
            }

            let totalPages = 1;
            let startIndex = 0;
            let endIndex = totalRows;

            if (rowsPerPage !== 'all') {
                totalPages = Math.ceil(totalRows / rowsPerPage);
                if (currentPage > totalPages) currentPage = totalPages;
                if (currentPage < 1) currentPage = 1;
                startIndex = (currentPage - 1) * rowsPerPage;
                endIndex = Math.min(startIndex + rowsPerPage, totalRows);
            }

            // Show/Hide rows
            rows.forEach((row, index) => {
                if (rowsPerPage === 'all' || (index >= startIndex && index < endIndex)) {
                    row.style.display = '';
                } else {
                    row.style.display = 'none';
                }
            });

            // Update Info text
            const displayStart = totalRows > 0 ? startIndex + 1 : 0;
            document.getElementById('tableInfo').innerText = `Showing ${displayStart} to ${endIndex} of ${totalRows} entries`;

            // Render Pagination Buttons (< 1 2 >)
            const paginationContainer = document.getElementById('paginationButtons');
            let buttonsHTML = '';

            if (rowsPerPage !== 'all' && totalPages > 1) {
                // Previous Button
                const prevDisabled = currentPage === 1 ? 'opacity-40 cursor-not-allowed' : 'hover:bg-orange-100';
                buttonsHTML += `<button onclick="goToPage(${currentPage - 1})" class="px-3 py-1.5 rounded-xl border border-orange-200 text-xs font-extrabold text-black ${prevDisabled}"><i class="fa-solid fa-chevron-left"></i></button>`;

                // Page Number Buttons
                for (let i = 1; i <= totalPages; i++) {
                    if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                        const activeClass = i === currentPage ? 'bg-[#ff6b4a] text-white shadow-md' : 'bg-white hover:bg-orange-100 text-black border border-orange-200';
                        buttonsHTML += `<button onclick="goToPage(${i})" class="px-3 py-1.5 rounded-xl text-xs font-extrabold ${activeClass}">${i}</button>`;
                    } else if (i === currentPage - 2 || i === currentPage + 2) {
                        buttonsHTML += `<span class="px-2 text-xs font-bold text-black">...</span>`;
                    }
                }

                // Next Button
                const nextDisabled = currentPage === totalPages ? 'opacity-40 cursor-not-allowed' : 'hover:bg-orange-100';
                buttonsHTML += `<button onclick="goToPage(${currentPage + 1})" class="px-3 py-1.5 rounded-xl border border-orange-200 text-xs font-extrabold text-black ${nextDisabled}"><i class="fa-solid fa-chevron-right"></i></button>`;
            }

            paginationContainer.innerHTML = buttonsHTML;
        }

        function goToPage(page) {
            currentPage = page;
            renderTablePagination();
        }

        document.addEventListener("DOMContentLoaded", function() {
            // Initialize pagination table on load
            renderTablePermissions = document.getElementById('rowsPerPageSelect');
            if(renderTablePermissions) {
                changeRowsPerPage();
            }
        });

        // FEATURE 8: Quick CSV Export Trigger
        function triggerQuickExport() {
            Swal.fire({
                title: 'Export Sales Report?',
                text: 'Download active earnings report as CSV spreadsheet.',
                icon: 'question',
                showCancelButton: true,
                confirmButtonColor: '#ff6b4a',
                confirmButtonText: 'Download CSV'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = '?tab=history&export=csv';
                }
            });
        }

        function switchTab(tabName) {
            window.location.href = `?tab=${tabName}`;
        }

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
                            gradient.addColorStop(0, 'rgba(255, 107, 74, 0.3)');
                            gradient.addColorStop(1, 'rgba(255, 107, 74, 0.0)');
                            return gradient;
                        },
                        borderColor: '#ff6b4a',                     
                        borderWidth: 3,
                        pointBackgroundColor: '#ff6b4a',            
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
                        y: { beginAtZero: true, ticks: { color: '#000000', font: { size: 11, weight: '600' }, callback: function(value) { return '₱' + value.toLocaleString(); } }, grid: { color: '#fff1ec' } }
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
                        borderColor: '#ff6b4a',
                        backgroundColor: (context) => {
                            const chart = context.chart;
                            const {ctx, chartArea} = chart;
                            if (!chartArea) return null;
                            const gradient = ctx.createLinearGradient(0, chartArea.top, 0, chartArea.bottom);
                            gradient.addColorStop(0, 'rgba(255, 107, 74, 0.3)');
                            gradient.addColorStop(1, 'rgba(255, 107, 74, 0.0)');
                            return gradient;
                        },
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#ff6b4a',
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
                        y: { beginAtZero: true, grid: { color: '#fff1ec' }, ticks: { color: '#000000', font: { size: 11, weight: '600' }, callback: function(value) { return '₱' + value.toLocaleString(); } } },
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
                if(btnToday) btnToday.className = 'px-5 py-2.5 rounded-xl text-xs font-extrabold tracking-wider uppercase text-slate-300 hover:text-white transition-all duration-300 flex items-center gap-2';
            } else {
                if(btnToday) btnToday.className = 'px-5 py-2.5 rounded-xl text-xs font-extrabold tracking-wider uppercase shadow-lg bg-white text-black transition-all duration-300 flex items-center gap-2';
                if(btnMonth) btnMonth.className = 'px-5 py-2.5 rounded-xl text-xs font-extrabold tracking-wider uppercase text-slate-300 hover:text-white transition-all duration-300 flex items-center gap-2';
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