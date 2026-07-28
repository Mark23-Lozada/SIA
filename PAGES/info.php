<?php
session_start();

// 1. I-check kung nakalogin.
if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

// 2. ROLE CHECK: Kung HINDI Employee ang role, harangan
if ($_SESSION['role'] !== 'Employee') {
    header("Location: admin.php"); // Ibalik sa admin kung admin pala ang nakalogin
    exit();
}

// 3. Pigilan ang Browser Caching
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Cache-Control: post-check=0, pre-check=0", false);
header("Pragma: no-cache");



// Set system time zone to Philippines
date_default_timezone_set('Asia/Manila');

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "pos";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$is_employee_role = ($_SESSION['role'] === 'Employee');
$all_employees = [];

// Fetch profile records
if ($is_employee_role) {
    $emp_gmail = $_SESSION['employee_gmail'];
    $stmt = $conn->prepare("SELECT * FROM employees WHERE employee_gmail = ? LIMIT 1");
    $stmt->bind_param("s", $emp_gmail);
    $stmt->execute();
    $result = $stmt->get_result();
    if ($row = $result->fetch_assoc()) {
        $row['display_emp_id'] = "EMP-" . str_pad($row['id'], 4, "0", STR_PAD_LEFT);
        $all_employees[] = $row;
    }
    $stmt->close();
} else {
    $res = $conn->query("SELECT * FROM employees ORDER BY id DESC");
    while ($row = $res->fetch_assoc()) {
        $row['display_emp_id'] = "EMP-" . str_pad($row['id'], 4, "0", STR_PAD_LEFT);
        $all_employees[] = $row;
    }
}

$notification_msg = "";

// ================= ACTION HANDLER: LEAVE APPLICATION =================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['apply_leave'])) {
    $emp_id = intval($_POST['employee_id']);
    $leave_type = $_POST['leave_type'] ?? '';
    $reason = $_POST['reason'] ?? '';
    
    if (empty($leave_type) || empty($reason)) {
        $_SESSION['system_msg'] = "Error: Fill up all fields.";
    } else {
        $leave_stmt = $conn->prepare("INSERT INTO leave_requests (employee_id, leave_type, reason, status) VALUES (?, ?, ?, 'Pending')");
        if ($leave_stmt) {
            $leave_stmt->bind_param("iss", $emp_id, $leave_type, $reason);
            if ($leave_stmt->execute()) {
                $_SESSION['system_msg'] = "Success: Leave application submitted.";
            } else {
                $_SESSION['system_msg'] = "Error: " . $leave_stmt->error;
            }
            $leave_stmt->close();
        }
    }
    header("Location: " . $_SERVER['PHP_SELF'] . "?page=leave-page");
    exit();
}

// ================= ACTION HANDLER: BIOMETRICS (TIME IN / OUT) =================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && (isset($_POST['time_in']) || isset($_POST['time_out']))) {
    $emp_id = intval($_POST['employee_id']);
    $current_time = date('H:i:s');
    $current_date = date('Y-m-d');
    $hour_min = date('H:i'); 

    if (isset($_POST['time_in'])) {
        // Evaluation Rules for Time In
        if ($hour_min >= '07:00' && $hour_min <= '07:30') {
            $status = "On Time";
        } elseif ($hour_min > '07:30' && $hour_min < '14:00') {
            $status = "Late";
        } else {
            $_SESSION['system_msg'] = "Error: Time In denied. System cutoff is at 2:00 PM.";
            header("Location: " . $_SERVER['PHP_SELF'] . "?page=attendance-page");
            exit();
        }

        // Check if already timed in today
        $check = $conn->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ?");
        $check->bind_param("is", $emp_id, $current_date);
        $check->execute();
        if ($check->get_result()->num_rows > 0) {
            $_SESSION['system_msg'] = "Error: You have already timed in for today.";
        } else {
            $att_stmt = $conn->prepare("INSERT INTO attendance (employee_id, date, time_in, status_in) VALUES (?, ?, ?, ?)");
            $att_stmt->bind_param("isss", $emp_id, $current_date, $current_time, $status);
            $att_stmt->execute();
            $_SESSION['system_msg'] = "Success: Timed In as [$status] at $current_time";
            $att_stmt->close();
        }
        $check->close();
    } 
    
    if (isset($_POST['time_out'])) {
        // Evaluation Rules for Time Out
        if ($hour_min < '17:00') {
            $status = "Early Out";
        } elseif ($hour_min >= '17:00' && $hour_min <= '18:00') {
            $status = "Normal";
        } elseif ($hour_min > '18:00' && $hour_min < '19:00') {
            $status = "Overtime";
        } else {
            // Kapag 7:00 PM onwards na pinindot or pinabayaan, automatic Overtime at mag-oout sa system
            $status = "Overtime (Auto Out)";
            $current_time = "19:00:00"; // I-force ang lock time sa 7:00 PM cutoff
        }

        // Check if time-in log exists for today
        $check = $conn->prepare("SELECT id FROM attendance WHERE employee_id = ? AND date = ?");
        $check->bind_param("is", $emp_id, $current_date);
        $check->execute();
        $res = $check->get_result();
        
        if ($res->num_rows == 0) {
            $_SESSION['system_msg'] = "Error: Cannot Time Out without initial Time In log.";
        } else {
            $att_stmt = $conn->prepare("UPDATE attendance SET time_out = ?, status_out = ? WHERE employee_id = ? AND date = ?");
            $att_stmt->bind_param("ssis", $current_time, $status, $emp_id, $current_date);
            $att_stmt->execute();
            $_SESSION['system_msg'] = "Success: Shift closed as [$status].";
            $att_stmt->close();
        }
        $check->close();
    }

    header("Location: " . $_SERVER['PHP_SELF'] . "?page=attendance-page");
    exit();
}

$notification_msg = isset($_SESSION['system_msg']) ? $_SESSION['system_msg'] : "";
unset($_SESSION['system_msg']);

// Leave History Fetching
$leave_history = [];
if ($is_employee_role && !empty($all_employees)) {
    $current_emp_id = $all_employees[0]['id'];
    $hist_stmt = $conn->prepare("SELECT * FROM leave_requests WHERE employee_id = ? ORDER BY id DESC");
    $hist_stmt->bind_param("i", $current_emp_id);
    $hist_stmt->execute();
    $leave_history = $hist_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $hist_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Core Interface</title>
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
    <script src="../LIBRARIES/tailwind.js"></script>
    <style>
        .page-section {
            transition: opacity 0.2s ease-in-out;
        }
       @media print {
    .no-print { display: none !important; }
    .print-container { border: 1px solid #000 !important; box-shadow: none !important; }
    body { background: white !important; }
}
    </style>
</head>
<body class="bg-[whitesmoke] font-sans antialiased h-screen overflow-hidden">

    <div class="flex h-screen w-full overflow-hidden">

        <div id="sidebar" class="h-screen bg-[#212121] text-white/90 p-6 flex flex-col justify-between shadow-xl flex-shrink-0 z-20" style="width: 272px; min-width: 272px; max-width: 272px;">
            <div>
                <div class="flex items-center gap-2 mb-8 px-2 text-white">
                    <div class="w-8 h-8 rounded-full bg-white/20 flex items-center justify-center">
                        <i class="bi bi-shop text-lg"></i>
                    </div>
                    <span class="font-bold text-xl tracking-wide">PannaKoda</span>
                </div>
                
                <nav class="space-y-1.5" id="sidebar-nav">
                    <?php if (!$is_employee_role): ?>
                        <a href="#" data-target="dashboard-page" class="nav-tab flex items-center gap-3.5 px-4 py-3 rounded-xl transition-all font-medium no-underline text-inherit hover:bg-white/10">
                            <i class="bi bi-grid-1x2-fill text-base"></i> Dashboard
                        </a>
                        <a href="#" data-target="recruitment-page" class="nav-tab flex items-center gap-3.5 px-4 py-3 rounded-xl transition-all font-medium no-underline text-inherit hover:bg-white/10">
                            <i class="bi bi-plus-circle-fill text-base"></i> Recruitment Management
                        </a>
                        <a href="#" data-target="applicant-page" class="nav-tab flex items-center gap-3.5 px-4 py-3 rounded-xl transition-all font-medium no-underline text-inherit hover:bg-white/10">
                            <i class="bi bi-bar-chart-line-fill text-base"></i> Applicant Management 
                        </a>
                    <?php endif; ?>

                    <a href="#" data-target="employee-page" class="nav-tab flex items-center gap-3.5 px-4 py-3 rounded-xl transition-all font-medium no-underline bg-[#FF8C00] text-white shadow-md font-semibold">
                        <i class="bi bi-people-fill text-base"></i> <?= $is_employee_role ? 'My Info & Payslip' : 'Employee Management' ?>
                    </a>

                    <?php if ($is_employee_role): ?>
                        <a href="#" data-target="leave-page" class="nav-tab flex items-center gap-3.5 px-4 py-3 rounded-xl transition-all font-medium no-underline text-inherit hover:bg-white/10">
                            <i class="bi bi-envelope-paper-fill text-base"></i> Leave Application
                        </a>
                    <?php endif; ?>

                    <a href="#" data-target="attendance-page" class="nav-tab flex items-center gap-3.5 px-4 py-3 rounded-xl transition-all font-medium no-underline text-inherit hover:bg-white/10">
                        <i class="bi bi-clock-history text-base"></i> <?= $is_employee_role ? 'Time In / Time Out' : 'Attendance & Time' ?>
                    </a>
                </nav>
            </div>
            
            <button type="button" onclick="confirmLogout()" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl text-white/70 hover:bg-white/10 hover:text-white transition-all font-medium mt-auto border border-white/20">
                <i class="bi bi-box-arrow-right text-base"></i> Log out
            </button>
        </div>

        <div class="flex-1 h-screen overflow-y-auto p-8 bg-slate-100 min-w-0">
            <div class="max-w-6xl mx-auto">
                
                <div class="flex justify-between items-center mb-8">
                    <div>
                        <h1 id="dynamic-title" class="text-3xl font-black text-orange-500 tracking-tight">
                            <?= $is_employee_role ? 'My Corporate Dashboard' : 'PannaKoda Employee Directory' ?>
                        </h1>
                        <p id="dynamic-subtitle" class="text-sm text-slate-400">Manage logs, review active payroll profiles and configurations.</p>
                    </div>
                    <span class="bg-orange-500/10 text-orange-400 text-xs px-3 py-1.5 rounded-full border border-orange-500/20 font-bold uppercase">
                        <?= $is_employee_role ? 'Employee View Mode' : 'HR Workspace Admin' ?>
                    </span>
                </div>

                <?php if(!empty($notification_msg)): ?>
                    <?php $alert_theme = strpos($notification_msg, 'Error') !== false ? 'bg-red-100 border-red-500 text-red-700' : 'bg-green-100 border-green-500 text-green-700'; ?>
                    <div class="border-l-4 p-4 mb-4 rounded-xl text-xs font-bold shadow-sm <?= $alert_theme ?>">
                        <?= htmlspecialchars($notification_msg) ?>
                    </div>
                <?php endif; ?>

                <div id="pages-container" >

                    <?php if (!$is_employee_role): ?>
                    <div id="dashboard-page" class="page-section hidden bg-white p-6  rounded-2xl border border-slate-200 shadow-sm">
                        <h3 class="text-xl font-bold mb-2">Analytics Overview</h3>
                        <p class="text-slate-500">Ito ang system dashboard section. Dito mo makikita ang stats.</p>
                    </div>

                    <div id="recruitment-page" class="page-section hidden bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                        <h3 class="text-xl font-bold mb-2">Recruitment Management</h3>
                        <p class="text-slate-500">Dito pinapamahalaan ang mga job postings at hiring workflows.</p>
                    </div>

                    <div id="applicant-page" class="page-section hidden bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                        <h3 class="text-xl font-bold mb-2">Applicant Management</h3>
                        <p class="text-slate-500">Listahan at statuses ng mga nag-apply sa kumpanya.</p>
                    </div>
                    <?php endif; ?>

                    <div id="employee-page" class="page-section">
                        <?php if(empty($all_employees)): ?>
                            <div class="p-8 bg-white text-center rounded-2xl border text-slate-500 font-medium shadow-sm">
                                No active employee account mapping found in the workspace data pipeline.
                            </div>
                        <?php else: ?>
                            <?php if ($is_employee_role): ?>
                                <?php $me = $all_employees[0]; ?>
                                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                                    <div class="lg:col-span-2 space-y-6">
                                        <div class="bg-white  p-6 rounded-2xl border border-slate-200 shadow-md">
                                            <h4 class="text-md font-bold text-slate-800 mb-4 border-b pb-2 text-orange-500"><i class="bi bi-person-lines-fill mr-2"></i>Employee Profile Data</h4>
                                            <div class="grid grid-cols-2 gap-y-4 gap-x-2 text-xs text-slate-700">
                                                <div><span class="text-slate-400 font-bold block uppercase">Full Name:</span> <span class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($me['full_name']) ?></span></div>
                                                <div><span class="text-slate-400 font-bold block uppercase">Assigned Station:</span> <span class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($me['department']) ?></span></div>
                                                <div><span class="text-slate-400 font-bold block uppercase">Employee ID:</span> <span class="font-mono font-bold text-indigo-600 text-sm"><?= htmlspecialchars($me['display_emp_id']) ?></span></div>
                                                <div><span class="text-slate-400 font-bold block uppercase">Contact Line:</span> <span class="text-sm font-semibold text-slate-900"><?= htmlspecialchars($me['phone']) ?></span></div>
                                                <div class="col-span-2"><span class="text-slate-400 font-bold block uppercase">Complete Address:</span> <span class="text-slate-900"><?= htmlspecialchars($me['address']) ?></span></div>
                                            </div>
                                        </div>
                                        <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-md">
                                            <h4 class="text-md font-bold text-slate-800 mb-4 border-b pb-2 text-indigo-500"><i class="bi bi-credit-card-2-front mr-2"></i>Statutory Benefits ID Records</h4>
                                            <div class="grid grid-cols-2 md:grid-cols-4 gap-3 font-mono text-xs">
                                                <div class="p-2.5 bg-slate-50 border rounded-xl"><strong>GSIS ID:</strong><br><span class="text-slate-600"><?= htmlspecialchars($me['gsis_id'] ?: '—') ?></span></div>
                                                <div class="p-2.5 bg-slate-50 border rounded-xl"><strong>SSS NO:</strong><br><span class="text-slate-600"><?= htmlspecialchars($me['sss_id'] ?: '—') ?></span></div>
                                                <div class="p-2.5 bg-slate-50 border rounded-xl"><strong>PHILHEALTH:</strong><br><span class="text-slate-600"><?= htmlspecialchars($me['philhealth_id'] ?: '—') ?></span></div>
                                                <div class="p-2.5 bg-slate-50 border rounded-xl"><strong>PAG-IBIG:</strong><br><span class="text-slate-600"><?= htmlspecialchars($me['pagibig_id'] ?: '—') ?></span></div>
                                            </div>
                                        </div>
                                        <button onclick="triggerPayslip(<?= $me['id'] ?>)" class="w-full py-3.5 bg-indigo-600 hover:bg-indigo-700 text-white font-black rounded-xl shadow-lg transition-all text-xs uppercase tracking-wider flex items-center justify-center gap-2">
                                            <i class="bi bi-file-earmark-pdf-fill text-sm"></i> Launch Active Pay Summary Sheet
                                        </button>
                                    </div>
                                </div>
                            <?php else: ?>
                                <div class="rounded-2xl border border-slate-200 shadow-xl bg-white overflow-hidden">
                                    <table class="w-full text-left border-collapse">
                                        <thead>
                                            <tr class="bg-slate-50 border-b text-xs font-bold uppercase tracking-wider text-slate-700">
                                                <th class="p-4">Emp ID</th>
                                                <th class="p-4">Full Name</th>
                                                <th class="p-4">System Corporate Account</th>
                                                <th class="p-4">Deployment</th>
                                                <th class="p-4 text-right">Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody class="divide-y text-sm text-slate-800">
                                            <?php foreach ($all_employees as $emp): ?>
                                                <tr class="hover:bg-slate-50/80 transition-colors">
                                                    <td class="p-4 font-mono font-bold text-indigo-600"><?= htmlspecialchars($emp['display_emp_id']) ?></td>
                                                    <td class="p-4 font-semibold"><?= htmlspecialchars($emp['full_name']) ?></td>
                                                    <td class="p-4">
                                                        <div class="text-xs font-medium text-slate-700"><?= htmlspecialchars($emp['employee_gmail']) ?></div>
                                                        <div class="text-[11px] text-slate-400">Phone: <?= htmlspecialchars($emp['phone']) ?></div>
                                                    </td>
                                                    <td class="p-4">
                                                        <span class="px-2.5 py-0.5 bg-slate-100 text-slate-800 text-xs font-bold rounded-full border border-slate-200">
                                                            <?= htmlspecialchars($emp['department']) ?>
                                                        </span>
                                                    </td>
                                                    <td class="p-4 text-right">
                                                        <button onclick="triggerPayslip(<?= $emp['id'] ?>)" class="px-3 py-1.5 bg-indigo-600 hover:bg-indigo-50 text-white font-bold text-xs rounded-lg shadow-sm transition-all">
                                                            Open Payslip Sheet
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                            <?php endif; ?>
                        <?php endif; ?>
                    </div>

                    <?php if ($is_employee_role && !empty($all_employees)): ?>
                    <div id="leave-page" class="page-section hidden">
                        <div class="grid grid-cols-1 lg:grid-cols-2 gap-8">
                            <div>
                                <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-md">
                                    <h4 class="text-md font-black text-slate-800 uppercase tracking-tight mb-4"><i class="bi bi-envelope-paper-fill mr-2 text-orange-500"></i>Fill Up Leave Form</h4>
                                    <form method="POST" class="space-y-4">
                                        <input type="hidden" name="employee_id" value="<?= $all_employees[0]['id'] ?>">
                                        <div>
                                            <label class="text-xs font-bold">Leave Type</label>
                                            <select name="leave_type" class="w-full p-2 border rounded-xl" required>
                                                <option value="Sick Leave">Sick Leave</option>
                                                <option value="Vacation Leave">Vacation Leave</option>
                                            </select>
                                        </div>
                                        <div>
                                            <label class="text-xs font-bold">Reason</label>
                                            <textarea name="reason" class="w-full p-2 border rounded-xl" required></textarea>
                                        </div>
                                        <button type="submit" name="apply_leave" class="w-full py-2.5 bg-orange-500 hover:bg-orange-600 text-white font-bold text-xs rounded-xl transition-all">
                                            Dispatch Application Node
                                        </button>
                                    </form>
                                </div>
                            </div>
                            <div class="bg-white p-6 rounded-2xl border border-slate-200 shadow-md">
                                <h4 class="text-md font-black text-slate-800 uppercase tracking-tight mb-4">Request Status</h4>
                                <div class="space-y-3 max-h-[400px] overflow-y-auto">
                                    <?php if(empty($leave_history)): ?>
                                        <p class="text-xs text-slate-400">No records found.</p>
                                    <?php else: ?>
                                        <?php foreach($leave_history as $req): 
                                            $status_color = ($req['status'] == 'Approved') ? 'bg-green-100 text-green-700' : 
                                                            (($req['status'] == 'Rejected') ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700');
                                        ?>
                                            <div class="p-3 border rounded-xl flex justify-between items-center">
                                                <div>
                                                    <p class="text-xs font-bold"><?= htmlspecialchars($req['leave_type']) ?></p>
                                                    <p class="text-[10px] text-slate-500 truncate w-40"><?= htmlspecialchars($req['reason']) ?></p>
                                                </div>
                                                <span class="px-2 py-1 text-[10px] font-bold rounded-full <?= $status_color ?>">
                                                    <?= htmlspecialchars($req['status']) ?>
                                                </span>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </div>
                    </div>
                    <?php endif; ?>

                    <div id="attendance-page" class="page-section hidden bg-white p-6 rounded-2xl border border-slate-200 shadow-sm">
                        <h3 class="text-xl font-bold mb-2"><?= $is_employee_role ? 'Time In / Time Out Engine' : 'Attendance Logs Data Pipeline' ?></h3>
                        <p class="text-slate-500 mb-4">Current Server Time Node: <span class="font-mono bg-slate-200 px-2 py-0.5 rounded text-sm font-bold"><?= date('h:i A') ?></span></p>
                        
                        <?php if ($is_employee_role && !empty($all_employees)): ?>
                            <form method="POST" class="flex gap-4">
                                <input type="hidden" name="employee_id" value="<?= $all_employees[0]['id'] ?>">
                                <button type="submit" name="time_in" class="px-6 py-3 bg-emerald-600 text-white font-bold rounded-xl text-xs uppercase shadow-md hover:bg-emerald-700">Time In</button>
                                <button type="submit" name="time_out" class="px-6 py-3 bg-rose-600 text-white font-bold rounded-xl text-xs uppercase shadow-md hover:bg-rose-700">Time Out</button>
                            </form>
                        <?php else: ?>
                            <div class="p-4 bg-slate-50 rounded-xl border text-xs font-mono text-slate-600">
                                [System Log] Biometric pipeline active. Server monitoring operational nodes...
                            </div>
                        <?php endif; ?>
                    </div>

                </div>
            </div>
        </div>
    </div>

    <div class="modal fade" id="payslipModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-md modal-dialog-centered">
            <div class="modal-content border-0 rounded-2xl overflow-hidden shadow-2xl">
                <div class="p-2 bg-slate-900 flex justify-end">
                    <button type="button" class="btn-close btn-close-white px-3" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body bg-slate-100 p-4" id="printArea"></div>
                <div class="bg-slate-50 p-3 border-t flex justify-end gap-2">
                    <button type="button" class="px-4 py-2 bg-slate-600 text-white font-bold rounded-xl text-xs" data-bs-dismiss="modal">Close</button>
                    <button type="button" onclick="window.print()" class="px-4 py-2 bg-emerald-600 text-white font-bold rounded-xl text-xs"><i class="bi bi-printer mr-1"></i> Print</button>
                </div>
            </div>
        </div>
    </div>

    <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>

    <script>
        function confirmLogout() {
            Swal.fire({
                title: 'Log Out?',
                text: "Are you sure you want to Log out?",
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: '#FF8C00',
                cancelButtonColor: '#475569',
                confirmButtonText: 'Yes',
                cancelButtonText: 'Cancel'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = 'logout.php';
                }
            });
        }

        document.addEventListener("DOMContentLoaded", function () {
            const tabs = document.querySelectorAll(".nav-tab");
            const sections = document.querySelectorAll(".page-section");
            const dynamicTitle = document.getElementById("dynamic-title");
            const dynamicSubtitle = document.getElementById("dynamic-subtitle");

            const pageMeta = {
                "dashboard-page": { title: "Analytics Dashboard", sub: "Real-time metrics tracking and performance nodes." },
                "recruitment-page": { title: "Recruitment Workspace", sub: "Control current corporate recruitment pipelines." },
                "applicant-page": { title: "Applicants Registry", sub: "Review system talent screening logs." },
                "employee-page": { 
                    title: "<?= $is_employee_role ? 'My Corporate Dashboard' : 'PannaKoda Employee Directory' ?>", 
                    sub: "Manage logs, review active payroll profiles and configurations." 
                },
                "leave-page": { title: "Leave Application Request", sub: "File and submit digital time-off records." },
                "attendance-page": { title: "Attendance Tracking Node", sub: "Biometric clock parameters and logs." }
            };

            function switchPage(targetId) {
                sections.forEach(sec => sec.classList.add("hidden"));
                const activeSection = document.getElementById(targetId);
                if (activeSection) activeSection.classList.remove("hidden");

                tabs.forEach(t => {
                    if (t.getAttribute("data-target") === targetId) {
                        t.classList.add("bg-[#FF8C00]", "text-white", "shadow-md", "font-semibold");
                        t.classList.remove("hover:bg-white/10", "text-inherit");
                    } else {
                        t.classList.remove("bg-[#FF8C00]", "text-white", "shadow-md", "font-semibold");
                        t.classList.add("hover:bg-white/10", "text-inherit");
                    }
                });

                if (pageMeta[targetId]) {
                    dynamicTitle.innerText = pageMeta[targetId].title;
                    dynamicSubtitle.innerText = pageMeta[targetId].sub;
                }
            }

            tabs.forEach(tab => {
                tab.addEventListener("click", function (e) {
                    e.preventDefault();
                    const targetId = this.getAttribute("data-target");
                    switchPage(targetId);
                    history.pushState(null, '', `?page=${targetId}`);
                });
            });

            const urlParams = new URLSearchParams(window.location.search);
            const initialPage = urlParams.get('page');
            if (initialPage && document.getElementById(initialPage)) {
                switchPage(initialPage);
            } else {
                switchPage("employee-page"); 
            }
        });

        const allEmployees = <?= json_encode($all_employees) ?>;
        let bsModalInstance = null;

        function triggerPayslip(dbId) {
            const emp = allEmployees.find(e => e.id == dbId);
            if (!emp) return;

            const modalBody = document.getElementById('printArea');
            
            function renderPayslip(mode = 'monthly') {
                const isManager = (emp.department === 'Manager' || emp.department === 'HR Department');
                const isMWE = true; // Minimum Wage Earner flag
                
                // Base calculations depending on Monthly or Kinsenas (Semi-Monthly)
                const baseMonthly = isManager ? 20000 : 15000;
                const divisor = (mode === 'monthly') ? 1 : 2;
                
                const dailyRate = baseMonthly / 22; // Assuming 22 working days a month
                const daysWorked = mode === 'monthly' ? 22 : 11;
                const basicPay = baseMonthly / divisor;
                
                // Allowances & Incentives
                const allowances = (mode === 'monthly' ? 1000 : 500);
                const incentives = (mode === 'monthly' ? 500 : 250);
                const overtimePay = (mode === 'monthly' ? 850 : 425);
                const holidayPay = (mode === 'monthly' ? 645 : 322.50);

                const grossPay = basicPay + allowances + incentives + overtimePay + holidayPay;
                
                // Government Deductions (pro-rated if semi-monthly)
                const sss = (isManager ? 900 : 675) / divisor;
                const phil = (isManager ? 400 : 300) / divisor;
                const pagibig = 100 / divisor;
                const withholdingTax = 0.00; // MWE Tax Exempt
                
                const lateDeduction = 0.00;
                const absenceDeduction = 0.00;

                const totalDeductions = sss + phil + pagibig + withholdingTax + lateDeduction + absenceDeduction;
                const netPay = grossPay - totalDeductions;

                const payPeriod = mode === 'monthly' ? 'July 1–31, 2026' : 'July 1–15, 2026';
                const payDate = mode === 'monthly' ? 'August 1, 2026' : 'July 18, 2026';

                return `
                    <div class="bg-white p-6 rounded-xl border print-container shadow-sm text-xs font-sans text-slate-800">
                        <div class="flex justify-between items-start border-b pb-4 mb-4">
                            <div>
                                <h2 class="font-black text-base text-slate-900 uppercase tracking-wide">PannaKoda Enterprise</h2>
                                <p class="text-[10px] text-slate-500 uppercase tracking-widest font-semibold">Official Payslip Statement (${mode.toUpperCase()})</p>
                                <p class="text-[9px] text-slate-400 mt-0.5">TIN: 000-123-456-000 | Dasmariñas, Cavite</p>
                            </div>
                            <div class="text-right">
                                <span class="px-2.5 py-1 bg-slate-100 font-bold rounded-md uppercase text-[10px]">${mode} View</span>
                                <p class="text-[10px] text-slate-400 mt-1">Pay Period: ${payPeriod}</p>
                                <p class="text-[10px] text-slate-400">Pay Date: ${payDate}</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-2 gap-4 mb-4 bg-slate-50 p-3 rounded-lg border border-slate-100">
                            <div>
                                <span class="text-slate-400 block font-bold text-[9px] uppercase">Employee Name:</span>
                                <span class="font-bold text-slate-900">${emp.full_name}</span>
                                <span class="block text-[10px] text-indigo-600 font-semibold">${isMWE ? '★ Minimum Wage Earner (Tax Exempt)' : 'Regular Wage Earner'}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-bold text-[9px] uppercase">Employee ID:</span>
                                <span class="font-mono font-bold text-indigo-600">${emp.display_emp_id}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-bold text-[9px] uppercase">Position / Dept:</span>
                                <span class="font-semibold text-slate-700">${emp.department}</span>
                            </div>
                            <div>
                                <span class="text-slate-400 block font-bold text-[9px] uppercase">Contact Line:</span>
                                <span class="font-semibold text-slate-700">${emp.phone}</span>
                            </div>
                        </div>

                        <div class="mb-4 no-print flex gap-2">
                            <button onclick="window.switchPayslipMode('${dbId}', 'monthly')" class="px-2.5 py-1 rounded bg-slate-200 hover:bg-slate-300 font-bold text-[10px] ${mode === 'monthly' ? 'bg-indigo-600 text-white hover:bg-indigo-700' : ''}">Monthly</button>
                            <button onclick="window.switchPayslipMode('${dbId}', 'semi-monthly')" class="px-2.5 py-1 rounded bg-slate-200 hover:bg-slate-300 font-bold text-[10px] ${mode === 'semi-monthly' ? 'bg-indigo-600 text-white hover:bg-indigo-700' : ''}">Kinsenas (Semi-Monthly)</button>
                        </div>

                        <div class="space-y-3">
                            <div>
                                <h4 class="font-bold text-slate-700 uppercase tracking-wide text-[10px] mb-1.5 border-b pb-1">Earnings</h4>
                                <div class="flex justify-between py-1 border-b border-dashed">
                                    <span class="text-slate-600">Basic Pay (Daily Rate: ₱${dailyRate.toFixed(2)} × ${daysWorked} days)</span>
                                    <span class="font-mono font-semibold">₱${basicPay.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-dashed">
                                    <span class="text-slate-600">Overtime Pay</span>
                                    <span class="font-mono font-semibold">₱${overtimePay.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-dashed">
                                    <span class="text-slate-600">Holiday Pay</span>
                                    <span class="font-mono font-semibold">₱${holidayPay.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-dashed">
                                    <span class="text-slate-600">Allowances (Meal, Transpo, Rice)</span>
                                    <span class="font-mono font-semibold">₱${allowances.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-dashed">
                                    <span class="text-slate-600">Incentives / Bonus</span>
                                    <span class="font-mono font-semibold">₱${incentives.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                </div>
                                <div class="flex justify-between py-1.5 font-bold text-slate-900 bg-slate-50 px-1 rounded mt-1">
                                    <span>Gross Pay</span>
                                    <span class="font-mono">₱${grossPay.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                </div>
                            </div>

                            <div>
                                <h4 class="font-bold text-slate-700 uppercase tracking-wide text-[10px] mb-1.5 border-b pb-1">Deductions</h4>
                                <div class="flex justify-between py-1 border-b border-dashed">
                                    <span class="text-slate-600">SSS Contribution</span>
                                    <span class="font-mono font-semibold text-rose-600">-₱${sss.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-dashed">
                                    <span class="text-slate-600">PhilHealth Contribution</span>
                                    <span class="font-mono font-semibold text-rose-600">-₱${phil.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-dashed">
                                    <span class="text-slate-600">Pag-IBIG Contribution</span>
                                    <span class="font-mono font-semibold text-rose-600">-₱${pagibig.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-dashed">
                                    <span class="text-slate-600">Withholding Tax (BIR Tax Exempt for MWE)</span>
                                    <span class="font-mono font-semibold text-slate-500">₱${withholdingTax.toFixed(2)}</span>
                                </div>
                                <div class="flex justify-between py-1 border-b border-dashed">
                                    <span class="text-slate-600">Absences / Late / Undertime</span>
                                    <span class="font-mono font-semibold text-rose-600">-₱${(lateDeduction + absenceDeduction).toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                </div>
                                <div class="flex justify-between py-1.5 font-bold text-rose-700 bg-rose-50/50 px-1 rounded mt-1">
                                    <span>Total Deductions</span>
                                    <span class="font-mono">-₱${totalDeductions.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                                </div>
                            </div>

                            <div class="pt-2 bg-indigo-50 p-3 rounded-lg border border-indigo-100 flex justify-between items-center mt-4">
                                <div>
                                    <span class="font-black text-indigo-900 block uppercase tracking-wider text-[11px]">Net Take-Home Pay</span>
                                    <span class="text-[9px] text-indigo-500">Computed via standard Philippine labor & tax compliance</span>
                                </div>
                                <span class="font-mono font-black text-indigo-700 text-sm">₱${netPay.toLocaleString(undefined, {minimumFractionDigits: 2, maximumFractionDigits: 2})}</span>
                            </div>
                        </div>
                    </div>
                `;
            }

            // Attach handler globally to support switching views dynamically inside modal
            window.switchPayslipMode = function(id, mode) {
                modalBody.innerHTML = renderPayslip(mode);
            };

            modalBody.innerHTML = renderPayslip('monthly');

            if (!bsModalInstance) {
                bsModalInstance = new bootstrap.Modal(document.getElementById('payslipModal'));
            }
            bsModalInstance.show();
        }
    </script>
</body>
</html>