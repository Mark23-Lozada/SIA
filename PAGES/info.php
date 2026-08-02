<?php
session_start();

if (!isset($_SESSION['user_id']) && !isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$host = "localhost";
$user = "root"; 
$pass = ""; 
$dbname = "pos";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}

$user_id = $_SESSION['user_id'] ?? 0;
$stmt = $conn->prepare("SELECT * FROM employees WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();
$employee = $result->fetch_assoc();

if (!$employee) {
    echo "Employee record not found.";
    exit();
}

// Philippine Statutory Contribution Computation Function in PHP
function computePHPayrollPHP($monthly_base) {
    $sss = round($monthly_base * 0.045, 2);
    if ($sss < 135) $sss = 135;
    if ($sss > 1350) $sss = 1350;

    $philhealth = round($monthly_base * 0.025, 2);
    if ($philhealth < 250) $philhealth = 250;
    if ($philhealth > 2500) $philhealth = 2500;

    $pagibig = round($monthly_base * 0.02, 2);
    if ($pagibig > 200) $pagibig = 200;

    $total_statutory = $sss + $philhealth + $pagibig;
    $taxable_income = max(0, $monthly_base - $total_statutory);
    $tax = 0.00;

    if ($taxable_income > 20833 && $taxable_income <= 33333) {
        $tax = round(($taxable_income - 20833) * 0.15, 2);
    } elseif ($taxable_income > 33333 && $taxable_income <= 66667) {
        $tax = round(1875 + ($taxable_income - 33333) * 0.20, 2);
    } elseif ($taxable_income > 66667 && $taxable_income <= 166667) {
        $tax = round(8541.80 + ($taxable_income - 66667) * 0.25, 2);
    } elseif ($taxable_income > 166667 && $taxable_income <= 666667) {
        $tax = round(33541.80 + ($taxable_income - 166667) * 0.30, 2);
    } elseif ($taxable_income > 666667) {
        $tax = round(183541.80 + ($taxable_income - 666667) * 0.35, 2);
    }

    return [
        'sss' => $sss,
        'philhealth' => $philhealth,
        'pagibig' => $pagibig,
        'tax' => $tax,
        'total_deductions' => $total_statutory + $tax
    ];
}

$role = $employee['position_title'] ?? $employee['role'] ?? 'Staff';
$monthly_base = isset($employee['salary']) && $employee['salary'] > 0 ? floatval($employee['salary']) : ($role === 'Manager' ? 45000 : 22000);
$monthly_allowance = 2000; 
$monthly_gross = $monthly_base + $monthly_allowance;

$ph = computePHPayrollPHP($monthly_base);
$monthly_net = $monthly_gross - $ph['total_deductions'];

$kinsenas_base = $monthly_base / 2;
$kinsenas_allowance = $monthly_allowance / 2;
$kinsenas_gross = $kinsenas_base + $kinsenas_allowance;

$kinsenas_sss = $ph['sss'] / 2;
$kinsenas_philhealth = $ph['philhealth'] / 2;
$kinsenas_pagibig = $ph['pagibig'] / 2;
$kinsenas_tax = $ph['tax'] / 2;
$kinsenas_deductions = $kinsenas_sss + $kinsenas_philhealth + $kinsenas_pagibig + $kinsenas_tax;
$kinsenas_net = $kinsenas_gross - $kinsenas_deductions;

// Date calculations para sa cut-off period text
$day = date('j');
$monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
$currentMonthYear = $monthNames[date('n') - 1] . ' ' . date('Y');
$cutOffPeriod = $day <= 15 ? "1st Cut-off (1–15, $currentMonthYear)" : "2nd Cut-off (16–31, $currentMonthYear)";
$payDateStr = date('M j, Y');

// Handle Leave Request Submission
$leave_msg = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_leave'])) {
    $leave_type = $_POST['leave_type'] ?? '';
    $reason = $_POST['reason'] ?? '';
    $status = 'Pending'; 

    $l_stmt = $conn->prepare("INSERT INTO leave_requests (employee_id, leave_type, reason, status) VALUES (?, ?, ?, ?)");
    $l_stmt->bind_param("isss", $user_id, $leave_type, $reason, $status);
    if ($l_stmt->execute()) {
        $leave_msg = "Leave request successfully submitted to HR screening!";
    }
    $l_stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Employee Portal - PannaKoda</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
    <style>
        @media print {
            body * { visibility: hidden; }
            #printArea, #printArea * { visibility: visible; }
            #printArea { position: absolute; left: 0; top: 0; width: 100%; padding: 0; margin: 0; background: white !important; color: black !important; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-zinc-100 font-sans antialiased h-screen overflow-hidden">

<div class="flex h-screen w-full overflow-hidden">
    
    <!-- MODERN SIDEBAR -->
    <div class="w-64 bg-zinc-900 text-zinc-300 flex flex-col justify-between border-r border-zinc-800 shrink-0">
        <div>
            <div class="p-6 border-b border-zinc-800">
                <h2 class="text-white font-black text-lg tracking-wider flex items-center gap-2">
                    <i class="bi bi-hexagon-fill text-[#FF8C00]"></i> PANNAKODA
                </h2>
                <p class="text-[10px] text-zinc-500 uppercase mt-0.5">Employee Portal</p>
            </div>

            <nav class="p-4 space-y-1.5 text-xs font-semibold">
                <a href="info.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-[#FF8C00] text-white shadow-md">
                    <i class="bi bi-person-badge text-base"></i> My Profile
                </a>
                
                <button onclick="openAttendanceModal()" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/10 hover:text-white transition-all text-left">
                    <i class="bi bi-geo-alt-fill text-base text-emerald-400"></i> Geo Attendance
                </button>

                <button onclick="openLeaveModal()" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/10 hover:text-white transition-all text-left">
                    <i class="bi bi-calendar-plus text-base text-amber-400"></i> Request Leave
                </button>

                <button onclick="openPayslipModal()" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-white/10 hover:text-white transition-all text-left">
                    <i class="bi bi-wallet2 text-base text-indigo-400"></i> Payslip Statement
                </button>
            </nav>
        </div>

        <div class="p-4 border-t border-zinc-800">
            <a href="logout.php" id="logoutBtn" class="flex items-center gap-3 px-4 py-2.5 rounded-xl bg-rose-500/10 text-rose-400 hover:bg-rose-500 hover:text-white transition-all text-xs font-bold">
                <i class="bi bi-box-arrow-right text-base"></i> Logout
            </a>
        </div>
    </div>

    <!-- MAIN CONTENT AREA -->
    <div class="flex-1 h-screen overflow-y-auto p-6 md:p-10 bg-zinc-100">
        <div class="max-w-5xl mx-auto space-y-6">
            
            <!-- Header Profile Banner -->
            <div class="bg-gradient-to-r from-zinc-900 via-zinc-800 to-zinc-900 text-white rounded-3xl p-8 shadow-xl border border-zinc-800 relative overflow-hidden flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
                <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-[#FF8C00]/10 rounded-full blur-3xl pointer-events-none"></div>
                
                <div class="flex items-center gap-5 z-10">
                    <div class="w-20 h-20 rounded-2xl bg-gradient-to-br from-[#FF8C00] to-orange-600 flex items-center text-white justify-center text-3xl font-black shadow-lg shadow-orange-500/20">
                        <?= strtoupper(substr($employee['full_name'], 0, 1)) ?>
                    </div>
                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/15 backdrop-blur-md text-[#FF8C00] text-xs font-bold uppercase tracking-wider mb-2 border border-white/5">
                            <i class="bi bi-shield-check"></i> <?= htmlspecialchars($role) ?>
                        </div>
                        <h1 class="text-2xl md:text-3xl font-black tracking-tight text-white">
                            <?php echo htmlspecialchars($employee['full_name']); ?>
                        </h1>
                        <p class="text-xs text-zinc-400 mt-1 font-mono">
                            ID: <?= htmlspecialchars($employee['employee_id'] ?? 'EMP-' . str_pad($employee['id'], 4, '0', STR_PAD_LEFT)) ?> &bull; Dept: <?= htmlspecialchars($employee['department'] ?? 'Unassigned') ?>
                        </p>
                    </div>
                </div>

               
            </div>

            <!-- Credentials Grid Details -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="md:col-span-2 space-y-6">
                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-zinc-200">
                        <h3 class="text-xs font-black text-zinc-400 uppercase tracking-wider mb-4 flex items-center gap-2">
                            <i class="bi bi-person-lines-fill text-[#FF8C00]"></i> Personal & Employment Details
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                            <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-100">
                                <span class="block text-[11px] font-bold text-zinc-400 uppercase">Full Name</span>
                                <span class="font-bold text-zinc-800 mt-0.5 block"><?php echo htmlspecialchars($employee['full_name']); ?></span>
                            </div>
                            <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-100">
                                <span class="block text-[11px] font-bold text-zinc-400 uppercase">Employee ID</span>
                                <span class="font-mono font-bold text-indigo-600 mt-0.5 block"><?php echo htmlspecialchars($employee['employee_id'] ?? 'EMP-2026-...'); ?></span>
                            </div>
                            <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-100">
                                <span class="block text-[11px] font-bold text-zinc-400 uppercase">Department</span>
                                <span class="font-bold text-zinc-800 mt-0.5 block"><?php echo htmlspecialchars($employee['department'] ?? 'Unassigned'); ?></span>
                            </div>
                            <div class="p-3.5 rounded-2xl bg-zinc-50 border border-zinc-100">
                                <span class="block text-[11px] font-bold text-zinc-400 uppercase">Position / Role</span>
                                <span class="font-bold text-zinc-800 mt-0.5 block"><?php echo htmlspecialchars($role); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Government Identifiers -->
                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-zinc-200">
                        <h3 class="text-xs font-black text-zinc-400 uppercase tracking-wider mb-4 flex items-center gap-2">
                            <i class="bi bi-shield-shaded text-emerald-500"></i> Government Mandated Identifiers
                        </h3>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                            <div class="bg-zinc-50 p-3.5 rounded-2xl border border-zinc-100">
                                <span class="block text-[10px] text-zinc-400 font-bold uppercase">SSS No.</span>
                                <span class="font-mono text-zinc-800 font-bold mt-1 block text-xs">33-1234567-8</span>
                            </div>
                            <div class="bg-zinc-50 p-3.5 rounded-2xl border border-zinc-100">
                                <span class="block text-[10px] text-zinc-400 font-bold uppercase">PhilHealth</span>
                                <span class="font-mono text-zinc-800 font-bold mt-1 block text-xs">12-345678901-2</span>
                            </div>
                            <div class="bg-zinc-50 p-3.5 rounded-2xl border border-zinc-100">
                                <span class="block text-[10px] text-zinc-400 font-bold uppercase">Pag-IBIG</span>
                                <span class="font-mono text-zinc-800 font-bold mt-1 block text-xs">1210-9876-5432</span>
                            </div>
                            <div class="bg-zinc-50 p-3.5 rounded-2xl border border-zinc-100">
                                <span class="block text-[10px] text-zinc-400 font-bold uppercase">GSIS No.</span>
                                <span class="font-mono text-zinc-800 font-bold mt-1 block text-xs">N/A</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Access Emails -->
                <div class="space-y-6">
                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-zinc-200 flex flex-col justify-between h-full">
                        <div>
                            <h3 class="text-xs font-black text-zinc-400 uppercase tracking-wider mb-4 flex items-center gap-2">
                                <i class="bi bi-envelope-at-fill text-indigo-500"></i> System Access Emails
                            </h3>
                            <div class="space-y-4">
                                <div class="p-4 rounded-2xl bg-indigo-50/50 border border-indigo-100">
                                    <span class="block text-[10px] text-indigo-600 font-bold uppercase tracking-wide">Employee Email</span>
                                    <span class="font-mono font-bold text-indigo-900 text-xs block mt-1 break-all">
                                        <?php echo htmlspecialchars($employee['employee_gmail'] ?? 'Not set'); ?>
                                    </span>
                                </div>
                                <div class="p-4 rounded-2xl bg-emerald-50/50 border border-emerald-100">
                                    <span class="block text-[10px] text-emerald-600 font-bold uppercase tracking-wide">Company Gmail</span>
                                    <span class="font-mono font-bold text-emerald-900 text-xs block mt-1 break-all">
                                        <?php echo htmlspecialchars($employee['company_gmail'] ?? 'Not set'); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<!-- LEAVE REQUEST MODAL -->
<div id="leaveModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl border border-zinc-200">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-black text-zinc-800"><i class="bi bi-calendar-plus text-amber-500"></i> File Leave Request</h3>
            <button onclick="closeLeaveModal()" class="text-zinc-400 hover:text-zinc-700 font-bold text-lg"><i class="bi bi-x-lg"></i></button>
        </div>
        <form method="POST" class="space-y-4">
            <div>
                <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Leave Type</label>
                <select name="leave_type" required class="w-full bg-zinc-50 border border-zinc-200 rounded-xl p-3 text-sm font-semibold text-zinc-700 focus:outline-orange-500">
                    <option value="Vacation Leave">Vacation Leave</option>
                    <option value="Sick Leave">Sick Leave</option>
                    <option value="Emergency Leave">Emergency Leave</option>
                </select>
            </div>
            <div>
                <label class="block text-xs font-bold text-zinc-500 uppercase mb-1">Reason Statement</label>
                <textarea name="reason" rows="3" required placeholder="Iahad ang dahilan ng iyong pagliban..." class="w-full bg-zinc-50 border border-zinc-200 rounded-xl p-3 text-sm font-semibold text-zinc-700 focus:outline-orange-500"></textarea>
            </div>
            <button type="submit" name="submit_leave" class="w-full bg-[#FF8C00] hover:bg-orange-600 text-white font-bold py-3 rounded-xl transition-all shadow-lg shadow-orange-500/20 text-sm">
                Submit Leave Application
            </button>
        </form>
    </div>
</div>

<!-- GEOLOCATION ATTENDANCE MODAL -->
<div id="attendanceModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-md rounded-3xl p-6 shadow-2xl border border-zinc-200 text-center">
        <div class="flex justify-between items-center mb-4">
            <h3 class="text-lg font-black text-zinc-800"><i class="bi bi-geo-alt-fill text-emerald-500"></i> Geo-Location Attendance</h3>
            <button onclick="closeAttendanceModal()" class="text-zinc-400 hover:text-zinc-700 font-bold text-lg"><i class="bi bi-x-lg"></i></button>
        </div>
        <p class="text-xs text-zinc-500 mb-6">Piliin kung magpapatala ka ng Time In o Time Out gamit ang iyong GPS coordinates.</p>
        
        <div id="geoStatus" class="mb-4 text-xs font-mono font-bold text-amber-600 bg-amber-50 p-2.5 rounded-xl border border-amber-200">
            Naghihintay ng GPS Location...
        </div>

        <div class="grid grid-cols-2 gap-3">
            <button onclick="recordAttendance('time_in')" id="btnTimeIn" disabled class="bg-emerald-600 hover:bg-emerald-700 text-white font-bold py-3 rounded-xl transition-all shadow-md text-xs opacity-50 cursor-not-allowed">
                <i class="bi bi-box-arrow-in-right"></i> RECORD TIME IN
            </button>
            <button onclick="recordAttendance('time_out')" id="btnTimeOut" disabled class="bg-rose-600 hover:bg-rose-700 text-white font-bold py-3 rounded-xl transition-all shadow-md text-xs opacity-50 cursor-not-allowed">
                <i class="bi bi-box-arrow-out-right"></i> RECORD TIME OUT
            </button>
        </div>
    </div>
</div>

<!-- EXACT PAYSLIP STATEMENT MODAL (PH Standards) -->
<div id="payslipModal" class="fixed inset-0 bg-black/60 backdrop-blur-sm z-50 hidden flex items-center justify-center p-4">
    <div class="bg-white w-full max-w-2xl rounded-3xl shadow-2xl border border-zinc-200 flex flex-col overflow-hidden">
        <div class="flex justify-between items-center bg-slate-50 px-6 py-4 border-b no-print">
            <h5 class="modal-title font-bold text-gray-800 flex items-center gap-2 text-sm">
                <i class="bi bi-receipt text-emerald-600"></i> Corporate Payroll Statement (PH Standards)
            </h5>
            <button onclick="closePayslipModal()" class="text-zinc-400 hover:text-zinc-700 font-bold text-lg"><i class="bi bi-x-lg"></i></button>
        </div>
        
        <div class="p-6 overflow-y-auto max-h-[75vh]" id="printArea">
            <div class="border border-gray-300 p-6 bg-white rounded-xl text-gray-800 text-xs shadow-sm">
                <div class="text-center border-b pb-4 mb-4">
                    <h3 class="font-black text-xl tracking-wide uppercase text-gray-900"><?= htmlspecialchars($employee['company_name'] ?? 'PannaKoda Stores Inc.') ?></h3>
                    <p class="text-[11px] text-gray-500 font-medium"><?= htmlspecialchars($employee['company_address'] ?? '123 Business Corporate Center, Cavite, Philippines') ?></p>
                    <p class="text-[11px] text-gray-400 font-mono">TIN: 000-123-456-000 &bull; SSS Employer No: 03-9876543-2</p>
                    <div class="mt-2 inline-block bg-slate-100 text-slate-800 font-mono text-[11px] font-bold px-3 py-1 rounded">
                        OFFICIAL PAYSLIP STATEMENT | <?= $cutOffPeriod ?>
                    </div>
                </div>
                
                <div class="grid grid-cols-2 gap-4 mb-4 border-b pb-4 bg-slate-50/60 p-3 rounded-lg">
                   <div>
                    <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Employee ID:</span> <span class="font-mono font-bold text-gray-800"><?= htmlspecialchars($employee['employee_id'] ?? 'EMP-' . str_pad($employee['id'], 4, '0', STR_PAD_LEFT)) ?></span></p>
                    <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Employee Name:</span> <span class="font-bold text-gray-800"><?= htmlspecialchars($employee['full_name']) ?></span></p>
                    <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Department:</span> <span class="font-semibold text-gray-800"><?= htmlspecialchars($employee['department'] ?? 'Unassigned') ?></span></p>
                    <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Tax Status:</span> <span class="font-semibold text-gray-800">Single / S / Z</span></p>
                   </div>
                   <div>
                    <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Position/Role:</span> <span class="font-bold text-gray-800"><?= htmlspecialchars($role) ?></span></p>
                    <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Pay Date:</span> <span class="font-mono text-gray-800"><?= $payDateStr ?></span></p>
                    <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Employment Type:</span> <span class="font-semibold text-indigo-600">Regular</span></p>
                    <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Statutory Ref:</span> <span class="font-mono text-gray-600 text-[10px]">SSS/PH/PAG-IBIG Compliant</span></p>
                   </div>
                </div>

                <div class="grid grid-cols-2 gap-6 items-start mb-4">
                    <div>
                        <h6 class="font-bold text-xs text-gray-900 border-b pb-1.5 mb-2 uppercase tracking-wide">Earnings (Kinsenas Breakdown)</h6>
                        <div class="space-y-1">
                            <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                                <span class="text-gray-600">Basic Salary (Semi-Monthly)</span> 
                                <span class="font-semibold font-mono">₱<?= number_format($kinsenas_base, 2) ?></span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                                <span class="text-gray-600">Rice & Clothing Allowance</span> 
                                <span class="font-semibold font-mono">₱<?= number_format($kinsenas_allowance, 2) ?></span>
                            </div>
                            <div class="flex justify-between py-1.5 font-bold text-gray-900 bg-gray-50 px-2 rounded mt-1">
                                <span>Gross Pay (Period)</span> 
                                <span class="font-mono text-emerald-700">₱<?= number_format($kinsenas_gross, 2) ?></span>
                            </div>
                        </div>
                    </div>

                    <div>
                        <h6 class="font-bold text-xs text-gray-900 border-b pb-1.5 mb-2 uppercase tracking-wide">Statutory & Tax Deductions</h6>
                        <div class="space-y-1">
                            <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                                <span class="text-gray-600">SSS Contribution (Employee)</span> 
                                <span class="font-mono text-red-600">-₱<?= number_format($kinsenas_sss, 2) ?></span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                                <span class="text-gray-600">PhilHealth (Employee)</span> 
                                <span class="font-mono text-red-600">-₱<?= number_format($kinsenas_philhealth, 2) ?></span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                                <span class="text-gray-600">Pag-IBIG Fund (Employee)</span> 
                                <span class="font-mono text-red-600">-₱<?= number_format($kinsenas_pagibig, 2) ?></span>
                            </div>
                            <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                                <span class="text-gray-600">BIR Withholding Tax</span> 
                                <span class="font-mono text-red-600">-₱<?= number_format($kinsenas_tax, 2) ?></span>
                            </div>
                            <div class="flex justify-between py-1.5 font-bold text-gray-900 bg-gray-50 px-2 rounded mt-1">
                                <span>Total Deductions</span> 
                                <span class="font-mono text-red-600">-₱<?= number_format($kinsenas_deductions, 2) ?></span>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-100 p-3 rounded-lg mb-4 text-[11px] grid grid-cols-2 gap-2 text-gray-700 border border-slate-200">
                    <div><span class="font-semibold">Monthly Basic Salary:</span> ₱<?= number_format($monthly_base, 2) ?></div>
                    <div><span class="font-semibold">Monthly Gross Earnings:</span> ₱<?= number_format($monthly_gross, 2) ?></div>
                    <div><span class="font-semibold">Monthly Total Statutory & Tax:</span> ₱<?= number_format($ph['total_deductions'], 2) ?></div>
                    <div><span class="font-semibold">Monthly Net Pay Reference:</span> ₱<?= number_format($monthly_net, 2) ?></div>
                </div>

                <div class="bg-[#212121] text-white p-4 rounded-xl flex justify-between items-center shadow-inner">
                    <div>
                        <h4 class="text-[10px] uppercase tracking-widest text-white/60">Net Pay for this Period</h4>
                        <p class="text-[10px] text-white/40">Kinsenas Payout (15-Day Cycle)</p>
                    </div>
                    <div class="text-right">
                        <h2 class="text-2xl font-black text-[#FF8C00] font-mono">₱<?= number_format($kinsenas_net, 2) ?></h2>
                    </div>
                </div>
            </div>
        </div>

        <div class="bg-slate-50 px-6 py-3 border-t flex justify-end gap-2 no-print">
            <button onclick="closePayslipModal()" class="px-4 py-2 bg-white border border-gray-300 hover:bg-gray-100 rounded-xl text-xs font-semibold text-gray-700">Close</button>
            <button onclick="window.print()" class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white rounded-xl text-xs font-bold shadow-sm flex items-center gap-1.5">
                <i class="bi bi-printer"></i> Print Statement
            </button>
        </div>
    </div>
</div>

<script>
    // Modal Controls
    function openLeaveModal() { document.getElementById('leaveModal').classList.remove('hidden'); }
    function closeLeaveModal() { document.getElementById('leaveModal').classList.add('hidden'); }
    
    function openPayslipModal() { document.getElementById('payslipModal').classList.remove('hidden'); }
    function closePayslipModal() { document.getElementById('payslipModal').classList.add('hidden'); }

    function openAttendanceModal() {
        document.getElementById('attendanceModal').classList.remove('hidden');
        getLocation();
    }
    function closeAttendanceModal() { document.getElementById('attendanceModal').classList.add('hidden'); }

    let userLat = null;
    let userLong = null;

    function getLocation() {
        const statusDiv = document.getElementById('geoStatus');
        if (navigator.geolocation) {
            statusDiv.textContent = "Kinukuha ang iyong GPS location...";
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    userLat = position.coords.latitude;
                    userLong = position.coords.longitude;
                    statusDiv.textContent = `GPS Nakuha! Lat: ${userLat.toFixed(4)}, Long: ${userLong.toFixed(4)}`;
                    statusDiv.className = "mb-4 text-xs font-mono font-bold text-emerald-600 bg-emerald-50 p-2.5 rounded-xl border border-emerald-200";
                    
                    const btnIn = document.getElementById('btnTimeIn');
                    const btnOut = document.getElementById('btnTimeOut');
                    btnIn.disabled = false; btnIn.classList.remove('opacity-50', 'cursor-not-allowed');
                    btnOut.disabled = false; btnOut.classList.remove('opacity-50', 'cursor-not-allowed');
                },
                (error) => {
                    statusDiv.textContent = "Nabigo makuha ang GPS: " + error.message;
                    statusDiv.className = "mb-4 text-xs font-mono font-bold text-rose-600 bg-rose-50 p-2.5 rounded-xl border border-rose-200";
                },
                { enableHighAccuracy: true }
            );
        } else {
            statusDiv.textContent = "Hindi suportado ng browser mo ang Geolocation.";
        }
    }

    function recordAttendance(type) {
        if (!userLat || !userLong) {
            Swal.fire('Error', 'Wala pang nakuhang GPS coordinates.', 'error');
            return;
        }

        fetch('process_attendance.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: `action=${type}&lat=${userLat}&lng=${userLong}`
        })
        .then(response => response.json())
        .then(data => {
            closeAttendanceModal();
            Swal.fire({
                title: data.status === 'success' ? 'Tagumpay!' : 'Paunawa',
                text: data.message,
                icon: data.status,
                confirmButtonColor: '#FF8C00'
            });
        })
        .catch(err => {
            Swal.fire('Error', 'Nagka-problema sa koneksyon sa sistema.', 'error');
        });
    }

    <?php if(!empty($leave_msg)): ?>
        Swal.fire({
            title: 'Ipinadala na!',
            text: '<?= addslashes($leave_msg) ?>',
            icon: 'success',
            confirmButtonColor: '#FF8C00'
        });
    <?php endif; ?>
</script>
</body>
</html>