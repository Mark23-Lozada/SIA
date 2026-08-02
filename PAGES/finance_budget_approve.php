<!-- finance_budget_approve.php -->
<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$current_role = strtolower($_SESSION['role']);
if ($current_role !== 'admin' && $current_role !== 'finance') {
    header("Location: login.php"); 
    exit();
}
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once __DIR__ . '../../project-test1/BACKEND/db_inventory.php';

if (isset($_GET['action']) && isset($_GET['id'])) {
    $req_id = (int)$_GET['id'];
    $action = $_GET['action'];
    
    if ($action == 'approve') {
        $new_status = 'Approved by Finance';
    } else {
        $new_status = 'Rejected';
    }
    
    $update_stmt = $conn->prepare("UPDATE budget_requests SET status = ? WHERE id = ?");
    $update_stmt->bind_param("si", $new_status, $req_id);
    $update_stmt->execute();
    $update_stmt->close();
    
    header("Location: finance_budget_approve.php");
    exit();
}

$result = $conn->query("SELECT * FROM budget_requests ORDER BY created_at DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance Budget Approval</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-slate-50 text-slate-800 antialiased font-sans">
    <div class="flex h-screen w-full overflow-hidden">
        <div class="flex-shrink-0 h-full">
            <?php include 'sidebar.php'; ?>
        </div>
        <div class="flex-1 flex flex-col overflow-y-auto">
            <header class="bg-white border-b border-slate-200 px-6 py-4 flex items-center justify-between">
                <h1 class="text-xl font-bold text-slate-900">Finance Budget Approval Queue</h1>
                <span class="text-xs font-medium text-slate-500 bg-slate-100 px-2.5 py-1 rounded-md">Finance Department</span>
            </header>
            <main class="p-6">
                <div class="bg-white border border-slate-200 rounded-xl shadow-sm overflow-hidden">
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="border-b text-xs font-semibold text-slate-500 uppercase bg-slate-50">
                                    <th class="py-3 px-4">Request ID / Title</th>
                                    <th class="py-3 px-4">Requested By</th>
                                    <th class="py-3 px-4 text-right">Amount</th>
                                    <th class="py-3 px-4 text-center">Status</th>
                                    <th class="py-3 px-4 text-center">Actions</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y text-sm">
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                    <tr class="hover:bg-slate-50">
                                        <td class="py-3 px-4">
                                            <div class="font-medium text-slate-900"><?php echo htmlspecialchars($row['request_id']); ?></div>
                                            <div class="text-xs text-slate-400"><?php echo htmlspecialchars($row['title']); ?></div>
                                        </td>
                                        <td class="py-3 px-4 text-slate-600"><?php echo htmlspecialchars($row['requested_by']); ?></td>
                                        <td class="py-3 px-4 text-right font-semibold">₱<?php echo number_format($row['amount'], 2); ?></td>
                                        <td class="py-3 px-4 text-center">
                                            <span class="px-2.5 py-1 text-xs font-medium bg-amber-50 text-amber-700 rounded-full">
                                                <?php echo htmlspecialchars($row['status']); ?>
                                            </span>
                                        </td>
                                        <td class="py-3 px-4 text-center">
                                            <?php if ($row['status'] == 'Pending'): ?>
                                                <div class="flex items-center justify-center gap-2">
                                                    <a href="finance_budget_approve.php?action=approve&id=<?php echo $row['id']; ?>" class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold" title="Forward to Admin">
                                                        <i class="bi bi-check-lg"></i> Forward
                                                    </a>
                                                    <a href="finance_budget_approve.php?action=reject&id=<?php echo $row['id']; ?>" class="bg-red-600 hover:bg-red-700 text-white px-3 py-1.5 rounded-lg text-xs font-bold" title="Reject">
                                                        <i class="bi bi-x-lg"></i> Reject
                                                    </a>
                                                </div>
                                            <?php else: ?>
                                                <span class="text-xs text-slate-400 font-medium"><?php echo htmlspecialchars($row['status']); ?></span>
                                            <?php endif; ?>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center py-8 text-slate-400">No requests found.</td></tr>
                                <?php endif; ?>
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