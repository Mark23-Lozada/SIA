<?php
session_start();

// 1. Siguraduhin muna na may naka-login na user[cite: 1]
if (!isset($_SESSION['role'])) {
    header("Location: ../../PAGES/login.php");
    exit();
}

// Anti-Back Button Cache Control[cite: 1]
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
include('../BACKEND/db_inventory.php');

// ---- IDAGDAG ITONG LINE SA BABA PARA MAWALA ANG ONLY_FULL_GROUP_BY ERROR ----[cite: 1]
$conn->query("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

// Kuhanin ang pangalan ng kasalukuyang file para sa sidebar highlight[cite: 1]
$current_page = basename($_SERVER['PHP_SELF']);

$filter_year = isset($_GET['filter_year']) && !empty($_GET['filter_year']) ? $conn->real_escape_string($_GET['filter_year']) : date('Y');
$filter_month = isset($_GET['filter_month']) && !empty($_GET['filter_month']) ? $conn->real_escape_string($_GET['filter_month']) : '';

// Base WHERE clauses para sa filter ng Search[cite: 1]
$where_clauses = [];
if (!empty($filter_year)) {
    $where_clauses[] = "YEAR(created_at) = '$filter_year'";
}
if (!empty($filter_month)) {
    $where_clauses[] = "MONTH(created_at) = '$filter_month'";
}

$where_sql = "";
if (count($where_clauses) > 0) {
    $where_sql = "WHERE " . implode(' AND ', $where_clauses);
}

// 1. QUERY: PER DAY[cite: 1]
$day_query = "SELECT DATE_FORMAT(created_at, '%M %d, %Y') as period_name, SUM(total_amount) as total_earnings, COUNT(id) as total_transactions FROM sales $where_sql GROUP BY DATE(created_at) ORDER BY DATE(created_at) DESC";
$day_result = $conn->query($day_query);

// 2. QUERY: PER WEEK[cite: 1]
$week_query = "SELECT CONCAT('Week ', WEEK(created_at, 1), ' (', DATE_FORMAT(created_at, '%b %Y'), ')') as period_name, SUM(total_amount) as total_earnings, COUNT(id) as total_transactions FROM sales $where_sql GROUP BY WEEK(created_at, 1), YEAR(created_at) ORDER BY YEAR(created_at) DESC, WEEK(created_at, 1) DESC";
$week_result = $conn->query($week_query);

// 3. QUERY: PER MONTH[cite: 1]
$month_query = "SELECT DATE_FORMAT(created_at, '%M %Y') as period_name, SUM(total_amount) as total_earnings, COUNT(id) as total_transactions FROM sales $where_sql GROUP BY MONTH(created_at), YEAR(created_at) ORDER BY YEAR(created_at) DESC, MONTH(created_at) DESC";
$month_result = $conn->query($month_query);

// 4. QUERY: PER YEAR[cite: 1]
$where_year_only = !empty($filter_year) ? "WHERE YEAR(created_at) = '$filter_year'" : "";
$year_query = "SELECT DATE_FORMAT(created_at, '%Y') as period_name, SUM(total_amount) as total_earnings, COUNT(id) as total_transactions FROM sales $where_year_only GROUP BY YEAR(created_at) ORDER BY YEAR(created_at) DESC";
$year_result = $conn->query($year_query);

// --- FETCH DATA FOR ARRAYS & OVERALL TOTALS ---[cite: 1]
$graph_weekly_data = [];
$wk_res = $conn->query($week_query);
if($wk_res) { while($row = $wk_res->fetch_assoc()) { $graph_weekly_data[] = $row; } }

$graph_monthly_data = [];
$mo_res = $conn->query($month_query);
if($mo_res) { while($row = $mo_res->fetch_assoc()) { $graph_monthly_data[] = $row; } }

$graph_yearly_data = [];
$yr_res = $conn->query($year_query);
if($yr_res) { while($row = $yr_res->fetch_assoc()) { $graph_yearly_data[] = $row; } }

// Calculate Overall Totals and Total Transactions for the summary cards
$overall_daily = 0;
$overall_daily_trans = 0;
$res_tot_day = $conn->query("SELECT SUM(total_amount) as total, COUNT(id) as trans_count FROM sales $where_sql");
if($res_tot_day && $row = $res_tot_day->fetch_assoc()) { 
    $overall_daily = $row['total'] ?? 0; 
    $overall_daily_trans = $row['trans_count'] ?? 0; 
}

$overall_weekly = 0;
$overall_weekly_trans = 0;
foreach($graph_weekly_data as $w) { 
    $overall_weekly += $w['total_earnings']; 
    $overall_weekly_trans += $w['total_transactions']; 
}

$overall_monthly = 0;
$overall_monthly_trans = 0;
foreach($graph_monthly_data as $m) { 
    $overall_monthly += $m['total_earnings']; 
    $overall_monthly_trans += $m['total_transactions']; 
}

$overall_yearly = 0;
$overall_yearly_trans = 0;
foreach($graph_yearly_data as $y) { 
    $overall_yearly += $y['total_earnings']; 
    $overall_yearly_trans += $y['total_transactions']; 
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple POS - Modern Dashboard</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
</head>

<body class="bg-slate-900/5 font-sans antialiased text-slate-600 flex min-h-screen relative overflow-x-hidden">
        
    <?php include '../../PAGES/sidebar.php'; ?>

    <div id="main-wrapper" class="flex-1 min-w-0 flex flex-col min-h-screen bg-slate-50 overflow-hidden">
        
        <main class="p-6 lg:p-8 flex-1 w-full mx-auto space-y-6 max-w-7xl">
            
            <!-- HEADER / WELCOME BANNER -->
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-gradient-to-r from-purple-900 via-purple-800 to-indigo-900 rounded-3xl p-6 lg:p-8 text-white shadow-xl shadow-purple-900/10">
                <div>
                    <span class="bg-white/10 text-purple-200 text-xs font-semibold px-3 py-1 rounded-full uppercase tracking-wider backdrop-blur-md">Analytics Overview</span>
                    <h1 class="text-2xl lg:text-3xl font-extrabold tracking-tight mt-2">Dashboard Analytics</h1>
                    <p class="text-purple-200/80 text-sm mt-1">Monitor real-time revenue streams, earnings, and system reports seamlessly.</p>
                </div>
            </div>

            <!-- OVERALL TOTAL SUMMARY BOXES WITH TRANSACTION COUNTS -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-5">
                
                <!-- TOTAL DAILY -->
                <div class="bg-white rounded-3xl p-5 border border-slate-100 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl shrink-0 shadow-inner">
                        <i class="bi bi-wallet2"></i>
                    </div>
                    <div>
                        <span class="text-slate-400 text-xs font-bold uppercase tracking-wider block">Total Daily Earnings</span>
                        <h4 class="text-slate-800 font-extrabold text-lg mt-0.5">₱<?php echo number_format($overall_daily, 2); ?></h4>
                        <span class="text-slate-400 text-[11px] font-medium flex items-center gap-1 mt-0.5">
                            <i class="bi bi-receipt"></i> <?php echo number_format($overall_daily_trans); ?> Transactions
                        </span>
                    </div>
                </div>

                <!-- TOTAL WEEKLY -->
                <div class="bg-white rounded-3xl p-5 border border-slate-100 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-green-50 text-green-600 flex items-center justify-center text-xl shrink-0 shadow-inner">
                        <i class="bi bi-graph-up-arrow"></i>
                    </div>
                    <div>
                        <span class="text-slate-400 text-xs font-bold uppercase tracking-wider block">Total Weekly Earnings</span>
                        <h4 class="text-slate-800 font-extrabold text-lg mt-0.5">₱<?php echo number_format($overall_weekly, 2); ?></h4>
                        <span class="text-slate-400 text-[11px] font-medium flex items-center gap-1 mt-0.5">
                            <i class="bi bi-receipt"></i> <?php echo number_format($overall_weekly_trans); ?> Transactions
                        </span>
                    </div>
                </div>

                <!-- TOTAL MONTHLY -->
                <div class="bg-white rounded-3xl p-5 border border-slate-100 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-xl shrink-0 shadow-inner">
                        <i class="bi bi-cash-stack"></i>
                    </div>
                    <div>
                        <span class="text-slate-400 text-xs font-bold uppercase tracking-wider block">Total Monthly Earnings</span>
                        <h4 class="text-slate-800 font-extrabold text-lg mt-0.5">₱<?php echo number_format($overall_monthly, 2); ?></h4>
                        <span class="text-slate-400 text-[11px] font-medium flex items-center gap-1 mt-0.5">
                            <i class="bi bi-receipt"></i> <?php echo number_format($overall_monthly_trans); ?> Transactions
                        </span>
                    </div>
                </div>

                <!-- TOTAL YEARLY -->
                <div class="bg-white rounded-3xl p-5 border border-slate-100 shadow-sm flex items-center gap-4">
                    <div class="w-12 h-12 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-xl shrink-0 shadow-inner">
                        <i class="bi bi-piggy-bank"></i>
                    </div>
                    <div>
                        <span class="text-slate-400 text-xs font-bold uppercase tracking-wider block">Total Yearly Earnings</span>
                        <h4 class="text-slate-800 font-extrabold text-lg mt-0.5">₱<?php echo number_format($overall_yearly, 2); ?></h4>
                        <span class="text-slate-400 text-[11px] font-medium flex items-center gap-1 mt-0.5">
                            <i class="bi bi-receipt"></i> <?php echo number_format($overall_yearly_trans); ?> Transactions
                        </span>
                    </div>
                </div>

            </div>

            <!-- FILTER SECTION -->
            <div class="bg-white rounded-3xl shadow-sm border border-slate-100/80 p-6 backdrop-blur-xl">
                <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-5 items-end">
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Filter Month</label>
                        <div class="relative">
                            <select name="filter_month" class="w-full bg-slate-50/80 border border-slate-200 rounded-2xl px-4 py-3 text-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-600 transition-all appearance-none">
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
                            <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                                <i class="bi bi-chevron-down text-xs"></i>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-400 uppercase tracking-wider mb-2">Filter Year</label>
                        <div class="relative">
                            <select name="filter_year" class="w-full bg-slate-50/80 border border-slate-200 rounded-2xl px-4 py-3 text-slate-700 text-sm focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-600 transition-all appearance-none">
                                <?php
                                $start_year = date('Y') - 5;
                                $end_year = date('Y');
                                for ($y = $end_year; $y >= $start_year; $y--) {
                                    $selected = ($filter_year == $y) ? 'selected' : '';
                                    echo "<option value='$y' $selected>$y</option>";
                                }
                                ?>
                            </select>
                            <div class="absolute right-4 top-1/2 -translate-y-1/2 pointer-events-none text-slate-400">
                                <i class="bi bi-chevron-down text-xs"></i>
                            </div>
                        </div>
                    </div>
                    <div class="flex gap-3">
                        <button type="submit" class="flex-1 bg-purple-600 hover:bg-purple-700 active:scale-[0.98] text-white font-semibold py-3 px-5 rounded-2xl flex items-center justify-center gap-2 transition-all shadow-lg shadow-purple-600/20 text-sm">
                            <i class="bi bi-funnel-fill"></i> Apply Filter
                        </button>
                        
                    </div>
                </form>
            </div>

            <!-- 4 BOXES SECTION (LISTS) -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">
                
                <!-- DAILY INCOME (BLUE) -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100/80 p-6 flex flex-col h-[420px] transition-all hover:shadow-xl hover:shadow-blue-500/5 hover:border-blue-200 relative overflow-hidden group">
                    <div class="absolute bottom-0 left-0 right-0 pointer-events-none opacity-20 group-hover:opacity-30 transition-opacity">
                        <svg viewBox="0 0 500 150" preserveAspectRatio="none" class="w-full h-28 text-blue-500 fill-current">
                            <path d="M0.00,49.98 C149.99,150.00 349.20,-49.98 500.00,49.98 L500.00,150.00 L0.00,150.00 Z"></path>
                        </svg>
                    </div>
                    <div class="flex items-center justify-between mb-4 z-10">
                        <h6 class="text-slate-800 font-bold text-sm tracking-wide">Daily Income</h6>
                        <span class="w-10 h-10 rounded-2xl bg-blue-50 text-blue-600 flex items-center justify-center text-base shadow-inner">
                            <i class="bi bi-calendar-event"></i>
                        </span>
                    </div>
                    <div class="overflow-y-auto flex-1 pr-1 space-y-2 z-10 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-blue-100 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-blue-200">
                        <?php if ($day_result && $day_result->num_rows > 0): ?>
                            <?php while($row = $day_result->fetch_assoc()): ?>
                                <div class="flex justify-between items-center p-3 rounded-2xl hover:bg-slate-50 transition-all border border-transparent hover:border-slate-100 gap-2">
                                    <div class="min-w-0">
                                        <span class="text-slate-700 font-semibold text-xs block truncate"><?php echo $row['period_name']; ?></span>
                                        <span class="text-slate-400 text-[11px] flex items-center gap-1 mt-0.5">
                                            <i class="bi bi-receipt"></i> <?php echo $row['total_transactions']; ?> Trans.
                                        </span>
                                    </div>
                                    <span class="font-extrabold text-blue-600 text-xs shrink-0 bg-blue-50/80 px-2.5 py-1 rounded-xl">₱<?php echo number_format($row['total_earnings'], 2); ?></span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="h-full flex flex-col items-center justify-center text-center py-6">
                                <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center text-slate-300 mb-2">
                                    <i class="bi bi-folder-x text-xl"></i>
                                </div>
                                <span class="text-slate-400 text-xs font-medium">No daily records</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- WEEKLY INCOME (GREEN) -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100/80 p-6 flex flex-col h-[420px] transition-all hover:shadow-xl hover:shadow-green-500/5 hover:border-green-200 relative overflow-hidden group">
                    <div class="absolute bottom-0 left-0 right-0 pointer-events-none opacity-20 group-hover:opacity-30 transition-opacity">
                        <svg viewBox="0 0 500 150" preserveAspectRatio="none" class="w-full h-28 text-green-500 fill-current">
                            <path d="M0.00,99.97 C149.99,150.00 349.20,-49.98 500.00,49.98 L500.00,150.00 L0.00,150.00 Z"></path>
                        </svg>
                    </div>
                    <div class="flex items-center justify-between mb-4 z-10">
                        <h6 class="text-slate-800 font-bold text-sm tracking-wide">Weekly Income</h6>
                        <span class="w-10 h-10 rounded-2xl bg-green-50 text-green-600 flex items-center justify-center text-base shadow-inner">
                            <i class="bi bi-calendar-range"></i>
                        </span>
                    </div>
                    <div class="overflow-y-auto flex-1 pr-1 space-y-2 z-10 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-green-100 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-green-200">
                        <?php if (!empty($graph_weekly_data)): ?>
                            <?php foreach($graph_weekly_data as $row): ?>
                                <div class="flex justify-between items-center p-3 rounded-2xl hover:bg-slate-50 transition-all border border-transparent hover:border-slate-100 gap-2">
                                    <div class="min-w-0">
                                        <span class="text-slate-700 font-semibold text-xs block truncate"><?php echo $row['period_name']; ?></span>
                                        <span class="text-slate-400 text-[11px] flex items-center gap-1 mt-0.5">
                                            <i class="bi bi-receipt"></i> <?php echo $row['total_transactions']; ?> Trans.
                                        </span>
                                    </div>
                                    <span class="font-extrabold text-green-600 text-xs shrink-0 bg-green-50/80 px-2.5 py-1 rounded-xl">₱<?php echo number_format($row['total_earnings'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="h-full flex flex-col items-center justify-center text-center py-6">
                                <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center text-slate-300 mb-2">
                                    <i class="bi bi-folder-x text-xl"></i>
                                </div>
                                <span class="text-slate-400 text-xs font-medium">No weekly records</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- MONTHLY INCOME (RED) -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100/80 p-6 flex flex-col h-[420px] transition-all hover:shadow-xl hover:shadow-rose-500/5 hover:border-rose-200 relative overflow-hidden group">
                    <div class="absolute bottom-0 left-0 right-0 pointer-events-none opacity-20 group-hover:opacity-30 transition-opacity">
                        <svg viewBox="0 0 500 150" preserveAspectRatio="none" class="w-full h-28 text-rose-500 fill-current">
                            <path d="M0.00,49.98 C200.00,150.00 290.00,-49.98 500.00,70.00 L500.00,150.00 L0.00,150.00 Z"></path>
                        </svg>
                    </div>
                    <div class="flex items-center justify-between mb-4 z-10">
                        <h6 class="text-slate-800 font-bold text-sm tracking-wide">Monthly Income</h6>
                        <span class="w-10 h-10 rounded-2xl bg-rose-50 text-rose-600 flex items-center justify-center text-base shadow-inner">
                            <i class="bi bi-calendar3"></i>
                        </span>
                    </div>
                    <div class="overflow-y-auto flex-1 pr-1 space-y-2 z-10 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-rose-100 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-rose-200">
                        <?php if (!empty($graph_monthly_data)): ?>
                            <?php foreach($graph_monthly_data as $row): ?>
                                <div class="flex justify-between items-center p-3 rounded-2xl hover:bg-slate-50 transition-all border border-transparent hover:border-slate-100 gap-2">
                                    <div class="min-w-0">
                                        <span class="text-slate-700 font-semibold text-xs block truncate"><?php echo $row['period_name']; ?></span>
                                        <span class="text-slate-400 text-[11px] flex items-center gap-1 mt-0.5">
                                            <i class="bi bi-receipt"></i> <?php echo $row['total_transactions']; ?> Trans.
                                        </span>
                                    </div>
                                    <span class="font-extrabold text-rose-600 text-xs shrink-0 bg-rose-50/80 px-2.5 py-1 rounded-xl">₱<?php echo number_format($row['total_earnings'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="h-full flex flex-col items-center justify-center text-center py-6">
                                <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center text-slate-300 mb-2">
                                    <i class="bi bi-folder-x text-xl"></i>
                                </div>
                                <span class="text-slate-400 text-xs font-medium">No monthly records</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- YEARLY INCOME (PURPLE) -->
                <div class="bg-white rounded-3xl shadow-sm border border-slate-100/80 p-6 flex flex-col h-[420px] transition-all hover:shadow-xl hover:shadow-purple-500/5 hover:border-purple-200 relative overflow-hidden group">
                    <div class="absolute bottom-0 left-0 right-0 pointer-events-none opacity-20 group-hover:opacity-30 transition-opacity">
                        <svg viewBox="0 0 500 150" preserveAspectRatio="none" class="w-full h-28 text-purple-500 fill-current">
                            <path d="M0.00,20.00 C150.00,150.00 350.00,-20.00 500.00,80.00 L500.00,150.00 L0.00,150.00 Z"></path>
                        </svg>
                    </div>
                    <div class="flex items-center justify-between mb-4 z-10">
                        <h6 class="text-slate-800 font-bold text-sm tracking-wide">Yearly Income</h6>
                        <span class="w-10 h-10 rounded-2xl bg-purple-50 text-purple-600 flex items-center justify-center text-base shadow-inner">
                            <i class="bi bi-calendar4-years"></i>
                        </span>
                    </div>
                    <div class="overflow-y-auto flex-1 pr-1 space-y-2 z-10 [&::-webkit-scrollbar]:w-1.5 [&::-webkit-scrollbar-track]:bg-transparent [&::-webkit-scrollbar-thumb]:bg-purple-100 [&::-webkit-scrollbar-thumb]:rounded-full hover:[&::-webkit-scrollbar-thumb]:bg-purple-200">
                        <?php if (!empty($graph_yearly_data)): ?>
                            <?php foreach($graph_yearly_data as $row): ?>
                                <div class="flex justify-between items-center p-3 rounded-2xl hover:bg-slate-50 transition-all border border-transparent hover:border-slate-100 gap-2">
                                    <div class="min-w-0">
                                        <span class="text-slate-700 font-semibold text-xs block truncate"><?php echo $row['period_name']; ?></span>
                                        <span class="text-slate-400 text-[11px] flex items-center gap-1 mt-0.5">
                                            <i class="bi bi-receipt"></i> <?php echo $row['total_transactions']; ?> Trans.
                                        </span>
                                    </div>
                                    <span class="font-extrabold text-purple-600 text-xs shrink-0 bg-purple-50/80 px-2.5 py-1 rounded-xl">₱<?php echo number_format($row['total_earnings'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="h-full flex flex-col items-center justify-center text-center py-6">
                                <div class="w-12 h-12 rounded-full bg-slate-50 flex items-center justify-center text-slate-300 mb-2">
                                    <i class="bi bi-folder-x text-xl"></i>
                                </div>
                                <span class="text-slate-400 text-xs font-medium">No yearly records</span>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

        </main>
    </div>

    <script>
        // Sidebar responsive toggle script
        const burgerToggle = document.getElementById('burgerToggle');
        const sidebar = document.getElementById('sidebar');

        if (burgerToggle) {
            burgerToggle.addEventListener('click', function(e) {
                e.stopPropagation();
                sidebar.classList.toggle('-translate-x-full');
            });
        }

        document.addEventListener('click', function(e) {
            if (window.innerWidth < 768) {
                if (!sidebar.contains(e.target) && e.target !== burgerToggle && !burgerToggle.contains(e.target)) {
                    sidebar.classList.add('-translate-x-full');
                }
            }
        });

        function handleResize() {
            if (window.innerWidth >= 768) {
                if (sidebar) sidebar.classList.remove('-translate-x-full');
            } else {
                if (sidebar) sidebar.classList.add('-translate-x-full');
            }
        }
        window.addEventListener('resize', handleResize);
        handleResize();

        // SweetAlert Logout Confirmation
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function(e) {
                e.preventDefault();

                Swal.fire({
                    title: 'Are you sure?',
                    text: "You will be logged out of your account.",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#9333ea',
                    cancelButtonColor: '#94a3b8',
                    confirmButtonText: 'Yes, Log out',
                    cancelButtonText: 'Cancel',
                    reverseButtons: true,
                    customClass: {
                        popup: 'rounded-3xl',
                        confirmButton: 'rounded-xl px-5 py-2.5 font-semibold',
                        cancelButton: 'rounded-xl px-5 py-2.5 font-semibold'
                    }
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = 'logout.php?role=admin';
                    }
                });
            });
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>