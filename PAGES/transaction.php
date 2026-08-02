<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}
require_once __DIR__ . '../../project-test1/BACKEND/db_inventory.php';

// Kunin ang filter type (daily, monthly, yearly) mula sa URL, default ay monthly
$filter = isset($_GET['filter']) ? $_GET['filter'] : 'monthly';

// Kunin ang mga transaksyon galing sa budget_requests o sales na patungkol sa Restock/Refill
// Dito kinukuha natin ang mga naaprubahang restock requests bilang mga refill/restock transactions
$query = "SELECT request_id, title, requested_by, department, amount, status, created_at 
          FROM budget_requests 
          WHERE (title LIKE '%Restock%' OR title LIKE '%Refill%')";

if ($filter == 'daily') {
    $query .= " AND DATE(created_at) = CURDATE()";
} elseif ($filter == 'monthly') {
    $query .= " AND MONTH(created_at) = MONTH(CURDATE()) AND YEAR(created_at) = YEAR(CURDATE())";
} elseif ($filter == 'yearly') {
    $query .= " AND YEAR(created_at) = YEAR(CURDATE())";
}

$query .= " ORDER BY created_at DESC";
$result = $conn->query($query);

// Para sa mga Summary Box (Totals)
$total_transactions_query = "SELECT COUNT(*) as total_count, SUM(amount) as total_amount FROM budget_requests WHERE (title LIKE '%Restock%' OR title LIKE '%Refill%')";
$summary_res = $conn->query($total_transactions_query);
$summary_data = ($summary_res) ? $summary_res->fetch_assoc() : ['total_count' => 0, 'total_amount' => 0];

$total_count = $summary_data['total_count'] ?? 0;
$total_amount = $summary_data['total_amount'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Refill Transactions History</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
</head>
<body class="bg-gray-50 text-gray-800 antialiased font-sans">
    <div class="flex min-h-screen w-full">
        <div class="bg-white border-r border-gray-200 block">
            <?php include 'sidebar.php'; ?>
        </div>

        <div class="flex-1 min-w-0 bg-white min-h-screen flex flex-col">
            <header class="h-[60px] bg-white border-b border-gray-200 px-6 flex items-center justify-between shrink-0">
                <h1 class="text-xl font-bold text-orange-600">Refill & Restock Transactions</h1>
                <span class="text-xs font-semibold bg-orange-50 text-orange-700 px-3 py-1 rounded-full border border-orange-200">Activity Logs</span>
            </header>

            <div class="p-6 flex-1 overflow-y-auto">
                <!-- Summary Boxes -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-6">
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Total Refill Requests</p>
                            <h3 class="text-2xl font-black text-gray-800"><?php echo number_format($total_count); ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-orange-50 text-orange-600 rounded-xl flex items-center justify-center text-xl font-bold">
                            <i class="bi bi-receipt"></i>
                        </div>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm flex items-center justify-between">
                        <div>
                            <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Total Refill Expenses</p>
                            <h3 class="text-2xl font-black text-orange-600">₱<?php echo number_format($total_amount, 2); ?></h3>
                        </div>
                        <div class="w-12 h-12 bg-orange-50 text-orange-600 rounded-xl flex items-center justify-center text-xl font-bold">
                            <i class="bi bi-wallet2"></i>
                        </div>
                    </div>
                </div>

                <!-- Filter Buttons (Daily, Monthly, Yearly) -->
                <div class="flex justify-between items-center mb-6 flex-wrap gap-4">
                    <div class="flex gap-2">
                        <a href="transaction.php?filter=daily" class="px-4 py-2 rounded-lg font-semibold text-xs transition-all <?php echo ($filter == 'daily') ? 'bg-orange-600 text-white shadow-sm' : 'border border-gray-300 text-gray-600 hover:bg-gray-50'; ?>">
                            Daily
                        </a>
                        <a href="transaction.php?filter=monthly" class="px-4 py-2 rounded-lg font-semibold text-xs transition-all <?php echo ($filter == 'monthly') ? 'bg-orange-600 text-white shadow-sm' : 'border border-gray-300 text-gray-600 hover:bg-gray-50'; ?>">
                            Monthly
                        </a>
                        <a href="transaction.php?filter=yearly" class="px-4 py-2 rounded-lg font-semibold text-xs transition-all <?php echo ($filter == 'yearly') ? 'bg-orange-600 text-white shadow-sm' : 'border border-gray-300 text-gray-600 hover:bg-gray-50'; ?>">
                            Yearly
                        </a>
                    </div>
                </div>

                <!-- Transactions Table -->
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
                            <?php if ($result && $result->num_rows > 0): ?>
                                <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-bold text-gray-900"><?php echo htmlspecialchars($row['request_id']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['title']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 text-gray-600 font-medium"><?php echo htmlspecialchars($row['requested_by']); ?></td>
                                        <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($row['department']); ?></td>
                                        <td class="px-6 py-4 text-right font-bold text-orange-600">₱<?php echo number_format($row['amount'], 2); ?></td>
                                        <td class="px-6 py-4 text-center">
                                            <span class="px-3 py-1 text-xs font-bold rounded-full 
                                                <?php 
                                                    if (strpos($row['status'], 'Approved') !== false) echo 'bg-emerald-100 text-emerald-800';
                                                    elseif (strpos($row['status'], 'Reject') !== false) echo 'bg-red-100 text-red-800';
                                                    else echo 'bg-amber-100 text-amber-800';
                                                ?>">
                                                <?php echo htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-center text-xs text-gray-500 font-medium">
                                            <?php echo date('M d, Y h:i A', strtotime($row['created_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="6" class="text-center py-12 text-gray-400">No refill or restock transactions found for this filter.</td>
                                </tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</body>
</html>
<?php $conn->close(); ?>