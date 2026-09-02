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

// Handle Profile Picture Upload
$upload_msg = "";
$upload_error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['profile_picture'])) {
    $file = $_FILES['profile_picture'];
    if ($file['error'] === UPLOAD_ERR_OK) {
        $fileExt = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        
        if (in_array($fileExt, $allowed)) {
            $fileName = "profile_" . $user_id . "_" . time() . "." . $fileExt;
            $uploadDir = "uploads/";
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0777, true);
            }
            $uploadPath = $uploadDir . $fileName;
            
            if (move_uploaded_file($file['tmp_name'], $uploadPath)) {
                $stmt_up = $conn->prepare("UPDATE employees SET profile_picture = ? WHERE id = ?");
                $stmt_up->bind_param("si", $uploadPath, $user_id);
                if ($stmt_up->execute()) {
                    $upload_msg = "Profile picture updated successfully!";
                }
                $stmt_up->close();
            } else {
                $upload_error = "Failed to move uploaded file.";
            }
        } else {
            $upload_error = "Invalid file type. Only JPG, JPEG, PNG, and WEBP are allowed.";
        }
    } else {
        $upload_error = "Error uploading file.";
    }
}

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

// Handle Salary Advance Request Submission with Range Validation
$advance_msg = "";
$advance_error = "";
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_advance'])) {
    $adv_amount = floatval($_POST['amount'] ?? 0);
    $adv_reason = $_POST['reason'] ?? '';
    
    if ($adv_amount < 1000 || $adv_amount > 10000) {
        $advance_error = "Salary advance amount must be between ₱1,000 and ₱10,000[cite: 1].";
    } else {
        $status = 'Pending';
        $a_stmt = $conn->prepare("INSERT INTO salary_advances (employee_id, amount, reason, status) VALUES (?, ?, ?, ?)");
        $a_stmt->bind_param("idss", $user_id, $adv_amount, $adv_reason, $status);
        if ($a_stmt->execute()) {
            $advance_msg = "Salary advance request successfully submitted for Finance review!";
        }
        $a_stmt->close();
    }
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
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        .animate-fade-in {
            animation: fadeIn 0.4s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        @keyframes pulseGlow {
            0%, 100% { box-shadow: 0 0 15px rgba(147, 51, 234, 0.2); }
            50% { box-shadow: 0 0 25px rgba(147, 51, 234, 0.4); }
        }
        .animate-glow {
            animation: pulseGlow 3s infinite;
        }
        @media print {
            body * { visibility: hidden; }
            #printArea, #printArea * { visibility: visible; }
            #printArea { position: absolute; left: 0; top: 0; width: 100%; padding: 0; margin: 0; background: white !important; color: black !important; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body class="bg-amber-50/40 font-sans antialiased h-screen overflow-hidden">

<div class="flex h-screen w-full overflow-hidden">
    
    <!-- LOAD MODULAR SIDEBAR -->
    <?php include 'sidebars.php'; ?>

    <!-- MAIN CONTENT AREA -->
    <div class="flex-1 h-screen overflow-y-auto p-6 md:p-10 bg-amber-50/30 animate-fade-in">
        <div class="max-w-5xl mx-auto space-y-6">
            
            <!-- Header Profile Banner (Modern White & amber Theme) -->
            <div class="bg-gradient-to-r from-white via-amber-50/50 to-white text-zinc-800 rounded-3xl p-8 shadow-xl shadow-amber-900/5 border border-amber-100 relative overflow-hidden flex flex-col md:flex-row justify-between items-start md:items-center gap-6 animate-glow">
                <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-amber-600/10 rounded-full blur-3xl pointer-events-none"></div>
                
                <div class="flex items-center gap-5 z-10">
                    <!-- Profile Picture Container / Click to Change Image & Add Profile Button functionality -->
                    <div class="relative group cursor-pointer" onclick="document.getElementById('profilePicInput').click()" title="Click image to change profile">
                        <?php if (!empty($employee['profile_picture']) && file_exists($employee['profile_picture'])): ?>
                            <div class=" rounded-2xl overflow-hidden shadow-lg shadow-amber-500/20 border-2 border-amber-500 transition-transform duration-300 group-hover:scale-105" style="border-radius: 50%; width: 180px; height: 200px ; ">
                                <img src="<?= htmlspecialchars($employee['profile_picture']) ?>" alt="Profile" class="w-full h-full object-cover">
                            </div>
                        <?php else: ?>
                            <div class="w-20 h-20 rounded-2xl bg-amber-500 flex items-center text-white justify-center text-3xl font-black shadow-lg shadow-amber-500/20 transition-transform duration-300 group-hover:scale-105">
                                <?= strtoupper(substr($employee['full_name'], 0, 1)) ?>
                            </div>
                        <?php endif; ?>
                        
                        <!-- Hover Overlay Icon -->
                        <div class="absolute inset-0 bg-black/40 rounded-2xl opacity-0 group-hover:opacity-100 transition-opacity flex items-center justify-center text-white">
                            <i class="bi bi-camera-fill text-lg"></i>
                        </div>
                    </div>

                    <div>
                        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-100 text-amber-700 text-xs font-bold uppercase tracking-wider mb-2 border border-amber-200">
                            <i class="bi bi-shield-check"></i> <?= htmlspecialchars($role) ?>
                        </div>
                        <h1 class="text-2xl md:text-3xl font-black tracking-tight text-zinc-900">
                            <?php echo htmlspecialchars($employee['full_name']); ?>
                        </h1>
                        <p class="text-xs text-zinc-500 mt-1 font-mono">
                            ID: <?= htmlspecialchars($employee['employee_id'] ?? 'EMP-' . str_pad($employee['id'], 4, '0', STR_PAD_LEFT)) ?> &bull; Dept: <?= htmlspecialchars($employee['department'] ?? 'Unassigned') ?>
                        </p>
                    </div>
                </div>

                <!-- Hidden form for direct image change / Add Profile -->
                <form id="profilePicForm" method="POST" enctype="multipart/form-data" class="hidden">
                    <input type="file" id="profilePicInput" name="profile_picture" accept="image/*" onchange="document.getElementById('profilePicForm').submit()">
                </form>

                <!-- Add Profile Button as requested -->
                <div class="z-10">
                    <button onclick="document.getElementById('profilePicInput').click()" class="px-5 py-3 bg-amber-600  text-white rounded-2xl font-bold text-xs shadow-lg shadow-amber-500/25 transition-all duration-300 hover:scale-105 flex items-center gap-2">
                        <i class="bi bi-person-plus-fill text-base"></i> Add / Change Profile
                    </button>
                </div>
            </div>

            <!-- Credentials Grid Details -->
            <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                <div class="md:col-span-2 space-y-6">
                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-amber-100 transition-all hover:shadow-md">
                        <h3 class="text-xs font-black text-amber-600 uppercase tracking-wider mb-4 flex items-center gap-2">
                            <i class="bi bi-person-lines-fill"></i> Personal & Employment Details
                        </h3>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-sm">
                            <div class="p-3.5 rounded-2xl bg-amber-50/40 border border-amber-100">
                                <span class="block text-[11px] font-bold text-amber-400 uppercase">Full Name</span>
                                <span class="font-bold text-zinc-800 mt-0.5 block"><?php echo htmlspecialchars($employee['full_name']); ?></span>
                            </div>
                            <div class="p-3.5 rounded-2xl bg-amber-50/40 border border-amber-100">
                                <span class="block text-[11px] font-bold text-amber-400 uppercase">Employee ID</span>
                                <span class="font-mono font-bold text-amber-600 mt-0.5 block"><?php echo htmlspecialchars($employee['employee_id'] ?? 'EMP-2026-...'); ?></span>
                            </div>
                            <div class="p-3.5 rounded-2xl bg-amber-50/40 border border-amber-100">
                                <span class="block text-[11px] font-bold text-amber-400 uppercase">Department</span>
                                <span class="font-bold text-zinc-800 mt-0.5 block"><?php echo htmlspecialchars($employee['department'] ?? 'Unassigned'); ?></span>
                            </div>
                            <div class="p-3.5 rounded-2xl bg-amber-50/40 border border-amber-100">
                                <span class="block text-[11px] font-bold text-amber-400 uppercase">Position / Role</span>
                                <span class="font-bold text-zinc-800 mt-0.5 block"><?php echo htmlspecialchars($role); ?></span>
                            </div>
                        </div>
                    </div>

                    <!-- Government Identifiers -->
                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-amber-100 transition-all hover:shadow-md">
                        <h3 class="text-xs font-black text-amber-600 uppercase tracking-wider mb-4 flex items-center gap-2">
                            <i class="bi bi-shield-shaded"></i> Government Mandated Identifiers
                        </h3>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 text-sm">
                            <div class="bg-amber-50/40 p-3.5 rounded-2xl border border-amber-100">
                                <span class="block text-[10px] text-amber-400 font-bold uppercase">SSS No.</span>
                                <span class="font-mono text-zinc-800 font-bold mt-1 block text-xs">33-1234567-8</span>
                            </div>
                            <div class="bg-amber-50/40 p-3.5 rounded-2xl border border-amber-100">
                                <span class="block text-[10px] text-amber-400 font-bold uppercase">PhilHealth</span>
                                <span class="font-mono text-zinc-800 font-bold mt-1 block text-xs">12-345678901-2</span>
                            </div>
                            <div class="bg-amber-50/40 p-3.5 rounded-2xl border border-amber-100">
                                <span class="block text-[10px] text-amber-400 font-bold uppercase">Pag-IBIG</span>
                                <span class="font-mono text-zinc-800 font-bold mt-1 block text-xs">1210-9876-5432</span>
                            </div>
                            <div class="bg-amber-50/40 p-3.5 rounded-2xl border border-amber-100">
                                <span class="block text-[10px] text-amber-400 font-bold uppercase">GSIS No.</span>
                                <span class="font-mono text-zinc-800 font-bold mt-1 block text-xs">N/A</span>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Right Column: Access Emails -->
                <div class="space-y-6">
                    <div class="bg-white rounded-3xl p-6 shadow-sm border border-amber-100 flex flex-col justify-between h-full transition-all hover:shadow-md">
                        <div>
                            <h3 class="text-xs font-black text-amber-600 uppercase tracking-wider mb-4 flex items-center gap-2">
                                <i class="bi bi-envelope-at-fill"></i> System Access Emails
                            </h3>
                            <div class="space-y-4">
                                <div class="p-4 rounded-2xl bg-amber-50/60 border border-amber-100">
                                    <span class="block text-[10px] text-amber-600 font-bold uppercase tracking-wide">Employee Email</span>
                                    <span class="font-mono font-bold text-amber-950 text-xs block mt-1 break-all">
                                        <?php echo htmlspecialchars($employee['employee_gmail'] ?? 'Not set'); ?>
                                    </span>
                                </div>
                                <div class="p-4 rounded-2xl bg-amber-50/60 border border-amber-100">
                                    <span class="block text-[10px] text-amber-600 font-bold uppercase tracking-wide">Company Gmail</span>
                                    <span class="font-mono font-bold text-amber-950 text-xs block mt-1 break-all">
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

<!-- LOAD MODULAR MODALS -->
<?php include 'modals.php'; ?>

<script>
    // Modal Controls
    function openLeaveModal() { document.getElementById('leaveModal').classList.remove('hidden'); }
    function closeLeaveModal() { document.getElementById('leaveModal').classList.add('hidden'); }
    
    function openAdvanceModal() { document.getElementById('advanceModal').classList.remove('hidden'); }
    function closeAdvanceModal() { document.getElementById('advanceModal').classList.add('hidden'); }

    function openPayslipModal() { document.getElementById('payslipModal').classList.remove('hidden'); }
    function closePayslipModal() { document.getElementById('payslipModal').classList.add('hidden'); }

    function openAttendanceModal() {
        document.getElementById('attendanceModal').classList.remove('hidden');
        getLocation();
    }
    function closeAttendanceModal() { document.getElementById('attendanceModal').classList.add('hidden'); }

    // Client-side validation for Salary Advance (Min: 1000, Max: 10000)[cite: 1]
    function validateAdvanceAmount() {
        const amount = parseFloat(document.getElementById('adv_amount_input').value);
        if (amount < 1000 || amount > 10000) {
            Swal.fire({
                title: 'Invalid Amount',
                text: 'The salary advance request must be between ₱1,000 and ₱10,000[cite: 1].',
                icon: 'warning',
                confirmButtonColor: '#9333ea'
            });
            return false;
        }
        return true;
    }

    let userLat = null;
    let userLong = null;

    function getLocation() {
        const statusDiv = document.getElementById('geoStatus');
        if (navigator.geolocation) {
            statusDiv.textContent = "Fetching your GPS location...";
            navigator.geolocation.getCurrentPosition(
                (position) => {
                    userLat = position.coords.latitude;
                    userLong = position.coords.longitude;
                    statusDiv.textContent = `GPS Acquired! Lat: ${userLat.toFixed(4)}, Long: ${userLong.toFixed(4)}`;
                    statusDiv.className = "mb-4 text-xs font-mono font-bold text-emerald-600 bg-emerald-50 p-2.5 rounded-xl border border-emerald-200";
                    
                    const btnIn = document.getElementById('btnTimeIn');
                    const btnOut = document.getElementById('btnTimeOut');
                    btnIn.disabled = false; btnIn.classList.remove('opacity-50', 'cursor-not-allowed');
                    btnOut.disabled = false; btnOut.classList.remove('opacity-50', 'cursor-not-allowed');
                },
                (error) => {
                    statusDiv.textContent = "Failed to get GPS: " + error.message;
                    statusDiv.className = "mb-4 text-xs font-mono font-bold text-rose-600 bg-rose-50 p-2.5 rounded-xl border border-rose-200";
                },
                { enableHighAccuracy: true }
            );
        } else {
            statusDiv.textContent = "Geolocation is not supported by your browser.";
        }
    }

    function recordAttendance(type) {
        if (!userLat || !userLong) {
            Swal.fire('Error', 'No GPS coordinates acquired yet.', 'error');
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
                title: data.status === 'success' ? 'Success!' : 'Notice',
                text: data.message,
                icon: data.status,
                confirmButtonColor: '#9333ea'
            });
        })
        .catch(err => {
            Swal.fire('Error', 'System connection problem occurred.', 'error');
        });
    }

    <?php if(!empty($upload_msg)): ?>
        Swal.fire({title: 'Success!', text: '<?= addslashes($upload_msg) ?>', icon: 'success', confirmButtonColor: '#9333ea'});
    <?php endif; ?>

    <?php if(!empty($upload_error)): ?>
        Swal.fire({title: 'Error', text: '<?= addslashes($upload_error) ?>', icon: 'error', confirmButtonColor: '#9333ea'});
    <?php endif; ?>

    <?php if(!empty($leave_msg)): ?>
        Swal.fire({title: 'Submitted!', text: '<?= addslashes($leave_msg) ?>', icon: 'success', confirmButtonColor: '#9333ea'});
    <?php endif; ?>

    <?php if(!empty($advance_msg)): ?>
        Swal.fire({title: 'Submitted!', text: '<?= addslashes($advance_msg) ?>', icon: 'success', confirmButtonColor: '#9333ea'});
    <?php endif; ?>

    <?php if(!empty($advance_error)): ?>
        Swal.fire({title: 'Notice', text: '<?= addslashes($advance_error) ?>', icon: 'warning', confirmButtonColor: '#9333ea'});
    <?php endif; ?>
</script>
</body>
</html>