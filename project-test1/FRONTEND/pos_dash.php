<?php
session_start();

// 1. Siguraduhin muna na may naka-login na user
if (!isset($_SESSION['role'])) {
    header("Location: ../../PAGES/login.php");
    exit();
}

// Anti-Back Button Cache Control
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");
include('../BACKEND/db_inventory.php');

// ---- IDAGDAG ITONG LINE SA BABA PARA MAWALA ANG ONLY_FULL_GROUP_BY ERROR ----
$conn->query("SET sql_mode=(SELECT REPLACE(@@sql_mode,'ONLY_FULL_GROUP_BY',''))");

// Kuhanin ang pangalan ng kasalukuyang file para sa sidebar highlight
$current_page = basename($_SERVER['PHP_SELF']);

$filter_year = isset($_GET['filter_year']) && !empty($_GET['filter_year']) ? $conn->real_escape_string($_GET['filter_year']) : date('Y');
$filter_month = isset($_GET['filter_month']) && !empty($_GET['filter_month']) ? $conn->real_escape_string($_GET['filter_month']) : '';

// Base WHERE clauses para sa filter ng Search
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

// 1. QUERY: PER DAY
$day_query = "SELECT DATE_FORMAT(created_at, '%M %d, %Y') as period_name, SUM(total_amount) as total_earnings, COUNT(id) as total_transactions FROM sales $where_sql GROUP BY DATE(created_at) ORDER BY DATE(created_at) DESC";
$day_result = $conn->query($day_query);

// 2. QUERY: PER WEEK
$week_query = "SELECT CONCAT('Week ', WEEK(created_at, 1), ' (', DATE_FORMAT(created_at, '%b %Y'), ')') as period_name, SUM(total_amount) as total_earnings, COUNT(id) as total_transactions FROM sales $where_sql GROUP BY WEEK(created_at, 1), YEAR(created_at) ORDER BY YEAR(created_at) DESC, WEEK(created_at, 1) DESC";
$week_result = $conn->query($week_query);

// 3. QUERY: PER MONTH
$month_query = "SELECT DATE_FORMAT(created_at, '%M %Y') as period_name, SUM(total_amount) as total_earnings, COUNT(id) as total_transactions FROM sales $where_sql GROUP BY MONTH(created_at), YEAR(created_at) ORDER BY YEAR(created_at) DESC, MONTH(created_at) DESC";
$month_result = $conn->query($month_query);

// 4. QUERY: PER YEAR
$where_year_only = !empty($filter_year) ? "WHERE YEAR(created_at) = '$filter_year'" : "";
$year_query = "SELECT DATE_FORMAT(created_at, '%Y') as period_name, SUM(total_amount) as total_earnings, COUNT(id) as total_transactions FROM sales $where_year_only GROUP BY YEAR(created_at) ORDER BY YEAR(created_at) DESC";
$year_result = $conn->query($year_query);

// --- FETCH DATA FOR GRAPH ARRAYS ---
// Kunin ang mga datos para sa Weekly, Monthly, Yearly graph data JSON conversion
$graph_weekly_data = [];
$wk_res = $conn->query($week_query);
if($wk_res) { while($row = $wk_res->fetch_assoc()) { $graph_weekly_data[] = $row; } }

$graph_monthly_data = [];
$mo_res = $conn->query($month_query);
if($mo_res) { while($row = $mo_res->fetch_assoc()) { $graph_monthly_data[] = $row; } }

$graph_yearly_data = [];
$yr_res = $conn->query($year_query);
if($yr_res) { while($row = $yr_res->fetch_assoc()) { $graph_yearly_data[] = $row; } }
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Simple POS - Dashboard</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
</head>

<body class="bg-slate-50 font-sans antialiased text-slate-600 flex min-h-screen relative overflow-x-hidden">
        
    <?php include '../../PAGES/sidebar.php'; ?>

    <div id="main-wrapper" class="flex-1 min-w-0 flex flex-col min-h-screen bg-white overflow-hidden">
        
        <main class="p-6 flex-1 w-full mx-auto space-y-6">
            
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5">
                <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Filter Month</label>
                        <select name="filter_month" class="w-full bg-slate-50 border border-gray-200 rounded-xl px-4 py-2.5 text-slate-700 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 transition-all">
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
                    </div>
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Filter Year</label>
                        <select name="filter_year" class="w-full bg-slate-50 border border-gray-200 rounded-xl px-4 py-2.5 text-slate-700 focus:outline-none focus:ring-2 focus:ring-purple-500/20 focus:border-purple-500 transition-all">
                            <?php
                            $start_year = date('Y') - 5;
                            $end_year = date('Y');
                            for ($y = $end_year; $y >= $start_year; $y--) {
                                $selected = ($filter_year == $y) ? 'selected' : '';
                                echo "<option value='$y' $selected>$y</option>";
                            }
                            ?>
                        </select>
                    </div>
                    <div class="flex gap-2">
                        <button type="submit" class="flex-1 bg-purple-600 hover:bg-purple-700 text-white font-medium py-2.5 px-4 rounded-xl flex items-center justify-center gap-2 transition-all shadow-md shadow-purple-500/10">
                            <i class="bi bi-funnel-fill"></i> Filter Income
                        </button>
                        <a href="dashboard.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-medium py-2.5 px-4 rounded-xl transition-all flex items-center justify-center">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <!-- 4 BOXES SECTION -->
            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">
                
                <!-- DAILY INCOME (BLUE) -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 flex flex-col h-[400px] transition-all hover:shadow-md hover:border-blue-200 relative overflow-hidden">
                    <div class="absolute bottom-0 left-0 right-0 pointer-events-none opacity-25">
                        <svg viewBox="0 0 500 150" preserveAspectRatio="none" class="w-full h-24 text-blue-500 fill-current">
                            <path d="M0.00,49.98 C149.99,150.00 349.20,-49.98 500.00,49.98 L500.00,150.00 L0.00,150.00 Z"></path>
                        </svg>
                    </div>
                    <div class="flex items-center justify-between mb-3 z-10">
                        <h6 class="text-slate-800 font-bold text-sm tracking-wide">Daily Income</h6>
                        <span class="w-8 h-8 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-sm">
                            <i class="bi bi-calendar-event"></i>
                        </span>
                    </div>
                    <div class="overflow-y-auto flex-1 pr-1 space-y-1 z-10 [&::-webkit-scrollbar]:w-1 [&::-webkit-scrollbar-track]:bg-slate-50 [&::-webkit-scrollbar-track]:rounded-lg [&::-webkit-scrollbar-thumb]:bg-blue-200 [&::-webkit-scrollbar-thumb]:rounded-lg hover:[&::-webkit-scrollbar-thumb]:bg-blue-300">
                        <?php if ($day_result && $day_result->num_rows > 0): ?>
                            <?php while($row = $day_result->fetch_assoc()): ?>
                                <div class="flex justify-between items-center py-3 border-b border-slate-50 last:border-0 gap-2">
                                    <div class="min-w-0">
                                        <span class="text-slate-700 font-medium text-sm block truncate"><?php echo $row['period_name']; ?></span>
                                        <small class="text-gray-400 text-xs flex items-center gap-1 mt-0.5">
                                            <i class="bi bi-receipt"></i> <?php echo $row['total_transactions']; ?> Trans.
                                        </small>
                                    </div>
                                    <span class="font-bold text-blue-600 text-sm shrink-0">₱<?php echo number_format($row['total_earnings'], 2); ?></span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="h-full flex flex-col items-center justify-center text-center py-6">
                                <i class="bi bi-folder-x text-3xl text-gray-300 mb-2"></i>
                                <small class="text-gray-400 text-xs">No daily records.</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- WEEKLY INCOME (GREEN) -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 flex flex-col h-[400px] transition-all hover:shadow-md hover:border-green-200 relative overflow-hidden">
                    <div class="absolute bottom-0 left-0 right-0 pointer-events-none opacity-25">
                        <svg viewBox="0 0 500 150" preserveAspectRatio="none" class="w-full h-24 text-green-500 fill-current">
                            <path d="M0.00,99.97 C149.99,150.00 349.20,-49.98 500.00,49.98 L500.00,150.00 L0.00,150.00 Z"></path>
                        </svg>
                    </div>
                    <div class="flex items-center justify-between mb-3 z-10">
                        <h6 class="text-slate-800 font-bold text-sm tracking-wide">Weekly Income</h6>
                        <span class="w-8 h-8 rounded-xl bg-green-50 text-green-600 flex items-center justify-center text-sm">
                            <i class="bi bi-calendar-range"></i>
                        </span>
                    </div>
                    <div class="overflow-y-auto flex-1 pr-1 space-y-1 z-10 [&::-webkit-scrollbar]:w-1 [&::-webkit-scrollbar-track]:bg-slate-50 [&::-webkit-scrollbar-track]:rounded-lg [&::-webkit-scrollbar-thumb]:bg-green-200 [&::-webkit-scrollbar-thumb]:rounded-lg hover:[&::-webkit-scrollbar-thumb]:bg-green-300">
                        <?php if (!empty($graph_weekly_data)): ?>
                            <?php foreach($graph_weekly_data as $row): ?>
                                <div class="flex justify-between items-center py-3 border-b border-slate-50 last:border-0 gap-2">
                                    <div class="min-w-0">
                                        <span class="text-slate-700 font-medium text-sm block truncate"><?php echo $row['period_name']; ?></span>
                                        <small class="text-gray-400 text-xs flex items-center gap-1 mt-0.5">
                                            <i class="bi bi-receipt"></i> <?php echo $row['total_transactions']; ?> Trans.
                                        </small>
                                    </div>
                                    <span class="font-bold text-green-600 text-sm shrink-0">₱<?php echo number_format($row['total_earnings'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="h-full flex flex-col items-center justify-center text-center py-6">
                                <i class="bi bi-folder-x text-3xl text-gray-300 mb-2"></i>
                                <small class="text-gray-400 text-xs">No weekly records.</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- MONTHLY INCOME (RED) -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 flex flex-col h-[400px] transition-all hover:shadow-md hover:border-red-200 relative overflow-hidden">
                    <div class="absolute bottom-0 left-0 right-0 pointer-events-none opacity-25">
                        <svg viewBox="0 0 500 150" preserveAspectRatio="none" class="w-full h-24 text-red-500 fill-current">
                            <path d="M0.00,49.98 C200.00,150.00 290.00,-49.98 500.00,70.00 L500.00,150.00 L0.00,150.00 Z"></path>
                        </svg>
                    </div>
                    <div class="flex items-center justify-between mb-3 z-10">
                        <h6 class="text-slate-800 font-bold text-sm tracking-wide">Monthly Income</h6>
                        <span class="w-8 h-8 rounded-xl bg-red-50 text-red-600 flex items-center justify-center text-sm">
                            <i class="bi bi-calendar3"></i>
                        </span>
                    </div>
                    <div class="overflow-y-auto flex-1 pr-1 space-y-1 z-10 [&::-webkit-scrollbar]:w-1 [&::-webkit-scrollbar-track]:bg-slate-50 [&::-webkit-scrollbar-track]:rounded-lg [&::-webkit-scrollbar-thumb]:bg-red-200 [&::-webkit-scrollbar-thumb]:rounded-lg hover:[&::-webkit-scrollbar-thumb]:bg-red-300">
                        <?php if (!empty($graph_monthly_data)): ?>
                            <?php foreach($graph_monthly_data as $row): ?>
                                <div class="flex justify-between items-center py-3 border-b border-slate-50 last:border-0 gap-2">
                                    <div class="min-w-0">
                                        <span class="text-slate-700 font-medium text-sm block truncate"><?php echo $row['period_name']; ?></span>
                                        <small class="text-gray-400 text-xs flex items-center gap-1 mt-0.5">
                                            <i class="bi bi-receipt"></i> <?php echo $row['total_transactions']; ?> Trans.
                                        </small>
                                    </div>
                                    <span class="font-bold text-red-600 text-sm shrink-0">₱<?php echo number_format($row['total_earnings'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="h-full flex flex-col items-center justify-center text-center py-6">
                                <i class="bi bi-folder-x text-3xl text-gray-300 mb-2"></i>
                                <small class="text-gray-400 text-xs">No monthly records.</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- YEARLY INCOME (PURPLE) -->
                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-5 flex flex-col h-[400px] transition-all hover:shadow-md hover:border-purple-200 relative overflow-hidden">
                    <div class="absolute bottom-0 left-0 right-0 pointer-events-none opacity-25">
                        <svg viewBox="0 0 500 150" preserveAspectRatio="none" class="w-full h-24 text-purple-500 fill-current">
                            <path d="M0.00,20.00 C150.00,150.00 350.00,-20.00 500.00,80.00 L500.00,150.00 L0.00,150.00 Z"></path>
                        </svg>
                    </div>
                    <div class="flex items-center justify-between mb-3 z-10">
                        <h6 class="text-slate-800 font-bold text-sm tracking-wide">Yearly Income</h6>
                        <span class="w-8 h-8 rounded-xl bg-purple-50 text-purple-600 flex items-center justify-center text-sm">
                            <i class="bi bi-calendar4-years"></i>
                        </span>
                    </div>
                    <div class="overflow-y-auto flex-1 pr-1 space-y-1 z-10 [&::-webkit-scrollbar]:w-1 [&::-webkit-scrollbar-track]:bg-slate-50 [&::-webkit-scrollbar-track]:rounded-lg [&::-webkit-scrollbar-thumb]:bg-purple-200 [&::-webkit-scrollbar-thumb]:rounded-lg hover:[&::-webkit-scrollbar-thumb]:bg-purple-300">
                        <?php if (!empty($graph_yearly_data)): ?>
                            <?php foreach($graph_yearly_data as $row): ?>
                                <div class="flex justify-between items-center py-3 border-b border-slate-50 last:border-0 gap-2">
                                    <div class="min-w-0">
                                        <span class="text-slate-700 font-medium text-sm block truncate"><?php echo $row['period_name']; ?></span>
                                        <small class="text-gray-400 text-xs flex items-center gap-1 mt-0.5">
                                            <i class="bi bi-receipt"></i> <?php echo $row['total_transactions']; ?> Trans.
                                        </small>
                                    </div>
                                    <span class="font-bold text-purple-600 text-sm shrink-0">₱<?php echo number_format($row['total_earnings'], 2); ?></span>
                                </div>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <div class="h-full flex flex-col items-center justify-center text-center py-6">
                                <i class="bi bi-folder-x text-3xl text-gray-300 mb-2"></i>
                                <small class="text-gray-400 text-xs">No yearly records.</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

            </div>

            <!-- MALAKING PURPLE WAVE GRAPH NA MAY SORT BUTTONS AT DYNAMIC VALUES -->
            <div class="bg-white rounded-2xl shadow-sm border border-slate-100 p-6 relative overflow-hidden">
                <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
                    <div>
                        <h6 class="text-slate-800 font-bold text-base tracking-wide flex items-center gap-2">
                            <i class="bi bi-graph-up-arrow text-purple-600"></i> Revenue Trend Analysis
                        </h6>
                        <p class="text-xs text-slate-400">Interactive wave performance overview based on records</p>
                    </div>
                    <!-- SORT BUTTONS -->
                    <div class="inline-flex bg-slate-100 p-1 rounded-xl text-xs font-medium">
                        <button onclick="switchGraph('weekly')" id="btn-weekly" class="px-4 py-2 rounded-lg transition-all text-slate-600 hover:text-purple-600">Weekly</button>
                        <button onclick="switchGraph('monthly')" id="btn-monthly" class="px-4 py-2 rounded-lg transition-all bg-white text-purple-600 shadow-sm font-semibold">Monthly</button>
                        <button onclick="switchGraph('yearly')" id="btn-yearly" class="px-4 py-2 rounded-lg transition-all text-slate-600 hover:text-purple-600">Yearly</button>
                    </div>
                </div>

                <div class="w-full h-72 relative">
                    <svg viewBox="0 0 1200 300" preserveAspectRatio="none" class="w-full h-full">
                        <defs>
                            <linearGradient id="purpleWaveGradient" x1="0%" y1="0%" x2="0%" y2="100%">
                                <stop offset="0%" stop-color="#9333ea" stop-opacity="0.35" />
                                <stop offset="100%" stop-color="#9333ea" stop-opacity="0.0" />
                            </linearGradient>
                        </defs>
                        <!-- Grid Lines -->
                        <line x1="0" y1="60" x2="1200" y2="60" stroke="#f1f5f9" stroke-width="1" />
                        <line x1="0" y1="120" x2="1200" y2="120" stroke="#f1f5f9" stroke-width="1" />
                        <line x1="0" y1="180" x2="1200" y2="180" stroke="#f1f5f9" stroke-width="1" />
                        <line x1="0" y1="240" x2="1200" y2="240" stroke="#f1f5f9" stroke-width="1" />

                        <!-- Wave Area & Line -->
                        <path id="wave-area" d="M0,180 C300,80 600,220 1200,100 L1200,300 L0,300 Z" fill="url(#purpleWaveGradient)"></path>
                        <path id="wave-line" d="M0,180 C300,80 600,220 1200,100" fill="none" stroke="#9333ea" stroke-width="3"></path>
                    </svg>

                    <!-- DYNAMIC NUMBERS / POINTS OVERLAY -->
                    <div id="graph-points-container" class="absolute inset-0 pointer-events-none flex justify-between items-center px-6">
                        <!-- Dynamic injected labels via JS -->
                    </div>
                </div>
                
                <!-- LABELS SA BABA (DATES / PERIODS) -->
                <div id="graph-labels-container" class="flex justify-between px-4 mt-2 text-xs font-semibold text-slate-400">
                    <!-- Dynamic bottom labels -->
                </div>
            </div>

        </main>
    </div>

    <script>
        // Data mula sa PHP papuntang Javascript
        const weeklyData = <?php echo json_encode($graph_weekly_data); ?>;
        const monthlyData = <?php echo json_encode($graph_monthly_data); ?>;
        const yearlyData = <?php echo json_encode($graph_yearly_data); ?>;

        function switchGraph(type) {
            const btnWeekly = document.getElementById('btn-weekly');
            const btnMonthly = document.getElementById('btn-monthly');
            const btnYearly = document.getElementById('btn-yearly');
            const waveArea = document.getElementById('wave-area');
            const waveLine = document.getElementById('wave-line');
            const pointsContainer = document.getElementById('graph-points-container');
            const labelsContainer = document.getElementById('graph-labels-container');

            // Reset buttons styling
            [btnWeekly, btnMonthly, btnYearly].forEach(btn => {
                btn.className = "px-4 py-2 rounded-lg transition-all text-slate-600 hover:text-purple-600";
            });

            let activeData = monthlyData;
            if (type === 'weekly') {
                btnWeekly.className = "px-4 py-2 rounded-lg transition-all bg-white text-purple-600 shadow-sm font-semibold";
                activeData = weeklyData;
                waveArea.setAttribute("d", "M0,120 C250,200 500,50 1200,150 L1200,300 L0,300 Z");
                waveLine.setAttribute("d", "M0,120 C250,200 500,50 1200,150");
            } else if (type === 'monthly') {
                btnMonthly.className = "px-4 py-2 rounded-lg transition-all bg-white text-purple-600 shadow-sm font-semibold";
                activeData = monthlyData;
                waveArea.setAttribute("d", "M0,180 C300,80 600,220 1200,100 L1200,300 L0,300 Z");
                waveLine.setAttribute("d", "M0,180 C300,80 600,220 1200,100");
            } else if (type === 'yearly') {
                btnYearly.className = "px-4 py-2 rounded-lg transition-all bg-white text-purple-600 shadow-sm font-semibold";
                activeData = yearlyData;
                waveArea.setAttribute("d", "M0,220 C400,50 800,180 1200,80 L1200,300 L0,300 Z");
                waveLine.setAttribute("d", "M0,220 C400,50 800,180 1200,80");
            }

            // I-render ang mga numero at label base sa piniling kategorya
            pointsContainer.innerHTML = '';
            labelsContainer.innerHTML = '';

            if (activeData.length > 0) {
                // Kunin ang pinakataas at pinakamababang halaga para sa tuldok o ilagay ang top items
                let limitDisplay = activeData.slice(0, 4); // Kunin hanggang 4 na highlights para sa points
                
                limitDisplay.forEach((item) => {
                    let formattedAmount = '₱' + Number(item.total_earnings).toLocaleString('en-US', {minimumFractionDigits: 2});
                    
                    // Number Badge sa ibabaw ng wave
                    let pointBadge = document.createElement('div');
                    pointBadge.className = "flex flex-col items-center bg-white/90 backdrop-blur-sm border border-purple-100 px-3 py-1.5 rounded-xl shadow-sm text-center";
                    pointBadge.innerHTML = `
                        <span class="text-xs font-bold text-purple-700">${formattedAmount}</span>
                        <span class="text-[10px] text-slate-400">${item.total_transactions} transactions</span>
                    `;
                    pointsContainer.appendChild(pointBadge);

                    // Label sa ilalim
                    let bottomLabel = document.createElement('span');
                    bottomLabel.className = "truncate text-center";
                    bottomLabel.innerText = item.period_name;
                    labelsContainer.appendChild(bottomLabel);
                });
            } else {
                pointsContainer.innerHTML = `<div class="w-full text-center text-xs text-slate-400">No data available for this view</div>`;
                labelsContainer.innerHTML = `<span>-</span><span>-</span>`;
            }
        }

        // Default load to monthly view
        document.addEventListener("DOMContentLoaded", function() {
            switchGraph('monthly');
        });

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
                    reverseButtons: true
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