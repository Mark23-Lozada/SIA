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

// Kunin ang filter type (daily, monthly, yearly) mula sa URL, default ay monthly
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'monthly';

// 1. Query para sa Inventory Refill / Restock
$refill_query = "SELECT request_id, title, requested_by, department, amount, status, created_at 
                 FROM budget_requests 
                 WHERE (title LIKE '%Restock%' OR title LIKE '%Refill%')";

// 2. Query para sa Salary Advances (na-approve o na-reject na)
$advance_query = "SELECT CONCAT('ADV-', sa.id) AS request_id, CONCAT('Salary Advance: ', sa.reason) AS title, 
                  e.full_name AS requested_by, e.department, sa.amount, sa.status, sa.created_at 
                  FROM salary_advances sa 
                  JOIN employees e ON sa.employee_id = e.id 
                  WHERE sa.status IN ('Approved', 'Rejected')";

// Paglalapat ng Filter sa Refill at Advance Queries
if ($filter == 'daily') {
    $refill_query .= " AND DATE(created_at) = CURDATE()";
    $advance_query .= " AND DATE(sa.created_at) = CURDATE()";
} elseif ($filter == 'monthly') {
    $refill_query .= " AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
    $advance_query .= " AND MONTH(sa.created_at) = MONTH(CURDATE()) AND YEAR(sa.created_at) = YEAR(CURDATE())";
} elseif ($filter == 'yearly') {
    $refill_query .= " AND YEAR(created_at) = YEAR(CURDATE())";
    $advance_query .= " AND YEAR(sa.created_at) = YEAR(CURDATE())";
}

$refill_query .= " ORDER BY created_at DESC";
$advance_query .= " ORDER BY created_at DESC";

$refill_result = $conn->query($refill_query);
$advance_result = $conn->query($advance_query);

// Summary Computations para sa Refill
$refill_summary_q = "SELECT COUNT(*) as total_count, SUM(amount) as total_amount FROM budget_requests WHERE (title LIKE '%Restock%' OR title LIKE '%Refill%')";
if ($filter == 'daily') $refill_summary_q .= " AND DATE(created_at) = CURDATE()";
elseif ($filter == 'monthly') $refill_summary_q .= " AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
elseif ($filter == 'yearly') $refill_summary_q .= " AND YEAR(created_at) = YEAR(CURDATE())";
$refill_sum_res = $conn->query($refill_summary_q)->fetch_assoc();

// Summary Computations para sa Salary Advance
$adv_summary_q = "SELECT COUNT(*) as total_count, SUM(amount) as total_amount FROM salary_advances WHERE status IN ('Approved', 'Rejected')";
if ($filter == 'daily') $adv_summary_q .= " AND DATE(created_at) = CURDATE()";
elseif ($filter == 'monthly') $adv_summary_q .= " AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
elseif ($filter == 'yearly') $adv_summary_q .= " AND YEAR(created_at) = YEAR(CURDATE())";
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
                <h1 class="text-xl font-bold text-orange-600">Transactions & Activity History</h1>
                <span class="text-xs font-semibold bg-orange-50 text-orange-700 px-3 py-1 rounded-full border border-orange-200">Activity Logs</span>
            </header>

            <div class="p-6 flex-1 overflow-y-auto space-y-8">
                <!-- Filter Buttons (Smooth Navigation) -->
                <div class="flex justify-between items-center flex-wrap gap-4">
                    <div class="flex gap-2">
                        <a href="transaction.php?filter=daily" class="px-4 py-2 rounded-lg font-semibold text-xs transition-all <?php echo ($filter == 'daily') ? 'bg-orange-600 text-white shadow-sm' : 'border border-gray-300 text-gray-600 hover:bg-gray-50'; ?>">Daily</a>
                        <a href="transaction.php?filter=monthly" class="px-4 py-2 rounded-lg font-semibold text-xs transition-all <?php echo ($filter == 'monthly') ? 'bg-orange-600 text-white shadow-sm' : 'border border-gray-300 text-gray-600 hover:bg-gray-50'; ?>">Monthly</a>
                        <a href="transaction.php?filter=yearly" class="px-4 py-2 rounded-lg font-semibold text-xs transition-all <?php echo ($filter == 'yearly') ? 'bg-orange-600 text-white shadow-sm' : 'border border-gray-300 text-gray-600 hover:bg-gray-50'; ?>">Yearly</a>
                    </div>
                </div>

                <!-- SECTION 1: INVENTORY REFILL / RESTOCK -->
                <div class="space-y-4">
                    <div class="flex justify-between items-center">
                        <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                            <span class="w-3 h-3 bg-orange-500 rounded-full"></span> Inventory Refill & Restock Transactions
                        </h2>
                        <span class="text-xs font-semibold text-gray-500">Total Expenses: <strong class="text-orange-600">₱<?php echo number_format($refill_sum_res['total_amount'] ?? 0, 2); ?></strong></span>
                    </div>

                    <div class="overflow-x-auto border border-gray-200 rounded-xl bg-white shadow-sm">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-orange-50 border-b border-gray-200 text-xs font-bold text-orange-700 uppercase">
                                    <th class="px-6 py-4">Request ID / Title</th>
                                    <th class="px-6 py-4">Requested By</th>
                                    <th class="px-6 py-4">Department</th>
                                    <th class="px-6 py-4 text-right">Amount</th>
                                    <th class="px-6 py-4 text-center">Status</th>
                                    <th class="px-6 py-4 text-center">Date & Time</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-200 text-sm">
                                <?php if ($refill_result && $refill_result->num_rows > 0): ?>
                                    <?php while ($row = $refill_result->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50 transition-colors">
                                            <td class="px-6 py-4">
                                                <div class="font-bold text-gray-900"><?php echo htmlspecialchars($row['request_id']); ?></div>
                                                <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['title']); ?></div>
                                            </td>
                                            <td class="px-6 py-4 text-gray-600 font-medium"><?php echo htmlspecialchars($row['requested_by']); ?></td>
                                            <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($row['department']); ?></td>
                                            <td class="px-6 py-4 text-right font-bold text-orange-600">₱<?php echo number_format($row['amount'], 2); ?></td>
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
                                    <tr><td colspan="6" class="text-center py-8 text-gray-400 italic">No inventory refill transactions found for this period.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- SECTION 2: SALARY ADVANCES -->
                <div class="space-y-4 pt-4">
                    <div class="flex justify-between items-center">
                        <h2 class="text-lg font-bold text-gray-800 flex items-center gap-2">
                            <span class="w-3 h-3 bg-cyan-500 rounded-full"></span> Salary Advance History
                        </h2>
                        <span class="text-xs font-semibold text-gray-500">Total Processed: <strong class="text-cyan-600">₱<?php echo number_format($adv_sum_res['total_amount'] ?? 0, 2); ?></strong></span>
                    </div>

                    <div class="overflow-x-auto border border-gray-200 rounded-xl bg-white shadow-sm">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-cyan-50 border-b border-gray-200 text-xs font-bold text-cyan-700 uppercase">
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
                                            <td class="px-6 py-4 text-right font-bold text-cyan-600">₱<?php echo number_format($row['amount'], 2); ?></td>
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
                                    <tr><td colspan="6" class="text-center py-8 text-gray-400 italic">No salary advance history found for this period.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>