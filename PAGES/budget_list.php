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
    $report_title = "This Year's Budget & Philippine-Standard Allocation Report ";
} elseif ($view === 'month') {
    $date_condition = "MONTH(sales.created_at) = MONTH(CURDATE()) AND YEAR(sales.created_at) = YEAR(CURDATE())";
    $report_title = "This Month's Budget & Philippine-Standard Allocation Report ";
} else {
    $date_condition = "DATE(sales.created_at) = CURDATE()";
    $report_title = "Today's Budget & Philippine-Standard Allocation Report ";
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

    $active_baseline = ($view === 'year') ? $total_company_budget : (($view === 'month') ? $monthly_total : $daily_total);
    
    $alloc_needs = $active_baseline * 0.50;  
    $alloc_wants = $active_baseline * 0.30;  
    $alloc_savings = $active_baseline * 0.20; 

    $weekly_estimated = $monthly_total > 0 ? ($monthly_total / 4.33) : ($daily_total * 7);

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
    $chart_labels = []; $chart_data = [];

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
    <!-- Main Metrics Grid with 8px Left Border -->
<div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
    <div class="bg-white/95 backdrop-blur-2xl rounded-3xl p-5 border border-blue-500/20 border-l-[8px] border-l-blue-500 shadow-xl shadow-blue-950/5 flex items-center gap-4 transition-all duration-300 hover:shadow-2xl hover:border-blue-500 hover:bg-blue-600 group cursor-pointer -translate-y-0 hover:-translate-y-1.5">
        <div class="w-12 h-12 rounded-2xl bg-blue-500/15 text-blue-700 flex items-center justify-center text-xl shrink-0 shadow-inner border border-blue-500/35 transition-all duration-300 group-hover:bg-white group-hover:text-blue-600 group-hover:rotate-6">
            <i class="bi bi-wallet2"></i>
        </div>
        <div class="overflow-hidden">
            <span class="text-blue-800 group-hover:text-white text-xs font-bold uppercase tracking-wider block transition-colors duration-300">Total Company Budget (Net)</span>
            <h4 class="text-blue-700 group-hover:text-white font-extrabold text-2xl mt-0.5 transition-colors duration-300">₱<?php echo number_format($total_company_budget, 2); ?></h4>
            <span class="bg-blue-500/10 group-hover:bg-white/20 text-blue-800 group-hover:text-white px-2.5 py-1 rounded-xl text-[11px] font-extrabold flex items-center gap-1 mt-1.5 border border-blue-500/20 group-hover:border-white/30 shadow-sm w-fit transition-colors duration-300">
                Deducted: ₱<?php echo number_format($total_deducted, 2); ?>
            </span>
        </div>
    </div>

    <div class="bg-white/95 backdrop-blur-2xl rounded-3xl p-5 border border-emerald-500/20 border-l-[8px] border-l-emerald-500 shadow-xl shadow-emerald-950/5 flex items-center gap-4 transition-all duration-300 hover:shadow-2xl hover:border-emerald-500 hover:bg-emerald-600 group cursor-pointer -translate-y-0 hover:-translate-y-1.5">
        <div class="w-12 h-12 rounded-2xl bg-emerald-500/15 text-emerald-700 flex items-center justify-center text-xl shrink-0 shadow-inner border border-emerald-500/35 transition-all duration-300 group-hover:bg-white group-hover:text-emerald-600 group-hover:rotate-6">
            <i class="bi bi-calendar-day"></i>
        </div>
        <div class="overflow-hidden">
            <span class="text-emerald-700 group-hover:text-white text-xs font-bold uppercase tracking-wider block transition-colors duration-300">Daily Revenue (Today)</span>
            <h4 class="text-emerald-700 group-hover:text-white font-extrabold text-2xl mt-0.5 transition-colors duration-300">₱<?php echo number_format($daily_total, 2); ?></h4>
            <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                <span class="bg-emerald-500/10 group-hover:bg-white/20 text-emerald-800 group-hover:text-white px-2 py-0.5 rounded-lg text-[10px] font-extrabold border border-emerald-500/20 group-hover:border-white/30 transition-colors">Orders: <?php echo number_format($daily_orders); ?></span>
                <span class="bg-amber-500/10 group-hover:bg-white/20 text-amber-800 group-hover:text-white px-2 py-0.5 rounded-lg text-[10px] font-extrabold border border-amber-500/20 group-hover:border-white/30 transition-colors">Cust: <?php echo number_format($daily_cust); ?></span>
            </div>
        </div>
    </div>

    <div class="bg-white/95 backdrop-blur-2xl rounded-3xl p-5 border border-purple-500/20 border-l-[8px] border-l-purple-500 shadow-xl shadow-purple-950/5 flex items-center gap-4 transition-all duration-300 hover:shadow-2xl hover:border-purple-500 hover:bg-purple-700 group cursor-pointer -translate-y-0 hover:-translate-y-1.5">
        <div class="w-12 h-12 rounded-2xl bg-purple-500/15 text-purple-700 flex items-center justify-center text-xl shrink-0 shadow-inner border border-purple-500/35 transition-all duration-300 group-hover:bg-white group-hover:text-purple-700 group-hover:rotate-6">
            <i class="bi bi-calendar-month"></i>
        </div>
        <div class="overflow-hidden">
            <span class="text-purple-700 group-hover:text-white text-xs font-bold uppercase tracking-wider block transition-colors duration-300">Monthly Revenue</span>
            <h4 class="text-purple-700 group-hover:text-white font-extrabold text-2xl mt-0.5 transition-colors duration-300">₱<?php echo number_format($monthly_total, 2); ?></h4>
            <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                <span class="bg-purple-500/10 group-hover:bg-white/20 text-purple-800 group-hover:text-white px-2 py-0.5 rounded-lg text-[10px] font-extrabold border border-purple-500/20 group-hover:border-white/30 transition-colors">Orders: <?php echo number_format($monthly_orders); ?></span>
                <span class="bg-amber-500/10 group-hover:bg-white/20 text-amber-800 group-hover:text-white px-2 py-0.5 rounded-lg text-[10px] font-extrabold border border-amber-500/20 group-hover:border-white/30 transition-colors">Cust: <?php echo number_format($monthly_cust); ?></span>
            </div>
        </div>
    </div>

    <div class="bg-white/95 backdrop-blur-2xl rounded-3xl p-5 border border-amber-500/20 border-l-[8px] border-l-amber-500 shadow-xl shadow-amber-950/5 flex items-center gap-4 transition-all duration-300 hover:shadow-2xl hover:border-amber-500 hover:bg-amber-600 group cursor-pointer -translate-y-0 hover:-translate-y-1.5">
        <div class="w-12 h-12 rounded-2xl bg-amber-500/15 text-amber-700 flex items-center justify-center text-xl shrink-0 shadow-inner border border-amber-500/35 transition-all duration-300 group-hover:bg-white group-hover:text-amber-600 group-hover:rotate-6">
            <i class="bi bi-calendar3-range"></i>
        </div>
        <div class="overflow-hidden">
            <span class="text-amber-500 group-hover:text-white text-xs font-bold uppercase tracking-wider block transition-colors duration-300">Yearly Revenue</span>
            <h4 class="text-amber-700 group-hover:text-white font-extrabold text-2xl mt-0.5 transition-colors duration-300">₱<?php echo number_format($yearly_total, 2); ?></h4>
            <div class="flex items-center gap-1.5 mt-1.5 flex-wrap">
                <span class="bg-amber-500/10 group-hover:bg-white/20 text-amber-800 group-hover:text-white px-2 py-0.5 rounded-lg text-[10px] font-extrabold border border-amber-500/20 group-hover:border-white/30 transition-colors">Orders: <?php echo number_format($yearly_orders); ?></span>
                <span class="bg-blue-500/10 group-hover:bg-white/20 text-blue-800 group-hover:text-white px-2 py-0.5 rounded-lg text-[10px] font-extrabold border border-blue-500/20 group-hover:border-white/30 transition-colors">Cust: <?php echo number_format($yearly_cust); ?></span>
            </div>
        </div>
    </div>
</div>

<!-- Breakdown Section with 8px Left Border -->
<div class="bg-white/95 backdrop-blur-2xl p-6 rounded-3xl shadow-xl border border-slate-200/60 mb-6 transition-all duration-300 hover:shadow-2xl">
    <h5 class="text-md font-bold text-slate-900 mb-1 flex items-center"><i class="bi bi-pie-chart-fill text-[#D4A017] me-2"></i>Philippine Standard Split Breakdown (Based on Active Scope Balance)</h5>
    <p class="text-xs text-slate-500 mb-6">Allocated systematically based on standard market percentages (50% Operational Needs, 30% Flexible Operations/Wants, 20% Reserve Fund).</p>
    
    <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
        <div class="bg-white/95 backdrop-blur-2xl rounded-3xl p-5 border border-indigo-500/20 border-l-[8px] border-l-indigo-500 shadow-lg shadow-indigo-950/5 flex items-center gap-4 transition-all duration-300 hover:shadow-xl hover:border-indigo-500 hover:bg-indigo-600 group cursor-pointer hover:-translate-y-1.5">
            <div class="w-12 h-12 rounded-2xl bg-indigo-500/15 text-indigo-700 flex items-center justify-center text-xl shrink-0 shadow-inner border border-indigo-500/35 transition-all duration-300 group-hover:bg-white group-hover:text-indigo-600 group-hover:rotate-6">
                <i class="bi bi-clock-history"></i>
            </div>
            <div class="overflow-hidden">
                <span class="text-indigo-700 group-hover:text-white text-xs font-bold uppercase tracking-wider block transition-colors">Weekly Slice</span>
                <h4 class="text-indigo-700 group-hover:text-white font-extrabold text-xl mt-0.5 transition-colors">₱<?php echo number_format($weekly_estimated, 2); ?></h4>
                <p class="text-[10px] text-slate-500 group-hover:text-indigo-100 mt-1 transition-colors leading-tight">Baseline run rate.</p>
            </div>
        </div>

        <div class="bg-white/95 backdrop-blur-2xl rounded-3xl p-5 border border-emerald-500/20 border-l-[8px] border-l-emerald-500 shadow-lg shadow-emerald-950/5 flex items-center gap-4 transition-all duration-300 hover:shadow-xl hover:border-emerald-500 hover:bg-emerald-600 group cursor-pointer hover:-translate-y-1.5">
            <div class="w-12 h-12 rounded-2xl bg-emerald-500/15 text-emerald-700 flex items-center justify-center text-xl shrink-0 shadow-inner border border-emerald-500/35 transition-all duration-300 group-hover:bg-white group-hover:text-emerald-600 group-hover:rotate-6">
                <i class="bi bi-shield-check"></i>
            </div>
            <div class="overflow-hidden">
                <span class="text-emerald-700 group-hover:text-white text-xs font-bold uppercase tracking-wider block transition-colors">Needs (50%)</span>
                <h4 class="text-emerald-700 group-hover:text-white font-extrabold text-xl mt-0.5 transition-colors">₱<?php echo number_format($alloc_needs, 2); ?></h4>
                <p class="text-[10px] text-slate-500 group-hover:text-emerald-100 mt-1 transition-colors leading-tight">Utilities & stock.</p>
            </div>
        </div>

        <div class="bg-white/95 backdrop-blur-2xl rounded-3xl p-5 border border-purple-500/20 border-l-[8px] border-l-purple-500 shadow-lg shadow-purple-950/5 flex items-center gap-4 transition-all duration-300 hover:shadow-xl hover:border-purple-500 hover:bg-purple-700 group cursor-pointer hover:-translate-y-1.5">
            <div class="w-12 h-12 rounded-2xl bg-purple-500/15 text-purple-700 flex items-center justify-center text-xl shrink-0 shadow-inner border border-purple-500/35 transition-all duration-300 group-hover:bg-white group-hover:text-purple-700 group-hover:rotate-6">
                <i class="bi bi-graph-up-arrow"></i>
            </div>
            <div class="overflow-hidden">
                <span class="text-purple-700 group-hover:text-white text-xs font-bold uppercase tracking-wider block transition-colors">Wants (30%)</span>
                <h4 class="text-purple-700 group-hover:text-white font-extrabold text-xl mt-0.5 transition-colors">₱<?php echo number_format($alloc_wants, 2); ?></h4>
                <p class="text-[10px] text-slate-500 group-hover:text-purple-100 mt-1 transition-colors leading-tight">Marketing & growth.</p>
            </div>
        </div>

        <div class="bg-white/95 backdrop-blur-2xl rounded-3xl p-5 border border-amber-500/20 border-l-[8px] border-l-amber-500 shadow-lg shadow-amber-950/5 flex items-center gap-4 transition-all duration-300 hover:shadow-xl hover:border-amber-500 hover:bg-amber-600 group cursor-pointer hover:-translate-y-1.5">
            <div class="w-12 h-12 rounded-2xl bg-amber-500/15 text-amber-700 flex items-center justify-center text-xl shrink-0 shadow-inner border border-amber-500/35 transition-all duration-300 group-hover:bg-white group-hover:text-amber-600 group-hover:rotate-6">
                <i class="bi bi-piggy-bank"></i>
            </div>
            <div class="overflow-hidden">
                <span class="text-amber-700 group-hover:text-white text-xs font-bold uppercase tracking-wider block transition-colors">Savings (20%)</span>
                <h4 class="text-amber-700 group-hover:text-white font-extrabold text-xl mt-0.5 transition-colors">₱<?php echo number_format($alloc_savings, 2); ?></h4>
                <p class="text-[10px] text-slate-500 group-hover:text-amber-100 mt-1 transition-colors leading-tight">Emergency fund.</p>
            </div>
        </div>
    </div>
</div>

    <div class="bg-white p-6 rounded-2xl shadow-sm border border-slate-100 mb-6 transition-all duration-300 hover:shadow-md">
        <h5 class="text-md font-bold text-slate-900 mb-4 flex items-center"><i class="bi bi-graph-up text-[#D4A017] me-2"></i>Budget Wave Performance Graph</h5>
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
<html lang="en" class="h-full bg-white">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Budget Management</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <!-- AOS Library CSS & JS -->
    <link href="../LIBRARIES/AOS/aos.css" rel="stylesheet">
    <script src="../LIBRARIES/AOS/AOS.js"></script>
    <style>
        @keyframes floatSlow {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-8px); }
        }
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 15px rgba(212, 160, 23, 0.15); }
            50% { box-shadow: 0 0 25px rgba(212, 160, 23, 0.35); }
        }
        .animate-float-1 { animation: floatSlow 4s ease-in-out infinite; }
        .feature-box-glow:hover {
            animation: pulseGlow 2s infinite;
        }
        .profile-banner-glow {
            transition: all 0.4s ease-in-out;
        }
        .profile-banner-glow:hover {
            box-shadow: 0 0 35px rgba(212, 160, 23, 0.45), inset 0 0 20px rgba(212, 160, 23, 0.15);
            border-color: rgba(212, 160, 23, 0.7);
            transform: translateY(-2px);
        }
    </style>
</head>
<body class="h-full flex overflow-hidden font-sans text-slate-800 antialiased bg-white">
   <div class="flex h-screen w-full overflow-hidden">
     <?php include 'sidebar.php'; ?>
    <div class="flex-1 flex flex-col min-w-0 overflow-hidden bg-white">
        <header class="bg-white border-b border-slate-100 h-16 flex items-center px-6 shrink-0 md:hidden">
            <button class="p-2 -ml-2 rounded-xl text-slate-600 hover:bg-slate-100 hover:text-[#D4A017] transition-colors" type="button" id="burgerToggle">
                <i class="bi bi-list text-2xl"></i>
            </button>
        </header>

        <div class="flex-1 overflow-y-auto p-6 md:p-8 bg-white">
            <div class="max-w-7xl mx-auto">
                <!-- Modern Top Hero Banner -->
                <div data-aos="fade-down" data-aos-duration="800" class="bg-amber-500 p-8 rounded-3xl shadow-xl text-white mb-8 border border-amber-500/40 relative overflow-hidden profile-banner-glow">
                    <div class="absolute -right-10 -bottom-10 w-40 h-40 bg-amber-500/5 rounded-full blur-2xl pointer-events-none"></div>
                    
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-6 relative z-10">
                        <div>
                            <span class="bg-amber-500/10 text-white text-xs font-extrabold px-3.5 py-1.5 rounded-full uppercase tracking-wider backdrop-blur-md border border-amber-500/30 shadow-sm mb-4 inline-block">
                                Analytics Overview
                            </span>
                            <h1 class="text-3xl font-extrabold tracking-tight text-white" id="report-title"><?php echo $report_title; ?></h1>
                            <p class="text-white text-sm mt-1">Monitor real-time revenue streams, earnings, and system reports seamlessly.</p>
                        </div>
                        
                        <!-- Live Philippine Time Clock Widget -->
                        <div class="flex items-center gap-2 bg-white px-4 py-2.5 rounded-2xl border border-amber-500/20 backdrop-blur-md self-start lg:self-auto animate-float-1 feature-box-glow hover: transition-colors">
                            <i class="bi bi-clock text-amber-500 text-lg"></i> 
                            <div>
                                <div class="text-amber-500 font-semibold text-xs" id="phTimeDisplay">Loading PH Time...</div>
                            </div>
                        </div>
                    </div>
                </div>

                <div data-aos="fade-up" data-aos-duration="800" data-aos-delay="100" class="flex flex-col md:flex-row md:items-center md:justify-end gap-4 mb-6">
                    <div class="inline-flex bg-amber-500/10 p-1.5 rounded-2xl border border-amber-500/30 shrink-0">
                        <button onclick="switchView('today')" id="btn-today" class="px-4 py-2 rounded-lg text-sm font-bold text-amber-500 hover:text-[#D4A017] transition-all duration-200 flex items-center gap-2">
                            <i class="bi bi-calendar-event"></i>Today
                        </button>
                        <button onclick="switchView('month')" id="btn-month" class="px-4 py-2 rounded-lg text-sm font-bold text-amber-500 hover:text-[#D4A017] transition-all duration-200 flex items-center gap-2">
                            <i class="bi bi-calendar-month"></i>Month
                        </button>
                        <button onclick="switchView('year')" id="btn-year" class="px-4 py-2 rounded-lg text-sm font-bold text-amber-500 hover:text-[#D4A017] transition-all duration-200 flex items-center gap-2">
                            <i class="bi bi-calendar-check"></i>Year
                        </button>
                    </div>
                </div>

                <div id="live-sales-container">
                    <div class="flex flex-col items-center justify-center py-20 bg-white rounded-2xl shadow-sm border border-slate-100">
                        <div class="animate-spin rounded-full h-8 w-8 border-2 border-[#D4A017] border-t-transparent mb-3"></div>
                        <p class="text-sm text-slate-400 font-medium">Loading budget data...</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
   </div>

    <script>
        // Real-time Philippine Time Clock Function
        function updatePhilippineTime() {
            const options = {
                timeZone: 'Asia/Manila',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            const formatter = new Intl.DateTimeFormat([], options);
            const timeString = formatter.format(new Date());
            const displayElem = document.getElementById('phTimeDisplay');
            if (displayElem) {
                displayElem.textContent = timeString;
            }
        }
        setInterval(updatePhilippineTime, 1000);
        updatePhilippineTime();

        const burger = document.getElementById('burgerToggle');
        if (burger) {
            burger.addEventListener('click', function() {
                const sb = document.getElementById('sidebar');
                if (sb) sb.classList.toggle('-translate-x-full');
            });
        }

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
            gradient.addColorStop(0, 'rgba(212, 160, 23, 0.35)');
            gradient.addColorStop(1, 'rgba(212, 160, 23, 0.0)');

            salesChartInstance = new Chart(ctx, {
                type: 'line', 
                data: {
                    labels: labels, 
                    datasets: [{
                        label: 'Revenue (₱)',
                        data: values,
                        borderColor: '#D4A017',
                        backgroundColor: gradient,
                        borderWidth: 3,
                        fill: true,
                        tension: 0.4,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#D4A017',
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
                    if (typeof AOS !== 'undefined') {
                        AOS.refresh();
                    }
                })
                .catch(error => console.error('Error fetching data:', error));
        }

        function switchView(viewType) {
            currentView = viewType;
            const btnToday = document.getElementById('btn-today');
            const btnMonth = document.getElementById('btn-month');
            const btnYear = document.getElementById('btn-year');
            
            [btnToday, btnMonth, btnYear].forEach(btn => {
                if(btn) btn.className = 'px-4 py-2 rounded-lg text-sm font-bold text-amber-500 hover:text-[#D4A017] transition-all flex items-center gap-2';
            });

            if(viewType === 'year') {
                if(btnYear) btnYear.className = 'px-4 py-2 rounded-lg text-sm font-bold shadow-md bg-[#D4A017] text-white transition-all flex items-center gap-2';
            } else if(viewType === 'month') {
                if(btnMonth) btnMonth.className = 'px-4 py-2 rounded-lg text-sm font-bold shadow-md bg-[#D4A017] text-white transition-all flex items-center gap-2';
            } else {
                if(btnToday) btnToday.className = 'px-4 py-2 rounded-lg text-sm font-bold shadow-md bg-[#D4A017] text-white transition-all flex items-center gap-2';
            }
            
            fetchSalesData();
        }

        document.addEventListener("DOMContentLoaded", function() {
            AOS.init({
                once: true,
                offset: 30,
                duration: 600,
                easing: 'ease-out-cubic',
            });

            switchView(currentView);
            setInterval(fetchSalesData, 5000);
        });
    </script>
</body>
</html>