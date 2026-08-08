<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

require_once __DIR__ . '../../project-test1/BACKEND/db_inventory.php';

// Fetch total revenue (from sales and sales_items) for the current year
$revenue_query = "SELECT SUM(sales_items.quantity * sales_items.price_at_sale) as total_revenue 
                  FROM sales_items 
                  JOIN sales ON sales_items.sale_id = sales.id 
                  WHERE YEAR(sales.created_at) = YEAR(CURDATE())";
$rev_res = $conn->query($revenue_query);
$total_revenue = ($rev_res && $row = $rev_res->fetch_assoc()) ? ($row['total_revenue'] ?? 0) : 0;

// Fetch total deducted expenses (from approved budget_requests / refill / restock) for this year
$expense_query = "SELECT SUM(amount) as total_expenses 
                  FROM budget_requests 
                  WHERE status = 'Fully Approved (Admin)' 
                  AND YEAR(created_at) = YEAR(CURDATE())";
$exp_res = $conn->query($expense_query);
$total_expenses = ($exp_res && $e_row = $exp_res->fetch_assoc()) ? ($e_row['total_expenses'] ?? 0) : 0;

// Calculate Taxable Income (Revenue - Expenses)
$taxable_income = max(0, $total_revenue - $total_expenses);

// Example tax rate
$tax_rate = 0.08; 
$estimated_tax = $taxable_income * $tax_rate;

// Fetch the list of deductions or expenses to display in the table
$deductions_query = "SELECT request_id, title, department, amount, created_at 
                     FROM budget_requests 
                     WHERE status = 'Fully Approved (Admin)' 
                     AND YEAR(created_at) = YEAR(CURDATE()) 
                     ORDER BY created_at DESC";
$deductions_result = $conn->query($deductions_query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tax Management & Compliance</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-gray-50 text-gray-800 antialiased font-sans">
    <div class="flex min-h-screen w-full">
        <div class="bg-white border-r border-gray-200 block">
            <?php include 'sidebar.php'; ?>
        </div>

        <div class="flex-1 min-w-0 bg-white min-h-screen flex flex-col">
            <header class="h-[60px] bg-white border-b border-gray-200 px-6 flex items-center justify-between shrink-0">
                <h1 class="text-xl font-bold text-purple-700">Tax Management & Computations</h1>
                <span class="text-xs font-semibold bg-purple-50 text-purple-700 px-3 py-1 rounded-full border border-purple-200">Active Fiscal Year <?php echo date('Y'); ?></span>
            </header>

            <div class="p-6 flex-1 overflow-y-auto space-y-6">
                <!-- CUSTOM BANNER HEADER -->
                <div class="w-full bg-gradient-to-r from-purple-900 via-indigo-900 to-violet-950 rounded-3xl p-8 text-white shadow-lg relative overflow-hidden">
                    <div class="relative z-10 space-y-2">
                        <span class="inline-block bg-white/20 backdrop-blur-md text-purple-100 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider border border-white/20">
                            Fiscal & Tax Compliance
                        </span>
                        <h2 class="text-2xl md:text-3xl font-extrabold tracking-tight">
                            Annual Tax Computation & Allowable Deductions (<?php echo date('Y'); ?>)
                        </h2>
                        <p class="text-purple-100 text-sm">
                            Compute taxable income, evaluate allowable business expense deductions, and check estimated tax dues.
                        </p>
                    </div>
                </div>

                <!-- Tax Summary Boxes -->
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm border-l-4 border-l-purple-600">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Total Gross Revenue</p>
                        <h3 class="text-2xl font-black text-gray-800">₱<?php echo number_format($total_revenue, 2); ?></h3>
                        <p class="text-xs text-gray-500 mt-2">Total sales revenue generated this year</p>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm border-l-4 border-l-indigo-600">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Allowable Deductions</p>
                        <h3 class="text-2xl font-black text-indigo-600">₱<?php echo number_format($total_expenses, 2); ?></h3>
                        <p class="text-xs text-gray-500 mt-2">From approved restock/refill requests</p>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm border-l-4 border-l-violet-600">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Taxable Income</p>
                        <h3 class="text-2xl font-black text-violet-600">₱<?php echo number_format($taxable_income, 2); ?></h3>
                        <p class="text-xs text-gray-500 mt-2">Revenue minus allowable expenses</p>
                    </div>
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm border-l-4 border-l-fuchsia-600">
                        <p class="text-xs font-bold text-gray-400 uppercase tracking-wider mb-1">Estimated Tax Due (8%)</p>
                        <h3 class="text-2xl font-black text-fuchsia-600">₱<?php echo number_format($estimated_tax, 2); ?></h3>
                        <p class="text-xs text-gray-500 mt-2">Estimated tax payable</p>
                    </div>
                </div>

                <!-- Section Title -->
                <div class="mb-4 pt-2">
                    <h2 class="text-lg font-bold text-gray-800">Approved Deductible Expenses (Refill & Restock History)</h2>
                    <p class="text-xs text-gray-500">These transactions are automatically deducted from total revenue to legally lower taxable income.</p>
                </div>

                <!-- Deductions Table -->
                <div class="overflow-x-auto border border-gray-200 rounded-xl bg-white shadow-sm">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-purple-50 border-b border-gray-200 text-xs font-bold text-purple-800 uppercase">
                                <th class="px-6 py-4">Request ID / Title</th>
                                <th class="px-6 py-4">Department</th>
                                <th class="px-6 py-4 text-right">Deducted Amount</th>
                                <th class="px-6 py-4 text-center">Date Approved</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-200 text-sm">
                            <?php if ($deductions_result && $deductions_result->num_rows > 0): ?>
                                <?php while ($row = $deductions_result->fetch_assoc()): ?>
                                    <tr class="hover:bg-gray-50 transition-colors">
                                        <td class="px-6 py-4">
                                            <div class="font-bold text-gray-900"><?php echo htmlspecialchars($row['request_id']); ?></div>
                                            <div class="text-xs text-gray-500"><?php echo htmlspecialchars($row['title']); ?></div>
                                        </td>
                                        <td class="px-6 py-4 text-gray-600"><?php echo htmlspecialchars($row['department']); ?></td>
                                        <td class="px-6 py-4 text-right font-bold text-purple-600">-₱<?php echo number_format($row['amount'], 2); ?></td>
                                        <td class="px-6 py-4 text-center text-xs text-gray-500 font-medium">
                                            <?php echo date('M d, Y', strtotime($row['created_at'])); ?>
                                        </td>
                                    </tr>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <tr>
                                    <td colspan="4" class="text-center py-12 text-gray-400">No approved restock or refill expenses recorded for this year.</td>
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