<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$host = "localhost";
$user = "root"; 
$pass = ""; 
$dbname = "pos";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}

// Kunin ang filter type mula sa URL, default ay 'inventory'
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'inventory';

// 1. Query para sa Inventory Refill / Restock
$inventory_query = "SELECT request_id, title, requested_by, department, amount, status, created_at 
                    FROM budget_requests 
                    WHERE (title LIKE '%Restock%' OR title LIKE '%Refill%')";

// 2. Query para sa Promotion & Salary Adjustments
$promo_query = "SELECT request_id, title, requested_by, department, amount, status, created_at 
                FROM budget_requests 
                WHERE title LIKE '%Promotion & Salary Adjustment%'";

// 3. Query para sa Salary Advances
$advance_query = "SELECT CONCAT('ADV-', sa.id) AS request_id, CONCAT('Salary Advance: ', sa.reason) AS title, 
                  e.full_name AS requested_by, e.department, sa.amount, sa.status, sa.created_at 
                  FROM salary_advances sa 
                  JOIN employees e ON sa.employee_id = e.id 
                  WHERE sa.status IN ('Approved', 'Rejected')";

// Order by queries
$inventory_query .= " ORDER BY created_at DESC";
$promo_query .= " ORDER BY created_at DESC";
$advance_query .= " ORDER BY created_at DESC";

$inventory_result = $conn->query($inventory_query);
$promo_result = $conn->query($promo_query);
$advance_result = $conn->query($advance_query);

// Summary Computations para sa Inventory Refill
$refill_summary_q = "SELECT COUNT(*) as total_count, SUM(amount) as total_amount FROM budget_requests WHERE (title LIKE '%Restock%' OR title LIKE '%Refill%')";
$refill_sum_res = $conn->query($refill_summary_q)->fetch_assoc();

// Summary Computations para sa Promotions[cite: 1]
$promo_summary_q = "SELECT COUNT(*) as total_count, SUM(amount) as total_amount FROM budget_requests WHERE title LIKE '%Promotion & Salary Adjustment%'";
$promo_sum_res = $conn->query($promo_summary_q)->fetch_assoc();

// Summary Computations para sa Salary Advance
$adv_summary_q = "SELECT COUNT(*) as total_count, SUM(amount) as total_amount FROM salary_advances WHERE status IN ('Approved', 'Rejected')";
$adv_sum_res = $conn->query($adv_summary_q)->fetch_assoc();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Transactions & Activity History</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50 text-gray-800 antialiased font-sans">
    <div class="flex min-h-screen w-full">
        <div class="bg-white border-r border-gray-200 block shrink-0">
            <?php include 'sidebar.php'; ?>
        </div>

        <div class="flex-1 min-w-0 bg-white min-h-screen flex flex-col">
            <header class="h-[60px] bg-white border-b border-gray-200 px-6 flex items-center justify-between shrink-0">
                <h1 class="text-xl font-bold text-purple-700">Transactions & Activity History</h1>
                <span class="text-xs font-semibold bg-purple-50 text-purple-700 px-3 py-1 rounded-full border border-purple-200">Activity Logs</span>
            </header>

            <div class="p-6 flex-1 overflow-y-auto space-y-8">
                <!-- CUSTOM BANNER HEADER -->
                <div class="w-full bg-gradient-to-r from-purple-800 via-indigo-800 to-violet-900 rounded-3xl p-8 text-white shadow-lg relative overflow-hidden">
                    <div class="relative z-10 space-y-2">
                        <span class="inline-block bg-white/20 backdrop-blur-md text-white text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider border border-white/20">
                            Activity Monitoring
                        </span>
                        <h2 class="text-2xl md:text-3xl font-extrabold tracking-tight">
                            System Activity Logs & Transaction Tracking (<?php echo date('F d, Y'); ?>)
                        </h2>
                        <p class="text-purple-100 text-sm">
                            Track inventory restocks, promotion adjustments, and salary advance workflows in real-time.
                        </p>
                    </div>
                </div>

                <!-- Filter Buttons -->
                <div class="flex justify-between items-center flex-wrap gap-4">
                    <div class="flex gap-2">
                        <a href="transaction.php?filter=inventory" class="px-4 py-2 rounded-lg font-semibold text-xs transition-all <?php echo ($filter == 'inventory') ? 'bg-purple-600 text-white shadow-sm' : 'border border-gray-300 text-gray-600 hover:bg-gray-50'; ?>">Inventory</a>
                        <a href="transaction.php?filter=advance_cash" class="px-4 py-2 rounded-lg font-semibold text-xs transition-all <?php echo ($filter == 'advance_cash') ? 'bg-purple-600 text-white shadow-sm' : 'border border-gray-300 text-gray-600 hover:bg-gray-50'; ?>">Advance Cash</a>
                        <a href="transaction.php?filter=promotion" class="px-4 py-2 rounded-lg font-semibold text-xs transition-all <?php echo ($filter == 'promotion') ? 'bg-purple-600 text-white shadow-sm' : 'border border-gray-300 text-gray-600 hover:bg-gray-50'; ?>">Promotion</a>
                    </div>
                </div>

                <!-- SECTION 1: INVENTORY REFILL / RESTOCK -->
                <?php if ($filter == 'inventory'): ?>
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                            <span class="w-3 h-3 bg-purple-600 rounded-full"></span> Inventory Refill & Restock Transactions
                        </h2>
                        <span class="text-xs font-semibold text-gray-500">Total Expenses: <strong class="text-purple-700">₱<?php echo number_format($refill_sum_res['total_amount'] ?? 0, 2); ?></strong></span>
                    </div>

                    <div class="overflow-x-auto border border-gray-200 rounded-xl bg-white shadow-sm">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-purple-50 border-b border-gray-200 text-xs font-bold text-purple-800 uppercase">
                                    <th class="px-6 py-4">Request ID / Title</th>
                                    <th class="px-6 py-4">Requested By</th>
                                    <th class="px-6 py-4">Department</th>
                                    <th class="px-6 py-4 text-right">Amount</th>
                                    <th class="px-6 py-4 text-center">Status</th>
                                    <th class="px-6 py-4 text-center">Date & Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 text-sm">
                                <?php if ($inventory_result && $inventory_result->num_rows > 0): ?>
                                    <?php while ($row = $inventory_result->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4">
                                                <div class="font-bold text-gray-900"><?php echo htmlspecialchars($row['request_id'] ?? 'N/A'); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['title']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-gray-600 font-medium"><?php echo htmlspecialchars($row['requested_by']); ?></td>
                                            <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($row['department']); ?></td>
                                            <td class="px-6 py-4 text-right font-bold text-purple-700">₱<?php echo number_format($row['amount'], 2); ?></td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="px-3 py-1 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800">
                                                    <?php echo htmlspecialchars($row['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-center text-xs text-gray-500 font-medium">
                                                <?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center py-8 text-gray-400 italic">No inventory refill transactions found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- SECTION 2: PROMOTION & SALARY ADJUSTMENTS -->
                <?php if ($filter == 'promotion'): ?>
                <div class="space-y-4 pt-4">
                    <div class="flex justify-between items-center">
                        <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                            <span class="w-3 h-3 bg-purple-600 rounded-full"></span> Promotion & Salary Adjustments Records
                        </h2>
                        <span class="text-xs font-semibold text-gray-500">Total Salary Adjustments: <strong class="text-purple-700">₱<?php echo number_format($promo_sum_res['total_amount'] ?? 0, 2); ?></strong></span>
                    </div>

                    <div class="overflow-x-auto border border-gray-200 rounded-xl bg-white shadow-sm">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-purple-50 border-b border-gray-200 text-xs font-bold text-purple-800 uppercase">
                                    <th class="px-6 py-4">Reference / Details</th>
                                    <th class="px-6 py-4">Employee Name</th>
                                    <th class="px-6 py-4">Department</th>
                                    <th class="px-6 py-4 text-right">New Salary Amount</th>
                                    <th class="px-6 py-4 text-center">Status</th>
                                    <th class="px-6 py-4 text-center">Date & Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 text-sm">
                                <?php if ($promo_result && $promo_result->num_rows > 0): ?>
                                    <?php while ($row = $promo_result->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4">
                                                <div class="font-bold text-gray-900"><?php echo htmlspecialchars($row['request_id'] ?? 'PROMO'); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['title']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-gray-600 font-medium"><?php echo htmlspecialchars($row['requested_by'] ?? 'N/A'); ?></td>
                                            <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($row['department'] ?? 'N/A'); ?></td>
                                            <td class="px-6 py-4 text-right font-bold text-purple-700">₱<?php echo number_format($row['amount'], 2); ?></td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="px-3 py-1 text-xs font-bold rounded-full bg-emerald-100 text-emerald-800">
                                                    <?php echo htmlspecialchars($row['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-center text-xs text-gray-500 font-medium">
                                                <?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center py-8 text-gray-400 italic">No promotion or salary adjustment records found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

                <!-- SECTION 3: SALARY ADVANCES (ADVANCE CASH) -->
                <?php if ($filter == 'advance_cash'): ?>
                <div class="space-y-4 pt-4">
                    <div class="flex justify-between items-center">
                        <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                            <span class="w-3 h-3 bg-purple-600 rounded-full"></span> Advance Cash / Salary Advance History
                        </h2>
                        <span class="text-xs font-semibold text-gray-500">Total Processed: <strong class="text-purple-700">₱<?php echo number_format($adv_sum_res['total_amount'] ?? 0, 2); ?></strong></span>
                    </div>

                    <div class="overflow-x-auto border border-gray-200 rounded-xl bg-white shadow-sm">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-purple-50 border-b border-gray-200 text-xs font-bold text-purple-800 uppercase">
                                    <th class="px-6 py-4">Reference ID / Details</th>
                                    <th class="px-6 py-4">Employee Name</th>
                                    <th class="px-6 py-4">Department</th>
                                    <th class="px-6 py-4 text-right">Amount</th>
                                    <th class="px-6 py-4 text-center">Status</th>
                                    <th class="px-6 py-4 text-center">Date & Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 text-sm">
                                <?php if ($advance_result && $advance_result->num_rows > 0): ?>
                                    <?php while ($row = $advance_result->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4">
                                                <div class="font-bold text-gray-900"><?php echo htmlspecialchars($row['request_id']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['title']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-gray-600 font-medium"><?php echo htmlspecialchars($row['requested_by'] ?? 'N/A'); ?></td>
                                            <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($row['department'] ?? 'N/A'); ?></td>
                                            <td class="px-6 py-4 text-right font-bold text-purple-700">₱<?php echo number_format($row['amount'], 2); ?></td>
                                            <td class="px-6 py-4 text-center">
                                                <span class="px-3 py-1 text-xs font-bold rounded-full 
                                                    <?php echo ($row['status'] === 'Approved') ? 'bg-emerald-100 text-emerald-800' : 'bg-red-100 text-red-800'; ?>">
                                                    <?php echo htmlspecialchars($row['status']); ?>
                                                </span>
                                            </td>
                                            <td class="px-6 py-4 text-center text-xs text-gray-500 font-medium">
                                                <?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="6" class="text-center py-8 text-gray-400 italic">No advance cash history found.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php endif; ?>

            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>