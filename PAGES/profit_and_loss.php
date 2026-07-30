<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PannaKoda - Profit and Loss Statement</title>
    <script src="../LIBRARIES//tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-gray-50 text-gray-800 antialiased font-sans">

    <div class="flex h-screen w-full overflow-hidden">
        
        <!-- Sidebar Integration -->
        <div class="flex-shrink-0 h-full">
            <?php include 'sidebar.php'; ?>
        </div>

        <!-- Main Content Area -->
        <div class="flex-1 flex flex-col overflow-y-auto">

            <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <h1 class="text-xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
                    <span class="w-3 h-3 bg-orange-500 rounded-full"></span> Profit and Loss Statement
                </h1>
                <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2.5 py-1 rounded-md">Financial Dashboard</span>
            </header>

            <main class="p-6">

                <!-- Filter Section -->
                <div class="bg-white border border-gray-200 rounded-xl p-4 mb-6 shadow-sm">
                    <div class="flex flex-wrap items-end gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Start Date</label>
                            <input type="date" value="2026-07-01" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-orange-500">
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">End Date</label>
                            <input type="date" value="2026-07-30" class="border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-orange-500">
                        </div>
                        <div>
                            <button type="button" class="bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-4 py-2 rounded-lg transition-colors flex items-center gap-2 shadow-sm cursor-pointer">
                                <i class="bi bi-filter"></i> Filter Report
                            </button>
                        </div>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-500">Total Revenue (Sales)</span>
                            <span class="p-2 bg-green-50 text-green-600 rounded-lg"><i class="bi bi-graph-up-arrow text-lg"></i></span>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mt-2">₱45,850.00</h2>
                        <p class="text-xs text-gray-400 mt-1">120 total transactions</p>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-500">Total Expenses</span>
                            <span class="p-2 bg-red-50 text-red-600 rounded-lg"><i class="bi bi-graph-down-arrow text-lg"></i></span>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mt-2">₱18,200.00</h2>
                        <p class="text-xs text-gray-400 mt-1">Operational costs & expenses</p>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-500">Net Profit / (Loss)</span>
                            <span class="p-2 bg-orange-50 text-orange-600 rounded-lg"><i class="bi bi-wallet2 text-lg"></i></span>
                        </div>
                        <h2 class="text-2xl font-bold text-green-600 mt-2">₱27,650.00</h2>
                        <p class="text-xs text-gray-400 mt-1">Net Income for period</p>
                    </div>
                </div>

                <!-- Table Card -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 font-bold text-gray-900 flex justify-between items-center">
                        <span>Financial Statement Breakdown</span>
                        <span class="text-xs text-gray-500 font-normal">Detailed Summary</span>
                    </div>
                    <div class="p-6">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wider">
                                    <th class="py-3 px-4">Account / Category</th>
                                    <th class="py-3 px-4 text-right">Total Amount</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm">
                                <tr>
                                    <td class="py-3 px-4 font-medium text-gray-900">Total Revenue (Sales)</td>
                                    <td class="py-3 px-4 text-right text-green-600 font-semibold">₱45,850.00</td>
                                </tr>
                                <tr>
                                    <td class="py-3 px-4 font-medium text-gray-900">Total Expenses</td>
                                    <td class="py-3 px-4 text-right text-red-600 font-semibold">(₱18,200.00)</td>
                                </tr>
                                <tr class="bg-orange-50 font-bold">
                                    <td class="py-3 px-4 text-gray-900">Net Profit / (Loss)</td>
                                    <td class="py-3 px-4 text-right text-green-600">₱27,650.00</td>
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