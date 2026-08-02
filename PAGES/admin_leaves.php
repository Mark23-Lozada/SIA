<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Kunin ang role at gawing lowercase para maiwasan ang error sa uppercase/lowercase letters
$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';

// HUWAG MAG-LOGOUT O MAG-REDIRECT KUNG ADMIN O HR ANG ROLE SA SIDEBAR
if (!isset($_SESSION['user_id']) || ($current_role !== 'admin' && $current_role !== 'hr')) {
    header("Location: login.php");
    exit();
}

// Anti-back/Cache control
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

// ==========================================
// DATABASE CONNECTION & INITIALIZATION
// ==========================================
$host = "localhost";
$user = "root";
$pass = "";
$dbname = "pos";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

$status_message = "";
$status_type = "success";

// Suriin kung may nakarehistro nang HR
$hr_check = $conn->query("SELECT COUNT(*) as total FROM hr_accounts");
$hr_count = $hr_check->fetch_assoc()['total'];

// ==========================================
// ACTION HANDLERS (STAY ON THIS PAGE)
// ==========================================

// ACTION: LEAVE - ADMIN APPROVE
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_action']) && $_POST['leave_action'] === 'admin_approve') {
    $request_id = intval($_POST['request_id']);
    $stmt = $conn->prepare("UPDATE leave_requests SET status = 'Approved', notified = 0 WHERE id = ? AND status = 'Pending Admin'");
    $stmt->bind_param("i", $request_id);
    $stmt->execute();
    
    if ($stmt->affected_rows > 0) {
        $status_message = "Leave application has been officially Approved!";
        $status_type = "success";
    } 
    $stmt->close();
}

// ACTION: LEAVE - ADMIN REJECT
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['leave_action']) && $_POST['leave_action'] === 'admin_reject') {
    $request_id = intval($_POST['request_id']);
    $stmt = $conn->prepare("UPDATE leave_requests SET status = 'Rejected', notified = 0 WHERE id = ?");
    $stmt->bind_param("i", $request_id);
    if ($stmt->execute()) {
        $status_message = "Leave application officially rejected. Log updated for user terminal view.";
        $status_type = "error";
    }
    $stmt->close();
}

// ==========================================
// DATA QUERIES
// ==========================================
$admin_query = $conn->query("SELECT lr.*, e.full_name, e.department 
                             FROM leave_requests lr 
                             JOIN employees e ON lr.employee_id = e.id 
                             WHERE lr.status = 'Approved' OR lr.status = 'Pending Admin' 
                             ORDER BY lr.id DESC");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Leave Dashboard - Administration</title>
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
    <script src="../LIBRARIES/tailwind.js"></script>
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
</head>
<body class="bg-zinc-100 font-sans antialiased h-screen overflow-hidden">

    <div class="flex h-screen w-full overflow-hidden">
        
 
  <?php include 'sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <div class="flex-1 h-screen overflow-y-auto p-8 bg-zinc-100 min-w-0">
            <div class="max-w-6xl mx-auto">
                
                <div class="mb-8">
                    <h1 class="text-3xl font-black text-zinc-800 tracking-tight">Executive Leave Review</h1>
                    <p class="text-sm text-zinc-500">Final authorization deck for employee leave requests endorsed and passed up by HR Screening.</p>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-zinc-200 overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <thead>
                            <tr class="bg-zinc-50 border-b border-zinc-200 text-xs font-bold uppercase text-zinc-500 tracking-wider">
                                <th class="p-4">Employee</th>
                                <th class="p-4">Type</th>
                                <th class="p-4">Reason</th>
                                <th class="p-4">Endorsement Status</th>
                                <th class="p-4 text-center">Action</th>
                            </tr>
                        </thead>
                        <tbody class="text-sm text-zinc-700 divide-y divide-zinc-200">
                            <?php if ($admin_query->num_rows == 0): ?>
                                <tr>
                                    <td colspan="5" class="p-12 text-center text-zinc-400 font-medium">No leave requests currently awaiting executive board action.</td>
                                </tr>
                            <?php endif; ?>
                            
                            <?php while($req = $admin_query->fetch_assoc()): ?>
                                <tr class="hover:bg-zinc-50/50 transition-colors">
                                    <td class="p-4">
                                        <div class="font-bold text-zinc-900"><?= htmlspecialchars($req['full_name']) ?></div>
                                        <div class="text-xs text-zinc-400">Dept: <?= htmlspecialchars($req['department']) ?></div>
                                    </td>
                                    <td class="p-4">
                                        <span class="px-2.5 py-1 bg-indigo-50 text-indigo-600 font-bold text-xs rounded-lg border border-indigo-100">
                                            <?= htmlspecialchars($req['leave_type']) ?>
                                        </span>
                                    </td>
                                    <td class="p-4 text-zinc-600 max-w-xs truncate" title="<?= htmlspecialchars($req['reason']) ?>"><?= htmlspecialchars($req['reason']) ?></td>
                                    <td class="p-4">
                                        <?php if ($req['status'] === 'Pending Admin'): ?>
                                            <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-blue-100 text-blue-700 border border-blue-200 uppercase">Awaiting Exec Sign-off</span>
                                        <?php else: ?>
                                            <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-emerald-100 text-emerald-700 border border-emerald-200 uppercase">Approved</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-center">
                                        <?php if ($req['status'] === 'Pending Admin'): ?>
                                            <div class="flex justify-center gap-2">
                                                <!-- Action triggers stay inside admin_leave.php -->
                                                <form method="POST" action="admin_leaves.php" class="inline">
                                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                    <input type="hidden" name="leave_action" value="admin_approve">
                                                    <button type="submit" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold text-xs rounded-lg shadow-sm transition-all">
                                                        <i class="bi bi-check-circle"></i> Approve
                                                    </button>
                                                </form>
                                                
                                                <form method="POST" action="admin_leaves.php" class="inline">
                                                    <input type="hidden" name="request_id" value="<?= $req['id'] ?>">
                                                    <input type="hidden" name="leave_action" value="admin_reject">
                                                    <button type="submit" class="px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white font-bold text-xs rounded-lg shadow-sm transition-all">
                                                        <i class="bi bi-trash"></i> Reject
                                                    </button>
                                                </form>
                                            </div>
                                        <?php else: ?>
                                            <span class="text-xs font-bold text-zinc-500 uppercase italic">
                                                <?= htmlspecialchars($req['status']) ?>
                                            </span>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", function() {
            <?php if(!empty($status_message)): ?>
                Swal.fire({
                    title: '<?= $status_type === "success" ? "Authorized!" : "Action Logged" ?>',
                    text: '<?= addslashes($status_message) ?>',
                    icon: '<?= $status_type ?>',
                    confirmButtonColor: '#dc2626'
                });
            <?php endif; ?>

           document.addEventListener("DOMContentLoaded", function () {
    // Pag-highlight ng active menu
    const currentPath = window.location.pathname;
    const navLinks = document.querySelectorAll(".sidebar-link");
    
    navLinks.forEach(link => {
        const linkPath = link.getAttribute("href");
        if (linkPath && currentPath.endsWith(linkPath)) {
            link.classList.remove("text-white/80", "hover:bg-white/10", "hover:text-white", "text-inherit");
            link.classList.add("bg-[#FF8C00]", "text-white", "shadow-md", "font-semibold");
        }
    });

    // SweetAlert2 para sa Logout Confirmation
    const logoutBtn = document.getElementById('logoutBtn');
    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault(); 
            Swal.fire({
                title: 'Log  out',
                text: "Are you sure you want to Log out?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#FF8C00', 
                cancelButtonColor: '#d33',
                confirmButtonText: 'Yes',
                cancelButtonText: 'Cancel',
                background: '#ffffff',
                color: '#212121'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = "logout.php"; 
                }
            });
        });
    }
});
        });
    </script>
</body>
</html>