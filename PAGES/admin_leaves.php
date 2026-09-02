<?php
ob_start();
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';

if ((!isset($_SESSION['user_id']) && !isset($_SESSION['admin_id'])) || ($current_role !== 'admin' && $current_role !== 'hr')) {
    header("Location: login.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "pos";

$current_page = basename($_SERVER['PHP_SELF']);

// 1. FETCH LEAVE REQUESTS VIA AJAX[cite: 1]
if (isset($_GET['action']) && $_GET['action'] === 'fetch_leaves') {
    header('Content-Type: application/json');
    $conn = new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_error) {
        echo json_encode(['pending' => [], 'processed' => []]);
        exit;
    }

    $pending_query = $conn->query("SELECT lr.*, e.full_name, e.department, e.email FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.status = 'Pending Admin' ORDER BY lr.id DESC");
    $pending = [];
    if ($pending_query) {
        while($row = $pending_query->fetch_assoc()) {
            $row['id'] = intval($row['id']);
            $pending[] = $row;
        }
    }

    $processed_query = $conn->query("SELECT lr.*, e.full_name, e.department, e.email FROM leave_requests lr JOIN employees e ON lr.employee_id = e.id WHERE lr.status = 'Approved' OR lr.status = 'Rejected' ORDER BY lr.id DESC LIMIT 20");
    $processed = [];
    if ($processed_query) {
        while($row = $processed_query->fetch_assoc()) {
            $row['id'] = intval($row['id']);
            $processed[] = $row;
        }
    }

    echo json_encode(['pending' => $pending, 'processed' => $processed]);
    $conn->close();
    exit;
}

// 2. PROCESS LEAVE APPROVAL ACTION[cite: 1]
if (isset($_GET['action']) && $_GET['action'] === 'admin_approve' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;

    if ($request_id > 0) {
        $conn = new mysqli($host, $user, $pass, $dbname);
        
        $stmt = $conn->prepare("UPDATE leave_requests SET status = 'Approved', notified = 0 WHERE id = ? AND status = 'Pending Admin'");
        $stmt->bind_param("i", $request_id);
        $stmt->execute();
        $affected = $stmt->affected_rows;
        $stmt->close();
        $conn->close();

        if ($affected > 0) {
            echo json_encode(['success' => true, 'message' => 'Leave application has been officially Approved!']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to approve request or already processed.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid Request ID.']);
    }
    exit;
}

// 3. PROCESS LEAVE REJECTION ACTION[cite: 1]
if (isset($_GET['action']) && $_GET['action'] === 'admin_reject' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $request_id = isset($_POST['request_id']) ? intval($_POST['request_id']) : 0;

    if ($request_id > 0) {
        $conn = new mysqli($host, $user, $pass, $dbname);

        $stmt = $conn->prepare("UPDATE leave_requests SET status = 'Rejected', notified = 0 WHERE id = ?");
        $stmt->bind_param("i", $request_id);
        $success = $stmt->execute();
        $stmt->close();
        $conn->close();

        if ($success) {
            echo json_encode(['success' => true, 'message' => 'Leave application officially rejected.']);
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to reject request.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid Request ID.']);
    }
    exit;
}
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
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
    
    <!-- AOS Library CSS & JS -->
    <link href="../LIBRARIES/AOS/aos.css" rel="stylesheet">
    <script src="../LIBRARIES/AOS/AOS.js"></script>

    <style>
        @keyframes floatSlow {
            0%, 100% { transform: translateY(0px); }
            50% { transform: translateY(-5px); }
        }
        .admin-card-glow {
            transition: all 0.4s ease-in-out;
        }
        .admin-card-glow:hover {
            box-shadow: 0 0 35px rgba(255, 107, 74, 0.25);
            border-color: rgba(255, 107, 74, 0.5);
        }
    </style>
</head>
<body class="bg-white text-amber-500 font-sans antialiased h-screen overflow-hidden">

    <div class="flex h-screen w-full overflow-hidden">
        <?php include 'sidebar.php'; ?>

        <!-- MAIN CONTENT -->
        <div class="flex-1 h-screen overflow-y-auto p-8 bg-white min-w-0">
            <div class="max-w-6xl mx-auto space-y-8">
                
                <!-- Top Header & Live Philippine Time Clock Widget -->
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4" data-aos="fade-down" data-aos-duration="800">
                    <div>
                        <h1 class="text-2xl font-extrabold text-amber-500 tracking-tight">EXECUTIVE LEAVE REVIEW</h1>
                        <p class="text-sm text-slate-500 mt-1">Final authorization deck for employee leave requests endorsed and passed up by HR Screening.</p>
                    </div>
                    <div class="flex items-center gap-3">
                        <div class="flex items-center gap-1.5 bg-slate-50 px-3 py-2 rounded-2xl shadow-sm border border-slate-200/80">
                            <i class="bi bi-clock text-amber-500"></i> 
                            <span class="text-slate-700 font-medium text-xs" id="phTimeDisplay">Loading PH Time...</span>
                        </div>
                        <div class="flex items-center gap-3 bg-orange-50/60 px-4 py-2 rounded-2xl shadow-sm border border-[#ff6b4a]/20 transition-transform duration-300 hover:scale-105">
                            <span class="relative flex h-3 w-3">
                              <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75"></span>
                              <span class="relative inline-flex rounded-full h-3 w-3 bg-emerald-500"></span>
                            </span>
                            <span class="text-xs font-semibold uppercase tracking-wider text-slate-700">System Active</span>
                        </div>
                    </div>
                </div>

                <!-- Search Bar -->
                <div class="flex items-center gap-3 bg-slate-50 p-4 rounded-3xl shadow-sm border border-slate-200/80 admin-card-glow" data-aos="fade-up" data-aos-duration="900">
                    <div class="relative flex-1">
                        <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-slate-400"><i class="bi bi-search"></i></span>
                        <input id="searchInput" type="text" class="w-full pl-11 pr-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-sm text-amber-500 focus:outline-none focus:ring-2 focus:ring-[#ff6b4a]/30 focus:border-[#ff6b4a] transition-all duration-300" placeholder="Search by employee name, department, leave type, or reason...">
                    </div>
                </div>

                <!-- TABLE 1: NEW REQUESTS / PENDING ADMIN DATA -->
                <div class="bg-slate-50 rounded-3xl shadow-sm border border-slate-200/80 p-6 admin-card-glow" data-aos="fade-up" data-aos-duration="1000">
                    <h2 class="text-md font-bold text-amber-500 mb-4 flex items-center gap-2">
                        <div class="p-2 bg-[#ff6b4a]/10 text-amber-500 rounded-xl border border-[#ff6b4a]/20">
                            <i class="bi bi-clock-history"></i>
                        </div> 
                        New Requests / Awaiting Executive Sign-off
                    </h2>
                    <div class="table-responsive bg-white rounded-2xl overflow-hidden border border-slate-100">
                        <table class="table table-hover align-middle mb-0 text-sm">
                            <thead class="table-dark">
                                <tr>
                                    <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0">Employee</th>
                                    <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0">Type</th>
                                    <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0">Reason</th>
                                    <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0">Endorsement Status</th>
                                    <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody id="pendingBody"></tbody>
                        </table>
                    </div>
                </div>

                <!-- TABLE 2: APPROVED / PROCESSED RECORDS -->
                <div class="bg-slate-50 rounded-3xl shadow-sm border border-slate-200/80 p-6 mb-8 admin-card-glow" data-aos="fade-up" data-aos-duration="1100">
                    <h2 class="text-md font-bold text-amber-500 mb-4 flex items-center gap-2">
                        <div class="p-2 bg-emerald-500/10 text-emerald-600 rounded-xl border border-emerald-500/20">
                            <i class="bi bi-check-circle-fill"></i>
                        </div> 
                        Approved & Processed Records
                    </h2>
                    <div class="table-responsive bg-white rounded-2xl overflow-hidden border border-slate-100">
                        <table class="table table-hover align-middle mb-0 text-sm">
                            <thead class="table-dark">
                                <tr>
                                    <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0">Employee</th>
                                    <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0">Type</th>
                                    <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0">Reason</th>
                                    <th class="py-3 px-4 bg-[#1a1010] text-white font-semibold border-0">Final Status</th>
                                </tr>
                            </thead>
                            <tbody id="processedBody"></tbody>
                        </table>
                    </div>
                </div>

            </div>
        </div>
    </div>

    <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>
    <script>
        // Real-time Philippine Time Clock Function
        function updatePhilippineTime() {
            const options = {
                timeZone: 'Asia/Manila',
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit',
                hour12: true
            };
            const formatter = new Intl.DateTimeFormat([], options);
            const timeString = formatter.format(new Date());
            const displayElem = document.getElementById('phTimeDisplay');
            if (displayElem) {
                displayElem.textContent = timeString;
            }
        }
        setInterval(updatePhilippineTime, 1000);
        updatePhilippineTime();

        let pendingLeaves = [];
        let processedLeaves = [];
        const phpEndpoint = "<?php echo $current_page; ?>";

        async function loadLeaves() {
            try {
                const response = await fetch(`${phpEndpoint}?action=fetch_leaves`);
                const data = await response.json();
                pendingLeaves = data.pending;
                processedLeaves = data.processed;
                renderTables();
            } catch (err) {
                console.error("Failed to fetch leave requests:", err);
            }
        }

        function renderTables() {
            const query = document.getElementById('searchInput').value.toLowerCase().trim();
            
            // Render Pending Table
            const pendingBody = document.getElementById('pendingBody');
            pendingBody.innerHTML = '';

            const filteredPending = pendingLeaves.filter(req => {
                const name = String(req.full_name || '').toLowerCase();
                const dept = String(req.department || '').toLowerCase();
                const type = String(req.leave_type || '').toLowerCase();
                const reason = String(req.reason || '').toLowerCase();
                return name.includes(query) || dept.includes(query) || type.includes(query) || reason.includes(query);
            });

            if (filteredPending.length === 0) {
                pendingBody.innerHTML = `<tr><td colspan="5" class="text-center py-8 text-slate-400 italic">No new leave requests currently awaiting executive board action.</td></tr>`;
            } else {
                filteredPending.forEach(req => {
                    const tr = document.createElement('tr');
                    tr.className = "border-b border-slate-100 hover:bg-orange-50/20 transition-colors";
                    tr.innerHTML = `
                        <td class="py-3.5 px-4">
                            <div class="font-bold text-amber-500">${escapeHtml(req.full_name)}</div>
                            <div class="text-xs text-slate-500">Dept: ${escapeHtml(req.department || 'N/A')}</div>
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="px-2.5 py-1 bg-indigo-50 text-indigo-600 font-bold text-xs rounded-xl border border-indigo-100">
                                ${escapeHtml(req.leave_type)}
                            </span>
                        </td>
                        <td class="py-3.5 px-4 text-slate-600 max-w-xs truncate" title="${escapeHtml(req.reason)}">${escapeHtml(req.reason)}</td>
                        <td class="py-3.5 px-4">
                            <span class="text-xs font-bold px-2.5 py-1 rounded-full bg-blue-50 text-blue-700 border border-blue-200 uppercase">Awaiting Exec Sign-off</span>
                        </td>
                        <td class="py-3.5 px-4 text-center">
                            <div class="flex justify-center gap-2">
                                <button onclick="processLeaveAction(${req.id}, 'admin_approve', '${escapeHtml(req.full_name)}')" class="btn btn-sm btn-success py-1.5 px-3 text-xs font-semibold rounded-xl bg-emerald-600 hover:bg-emerald-700 border-0 shadow-sm flex items-center gap-1">
                                    <i class="bi bi-check-circle"></i> Approve
                                </button>
                                <button onclick="processLeaveAction(${req.id}, 'admin_reject', '${escapeHtml(req.full_name)}')" class="btn btn-sm btn-outline-danger py-1.5 px-2.5 text-xs font-semibold rounded-xl border-red-500 text-red-500 hover:bg-red-500 hover:text-white flex items-center gap-1">
                                    <i class="bi bi-trash"></i> Reject
                                </button>
                            </div>
                        </td>
                    `;
                    pendingBody.appendChild(tr);
                });
            }

            // Render Processed Table
            const processedBody = document.getElementById('processedBody');
            processedBody.innerHTML = '';

            const filteredProcessed = processedLeaves.filter(proc => {
                const name = String(proc.full_name || '').toLowerCase();
                const dept = String(proc.department || '').toLowerCase();
                const type = String(proc.leave_type || '').toLowerCase();
                const reason = String(proc.reason || '').toLowerCase();
                return name.includes(query) || dept.includes(query) || type.includes(query) || reason.includes(query);
            });

            if (filteredProcessed.length === 0) {
                processedBody.innerHTML = `<tr><td colspan="4" class="text-center py-8 text-slate-400 italic">No approved or rejected records found.</td></tr>`;
            } else {
                filteredProcessed.forEach(proc => {
                    const isRejected = proc.status === 'Rejected';
                    const statusBadge = isRejected 
                        ? `<span class="text-xs font-bold px-2.5 py-1 rounded-full bg-rose-50 text-rose-700 border border-rose-200 uppercase">${escapeHtml(proc.status)}</span>`
                        : `<span class="text-xs font-bold px-2.5 py-1 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200 uppercase">${escapeHtml(proc.status)}</span>`;

                    const tr = document.createElement('tr');
                    tr.className = "border-b border-slate-100 hover:bg-orange-50/20 transition-colors";
                    tr.innerHTML = `
                        <td class="py-3.5 px-4">
                            <div class="font-bold text-amber-500">${escapeHtml(proc.full_name)}</div>
                            <div class="text-xs text-slate-500">Dept: ${escapeHtml(proc.department || 'N/A')}</div>
                        </td>
                        <td class="py-3.5 px-4">
                            <span class="px-2.5 py-1 bg-indigo-50 text-indigo-600 font-bold text-xs rounded-xl border border-indigo-100">
                                ${escapeHtml(proc.leave_type)}
                            </span>
                        </td>
                        <td class="py-3.5 px-4 text-slate-600 max-w-xs truncate" title="${escapeHtml(proc.reason)}">${escapeHtml(proc.reason)}</td>
                        <td class="py-3.5 px-4">${statusBadge}</td>
                    `;
                    processedBody.appendChild(tr);
                });
            }
        }

        function escapeHtml(text) {
            if (!text) return '';
            return text.replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;").replace(/"/g, "&quot;").replace(/'/g, "&#039;");
        }

        async function processLeaveAction(id, actionType, employeeName) {
            const isApprove = actionType === 'admin_approve';
            const actionName = isApprove ? 'Approve Leave Application' : 'Reject Leave Application';
            const confirmColor = isApprove ? '#10b981' : '#ef4444';

            const confirmResult = await Swal.fire({
                title: `${isApprove ? 'Approve' : 'Reject'} Request?`,
                text: `Are you sure you want to process the leave application for ${employeeName}?`,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: confirmColor,
                cancelButtonColor: '#64748b',
                confirmButtonText: `Yes, ${isApprove ? 'Approve' : 'Reject'}`,
                background: '#09090b',
                color: '#ffffff',
                customClass: { popup: 'rounded-3xl border border-[#ff6b4a]/30 shadow-2xl' }
            });

            if (!confirmResult.isConfirmed) return;

            Swal.fire({
                title: 'Processing Action...',
                text: 'Please wait a moment.',
                allowOutsideClick: false,
                background: '#09090b',
                color: '#ffffff',
                customClass: { popup: 'rounded-3xl border border-[#ff6b4a]/30 shadow-2xl backdrop-blur-xl' },
                didOpen: () => { Swal.showLoading(); }
            });

            const fd = new FormData();
            fd.append('request_id', id);

            try {
                const res = await fetch(`${phpEndpoint}?action=${actionType}`, { method: 'POST', body: fd });
                const data = await res.json();

                if (data.success) {
                    await loadLeaves();
                    Swal.fire({
                        title: 'Success!',
                        text: data.message,
                        icon: 'success',
                        timer: 1200,
                        showConfirmButton: false,
                        background: '#09090b',
                        color: '#ffffff',
                        customClass: { popup: 'rounded-3xl border border-[#ff6b4a]/30 shadow-2xl' }
                    });
                } else {
                    Swal.fire({
                        title: 'Error!',
                        text: data.message || 'Action failed.',
                        icon: 'error',
                        confirmButtonColor: '#ff6b4a',
                        background: '#09090b',
                        color: '#ffffff',
                        customClass: { popup: 'rounded-3xl border border-[#ff6b4a]/30 shadow-2xl' }
                    });
                }
            } catch (e) {
                Swal.fire({
                    title: 'Error!',
                    text: 'An unexpected connection error occurred.',
                    icon: 'error',
                    confirmButtonColor: '#ff6b4a',
                    background: '#09090b',
                    color: '#ffffff',
                    customClass: { popup: 'rounded-3xl border border-[#ff6b4a]/30 shadow-2xl' }
                });
            }
        }

        document.getElementById('searchInput').addEventListener('input', renderTables);

        window.addEventListener('DOMContentLoaded', () => {
            loadLeaves();
        });

        // Global SweetAlert Logout Interceptor
        document.addEventListener('click', function(e) {
            const logoutBtn = e.target.closest('#logoutBtn, #sidebarLogoutBtn, .logout-btn, a[href*="logout.php"]');
            
            if (logoutBtn) {
                e.preventDefault();
                e.stopPropagation();
                if (typeof e.stopImmediatePropagation === 'function') {
                    e.stopImmediatePropagation();
                }

                const logoutUrl = logoutBtn.getAttribute('href') || "logout.php";
                
                if (typeof Swal !== 'undefined') {
                    Swal.fire({
                        title: 'System Sign Out',
                        text: "Are you sure you want to end your current session?",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#ff6b4a', 
                        cancelButtonColor: '#ef4444',
                        confirmButtonText: 'Yes, Sign Out',
                        cancelButtonText: 'Cancel',
                        background: '#09090b',
                        color: '#ffffff',
                        customClass: {
                            popup: 'rounded-3xl border border-[#ff6b4a]/30 shadow-2xl backdrop-blur-xl'
                        }
                    }).then((result) => {
                        if (result.isConfirmed && logoutUrl && logoutUrl !== '#') {
                            window.location.href = logoutUrl; 
                        }
                    });
                } else {
                    if (confirm("Are you sure you want to log out?")) {
                        window.location.href = logoutUrl;
                    }
                }
            }
        }, true);

        // Initialize AOS animations
        AOS.init({
            once: true,
            offset: 50,
            duration: 800,
        });
    </script>
</body>
</html>