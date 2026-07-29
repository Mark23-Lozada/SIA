<?php
session_start();

// 1. Authentication Check
if (!isset($_SESSION['role']) || strtolower($_SESSION['role']) !== 'admin') {
    header("Location: login.php");
    exit();
}

// Anti-back/Cache control
header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
header("Pragma: no-cache");

// Database Connection
$conn = new mysqli("localhost", "root", "", "pos");
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// ==========================================
// BACKEND ACTION HANDLERS
// ==========================================
// ACTION: REGISTER HR ACCOUNT
if (isset($_POST['action']) && $_POST['action'] == 'register_hr') {
    $hr_check = $conn->query("SELECT COUNT(*) as total FROM hr_accounts");
    if ($hr_check->fetch_assoc()['total'] >= 1) {
        echo "<script>alert('An HR Account is already registered.'); window.location.href='admin_applicants.php';</script>";
        exit;
    }
    $hashed_pass = password_hash($_POST['hr_password'], PASSWORD_BCRYPT);
    $stmt = $conn->prepare("INSERT INTO hr_accounts (gmail, password, role) VALUES (?, ?, ?)");
    $role = 'hr';
    $stmt->bind_param("sss", $_POST['hr_gmail'], $hashed_pass, $role);
    $stmt->execute();
    echo "<script>alert('HR Account registered successfully!'); window.location.href='admin_applicants.php';</script>";
    exit;
}

// ACTION: APPROVE
if (isset($_GET['action']) && $_GET['action'] == 'approve' && isset($_GET['id'])) {
    $id = intval($_GET['id']);

    $stmt = $conn->prepare("UPDATE applicants SET status = 'Approved' WHERE id = ?");
    $stmt->bind_param("i", $id);
    $update = $stmt->execute();

    header('Content-Type: application/json');

    if ($update) {
        $fetch = $conn->prepare("SELECT * FROM applicants WHERE id = ?");
        $fetch->bind_param("i", $id);
        $fetch->execute();
        $applicant = $fetch->get_result()->fetch_assoc();

        echo json_encode(['status' => 'success', 'applicant' => $applicant]);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
    exit;
}

// ACTION: REJECT (permanently delete the applicant record)
if (isset($_GET['action']) && $_GET['action'] == 'admin_reject' && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    header('Content-Type: application/json');

    $fetch = $conn->prepare("SELECT resume_path FROM applicants WHERE id = ?");
    $fetch->bind_param("i", $id);
    $fetch->execute();
    $applicant = $fetch->get_result()->fetch_assoc();

    $stmt = $conn->prepare("DELETE FROM applicants WHERE id = ?");
    $stmt->bind_param("i", $id);
    $deleted = $stmt->execute();

    if ($deleted) {
        if ($applicant && !empty($applicant['resume_path']) && file_exists($applicant['resume_path'])) {
            @unlink($applicant['resume_path']);
        }
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
    exit;
}

// ACTION: CONFIRM HIRE & REGISTER EMPLOYEE
if (isset($_POST['action']) && $_POST['action'] == 'confirm_hire') {
    header('Content-Type: application/json');
    $app_id = intval($_POST['applicant_id']);

    // Server-side validations for dates and contract duration limits
    $today = date('Y-m-d');
    $date_hired = $_POST['date_hired'];
    $contract_start = $_POST['contract_start_date'];
    $duration_years = floatval($_POST['contract_duration_years']);

    if ($date_hired < $today || $contract_start < $today) {
        echo json_encode(['status' => 'error', 'message' => 'Bawal po mag-set ng past date para sa Date Hired o Contract Start Date.']);
        exit;
    }

    if ($duration_years <= 0 || $duration_years > 10) {
        echo json_encode(['status' => 'error', 'message' => 'Ang contract duration ay dapat nasa pagitan ng 0.5 hanggang 10 taon lamang.']);
        exit;
    }

    // 1. Check for duplicates across critical fields
    $check = $conn->prepare("SELECT id FROM employees WHERE employee_gmail = ? OR email = ? OR phone = ? OR gsis_id = ? OR philhealth_id = ? OR pagibig_id = ? OR sss_id = ?");
    $check->bind_param("sssssss",
        $_POST['employee_gmail'],
        $_POST['email'],
        $_POST['phone'],
        $_POST['gsis_id'],
        $_POST['philhealth_id'],
        $_POST['pagibig_id'],
        $_POST['sss_id']
    );
    $check->execute();

    if ($check->get_result()->num_rows > 0) {
        echo json_encode(['status' => 'error', 'message' => 'Duplicate credentials found.']);
        exit;
    }

    // 2. Insert into employees including validated contract fields
    $insert_sql = "INSERT INTO employees (
        company_name, company_address, contact_number, company_email, 
        employee_id, full_name, address, phone, email, date_of_birth, 
        civil_status, nationality, gender, position_title, department, 
        employment_type, immediate_supervisor, date_hired, contract_start_date, 
        contract_end_date, contract_duration_years, work_location, employee_gmail, gsis_id, sss_id, 
        philhealth_id, pagibig_id, employee_password
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
    
    $stmt = $conn->prepare($insert_sql);
    $hashed_password = password_hash($_POST['employee_password'], PASSWORD_BCRYPT);
    
    // Hardcoded company information constants
    $company_name = 'Pannakoda';
    $company_address = 'Bagong Bayan Dasmarinas Cavite';
    $company_contact = '0987000';
    $company_email = 'Pannakoda@gmail.com';

    $stmt->bind_param("ssssssssssssssssssssssssssss",
        $company_name, $company_address, $company_contact, $company_email,
        $_POST['employee_id_val'], $_POST['full_name'], $_POST['address'], $_POST['phone'], $_POST['email'], $_POST['date_of_birth'],
        $_POST['civil_status'], $_POST['nationality'], $_POST['gender'], $_POST['position_title'], $_POST['department'],
        $_POST['employment_type'], $_POST['immediate_supervisor'], $_POST['date_hired'], $_POST['contract_start_date'],
        $_POST['contract_end_date'], $_POST['contract_duration_years'], $_POST['work_location'], $_POST['employee_gmail'], $_POST['gsis_id'], $_POST['sss_id'],
        $_POST['philhealth_id'], $_POST['pagibig_id'], $hashed_password
    );

    if ($stmt->execute()) {
        $status_stmt = $conn->prepare("UPDATE applicants SET status = 'Hired' WHERE id = ?");
        $status_stmt->bind_param("i", $app_id);
        $status_stmt->execute();

        echo json_encode(['status' => 'success', 'message' => 'Employee added successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => $stmt->error]);
    }
    exit;
}

// Data Fetching: Filter out 'Hired' applicants
$admin_pipeline = $conn->query("
    SELECT * FROM applicants 
    WHERE status IN ('Final Interview Set', 'Approved') 
    AND status != 'Hired' 
    ORDER BY id DESC
");

// Fetch existing employees to check for already-registered status highlighting
$employees_result = $conn->query("SELECT email FROM employees");
$hired_emails = [];
while ($emp = $employees_result->fetch_assoc()) {
    $hired_emails[] = $emp['email'];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Executive Control Board - Administration</title>
    <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
</head>
<body class="bg-zinc-100 font-sans antialiased h-screen overflow-hidden">

    <div class="flex h-screen w-full overflow-hidden">
       <?php include 'sidebar.php'; ?>

        <div class="flex-1 h-screen overflow-y-auto p-8 bg-zinc-100 min-w-0">
            <div class="max-w-6xl mx-auto">

                <div class="mb-8">
                    <h1 class="text-3xl font-black text-zinc-800 tracking-tight">Final Decision Terminal</h1>
                    <p class="text-sm text-zinc-500">Review applicants forwarded by the HR Screening Desk. Complete corporate onboarding details upon hiring.</p>
                </div>

                <div class="bg-white rounded-xl shadow-sm border border-zinc-200 overflow-hidden">
                    <table class="w-full text-left border-collapse">
                        <tbody class="text-sm text-zinc-700 divide-y divide-zinc-200">
                            <?php if($admin_pipeline->num_rows == 0): ?>
                                <tr>
                                    <td colspan="5" class="p-12 text-center text-zinc-400 font-medium">No candidates are currently scheduled for executive decision review.</td>
                                </tr>
                            <?php endif; ?>

                            <?php while($row = $admin_pipeline->fetch_assoc()): 
                                $is_already_registered = in_array($row['email'], $hired_emails);
                            ?>
                                <tr class="hover:bg-zinc-50/50 transition-colors <?= $is_already_registered ? 'bg-emerald-50/60 font-semibold' : '' ?>" data-id="<?= $row['id'] ?>">
                                    <td class="p-4">
                                        <div class="font-bold text-zinc-900"><?= htmlspecialchars($row['full_name']) ?></div>
                                        <?php if ($is_already_registered): ?>
                                            <span class="text-[10px] uppercase font-bold tracking-tight px-1.5 py-0.5 bg-emerald-200 text-emerald-900 rounded mt-1 inline-block">Already Registered</span>
                                        <?php else: ?>
                                            <span class="text-[10px] uppercase font-bold tracking-tight px-1.5 py-0.5 bg-indigo-100 text-indigo-800 rounded mt-1 inline-block">Board Review Status</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4">
                                        <div class="font-medium text-zinc-800"><?= htmlspecialchars($row['email']) ?></div>
                                        <div class="text-xs text-zinc-400 mt-0.5"><?= htmlspecialchars($row['phone']) ?></div>
                                    </td>
                                    <td class="p-4">
                                        <?php if (!empty($row['resume_path'])): ?>
                                            <a href="<?= htmlspecialchars($row['resume_path']) ?>" target="_blank" class="inline-flex items-center gap-2 px-3 py-2 bg-red-600 hover:bg-red-700 text-white rounded-lg text-xs font-bold no-underline">
                                                <i class="bi bi-file-earmark-pdf-fill"></i> View Resume
                                            </a>
                                        <?php else: ?>
                                            <span class="text-zinc-400 text-xs">No Resume</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="p-4 text-zinc-600 font-semibold">
                                        <i class="bi bi-calendar-event text-red-500 mr-1.5"></i>
                                        <?= date('M d, Y - h:i A', strtotime($row['final_interview_date'])) ?>
                                    </td>
                                    <td class="p-4 space-x-2" id="action-cell-<?= $row['id'] ?>">
                                        <?php if ($row['status'] === 'Final Interview Set'): ?>
                                            <button type="button" onclick="approveApplicant(<?= $row['id'] ?>)"
                                                    class="bg-blue-600 hover:bg-blue-700 text-white px-3 py-1 rounded text-xs cursor-pointer">
                                                Approve
                                            </button>
                                        <?php elseif ($row['status'] === 'Approved'): ?>
                                            <button type="button" data-applicant='<?= htmlspecialchars(json_encode($row), ENT_QUOTES, "UTF-8") ?>' onclick="openHireModal(this)" 
                                                    class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1 rounded text-xs cursor-pointer">
                                                Hire
                                            </button>
                                        <?php endif; ?>
                                        <button type="button" onclick="rejectApplicant(<?= $row['id'] ?>)" 
                                                class="bg-zinc-200 hover:bg-zinc-300 px-3 py-1 rounded text-xs cursor-pointer">Reject</button>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                </div>

            </div>
        </div>
    </div>

    <!-- Hire Modal with Contract Duration and Date Constraints (No Past Dates & Max 10 Years) -->
    <div id="hireModal" class="hidden fixed inset-0 bg-zinc-900/60 backdrop-blur-sm flex items-center justify-center p-4 z-50 overflow-y-auto">
        <div class="bg-white rounded-2xl border border-zinc-200 shadow-2xl w-full max-w-4xl p-6 my-8 max-h-[90vh] overflow-y-auto">
            <div class="flex items-center gap-2 mb-2 text-emerald-600">
                <i class="bi bi-check-circle-fill text-xl"></i>
                <h3 class="text-lg font-bold text-zinc-900">Official Employee Onboarding Terminal</h3>
            </div>

            <form id="confirmHireForm" method="POST" class="space-y-4">
                <input type="hidden" name="action" value="confirm_hire">
                <input type="hidden" name="applicant_id" id="hire_candidate_id"> 
                
                <!-- Company Details Section -->
                <div class="bg-zinc-50 border border-zinc-200 rounded-xl p-4">
                    <h4 class="text-xs font-bold text-zinc-500 uppercase tracking-wide mb-3">Company Details</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">Company Name</label>
                            <input type="text" name="company_name" value="Pannakoda" readonly required class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2 text-zinc-500 outline-none select-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">Company Address</label>
                            <input type="text" name="company_address" value="Bagong Bayan Dasmarinas Cavite" readonly required class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2 text-zinc-500 outline-none select-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">Contact Number</label>
                            <input type="text" name="company_contact_number" value="0987000" readonly required class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2 text-zinc-500 outline-none select-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">Company Email</label>
                            <input type="email" name="company_email" value="Pannakoda@gmail.com" readonly required class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2 text-zinc-500 outline-none select-none">
                        </div>
                    </div>
                </div>

                <!-- Personal Information Section -->
                <div class="bg-zinc-50 border border-zinc-200 rounded-xl p-4">
                    <h4 class="text-xs font-bold text-zinc-500 uppercase tracking-wide mb-3">Employee Information</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">Employee ID</label>
                            <input type="text" name="employee_id_val" required placeholder="e.g. EMP-001" class="w-full text-sm bg-white border border-zinc-200 rounded-xl px-4 py-2 text-zinc-800">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">Full Name</label>
                            <input type="text" name="full_name" id="modal_full_name" readonly required class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2 text-zinc-500 outline-none select-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">Date of Birth</label>
                            <input type="date" name="date_of_birth" required class="w-full text-sm bg-white border border-zinc-200 rounded-xl px-4 py-2 text-zinc-800">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">Civil Status</label>
                            <select name="civil_status" required class="w-full text-sm bg-white border border-zinc-200 rounded-xl px-4 py-2 text-zinc-800">
                                <option value="Single">Single</option>
                                <option value="Married">Married</option>
                                <option value="Divorced">Divorced</option>
                                <option value="Widowed">Widowed</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">Nationality</label>
                            <input type="text" name="nationality" value="Filipino" required class="w-full text-sm bg-white border border-zinc-200 rounded-xl px-4 py-2 text-zinc-800">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">Gender</label>
                            <select name="gender" required class="w-full text-sm bg-white border border-zinc-200 rounded-xl px-4 py-2 text-zinc-800">
                                <option value="Male">Male</option>
                                <option value="Female">Female</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mt-4">
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">Contact Number (Phone)</label>
                            <input type="text" name="phone" id="modal_phone" readonly required class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2 text-zinc-500 outline-none select-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">Personal Email Address</label>
                            <input type="email" name="email" id="modal_personal_email" readonly required class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2 text-zinc-500 outline-none select-none">
                        </div>
                    </div>
                    <div class="mt-4">
                        <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">Home Address</label>
                        <textarea name="address" id="modal_address" readonly required rows="2" class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2 text-zinc-500 outline-none select-none resize-none"></textarea>
                    </div>
                </div>

                <!-- Job Details & Assignments (With Min/Max validations) -->
                <div class="bg-orange-50/70 border border-orange-100 rounded-xl p-4">
                    <h4 class="text-xs font-bold text-orange-800 uppercase tracking-wide mb-3">Employment & Contract Duration Configuration</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-orange-700 uppercase mb-1">Position / Job Title</label>
                            <input type="text" name="position_title" required placeholder="ex Staff" class="w-full text-sm bg-white border border-orange-200 rounded-xl px-4 py-2 text-zinc-800">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-orange-700 uppercase mb-1">Department</label>
                            <input type="text" name="department" id="modal_department" readonly required class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2 text-zinc-500 outline-none select-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-orange-700 uppercase mb-1">Employment Type</label>
                            <select name="employment_type" required class="w-full text-sm bg-white border border-orange-200 rounded-xl px-4 py-2 text-zinc-800">
                                <option value="Regular">Regular</option>
                                <option value="Probationary">Probationary</option>
                                <option value="Contractual">Contractual</option>
                                <option value="Part-Time">Part-Time</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-orange-700 uppercase mb-1">Immediate Supervisor</label>
                            <input type="text" name="immediate_supervisor" required placeholder="Supervisor Name" class="w-full text-sm bg-white border border-orange-200 rounded-xl px-4 py-2 text-zinc-800">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-orange-700 uppercase mb-1">Work Location</label>
                            <input type="text" name="work_location" required placeholder="Office/Remote Location" class="w-full text-sm bg-white border border-orange-200 rounded-xl px-4 py-2 text-zinc-800">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-orange-700 uppercase mb-1">Auto-Generated Work Email</label>
                            <input type="email" name="employee_gmail" id="modal_employee_gmail" required readonly class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2 text-zinc-600">
                        </div>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-5 gap-4 mt-4">
                        <div>
                            <label class="block text-[10px] font-bold text-orange-700 uppercase mb-1">Date Hired</label>
                            <input type="date" name="date_hired" id="date_hired" required class="w-full text-sm bg-white border border-orange-200 rounded-xl px-4 py-2 text-zinc-800">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-orange-700 uppercase mb-1">Contract Duration (Years, max 10)</label>
                            <input type="number" step="0.5" min="0.5" max="10" name="contract_duration_years" id="contract_duration_years" required placeholder="e.g. 1 - 10" class="w-full text-sm bg-white border border-orange-200 rounded-xl px-4 py-2 text-zinc-800">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-orange-700 uppercase mb-1">Contract Start Date</label>
                            <input type="date" name="contract_start_date" id="contract_start_date" required class="w-full text-sm bg-white border border-orange-200 rounded-xl px-4 py-2 text-zinc-800">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-orange-700 uppercase mb-1">Contract End Date</label>
                            <input type="date" name="contract_end_date" id="contract_end_date" readonly class="w-full text-sm bg-zinc-100 border border-orange-200 rounded-xl px-4 py-2 text-zinc-600">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-orange-700 uppercase mb-1">Employee Password</label>
                            <input type="text" name="employee_password" required placeholder="Secure password" class="w-full text-sm bg-white border border-orange-200 rounded-xl px-4 py-2 text-zinc-800">
                        </div>
                    </div>
                </div>

                <!-- Statutory IDs -->
                <div class="bg-zinc-50 border border-zinc-200 rounded-xl p-4">
                    <h4 class="text-xs font-bold text-zinc-500 uppercase tracking-wide mb-3">Statutory & Government Identification</h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">GSIS ID</label>
                            <input type="text" name="gsis_id" id="modal_gsis_id" readonly required class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2 text-zinc-500 outline-none select-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">SSS ID</label>
                            <input type="text" name="sss_id" id="modal_sss_id" readonly required class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2 text-zinc-500 outline-none select-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">PhilHealth ID</label>
                            <input type="text" name="philhealth_id" id="modal_philhealth_id" readonly required class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2 text-zinc-500 outline-none select-none">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-zinc-400 uppercase mb-1">Pag-IBIG MID</label>
                            <input type="text" name="pagibig_id" id="modal_pagibig_id" readonly required class="w-full text-sm bg-zinc-100 border border-zinc-200 rounded-xl px-4 py-2 text-zinc-500 outline-none select-none">
                        </div>
                    </div>
                </div>

                <div class="flex justify-end gap-3 pt-2">
                    <button type="button" onclick="closeHireModal()" class="px-4 py-2.5 bg-zinc-100 hover:bg-zinc-200 text-zinc-600 font-bold rounded-xl text-xs transition-all">Cancel</button>
                    <button type="submit" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-700 text-white font-bold rounded-xl text-xs shadow-md transition-all">Confirm Board Deployment</button>
                </div>
            </form>
        </div>
    </div>

    <script src="../LIBRARIES/tailwind.js"></script>
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
    <script>
    // Auto-generate employee work email with @pannakoda.com domain
    function generateEmployeeEmail(fullName) {
        const parts = fullName.trim().split(/\s+/);
        const clean = (str) => str.toLowerCase().replace(/[^a-z0-9]/g, '');

        if (parts.length < 2) {
            return clean(parts[0]) + '@pannakoda.com';
        }

        const lastName = parts[parts.length - 1];      
        const firstName = parts.slice(0, -1).join('');  

        return `${clean(lastName)}.${clean(firstName)}@pannakoda.com`;
    }

    // Set min date constraint dynamically on inputs to prevent past dates
    document.addEventListener('DOMContentLoaded', function() {
        const todayStr = new Date().toISOString().split('T')[0];
        
        const dateHiredInput = document.getElementById('date_hired');
        const contractStartInput = document.getElementById('contract_start_date');
        
        if (dateHiredInput) dateHiredInput.setAttribute('min', todayStr);
        if (contractStartInput) contractStartInput.setAttribute('min', todayStr);

        const durationInput = document.getElementById('contract_duration_years');
        if (durationInput) {
            durationInput.addEventListener('input', function() {
                if (parseFloat(this.value) > 10) {
                    this.value = 10;
                } else if (parseFloat(this.value) < 0.5) {
                    this.value = 0.5;
                }
                calculateEndDate();
            });
        }

        if (contractStartInput) {
            contractStartInput.addEventListener('change', calculateEndDate);
        }
    });

    function calculateEndDate() {
        const startDateInput = document.getElementById('contract_start_date');
        const durationInput = document.getElementById('contract_duration_years');
        const endDateInput = document.getElementById('contract_end_date');

        if (startDateInput.value && durationInput.value) {
            const startDate = new Date(startDateInput.value);
            const years = parseFloat(durationInput.value);
            
            if (!isNaN(years) && !isNaN(startDate.getTime())) {
                const totalDays = Math.round(years * 365);
                startDate.setDate(startDate.getDate() + totalDays);
                
                const yyyy = startDate.getFullYear();
                const mm = String(startDate.getMonth() + 1).padStart(2, '0');
                const dd = String(startDate.getDate()).padStart(2, '0');
                
                endDateInput.value = `${yyyy}-${mm}-${dd}`;
            }
        }
    }

    function openHireModal(btn) {
        const data = JSON.parse(btn.dataset.applicant);

        document.getElementById('hire_candidate_id').value = data.id;

        const readonlyFields = {
            modal_full_name: 'full_name',
            modal_personal_email: 'email',
            modal_phone: 'phone',
            modal_address: 'address',
            modal_gsis_id: 'gsis_id',
            modal_sss_id: 'sss_id',
            modal_philhealth_id: 'philhealth_id',
            modal_pagibig_id: 'pagibig_id',
            modal_department: 'department'
        };

        for (const [elementId, key] of Object.entries(readonlyFields)) {
            document.getElementById(elementId).value = data[key] || '';
        }

        // Set default values to current date
        const today = new Date().toISOString().split('T')[0];
        document.getElementById('date_hired').value = today;
        document.getElementById('contract_start_date').value = today;
        document.getElementById('contract_duration_years').value = '1';
        
        calculateEndDate();

        document.getElementById('modal_employee_gmail').value = generateEmployeeEmail(data.full_name);
        document.getElementById('hireModal').classList.remove('hidden');
    }

    function closeHireModal() {
        document.getElementById('hireModal').classList.add('hidden');
    }

    async function approveApplicant(id) {
        const result = await Swal.fire({
            title: 'Approve Candidate?',
            text: "Are you sure you want to approve?",
            icon: 'question',
            showCancelButton: true,
            confirmButtonColor: '#2563eb',
            confirmButtonText: 'Yes, Approve'
        });

        if (result.isConfirmed) {
            try {
                const response = await fetch('admin_applicants.php?action=approve&id=' + id);
                const data = await response.json();

                if (data.status === 'success') {
                    const cell = document.getElementById('action-cell-' + id);
                    const applicantJson = JSON.stringify(data.applicant).replace(/'/g, '&#39;');

                    cell.innerHTML = `
                        <button type="button" data-applicant='${applicantJson}' onclick="openHireModal(this)" 
                                class="bg-emerald-600 hover:bg-emerald-700 text-white px-3 py-1 rounded text-xs cursor-pointer">
                            Hire
                        </button>
                        <button type="button" onclick="rejectApplicant(${id})" 
                                class="bg-zinc-200 hover:bg-zinc-300 px-3 py-1 rounded text-xs cursor-pointer">Reject</button>
                    `;

                    Swal.fire('Success!', 'Na-approve na ang candidate.', 'success');
                } else {
                    Swal.fire('Error', data.message || 'Something went wrong.', 'error');
                }
            } catch (error) {
                console.error("Error parsing JSON:", error);
                Swal.fire('Error', 'error');
            }
        }
    }

    async function rejectApplicant(id) {
        const result = await Swal.fire({
            title: 'Reject Candidate?',
            text: "This will permanently delete the applicant's record, including their resume file. This cannot be undone.",
            icon: 'warning',
            showCancelButton: true,
            confirmButtonColor: '#dc2626',
            confirmButtonText: 'Yes, Reject & Delete'
        });

        if (result.isConfirmed) {
            try {
                const response = await fetch('admin_applicants.php?action=admin_reject&id=' + id);
                const data = await response.json();

                if (data.status === 'success') {
                    const row = document.querySelector(`tr[data-id="${id}"]`);
                    if (row) row.remove();
                    Swal.fire('Rejected', 'The applicant record has been removed.', 'success');
                } else {
                    Swal.fire('Error', data.message || 'Something went wrong.', 'error');
                }
            } catch (error) {
                console.error("Error rejecting applicant:", error);
                Swal.fire('Error', 'error');
            }
        }
    }

    function validateForm(formData) {
        const password = formData.get('employee_password');
        const strongPasswordRegex = /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/;

        if (!strongPasswordRegex.test(password)) {
            Swal.fire(
                'Weak Password',
                'Password must be at least 8 characters long and include an uppercase letter, a lowercase letter, a number, and a special character (@$!%*?&).',
                'warning'
            );
            return false;
        }

        const today = new Date().toISOString().split('T')[0];
        const dateHired = formData.get('date_hired');
        const contractStart = formData.get('contract_start_date');
        const durationYears = parseFloat(formData.get('contract_duration_years'));

        if (dateHired < today || contractStart < today) {
            Swal.fire('Invalid Date', 'Bawal mag-set ng past date para sa Date Hired o Contract Start Date.', 'warning');
            return false;
        }

        if (isNaN(durationYears) || durationYears <= 0 || durationYears > 10) {
            Swal.fire('Invalid Duration', 'Ang contract duration ay dapat mula 0.5 hanggang 10 taon lamang.', 'warning');
            return false;
        }

        return true;
    }

    document.addEventListener("DOMContentLoaded", function() {
        const hireForm = document.getElementById('confirmHireForm');

        if (hireForm) {
            hireForm.addEventListener('submit', function(e) {
                e.preventDefault();
                let formData = new FormData(this);

                if (!validateForm(formData)) return;

                fetch('admin_applicants.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => {
                    if (!response.ok) {
                        throw new Error('Server responded with status: ' + response.status);
                    }
                    return response.text();
                })
                .then(text => {
                    try {
                        const data = JSON.parse(text);
                        if (data.status === 'success') {
                            Swal.fire('Success', data.message, 'success');

                            const candidateId = document.getElementById('hire_candidate_id').value;
                            const rowToRemove = document.querySelector(`tr[data-id="${candidateId}"]`);
                            if (rowToRemove) {
                                rowToRemove.remove();
                            }

                            closeHireModal();
                        } else {
                            Swal.fire('Error', data.message, 'error');
                        }
                    } catch (e) {
                        console.error('Raw response:', text);
                        Swal.fire('Error', 'Invalid JSON response.', 'error');
                    }
                })
                .catch(error => {
                    console.error('Error:', error);
                    Swal.fire('Error', 'error');
                });
            });
        }
    });
    </script>
</body>
</html>