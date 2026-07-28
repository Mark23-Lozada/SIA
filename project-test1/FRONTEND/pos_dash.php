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

// 4. QUERY: PER YEAR (Hindi isinasama ang Month filter para sa Yearly)
$where_year_only = !empty($filter_year) ? "WHERE YEAR(created_at) = '$filter_year'" : "";
$year_query = "SELECT DATE_FORMAT(created_at, '%Y') as period_name, SUM(total_amount) as total_earnings, COUNT(id) as total_transactions FROM sales $where_year_only GROUP BY YEAR(created_at) ORDER BY YEAR(created_at) DESC";
$year_result = $conn->query($year_query);
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

<body class="bg-orange-50/30 font-sans antialiased text-slate-600 flex min-h-screen relative overflow-x-hidden">
        
    <?php include '../../PAGES/sidebar.php'; ?>

    <div id="main-wrapper" class="flex-1 min-w-0 flex flex-col min-h-screen bg-white overflow-hidden">
        
        <main class="p-6 flex-1 w-full mx-auto space-y-6">
            
            <div class="bg-white rounded-2xl shadow-sm border border-orange-100 p-5">
                <form method="GET" action="" class="grid grid-cols-1 md:grid-cols-3 gap-4 items-end">
                    <div>
                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Filter Month</label>
                        <select name="filter_month" class="w-full bg-slate-50 border border-gray-200 rounded-xl px-4 py-2.5 text-slate-700 focus:outline-none focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 transition-all">
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
                        <select name="filter_year" class="w-full bg-slate-50 border border-gray-200 rounded-xl px-4 py-2.5 text-slate-700 focus:outline-none focus:ring-2 focus:ring-orange-500/20 focus:border-orange-500 transition-all">
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
                        <button type="submit" class="flex-1 bg-orange-500 hover:bg-orange-600 text-white font-medium py-2.5 px-4 rounded-xl flex items-center justify-center gap-2 transition-all shadow-md shadow-orange-500/10">
                            <i class="bi bi-funnel-fill"></i> Filter Income
                        </button>
                        <a href="dashboard.php" class="bg-slate-100 hover:bg-slate-200 text-slate-600 font-medium py-2.5 px-4 rounded-xl transition-all flex items-center justify-center">
                            Reset
                        </a>
                    </div>
                </form>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-4 gap-6">
                
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex flex-col h-[400px] transition-all hover:shadow-md hover:border-orange-100">
                    <div class="flex items-center justify-between mb-3">
                        <h6 class="text-slate-800 font-bold text-sm tracking-wide">Daily Income</h6>
                        <span class="w-8 h-8 rounded-xl bg-orange-50 text-orange-500 flex items-center justify-center text-sm">
                            <i class="bi bi-calendar-event"></i>
                        </span>
                    </div>
                    <div class="overflow-y-auto flex-1 pr-1 space-y-1 [&::-webkit-scrollbar]:w-1 [&::-webkit-scrollbar-track]:bg-slate-50 [&::-webkit-scrollbar-track]:rounded-lg [&::-webkit-scrollbar-thumb]:bg-orange-200 [&::-webkit-scrollbar-thumb]:rounded-lg hover:[&::-webkit-scrollbar-thumb]:bg-orange-300">
                        <?php if ($day_result && $day_result->num_rows > 0): ?>
                            <?php while($row = $day_result->fetch_assoc()): ?>
                                <div class="flex justify-between items-center py-3 border-b border-slate-50 last:border-0 gap-2">
                                    <div class="min-w-0">
                                        <span class="text-slate-700 font-medium text-sm block truncate"><?php echo $row['period_name']; ?></span>
                                        <small class="text-gray-400 text-xs flex items-center gap-1 mt-0.5">
                                            <i class="bi bi-receipt"></i> <?php echo $row['total_transactions']; ?> Trans.
                                        </small>
                                    </div>
                                    <span class="font-bold text-orange-500 text-sm shrink-0">₱<?php echo number_format($row['total_earnings'], 2); ?></span>
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

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex flex-col h-[400px] transition-all hover:shadow-md hover:border-orange-100">
                    <div class="flex items-center justify-between mb-3">
                        <h6 class="text-slate-800 font-bold text-sm tracking-wide">Weekly Income</h6>
                        <span class="w-8 h-8 rounded-xl bg-amber-50 text-amber-500 flex items-center justify-center text-sm">
                            <i class="bi bi-calendar-range"></i>
                        </span>
                    </div>
                    <div class="overflow-y-auto flex-1 pr-1 space-y-1 [&::-webkit-scrollbar]:w-1 [&::-webkit-scrollbar-track]:bg-slate-50 [&::-webkit-scrollbar-track]:rounded-lg [&::-webkit-scrollbar-thumb]:bg-orange-200 [&::-webkit-scrollbar-thumb]:rounded-lg hover:[&::-webkit-scrollbar-thumb]:bg-orange-300">
                        <?php if ($week_result && $week_result->num_rows > 0): ?>
                            <?php while($row = $week_result->fetch_assoc()): ?>
                                <div class="flex justify-between items-center py-3 border-b border-slate-50 last:border-0 gap-2">
                                    <div class="min-w-0">
                                        <span class="text-slate-700 font-medium text-sm block truncate"><?php echo $row['period_name']; ?></span>
                                        <small class="text-gray-400 text-xs flex items-center gap-1 mt-0.5">
                                            <i class="bi bi-receipt"></i> <?php echo $row['total_transactions']; ?> Trans.
                                        </small>
                                    </div>
                                    <span class="font-bold text-orange-500 text-sm shrink-0">₱<?php echo number_format($row['total_earnings'], 2); ?></span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="h-full flex flex-col items-center justify-center text-center py-6">
                                <i class="bi bi-folder-x text-3xl text-gray-300 mb-2"></i>
                                <small class="text-gray-400 text-xs">No weekly records.</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex flex-col h-[400px] transition-all hover:shadow-md hover:border-orange-100">
                    <div class="flex items-center justify-between mb-3">
                        <h6 class="text-slate-800 font-bold text-sm tracking-wide">Monthly Income</h6>
                        <span class="w-8 h-8 rounded-xl bg-orange-100/50 text-orange-600 flex items-center justify-center text-sm">
                            <i class="bi bi-calendar3"></i>
                        </span>
                    </div>
                    <div class="overflow-y-auto flex-1 pr-1 space-y-1 [&::-webkit-scrollbar]:w-1 [&::-webkit-scrollbar-track]:bg-slate-50 [&::-webkit-scrollbar-track]:rounded-lg [&::-webkit-scrollbar-thumb]:bg-orange-200 [&::-webkit-scrollbar-thumb]:rounded-lg hover:[&::-webkit-scrollbar-thumb]:bg-orange-300">
                        <?php if ($month_result && $month_result->num_rows > 0): ?>
                            <?php while($row = $month_result->fetch_assoc()): ?>
                                <div class="flex justify-between items-center py-3 border-b border-slate-50 last:border-0 gap-2">
                                    <div class="min-w-0">
                                        <span class="text-slate-700 font-medium text-sm block truncate"><?php echo $row['period_name']; ?></span>
                                        <small class="text-gray-400 text-xs flex items-center gap-1 mt-0.5">
                                            <i class="bi bi-receipt"></i> <?php echo $row['total_transactions']; ?> Trans.
                                        </small>
                                    </div>
                                    <span class="font-bold text-orange-500 text-sm shrink-0">₱<?php echo number_format($row['total_earnings'], 2); ?></span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="h-full flex flex-col items-center justify-center text-center py-6">
                                <i class="bi bi-folder-x text-3xl text-gray-300 mb-2"></i>
                                <small class="text-gray-400 text-xs">No monthly records.</small>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5 flex flex-col h-[400px] transition-all hover:shadow-md hover:border-orange-100">
                    <div class="flex items-center justify-between mb-3">
                        <h6 class="text-slate-800 font-bold text-sm tracking-wide">Yearly Income</h6>
                        <span class="w-8 h-8 rounded-xl bg-amber-100 text-amber-600 flex items-center justify-center text-sm">
                            <i class="bi bi-calendar4-years"></i>
                        </span>
                    </div>
                    <div class="overflow-y-auto flex-1 pr-1 space-y-1 [&::-webkit-scrollbar]:w-1 [&::-webkit-scrollbar-track]:bg-slate-50 [&::-webkit-scrollbar-track]:rounded-lg [&::-webkit-scrollbar-thumb]:bg-orange-200 [&::-webkit-scrollbar-thumb]:rounded-lg hover:[&::-webkit-scrollbar-thumb]:bg-orange-300">
                        <?php if ($year_result && $year_result->num_rows > 0): ?>
                            <?php while($row = $year_result->fetch_assoc()): ?>
                                <div class="flex justify-between items-center py-3 border-b border-slate-50 last:border-0 gap-2">
                                    <div class="min-w-0">
                                        <span class="text-slate-700 font-medium text-sm block truncate"><?php echo $row['period_name']; ?></span>
                                        <small class="text-gray-400 text-xs flex items-center gap-1 mt-0.5">
                                            <i class="bi bi-receipt"></i> <?php echo $row['total_transactions']; ?> Trans.
                                        </small>
                                    </div>
                                    <span class="font-bold text-orange-500 text-sm shrink-0">₱<?php echo number_format($row['total_earnings'], 2); ?></span>
                                </div>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <div class="h-full flex flex-col items-center justify-center text-center py-6">
                                <i class="bi bi-folder-x text-3xl text-gray-300 mb-2"></i>
                                <small class="text-gray-400 text-xs">No yearly records.</small>
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
                    confirmButtonColor: '#f97316',
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