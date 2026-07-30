<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PannaKoda - Budget Approval</title>
    <script src="../LIBRARIES/tailwind.js"></script>
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
                    <span class="w-3 h-3 bg-orange-500 rounded-full"></span> Budget Approval Requests
                </h1>
                <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2.5 py-1 rounded-md">Financial Management</span>
            </header>

            <main class="p-6">

                <!-- Action/Filter Header -->
                <div class="bg-white border border-gray-200 rounded-xl p-4 mb-6 shadow-sm flex flex-wrap items-center justify-between gap-4">
                    <div class="flex flex-wrap items-center gap-3">
                        <div class="relative">
                            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
                                <i class="bi bi-search"></i>
                            </span>
                            <input type="text" placeholder="Search requests..." class="pl-9 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-orange-500 w-64">
                        </div>
                        <select class="border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-600 focus:outline-none focus:border-orange-500 bg-white">
                            <option value="">All Status</option>
                            <option value="pending">Pending</option>
                            <option value="approved">Approved</option>
                            <option value="rejected">Rejected</option>
                        </select>
                    </div>
                   
                </div>

                <!-- Summary Cards -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-6 mb-6">
                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-500">Pending Requests</span>
                            <span class="p-2 bg-amber-50 text-amber-600 rounded-lg"><i class="bi bi-clock-history text-lg"></i></span>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mt-2">₱14,500.00</h2>
                        <p class="text-xs text-gray-400 mt-1">4 requests awaiting review</p>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-500">Approved This Month</span>
                            <span class="p-2 bg-green-50 text-green-600 rounded-lg"><i class="bi bi-check-circle text-lg"></i></span>
                        </div>
                        <h2 class="text-2xl font-bold text-gray-900 mt-2">₱62,300.00</h2>
                        <p class="text-xs text-gray-400 mt-1">15 requests successfully funded</p>
                    </div>

                    <div class="bg-white border border-gray-200 rounded-xl p-5 shadow-sm">
                        <div class="flex items-center justify-between">
                            <span class="text-sm font-medium text-gray-500">Total Allocated Budget</span>
                            <span class="p-2 bg-orange-50 text-orange-600 rounded-lg"><i class="bi bi-pie-chart text-lg"></i></span>
                        </div>
                        <h2 class="text-2xl font-bold text-orange-600 mt-2">₱100,000.00</h2>
                        <p class="text-xs text-gray-400 mt-1">Monthly operational fund limit</p>
                    </div>
                </div>

                <!-- Table Card -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 font-bold text-gray-900 flex justify-between items-center">
                        <span>Budget Approval Queue</span>
                        <span class="text-xs text-gray-500 font-normal">Showing recent submissions</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase tracking-wider bg-gray-50/50">
                                    <th class="py-3 px-4">Request ID / Title</th>
                                    <th class="py-3 px-4">Requested By</th>
                                    <th class="py-3 px-4">Department</th>
                                    <th class="py-3 px-4 text-right">Amount</th>
                                    <th class="py-3 px-4 text-center">Status</th>
                                    <th class="py-3 px-4 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100 text-sm">
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-gray-900">BR-2026-041</div>
                                        <div class="text-xs text-gray-400">Kitchen Ingredient Restock</div>
                                    </td>
                                    <td class="py-3 px-4 text-gray-600">Juan Dela Cruz</td>
                                    <td class="py-3 px-4 text-gray-600">Inventory</td>
                                    <td class="py-3 px-4 text-right font-semibold text-gray-900">₱5,200.00</td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="px-2.5 py-1 text-xs font-medium bg-amber-50 text-amber-700 rounded-full">Pending</span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <div class="flex items-center justify-center gap-2">
                                            <button type="button" class="p-1.5 text-green-600 hover:bg-green-50 rounded-lg transition-colors cursor-pointer" title="Approve">
                                                <i class="bi bi-check-lg text-base"></i>
                                            </button>
                                            <button type="button" class="p-1.5 text-red-600 hover:bg-red-50 rounded-lg transition-colors cursor-pointer" title="Reject">
                                                <i class="bi bi-x-lg text-base"></i>
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-gray-900">BR-2026-040</div>
                                        <div class="text-xs text-gray-400">Store Front Signage Upgrade</div>
                                    </td>
                                    <td class="py-3 px-4 text-gray-600">Maria Santos</td>
                                    <td class="py-3 px-4 text-gray-600">Marketing</td>
                                    <td class="py-3 px-4 text-right font-semibold text-gray-900">₱8,000.00</td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="px-2.5 py-1 text-xs font-medium bg-green-50 text-green-700 rounded-full">Approved</span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <button type="button" class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg transition-colors cursor-pointer" title="View Details">
                                            <i class="bi bi-eye text-base"></i>
                                        </button>
                                    </td>
                                </tr>
                                <tr class="hover:bg-gray-50/50 transition-colors">
                                    <td class="py-3 px-4">
                                        <div class="font-medium text-gray-900">BR-2026-039</div>
                                        <div class="text-xs text-gray-400">POS Hardware Repair</div>
                                    </td>
                                    <td class="py-3 px-4 text-gray-600">Carlos Gomez</td>
                                    <td class="py-3 px-4 text-gray-600">Operations</td>
                                    <td class="py-3 px-4 text-right font-semibold text-gray-900">₱1,300.00</td>
                                    <td class="py-3 px-4 text-center">
                                        <span class="px-2.5 py-1 text-xs font-medium bg-red-50 text-red-700 rounded-full">Rejected</span>
                                    </td>
                                    <td class="py-3 px-4 text-center">
                                        <button type="button" class="p-1.5 text-gray-400 hover:text-gray-600 rounded-lg transition-colors cursor-pointer" title="View Details">
                                            <i class="bi bi-eye text-base"></i>
                                        </button>
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