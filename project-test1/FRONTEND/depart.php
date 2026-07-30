<?php
session_start();

// 1. Siguraduhin muna na may naka-login na user
if (!isset($_SESSION['role'])) {
    header("Location: ../../PAGES/login.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once __DIR__ . '/../BACKEND/db_inventory.php';

// Kunin ang filter para sa date (default ay buong kasalukuyang buwan kung walang pinili)
$start_date = isset($_GET['start_date']) ? $_GET['start_date'] : date('Y-m-01');
$end_date = isset($_GET['end_date']) ? $_GET['end_date'] : date('Y-m-t');

// ==========================================
// 1. KUNIN ANG TOTAL SALES (REVENUE)
// ==========================================
$sales_query = "SELECT SUM(total_amount) AS total_revenue, COUNT(id) AS total_transactions 
                FROM sales 
                WHERE DATE(created_at) BETWEEN ? AND ?";
$stmt = $conn->prepare($sales_query);
$stmt->bind_param("ss", $start_date, $end_date);
$stmt->execute();
$sales_result = $stmt->get_result()->fetch_assoc();
$total_revenue = $sales_result['total_revenue'] ?? 0;
$total_transactions = $sales_result['total_transactions'] ?? 0;
$stmt->close();

// ==========================================
// 2. KUNIN ANG COST OF GOODS SOLD (COGS) / EXPENSES 
// Note: Ayusin ang table/column names depende sa database structure mo para sa expenses o inventory cost
// ==========================================
// Halimbawa kung may table kang 'expenses':
$expenses_query = "SELECT SUM(amount) AS total_expenses FROM expenses WHERE DATE(expense_date) BETWEEN ? AND ?";
$stmt_exp = $conn->prepare($expenses_query);
$total_expenses = 0;
if ($stmt_exp) {
    $stmt_exp->bind_param("ss", $start_date, $end_date);
    $stmt_exp->execute();
    $exp_result = $stmt_exp->get_result()->fetch_assoc();
    $total_expenses = $exp_result['total_expenses'] ?? 0;
    $stmt_exp->close();
}

// 3. KWENTA NG NET PROFIT / LOSS
$net_profit = $total_revenue - $total_expenses;
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PannaKoda - Profit and Loss Statement</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-gray-50 text-gray-800 antialiased font-sans">

    <div class="flex h-screen w-full overflow-hidden">
        
        <!-- Sidebar Integration -->
        <div class="flex-shrink-0 h-full">
            <?php include '../../PAGES/sidebar.php'; ?>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col overflow-y-auto">
            
            <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <h1 class="text-xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
                    <i class="bi bi-file-earmark-bar-graph text-orange-500"></i>
                    Profit and Loss Statement
                </h1>
                <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2.5 py-1 rounded-md">Financial Reports</span>
            </header>

            <main class="p-6">
                
                <!-- Date Filter Form -->
                <div class="bg-white border border-gray-200 rounded-xl p-4 mb-6 shadow-sm">
                    <form method="GET" class="flex flex-wrap items-end gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Start Date</label>
                            <input type="date" name="start_date" value="<?php echo htmlspecialchars($start_date); ?>" 
                                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">End Date</label>
                            <input type="date" name="end_date" value="<?php echo htmlspecialchars($end_date); ?>" 
                                   class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-orange-500">
                        </div>
                        <div>
                            <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors flex items-center gap-2 shadow-sm">
                                <i class="bi bi-filter"></i> Filter Report
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
                    <!-- Total Revenue -->
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-500">Total Revenue (Sales)</span>
                            <span class="p-2 bg-green-50 text-green-600 rounded-lg"><i class="bi bi-graph-up-arrow text-lg"></i></span>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mt-2">₱<?php echo number_format($total_revenue, 2); ?></h2>
                        <p class="text-xs text-gray-400 mt-1"><?php echo $total_transactions; ?> total transactions</p>
                    </div>

                    <!-- Total Expenses -->
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-500">Total Expenses</span>
                            <span class="p-2 bg-red-50 text-red-600 rounded-lg"><i class="bi bi-graph-down-arrow text-lg"></i></span>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mt-2">₱<?php echo number_format($total_expenses, 2); ?></h2>
                        <p class="text-xs text-gray-400 mt-1">Operational costs & expenses</p>
                    </div>

                    <!-- Net Profit / Loss -->
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-500">Net Profit / (Loss)</span>
                            <span class="p-2 <?php echo $net_profit >= 0 ? 'bg-orange-50 text-orange-600' : 'bg-red-50 text-red-600'; ?> rounded-lg">
                                <i class="bi bi-wallet2 text-lg"></i>
                            </span>
                        </div>
                        <h2 class="text-2xl font-bold <?php echo $net_profit >= 0 ? 'text-green-600' : 'text-red-600'; ?> mt-2">
                            ₱<?php echo number_format($net_profit, 2); ?>
                        </h2>
                        <p class="text-xs text-gray-400 mt-1"><?php echo $net_profit >= 0 ? 'Net Income for period' : 'Net Loss for period'; ?></p>
                    </div>
                </div>

                <!-- Detailed Breakdown Table -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 font-bold text-gray-900">
                        Financial Statement Breakdown
                    </div>
                    <div class="p-6">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="py-3 px-4">Category</th>
                                    <th class="py-3 px-4 text-right">Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm">
                                <tr>
                                    <td class="py-3 px-4 font-medium text-gray-900">Gross Revenue</td>
                                    <td class="py-3 px-4 text-right text-green-600 font-semibold">₱<?php echo number_format($total_revenue, 2); ?></td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-4 font-medium text-gray-900">Total Expenses / COGS</td>
                                    <td class="py-3 px-4 text-right text-red-600 font-semibold">(₱<?php echo number_format($total_expenses, 2); ?>)</td>
                                </tr>
                                <tr class="bg-gray-50 font-bold">
                                    <td class="py-3 px-4 text-gray-900">Net Income / Loss</td>
                                    <td class="py-3 px-4 text-right <?php echo $net_profit >= 0 ? 'text-green-600' : 'text-red-600'; ?>">
                                        ₱<?php echo number_format($net_profit, 2); ?>
                                    </td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>

            </main>
        </div>
    </div>

</body>
</html>
<?php $conn->close(); ?>