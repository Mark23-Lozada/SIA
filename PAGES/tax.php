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
    <!-- AOS Library CSS -->
    <link href="https://unpkg.com/aos@2.3.1/dist/aos.css" rel="stylesheet">
</head>
<body class="bg-gray-50 text-gray-800 antialiased font-sans">
    <div class="flex min-h-screen w-full">
        <div class="bg-white border-r border-gray-200 block">
            <?php include 'sidebar.php'; ?>
        </div>

        <div class="flex-1 min-w-0 bg-white min-h-screen flex flex-col">
       

            <div class="p-6 flex-1 overflow-y-auto space-y-6">
                <!-- CUSTOM BANNER HEADER WITH AOS -->
                <div class="w-full bg-amber-500  rounded-3xl p-8 text-white shadow-lg relative overflow-hidden" data-aos="fade-up">
                    <div class="relative z-10 space-y-2">
                        <span class="inline-block bg-white/20 backdrop-blur-md text-blue-100 text-xs font-bold px-3 py-1 rounded-full uppercase tracking-wider border border-white/20">
                            Fiscal & Tax Compliance
                        </span>
                        <h2 class="text-2xl md:text-3xl font-extrabold tracking-tight">
                            Annual Tax Computation & Allowable Deductions (<?php echo date('Y'); ?>)
                        </h2>
                        <p class="text-blue-100 text-sm">
                            Compute taxable income, evaluate allowable business expense deductions, and check estimated tax dues.
                        </p>
                    </div>
                </div>

                <!-- Tax Summary Boxes WITH AOS -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                    
                    <!-- Total Gross Revenue -->
                    <div class="bg-white/90 backdrop-blur-2xl p-6 lg:p-8 rounded-3xl shadow-xl shadow-amber-500/5 border border-blue-500/20 flex items-center justify-between relative overflow-hidden hover:border-blue-500 hover:bg-blue-50/10 group transition-all duration-300" data-aos="fade-up" data-aos-delay="100">
                        <div class="absolute left-0 top-0 bottom-0 w-2 bg-blue-600 group-hover:w-3 transition-all"></div>
                        <div>
                            <p class="text-xs font-extrabold text-blue-700 uppercase tracking-wider mb-1 flex items-center gap-1.5"><i class="bi bi-graph-up-arrow"></i>Gross Revenue</p>
                            <h4 class="text-xl font-bold text-amber-500 mt-2">₱<?php echo number_format($total_revenue, 2); ?></h4>
                            <p class="text-xs font-semibold text-gray-600 mt-1">Total sales this year</p>
                        </div>
                        <div class="w-14 h-14 bg-blue-500/10 rounded-2xl flex items-center justify-center text-blue-600 text-2xl shadow-inner border border-blue-500/20 transition-all duration-300 group-hover:scale-110 group-hover:bg-blue-600 group-hover:text-white">
                            <i class="bi bi-cash-stack"></i>
                        </div>
                    </div>

                    <!-- Allowable Deductions -->
                    <div class="bg-white/90 backdrop-blur-2xl p-6 lg:p-8 rounded-3xl shadow-xl shadow-emerald-950/5 border border-emerald-500/20 flex items-center justify-between relative overflow-hidden hover:border-emerald-500 hover:bg-emerald-50/10 group transition-all duration-300" data-aos="fade-up" data-aos-delay="200">
                        <div class="absolute left-0 top-0 bottom-0 w-2 bg-emerald-600 group-hover:w-3 transition-all"></div>
                        <div>
                            <p class="text-xs font-extrabold text-emerald-700 uppercase tracking-wider mb-1 flex items-center gap-1.5"><i class="bi bi-receipt"></i>Deductions</p>
                            <h4 class="text-xl font-bold text-amber-500 mt-2">₱<?php echo number_format($total_expenses, 2); ?></h4>
                            <p class="text-xs font-semibold text-gray-600 mt-1">Approved restock/refill</p>
                        </div>
                        <div class="w-14 h-14 bg-emerald-500/10 rounded-2xl flex items-center justify-center text-emerald-600 text-2xl shadow-inner border border-emerald-500/20 transition-all duration-300 group-hover:scale-110 group-hover:bg-emerald-600 group-hover:text-white">
                            <i class="bi bi-cart-dash"></i>
                        </div>
                    </div>

                    <!-- Taxable Income -->
                    <div class="bg-white/90 backdrop-blur-2xl p-6 lg:p-8 rounded-3xl shadow-xl shadow-purple-950/5 border border-purple-500/20 flex items-center justify-between relative overflow-hidden hover:border-purple-500 hover:bg-purple-50/10 group transition-all duration-300" data-aos="fade-up" data-aos-delay="300">
                        <div class="absolute left-0 top-0 bottom-0 w-2 bg-purple-600 group-hover:w-3 transition-all"></div>
                        <div>
                            <p class="text-xs font-extrabold text-purple-700 uppercase tracking-wider mb-1 flex items-center gap-1.5"><i class="bi bi-calculator"></i>Taxable Income</p>
                            <h4 class="text-xl font-bold text-amber-500 mt-2">₱<?php echo number_format($taxable_income, 2); ?></h4>
                            <p class="text-xs font-semibold text-gray-600 mt-1">Revenue minus expenses</p>
                        </div>
                        <div class="w-14 h-14 bg-purple-500/10 rounded-2xl flex items-center justify-center text-purple-600 text-2xl shadow-inner border border-purple-500/20 transition-all duration-300 group-hover:scale-110 group-hover:bg-purple-600 group-hover:text-white">
                            <i class="bi bi-calculator-fill"></i>
                        </div>
                    </div>

                    <!-- Estimated Tax Due -->
                    <div class="bg-white/90 backdrop-blur-2xl p-6 lg:p-8 rounded-3xl shadow-xl shadow-amber-950/5 border border-amber-500/20 flex items-center justify-between relative overflow-hidden hover:border-amber-500 hover:bg-amber-50/10 group transition-all duration-300" data-aos="fade-up" data-aos-delay="400">
                        <div class="absolute left-0 top-0 bottom-0 w-2 bg-amber-600 group-hover:w-3 transition-all"></div>
                        <div>
                            <p class="text-xs font-extrabold text-amber-700 uppercase tracking-wider mb-1 flex items-center gap-1.5"><i class="bi bi-shield-check"></i>Tax Due (8%)</p>
                            <h4 class="text-xl font-bold text-amber-500 mt-2">₱<?php echo number_format($estimated_tax, 2); ?></h4>
                            <p class="text-xs font-semibold text-gray-600 mt-1">Total tax payable</p>
                        </div>
                        <div class="w-14 h-14 bg-amber-500/10 rounded-2xl flex items-center justify-center text-amber-600 text-2xl shadow-inner border border-amber-500/20 transition-all duration-300 group-hover:scale-110 group-hover:bg-amber-600 group-hover:text-white">
                            <i class="bi bi-bank"></i>
                        </div>
                    </div>
                </div>

                <!-- Section Title WITH AOS -->
                <div class="mb-4 pt-2" data-aos="fade-up" data-aos-delay="500">
                    <h2 class="text-lg font-bold text-amber-500">Approved Deductible Expenses (Refill & Restock History)</h2>
                    <p class="text-xs text-gray-500">These transactions are automatically deducted from total revenue to legally lower taxable income.</p>
                </div>

                <!-- Deductions Table (NO AOS applied here) -->
                <div class="overflow-x-auto border border-gray-200 rounded-xl bg-white shadow-sm">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-blue-50 border-b border-gray-200 text-xs font-bold text-blue-900 uppercase">
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
                                        <td class="px-6 py-4">
                                            <span class="inline-block bg-blue-50 text-blue-800 text-xs font-semibold px-2.5 py-1 rounded-md border border-blue-200">
                                                <?php echo htmlspecialchars($row['department']); ?>
                                            </span>
                                        </td>
                                        <td class="px-6 py-4 text-right font-bold text-blue-800">-₱<?php echo number_format($row['amount'], 2); ?></td>
                                        <td class="px-6 py-4 text-center text-xs text-amber-500 font-medium">
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

    <!-- AOS Library JS -->
    <script src="https://unpkg.com/aos@2.3.1/dist/aos.js"></script>
    <script>
        AOS.init({
            duration: 800,
            once: true
        });
    </script>
</body>
</html>
<?php $conn->close(); ?>