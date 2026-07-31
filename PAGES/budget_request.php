<?php
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
git 
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PannaKoda - Submit Budget Request</title>
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
                    <span class="w-3 h-3 bg-orange-500 rounded-full"></span> Submit Budget Request
                </h1>
                <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2.5 py-1 rounded-md">Financial Management</span>
            </header>

            <main class="p-6 max-w-4xl mx-auto w-full">

                <!-- Form Card -->
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="px-6 py-4 border-b border-gray-200 font-bold text-gray-900 flex justify-between items-center">
                        <span>New Budget Request Form</span>
                        <span class="text-xs text-gray-500 font-normal">Fill out all required details</span>
                    </div>
                    
                    <form action="" method="POST" class="p-6 space-y-6">
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <!-- Request Title -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Request Title / Purpose <span class="text-red-500">*</span></label>
                                <input type="text" name="title" placeholder="e.g. Kitchen Ingredient Restock" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-orange-500">
                            </div>

                            <!-- Department -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Department <span class="text-red-500">*</span></label>
                                <select name="department" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm text-gray-700 bg-white focus:outline-none focus:border-orange-500">
                                    <option value="">Select Department</option>
                                    <option value="Inventory">Inventory</option>
                                    <option value="Marketing">Marketing</option>
                                    <option value="Operations">Operations</option>
                                    <option value="Sales">Sales</option>
                                </select>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                            <!-- Requested By -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Requested By <span class="text-red-500">*</span></label>
                                <input type="text" name="requested_by" placeholder="Full Name" required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-orange-500">
                            </div>

                            <!-- Amount -->
                            <div>
                                <label class="block text-xs font-semibold text-gray-600 mb-1">Requested Amount (₱) <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <span class="absolute inset-y-0 left-0 flex items-center pl-3 text-gray-500 text-sm">₱</span>
                                    <input type="number" step="0.01" name="amount" placeholder="0.00" required class="w-full pl-8 pr-4 py-2 border border-gray-300 rounded-lg text-sm focus:outline-none focus:border-orange-500">
                                </div>
                            </div>
                        </div>

                        <!-- Description / Justification -->
                        <div>
                            <label class="block text-xs font-semibold text-gray-600 mb-1">Justification / Details <span class="text-red-500">*</span></label>
                            <textarea name="description" rows="4" placeholder="Provide a brief explanation of why this budget is needed..." required class="w-full border border-gray-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:border-orange-500"></textarea>
                        </div>

                        <!-- Form Actions -->
                        <div class="flex items-center justify-end gap-3 pt-4 border-t border-gray-100">
                            <button type="button" onclick="history.back()" class="bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-medium px-4 py-2 rounded-lg transition-colors cursor-pointer">
                                Cancel
                            </button>
                            <button type="submit" class="bg-orange-500 hover:bg-orange-600 text-white text-sm font-medium px-5 py-2 rounded-lg transition-colors shadow-sm cursor-pointer flex items-center gap-2">
                                <i class="bi bi-send"></i> Submit Request
                            </button>
                        </div>

                    </form>
                </div>

            </main>
        </div>
    </div>

</body>

</html>