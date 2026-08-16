<?php
session_start();

// 1. Ensure user is logged in[cite: 7]
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

// 2. Get role and convert to lowercase to avoid case-sensitivity issues[cite: 7]
$current_role = strtolower($_SESSION['role']);

// 3. Block if user is neither admin nor hr[cite: 7]
if ($current_role !== 'admin' && $current_role !== 'hr') {
    header("Location: login.php"); 
    exit();
}

// ==========================================
// 1. DATABASE CONNECTION & INITIALIZATION
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

// Determine active page in view (default is 'leaves')[cite: 7]
$current_page = isset($_GET['page']) ? $_GET['page'] : 'leaves';

// ==========================================
// 2. BACKEND ACTION HANDLERS (HR/SUPERVISOR NODE)[cite: 7]
// ==========================================
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['hr_action'])) {
    $request_id = intval($_POST['request_id']);
    $action = $_POST['hr_action'];

    if ($action === 'hr_approve') {
        // Forward to Admin Board[cite: 7]
        $stmt = $conn->prepare("UPDATE leave_requests SET status = 'Pending Admin' WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        if ($stmt->execute()) {
            $status_message = "Leave application approved by HR and forwarded to Executive Admin Board!";
            $status_type = "success";
        }
        $stmt->close();
    } elseif ($action === 'hr_reject') {
        // Set to 'Rejected' so it no longer shows in the new requests list[cite: 7]
        $stmt = $conn->prepare("UPDATE leave_requests SET status = 'Rejected' WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        if ($stmt->execute()) {
            $status_message = "Leave application rejected.";
            $status_type = "error";
        }
        $stmt->close();
    }
}

// ==========================================
// 3. DATA QUERIES[cite: 7]
// ==========================================
// A. Fetch incoming requests (Pending or Pending HR)[cite: 7]
$sql_pending = "SELECT lr.*, e.full_name, e.department 
                FROM leave_requests lr 
                JOIN employees e ON lr.employee_id = e.id 
                WHERE lr.status = 'Pending' OR lr.status = 'Pending HR' 
                ORDER BY lr.id DESC";
$hr_leave_queue = $conn->query($sql_pending);

// B. Fetch approved, forwarded, or processed records[cite: 7]
$sql_processed = "SELECT lr.*, e.full_name, e.department 
                  FROM leave_requests lr 
                  JOIN employees e ON lr.employee_id = e.id 
                  WHERE lr.status != 'Pending' AND lr.status != 'Pending HR' 
                  ORDER BY lr.id DESC LIMIT 20";
$hr_processed_queue = $conn->query($sql_processed);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>HR Screening Desk - Administration</title>
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
    <script src="../LIBRARIES/tailwind.js"></script>
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>
<body class="bg-zinc-100 font-sans antialiased h-screen overflow-hidden">

    <div class="flex h-screen w-full overflow-hidden">
        
        <?php include 'sidebar.php'; ?>

        <div class="flex-1 h-screen overflow-y-auto p-8 bg-zinc-100 min-w-0">
    
            <div class="max-w-6xl mx-auto space-y-10">
                
                <div class="mb-2">
                    <h1 class="text-3xl font-black text-zinc-800 tracking-tight">HR Leave Screening</h1>
                    <p class="text-sm text-zinc-500">Initial verification queue for incoming employee leave files. Approved data transfers up to Executive Board[cite: 7].</p>
                </div>

                <!-- TABLE 1: NEW DATA (PENDING QUEUE)[cite: 7] -->
                <div>
                    <h2 class="text-lg font-bold text-zinc-700 mb-3 flex items-center gap-2">
                        <i class="bi bi-clock-history text-amber-500"></i> New / Pending Requests
                    </h2>
                    <div class="bg-white rounded-xl shadow-sm border border-zinc-200 overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-zinc-50 border-b border-zinc-200 text-xs font-bold uppercase text-zinc-500 tracking-wider">
                                    <th class="p-4">Employee Details</th>
                                    <th class="p-4">Leave Type</th>
                                    <th class="p-4">Reason Statement</th>
                                    <th class="p-4">Verification Level</th>
                                    <th class="p-4 text-center">Screening Operations</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm text-zinc-700 divide-y divide-zinc-200">
                                <?php if (!$hr_leave_queue || $hr_leave_queue->num_rows == 0): ?>
                                    <tr>
                                        <td colspan="5" class="p-10 text-center text-zinc-400 font-medium">Clear! No leave requests pending for HR screening evaluation[cite: 7].</td>
                                    </tr>
                                <?php else: ?>
                                    <?php while($req = $hr_leave_queue->fetch_assoc()): ?>
                                        <tr class="hover:bg-zinc-50/50 transition-colors">
                                            <td class="p-4">
                                                <div class="font-bold text-zinc-900"><?= htmlspecialchars($req['full_name']) ?></div>
                                                <div class="text-xs text-zinc-400">Dept: <?= htmlspecialchars($req['department']) ?></div>
                                            </td>
                                            <td class="p-4">
                                                <span class="px-2.5 py-1 bg-zinc-100 text-zinc-800 font-bold text-xs rounded-lg border border-zinc-200">
                                                    <?= htmlspecialchars($req['leave_type']) ?>
                                                </span>
                                            </td>
                                            <td class="p-4 text-zinc-600 max-w-xs truncate" title="<?= htmlspecialchars($req['reason']) ?>"><?= htmlspecialchars($req['reason']) ?></td>
                                            <td class="p-4">
                                                <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-amber-100 text-amber-800 border border-amber-200 uppercase tracking-wide">
                                                    <?= htmlspecialchars($req['status']) ?> Phase
                                                </span>
                                            </td>
                                            <td class="p-4 text-center">
                                                <div class="flex justify-center gap-2">
                                                    <button onclick="triggerAction(<?= $req['id'] ?>, 'hr_approve')" class="px-3 py-1.5 bg-blue-600 hover:bg-blue-700 text-white font-bold text-xs rounded-lg transition-all flex items-center gap-1 shadow-sm">
                                                        <i class="bi bi-chevron-right"></i> Transfer to Admin
                                                    </button>
                                                    <button onclick="triggerAction(<?= $req['id'] ?>, 'hr_reject')" class="px-3 py-1.5 bg-rose-50 text-rose-600 hover:bg-rose-100 font-bold text-xs rounded-lg transition-all flex items-center gap-1">
                                                        <i class="bi bi-x-circle"></i> Deny File
                                                    </button>
                                                </div>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- TABLE 2: APPROVED / FORWARDED RECORDS[cite: 7] -->
                <div class="pt-4">
                    <h2 class="text-lg font-bold text-zinc-700 mb-3 flex items-center gap-2">
                        <i class="bi bi-check-circle-fill text-blue-500"></i> Approved & Forwarded Records
                    </h2>
                    <div class="bg-white rounded-xl shadow-sm border border-zinc-200 overflow-hidden">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-zinc-50 border-b border-zinc-200 text-xs font-bold uppercase text-zinc-500 tracking-wider">
                                    <th class="p-4">Employee Details</th>
                                    <th class="p-4">Leave Type</th>
                                    <th class="p-4">Reason Statement</th>
                                    <th class="p-4">Current Status</th>
                                </tr>
                            </thead>
                            <tbody class="text-sm text-zinc-700 divide-y divide-zinc-200">
                                <?php if (!$hr_processed_queue || $hr_processed_queue->num_rows == 0): ?>
                                    <tr>
                                        <td colspan="4" class="p-10 text-center text-zinc-400 font-medium">No approved or forwarded records found.</td>
                                    </tr>
                                <?php else: ?>
                                    <?php while($proc = $hr_processed_queue->fetch_assoc()): ?>
                                        <tr class="hover:bg-zinc-50/50 transition-colors">
                                            <td class="p-4">
                                                <div class="font-bold text-zinc-900"><?= htmlspecialchars($proc['full_name']) ?></div>
                                                <div class="text-xs text-zinc-400">Dept: <?= htmlspecialchars($proc['department']) ?></div>
                                            </td>
                                            <td class="p-4">
                                                <span class="px-2.5 py-1 bg-zinc-100 text-zinc-800 font-bold text-xs rounded-lg border border-zinc-200">
                                                    <?= htmlspecialchars($proc['leave_type']) ?>
                                                </span>
                                            </td>
                                            <td class="p-4 text-zinc-600 max-w-xs truncate" title="<?= htmlspecialchars($proc['reason']) ?>"><?= htmlspecialchars($proc['reason']) ?></td>
                                            <td class="p-4">
                                                <?php if($proc['status'] == 'Rejected'): ?>
                                                    <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-rose-100 text-rose-800 border border-rose-200 uppercase tracking-wide">
                                                        <?= htmlspecialchars($proc['status']) ?>
                                                    </span>
                                                <?php else: ?>
                                                    <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-blue-100 text-blue-800 border border-blue-200 uppercase tracking-wide">
                                                        <?= htmlspecialchars($proc['status']) ?>
                                                    </span>
                                                <?php endif; ?>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <form id="actionForm" method="POST" action="leave.php" style="display:none;">
        <input type="hidden" id="form_request_id" name="request_id" value="">
        <input type="hidden" id="form_action_type" name="hr_action" value="">
    </form>

    <script>
       document.addEventListener("DOMContentLoaded", function () {
        // 1. Sidebar Highlight Logic
        highlightActiveSidebarLink();

        // 2. Logout Button Logic
        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function(e) {
                e.preventDefault(); 
                Swal.fire({
                    title: 'Log out?',
                    text: "Are you sure you want to exit the system?",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#FF8C00', 
                    cancelButtonColor: '#d33',
                    cancelButtonText: 'Cancel',
                    confirmButtonText: 'Yes'
                }).then((result) => {
                    if (result.isConfirmed) {
                        window.location.href = "logout.php"; 
                    }
                });
            });
        }

        // 3. Auto-refresh Logic
        setInterval(function() {
            if (document.querySelector('.swal2-container') === null) {
                location.reload();
            }
        }, 30000);
    });

    // 4. Global Function 
    function triggerAction(requestId, action) {
        const isApprove = action === 'hr_approve';
        Swal.fire({
            title: isApprove ? 'Forward Data Packet?' : 'Reject Request Pipeline?',
            text: isApprove ? "This file moves out of HR and transfers to the Executive Admin Control Board[cite: 7]." : "This record stops here and reflects as Rejected[cite: 7].",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: isApprove ? 'blue' : '#dc2626',
            confirmButtonText: isApprove ? 'Yes, Forward' : 'Yes, Deny File'
        }).then((result) => {
            if (result.isConfirmed) {
                document.getElementById('form_request_id').value = requestId;
                document.getElementById('form_action_type').value = action;
                document.getElementById('actionForm').submit();
            }
        });
    }

    function highlightActiveSidebarLink() {
        const currentPath = window.location.pathname;
        const navLinks = document.querySelectorAll(".nav-link, .sidebar-link");
        navLinks.forEach(link => {
            const linkPath = link.getAttribute("href");
            if (linkPath && currentPath.endsWith(linkPath)) {
                link.classList.add("bg-[#FF8C00]", "!text-white", "shadow-md", "font-semibold");
            }
        });
    }
    </script>
    <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>
</body>
</html>