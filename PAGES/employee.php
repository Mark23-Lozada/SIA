<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$current_role = strtolower($_SESSION['role']);
if ($current_role !== 'admin' && $current_role !== 'hr') {
    header("Location: login.php"); 
    exit();
}

$host = "localhost";
$user = "root"; 
$pass = ""; 
$dbname = "pos";

$conn = new mysqli($host, $user, $pass, $dbname);

// 1. HANDLE ONBOARDING SUBMISSION / UPDATE VIA POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_onboarding') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $id = intval($_POST['id']);
    $password_input = $_POST['password'] ?? '';
    
    $company_name = $conn->real_escape_string($_POST['company_name'] ?? '');
    $company_address = $conn->real_escape_string($_POST['company_address'] ?? '');
    $contact_number = $conn->real_escape_string($_POST['contact_number'] ?? '');
    $date_of_birth = $conn->real_escape_string($_POST['date_of_birth'] ?? '');
    $civil_status = $conn->real_escape_string($_POST['civil_status'] ?? '');
    $nationality = $conn->real_escape_string($_POST['nationality'] ?? '');
    $gender = $conn->real_escape_string($_POST['gender'] ?? '');
    
    $department = trim($_POST['department']);
    $dept_lower = strtolower($department);
    $role = $conn->real_escape_string($_POST['role']);
    
    $employment_type = $conn->real_escape_string($_POST['employment_type'] ?? 'Regular');
    $immediate_supervisor = $conn->real_escape_string($_POST['immediate_supervisor'] ?? '');
    $date_hired = $conn->real_escape_string($_POST['date_hired'] ?? date('Y-m-d'));
    $contract_start_date = $conn->real_escape_string($_POST['contract_start_date'] ?? '');
    $contract_end_date = $conn->real_escape_string($_POST['contract_end_date'] ?? '');
    $contract_duration_years = floatval($_POST['contract_duration_years'] ?? 1.0);
    $work_location = $conn->real_escape_string($_POST['work_location'] ?? '');
    $salary = floatval($_POST['salary']);

    // Backend Strong Password Validation
    if (strlen($password_input) < 8 || 
        !preg_match('/[A-Z]/', $password_input) || 
        !preg_match('/[a-z]/', $password_input) || 
        !preg_match('/[0-9]/', $password_input) || 
        !preg_match('/[\W_]/', $password_input)) {
        
        echo json_encode([
            "success" => false, 
            "message" => "Ang password ay dapat hindi bababa sa 8 characters at naglalaman ng uppercase, lowercase, number, at special symbol."
        ]);
        exit;
    }

    $employee_id = 'EMP-' . date('Y') . '-' . str_pad($id, 4, '0', STR_PAD_LEFT);
    $password_hash = password_hash($password_input, PASSWORD_DEFAULT);

    $emp_check_query = "SELECT * FROM employees WHERE id = $id LIMIT 1";
    $emp_result = $conn->query($emp_check_query);
    
    if ($emp_result && $emp_result->num_rows > 0) {
        $emp_data = $emp_result->fetch_assoc();
        $full_name = trim($emp_data['full_name']);
        
        // 1. Employee Email (employee_gmail): lastname.firstname@pannakoda.com
        $name_parts = explode(' ', $full_name);
        $firstname = strtolower(preg_replace('/[^a-z]/', '', $name_parts[0]));
        $lastname = count($name_parts) > 1 ? strtolower(preg_replace('/[^a-z]/', '', end($name_parts))) : $firstname;
        $employee_gmail = $lastname . '.' . $firstname . '@pannakoda.com';

        // 2. Company Gmail (company_gmail): Department-based o clean fullname
        if ($dept_lower === 'hr') {
            $company_gmail = 'hr@pannakoda.com';
        } elseif ($dept_lower === 'finance') {
            $company_gmail = 'finance@pannakoda.com';
        } elseif ($dept_lower === 'manager') {
            $company_gmail = 'manager@pannakoda.com';
        } else {
            $cleanFullName = strtolower(preg_replace('/[^a-z]/', '', $full_name));
            if (empty($cleanFullName)) {
                $cleanFullName = "employee" . $id;
            }
            $company_gmail = $cleanFullName . "@pannakoda.com";
        }

        // Update database with auto-generated emails and shared password hash
        $update_sql = "UPDATE employees SET 
            employee_id = '$employee_id', 
            employee_password = '$password_hash', 
            employee_gmail = '$employee_gmail',
            company_gmail = '$company_gmail',
            department = '$department', 
            position_title = '$role',
            status = 'hired' 
            WHERE id = $id";
        
        if ($conn->query($update_sql)) {
            echo json_encode(["success" => true, "message" => "Employee successfully fully hired with auto-generated employee_gmail & company_gmail!"]);
        } else {
            echo json_encode(["success" => false, "message" => $conn->error]);
        }
    }
    $conn->close();
    exit;
}

// 2. FETCH EMPLOYEES ENDPOINT
if (isset($_GET['action']) && $_GET['action'] === 'fetch_employees') {
    header('Content-Type: application/json');
    
    if ($conn->connect_error) {
        echo json_encode([]);
        exit;
    }

    $query = "SELECT * FROM employees ORDER BY id DESC";
    $result = $conn->query($query);
    $employees = [];
    
    while($row = $result->fetch_assoc()) {
        $row['id'] = isset($row['id']) ? intval($row['id']) : 0;
        $row['display_emp_id'] = isset($row['employee_id']) && !empty($row['employee_id']) ? $row['employee_id'] : $row['id'];
        
        if (!isset($row['status']) || empty($row['status'])) {
            $row['status'] = 'onboarding';
        }
        
        if (!isset($row['department']) || empty($row['department'])) {
            $row['department'] = 'Unassigned'; 
        }

        if (!isset($row['role']) && !isset($row['position'])) {
            $row['role'] = 'Staff';
        } else {
            $row['role'] = $row['position_title'] ?? ($row['position'] ?? 'Staff');
        }
        
        $employees[] = $row;
    }
    
    echo json_encode($employees);
    $conn->close();
    exit;
}

// 3. DELETE EMPLOYEE ENDPOINT
if (isset($_GET['action']) && $_GET['action'] === 'delete_employee' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id > 0) {
        $stmt = $conn->prepare("DELETE FROM employees WHERE id = ?");
        $stmt->bind_param("i", $id);
        
        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                echo json_encode(['success' => true, 'message' => 'Employee record successfully deleted from the database.']);
            } else {
                echo json_encode(['success' => false, 'message' => 'No record found with that reference ID.']);
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Failed to execute record deletion.']);
        }
        
        $stmt->close();
        $conn->close();
    } else {
        echo json_encode(['success' => false, 'message' => 'Error: Received an invalid or empty Primary Key reference.']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Employee Management & Payroll</title>
  
  <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
  <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
  <script src="../LIBRARIES/tailwind.js"></script> 
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <style>
    @media print {
      body * { visibility: hidden; }
      #printArea, #printArea * { visibility: visible; }
      #printArea { position: absolute; left: 0; top: 0; width: 100%; padding: 0; margin: 0; background: white !important; color: black !important; }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body class="bg-[whitesmoke] font-sans antialiased h-screen overflow-hidden">

  <div class="flex h-screen w-full overflow-hidden">
    
    <?php include 'sidebar.php'; ?>
    <div class="flex-1 h-screen overflow-y-auto p-8 bg-slate-100 min-w-0">
      
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Employee Directory & Payroll</h1>
          <p class="text-sm text-gray-500">Manage onboarding candidates, personal profiles, credentials, and payroll rates.</p>
        </div>
        
        <!-- TABS NAVIGATION -->
        <div class="bg-gray-200/80 p-1 rounded-xl flex gap-1 shadow-inner">
          <button id="tabNewlyHired" class="px-3 py-2 rounded-lg text-sm transition-all flex items-center gap-2 bg-white text-gray-900 shadow-sm font-semibold">
            <i class="bi bi-person-plus text-amber-600"></i> Newly Hired <span id="badgeNewlyHired" class="badge bg-amber-500 text-white rounded-pill px-2">0</span>
          </button>
          <button id="tabPersonal" class="px-4 py-2 rounded-lg text-sm transition-all flex items-center gap-2 text-gray-600 hover:text-gray-900">
            <i class="bi bi-person-bounding-box text-[#FF8C00]"></i> Personal Details
          </button>
          <button id="tabPayroll" class="px-4 py-2 rounded-lg text-sm transition-all flex items-center gap-2 text-gray-600 hover:text-gray-900">
            <i class="bi bi-cash-stack text-emerald-600"></i> Payroll Profile
          </button>
        </div>
      </div>

      <div class="mb-4 flex items-center gap-3 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <div class="relative flex-1 max-w-md">
          <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400">
            <i class="bi bi-search"></i>
          </span>
          <input id="searchInput" type="text" class="form-control pl-10 pr-4 py-2 rounded-xl text-sm border-gray-200 focus:border-[#FF8C00] focus:ring-1 focus:ring-[#FF8C00]" placeholder="Search by ID, name, department, or role...">
        </div>
      </div>

      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
        <div class="table-responsive bg-white rounded-xl overflow-hidden">
          
          <!-- 1. NEWLY HIRED / ONBOARDING TABLE -->
          <table id="newlyHiredTable" class="table table-hover align-middle mb-0 text-sm">
            <thead class="table-dark">
              <tr>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Full Name</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Position/Role</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Status</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0 text-center">Action (Onboarding Form)</th>
              </tr>
            </thead>
            <tbody id="newlyHiredBody"></tbody>
          </table>

          <!-- 2. PERSONAL DETAILS TABLE (Hired) -->
          <table id="personalTable" class="table table-hover align-middle mb-0 text-sm d-none">
            <thead class="table-dark">
              <tr>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Employee ID</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Full Name</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Role</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Department</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0 text-center">Action</th>
              </tr>
            </thead>
            <tbody id="personalBody"></tbody>
          </table>

          <!-- 3. PAYROLL PROFILE TABLE -->
          <table id="payrollTable" class="table table-hover align-middle mb-0 text-sm d-none">
            <thead class="table-dark">
              <tr>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Full Name</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Role</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">SSS No.</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">PhilHealth</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Pag-IBIG No.</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">GSIS No.</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0 text-end">Base Salary</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0 text-center">Action</th>
              </tr>
            </thead>
            <tbody id="payrollBody"></tbody>
          </table>

        </div>
      </div>
    </div>

    <!-- PAYSLIP MODAL -->
    <div class="modal fade" id="payslipModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-2xl shadow-xl border-0">
          <div class="modal-header border-0 bg-slate-50 rounded-t-2xl px-6 py-4 no-print">
            <h5 class="modal-title font-bold text-gray-800 flex items-center gap-2">
              <i class="bi bi-receipt text-emerald-600"></i> Employee Payroll Statement
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-6" id="printArea"></div>
          <div class="modal-footer border-0 bg-slate-50 rounded-b-2xl px-6 py-3 no-print">
            <button type="button" class="btn btn-light font-semibold border text-gray-600 px-4" data-bs-dismiss="modal">Close</button>
            <button type="button" id="btnPrintStatement" class="btn btn-primary font-semibold bg-blue-600 border-0 px-4 flex items-center gap-1.5 shadow-sm">
              <i class="bi bi-printer"></i> Print Statement
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ONBOARDING MODAL FORM -->
    <div class="modal fade" id="onboardingModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-2xl shadow-xl border-0">
          <div class="modal-header border-0 bg-slate-50 rounded-t-2xl px-6 py-4">
            <h5 class="modal-title font-bold text-gray-800 flex items-center gap-2">
              <i class="bi bi-person-check text-indigo-600"></i> Complete Onboarding Details
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="onboardingForm" onsubmit="submitOnboarding(event)">
            <div class="modal-body p-6 space-y-4 max-h-[75vh] overflow-y-auto">
              <input type="hidden" id="modalId">
              
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Full Name</label>
                  <input type="text" id="modalName" readonly class="form-control bg-gray-100 text-sm">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Contact Number</label>
                  <input type="text" id="modalContactNumber" class="form-control text-sm" placeholder="e.g. 09123456789">
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Company Name</label>
                  <input type="text" id="modalCompanyName" class="form-control text-sm" value="PannaKoda Stores Inc.">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Company Address</label>
                  <input type="text" id="modalCompanyAddress" class="form-control text-sm" placeholder="e.g. Dasmarinas Cavite">
                </div>
              </div>

              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Date of Birth</label>
                  <input type="date" id="modalDob" class="form-control text-sm">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Civil Status</label>
                  <select id="modalCivilStatus" class="form-control text-sm">
                    <option value="Single">Single</option>
                    <option value="Married">Married</option>
                    <option value="Widowed">Widowed</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Nationality</label>
                  <input type="text" id="modalNationality" class="form-control text-sm" value="Filipino">
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Gender</label>
                  <select id="modalGender" class="form-control text-sm">
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Work Location</label>
                  <input type="text" id="modalWorkLocation" class="form-control text-sm" value="Main Office">
                </div>
              </div>

              <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Home Address</label>
                <textarea id="modalAddress" readonly rows="2" class="form-control bg-gray-100 text-sm"></textarea>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Employee Gmail (Auto: last.firstname@...)</label>
                  <input type="text" id="modalEmployeeGmail" readonly class="form-control bg-gray-100 text-sm text-indigo-600 font-medium">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Company Gmail (Auto: Dept/Name@...)</label>
                  <input type="text" id="modalCompanyGmail" readonly class="form-control bg-gray-100 text-sm text-emerald-600 font-medium">
                </div>
              </div>

              <div>
                <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Set Password (Shared for Both Accounts)</label>
                <input type="password" id="modalPassword" required class="form-control text-sm" placeholder="Ilagay ang strong password" oninput="validatePasswordStrength(this.value)">
                <div id="passwordFeedback" class="text-[11px] mt-1 text-gray-500">
                  Dapat 8+ chars, may malaking titik, maliit na titik, numero, at symbol.
                </div>
              </div>

              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Department</label>
                  <input type="text" id="modalDepartment" required class="form-control text-sm" placeholder="e.g. HR, Finance, Manager, Staff" oninput="updateModalGmailPreview()">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Role / Position Tier</label>
                  <select id="modalRole" class="form-control text-sm" onchange="updateDefaultSalary()">
                    <option value="Staff">Staff</option>
                    <option value="Manager">Manager</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Employment Type</label>
                  <select id="modalEmploymentType" class="form-control text-sm">
                    <option value="Regular">Regular</option>
                    <option value="Probationary">Probationary</option>
                    <option value="Part-Time">Part-Time</option>
                    <option value="Full-Time">Full-Time</option>
                  </select>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Immediate Supervisor</label>
                  <input type="text" id="modalSupervisor" class="form-control text-sm" placeholder="Supervisor Name">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Base Salary (PHP)</label>
                  <input type="number" step="0.01" id="modalSalary" required class="form-control text-sm" placeholder="Base salary">
                </div>
              </div>

              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Date Hired</label>
                  <input type="date" id="modalDateHired" class="form-control text-sm">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Contract Start Date</label>
                  <input type="date" id="modalContractStart" class="form-control text-sm">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Contract End Date</label>
                  <input type="date" id="modalContractEnd" class="form-control text-sm">
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Contract Duration (Years)</label>
                  <input type="number" step="0.1" id="modalContractDuration" class="form-control text-sm" value="1.0">
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">SSS No.</label>
                  <input type="text" id="modalSss" readonly class="form-control bg-gray-100 text-sm">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">PhilHealth No.</label>
                  <input type="text" id="modalPhilhealth" readonly class="form-control bg-gray-100 text-sm">
                </div>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">Pag-IBIG No.</label>
                  <input type="text" id="modalPagibig" readonly class="form-control bg-gray-100 text-sm">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-gray-600 uppercase mb-1">GSIS No.</label>
                  <input type="text" id="modalGsis" readonly class="form-control bg-gray-100 text-sm">
                </div>
              </div>
            </div>
            <div class="modal-footer border-0 bg-slate-50 rounded-b-2xl px-6 py-3">
              <button type="button" class="btn btn-light font-semibold border text-gray-600 px-4" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn btn-dark font-semibold bg-indigo-600 hover:bg-indigo-700 border-0 px-4">Fully Hired & Save</button>
            </div>
          </form>
        </div>
      </div>
    </div>

  </div>

  <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>

  <script>
    let allEmployees = [];
    let bsModalInstance = null;
    let onboardingModalInstance = null;

    async function loadEmployees() {
      try {
        const response = await fetch(`${window.location.pathname}?action=fetch_employees`);
        allEmployees = response.ok ? await response.json() : [];
        renderTables();
      } catch (err) {
        console.error("Pipeline failure:", err);
      }
    }

    function updateDefaultSalary() {
      const role = document.getElementById('modalRole').value;
      const salaryInput = document.getElementById('modalSalary');
      salaryInput.value = role === 'Manager' ? 20000 : 15000;
    }

    function updateModalGmailPreview() {
      const deptInput = document.getElementById('modalDepartment').value.trim().toLowerCase();
      const employeeEmailField = document.getElementById('modalEmployeeGmail');
      const companyEmailField = document.getElementById('modalCompanyGmail');
      const empName = document.getElementById('modalName').value;
      const empId = document.getElementById('modalId').value;

      const nameParts = empName.trim().split(' ');
      const firstname = nameParts[0] ? nameParts[0].toLowerCase().replace(/[^a-z]/g, '') : 'user';
      const lastname = nameParts.length > 1 ? nameParts[nameParts.length - 1].toLowerCase().replace(/[^a-z]/g, '') : firstname;
      employeeEmailField.value = `${lastname}.${firstname}@pannakoda.com`;

      if (deptInput === 'hr') {
        companyEmailField.value = 'hr@pannakoda.com';
      } else if (deptInput === 'finance') {
        companyEmailField.value = 'finance@pannakoda.com';
      } else if (deptInput === 'manager') {
        companyEmailField.value = 'manager@pannakoda.com';
      } else {
        let cleanName = empName.toLowerCase().replace(/[^a-z]/g, '');
        if (!cleanName) cleanName = 'employee' + empId;
        companyEmailField.value = `${cleanName}@pannakoda.com`;
      }
    }

    function validatePasswordStrength(password) {
      const feedback = document.getElementById('passwordFeedback');
      
      const minLength = password.length >= 8;
      const hasUpperCase = /[A-Z]/.test(password);
      const hasLowerCase = /[a-z]/.test(password);
      const hasNumbers = /\d/.test(password);
      const hasNonalphanumeric = /[\W_]/.test(password);

      if (minLength && hasUpperCase && hasLowerCase && hasNumbers && hasNonalphanumeric) {
        feedback.className = "text-[11px] mt-1 text-emerald-600 font-semibold";
        feedback.innerHTML = '<i class="bi bi-check-circle-fill"></i> Strong password!';
      } else {
        feedback.className = "text-[11px] mt-1 text-red-500 font-semibold";
        feedback.innerHTML = '<i class="bi bi-exclamation-triangle-fill"></i> Mahina pa ang password. Sundin ang kinakailangan.';
      }
    }

    function renderTables() {
      const query = document.getElementById('searchInput').value.toLowerCase().trim();
      
      const filtered = allEmployees.filter(emp => {
        const customId = String(emp.display_emp_id).toLowerCase();
        const name = String(emp.full_name || '').toLowerCase();
        const dept = String(emp.department || '').toLowerCase();
        const role = String(emp.role || '').toLowerCase();
        return customId.includes(query) || name.includes(query) || dept.includes(query) || role.includes(query);
      });

      const newlyHiredBody = document.getElementById('newlyHiredBody');
      const personalBody = document.getElementById('personalBody');
      const payrollBody = document.getElementById('payrollBody');

      newlyHiredBody.innerHTML = '';
      personalBody.innerHTML = '';
      payrollBody.innerHTML = '';

      let onboardingCount = 0;
      let hiredPersonalCount = 0;
      let activePayrollCount = 0;

      filtered.forEach(emp => {
        const status = (emp.status || 'onboarding').toLowerCase();
        const baseSalary = emp.salary ? parseFloat(emp.salary) : (emp.role === 'Manager' ? 20000 : 15000);
        const roleClass = emp.role === 'Manager' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-blue-50 text-blue-700 border-blue-200';

        if (status !== 'hired') {
          onboardingCount++;
          const trNew = document.createElement('tr');
          trNew.className = "border-b border-gray-100 hover:bg-gray-50/50 transition-colors";
          trNew.innerHTML = `
            <td class="py-3 px-4 font-semibold text-gray-800">${emp.full_name || ''}</td>
            <td class="py-3 px-4"><span class="${roleClass} px-2.5 py-1 rounded-md text-xs font-semibold border">${emp.role || 'Staff'}</span></td>
            <td class="py-3 px-4"><span class="bg-amber-50 text-amber-700 border-amber-200 px-2.5 py-1 rounded-md text-xs font-semibold border">Onboarding</span></td>
            <td class="py-3 px-4 text-center">
              <button onclick="openOnboardingModal(${emp.id})" class="btn btn-sm btn-dark py-1.5 px-3 text-xs font-semibold rounded-lg flex items-center gap-1.5 mx-auto bg-indigo-600 hover:bg-indigo-700 border-0 shadow-sm">
                <i class="bi bi-person-check-fill"></i> Setup Form / Onboarding
              </button>
            </td>
          `;
          newlyHiredBody.appendChild(trNew);
        } else {
          hiredPersonalCount++;
          const trPersonal = document.createElement('tr');
          trPersonal.className = "border-b border-gray-100 hover:bg-gray-50/50 transition-colors";
          trPersonal.innerHTML = `
            <td class="py-3 px-4 font-mono font-semibold text-gray-700">${emp.display_emp_id || ''}</td>
            <td class="py-3 px-4 font-semibold text-gray-800">${emp.full_name || ''}</td>
            <td class="py-3 px-4"><span class="${roleClass} px-2.5 py-1 rounded-md text-xs font-semibold border">${emp.role}</span></td>
            <td class="py-3 px-4 text-gray-600">${emp.department}</td>
            <td class="py-3 px-4 text-center">
              <button onclick="triggerDelete(${emp.id}, '${(emp.full_name || '').replace(/'/g, "\\'")}')" class="btn btn-sm btn-outline-danger py-1 px-2 text-xs font-semibold rounded-lg flex items-center gap-1 mx-auto">
                <i class="bi bi-trash3"></i> Delete
              </button>
            </td>
          `;
          personalBody.appendChild(trPersonal);

          activePayrollCount++;
          const trPayroll = document.createElement('tr');
          trPayroll.className = "border-b border-gray-100 hover:bg-gray-50/50 transition-colors";
          trPayroll.innerHTML = `
            <td class="py-3 px-4 font-semibold text-gray-800">${emp.full_name || ''}</td>
            <td class="py-3 px-4 font-semibold text-xs ${emp.role === 'Manager' ? 'text-amber-700' : 'text-blue-700'}">${emp.role}</td>
            <td class="py-3 px-4 text-gray-600 font-mono">${emp.sss_id || '-'}</td>
            <td class="py-3 px-4 text-gray-600 font-mono">${emp.philhealth_id || '-'}</td>
            <td class="py-3 px-4 text-gray-600 font-mono">${emp.pagibig_id || '-'}</td>
            <td class="py-3 px-4 text-gray-600 font-mono">${emp.gsis_id || '-'}</td>
            <td class="py-3 px-4 text-end font-bold text-gray-900">₱${baseSalary.toLocaleString('en-US', {minimumFractionDigits:2})}</td>
            <td class="py-3 px-4 text-center">
              <button onclick="triggerPayslip(${emp.id})" class="btn btn-sm btn-success py-1 px-2.5 text-xs font-semibold rounded-lg flex items-center gap-1 mx-auto">
                <i class="bi bi-file-earmark-spreadsheet"></i> Payslip
              </button>
            </td>
          `;
          payrollBody.appendChild(trPayroll);
        }
      });

      document.getElementById('badgeNewlyHired').innerText = onboardingCount;

      if (onboardingCount === 0) {
        newlyHiredBody.innerHTML = `<tr><td colspan="4" class="text-center py-8 text-gray-400 italic">No candidates undergoing onboarding.</td></tr>`;
      }
      if (hiredPersonalCount === 0) {
        personalBody.innerHTML = `<tr><td colspan="5" class="text-center py-8 text-gray-400 italic">No hired operational records matched.</td></tr>`;
      }
      if (activePayrollCount === 0) {
        payrollBody.innerHTML = `<tr><td colspan="8" class="text-center py-8 text-gray-400 italic">No operational payroll records matched.</td></tr>`;
      }
    }

    function openOnboardingModal(id) {
      const emp = allEmployees.find(e => e.id == id);
      if (!emp) return;

      document.getElementById('modalId').value = emp.id;
      document.getElementById('modalName').value = emp.full_name || '';
      document.getElementById('modalContactNumber').value = emp.contact_number || emp.phone || '';
      document.getElementById('modalCompanyName').value = emp.company_name || 'PannaKoda Stores Inc.';
      document.getElementById('modalCompanyAddress').value = emp.company_address || '';
      document.getElementById('modalDob').value = emp.date_of_birth || '';
      document.getElementById('modalCivilStatus').value = emp.civil_status || 'Single';
      document.getElementById('modalNationality').value = emp.nationality || 'Filipino';
      document.getElementById('modalGender').value = emp.gender || 'Male';
      document.getElementById('modalWorkLocation').value = emp.work_location || 'Main Office';
      document.getElementById('modalDepartment').value = emp.department || '';

      updateModalGmailPreview();

      document.getElementById('modalAddress').value = emp.address || '';
      document.getElementById('modalRole').value = emp.role || 'Staff';
      document.getElementById('modalEmploymentType').value = emp.employment_type || 'Regular';
      document.getElementById('modalSupervisor').value = emp.immediate_supervisor || '';
      document.getElementById('modalDateHired').value = emp.date_hired || new Date().toISOString().split('T')[0];
      document.getElementById('modalContractStart').value = emp.contract_start_date || '';
      document.getElementById('modalContractEnd').value = emp.contract_end_date || '';
      document.getElementById('modalContractDuration').value = emp.contract_duration_years || 1.0;

      document.getElementById('modalPassword').value = '';
      document.getElementById('passwordFeedback').className = "text-[11px] mt-1 text-gray-500";
      document.getElementById('passwordFeedback').innerHTML = "Dapat 8+ chars, may malaking titik, maliit na titik, numero, at symbol.";
      document.getElementById('modalSss').value = emp.sss_id || '';
      document.getElementById('modalPhilhealth').value = emp.philhealth_id || '';
      document.getElementById('modalPagibig').value = emp.pagibig_id || '';
      document.getElementById('modalGsis').value = emp.gsis_id || '';
      
      const defaultSal = (emp.role || 'Staff') === 'Manager' ? 20000 : 15000;
      document.getElementById('modalSalary').value = emp.salary ? emp.salary : defaultSal;

      if (!onboardingModalInstance) {
        onboardingModalInstance = new bootstrap.Modal(document.getElementById('onboardingModal'));
      }
      onboardingModalInstance.show();
    }

    async function submitOnboarding(event) {
      event.preventDefault();
      const id = document.getElementById('modalId').value;
      const password = document.getElementById('modalPassword').value;
      
      const formData = new URLSearchParams();
      formData.append('action', 'complete_onboarding');
      formData.append('id', id);
      formData.append('password', password);
      formData.append('company_name', document.getElementById('modalCompanyName').value);
      formData.append('company_address', document.getElementById('modalCompanyAddress').value);
      formData.append('contact_number', document.getElementById('modalContactNumber').value);
      formData.append('date_of_birth', document.getElementById('modalDob').value);
      formData.append('civil_status', document.getElementById('modalCivilStatus').value);
      formData.append('nationality', document.getElementById('modalNationality').value);
      formData.append('gender', document.getElementById('modalGender').value);
      formData.append('department', document.getElementById('modalDepartment').value);
      formData.append('role', document.getElementById('modalRole').value);
      formData.append('employment_type', document.getElementById('modalEmploymentType').value);
      formData.append('immediate_supervisor', document.getElementById('modalSupervisor').value);
      formData.append('date_hired', document.getElementById('modalDateHired').value);
      formData.append('contract_start_date', document.getElementById('modalContractStart').value);
      formData.append('contract_end_date', document.getElementById('modalContractEnd').value);
      formData.append('contract_duration_years', document.getElementById('modalContractDuration').value);
      formData.append('work_location', document.getElementById('modalWorkLocation').value);
      formData.append('salary', document.getElementById('modalSalary').value);

      try {
        const res = await fetch(window.location.pathname, {
          method: 'POST',
          body: formData
        });
        const result = await res.json();
        if (result.success) {
          onboardingModalInstance.hide();
          Swal.fire({
            icon: 'success',
            title: 'Fully Hired!',
            text: result.message,
            timer: 1500,
            showConfirmButton: false
          });
          loadEmployees();
        } else {
          Swal.fire('Error', result.message, 'error');
        }
      } catch (err) {
        console.error(err);
        Swal.fire('Error', 'Failed to process onboarding request.', 'error');
      }
    }

    document.addEventListener("DOMContentLoaded", function () {
      const currentPath = window.location.pathname;
      const navLinks = document.querySelectorAll(".sidebar-link");
      
      navLinks.forEach(link => {
        const linkPath = link.getAttribute("href");
        if (linkPath && currentPath.endsWith(linkPath)) {
          link.classList.remove("text-white/80", "hover:bg-white/10", "hover:text-white", "text-inherit");
          link.classList.add("bg-[#FF8C00]", "text-white", "shadow-md", "font-semibold");
        }
      });
    });

    function triggerDelete(dbId, name) {
      Swal.fire({
        title: 'Delete Data',
        text: `Are you sure you want to delete this data "${name}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#d33',
        cancelButtonColor: '#3085d6',
        confirmButtonText: 'Yes',
        cancelButtonText: 'Cancel'
      }).then(async (result) => {
        if (result.isConfirmed) {
          try {
            const formData = new FormData();
            formData.append('id', dbId);

            const response = await fetch(`${window.location.pathname}?action=delete_employee`, {
              method: 'POST',
              body: formData
            });

            const resultData = await response.json();
            if (resultData.success) {
              Swal.fire('Deleted!', resultData.message, 'success');
              allEmployees = allEmployees.filter(emp => emp.id != dbId);
              renderTables();
            } else {
              Swal.fire('Error!', resultData.message || "Failed.", 'error');
            }
          } catch (err) {
            console.error("Error:", err);
          }
        }
      });
    }

    function triggerPayslip(dbId) {
      const emp = allEmployees.find(e => e.id == dbId);
      if (!emp) return;

      const monthlyBase = emp.salary ? parseFloat(emp.salary) : (emp.role === 'Manager' ? 20000 : 15000);
      const monthlyAllowance = 1000; 
      const monthlyGross = monthlyBase + monthlyAllowance;

      const monthlySSS = emp.role === 'Manager' ? 900 : 675;
      const monthlyPhilHealth = emp.role === 'Manager' ? 400 : 300;
      const monthlyPagibig = 200;
      const monthlyTax = 0.00; 
      const monthlyDeductions = monthlySSS + monthlyPhilHealth + monthlyPagibig + monthlyTax;
      const monthlyNet = monthlyGross - monthlyDeductions;

      const kinsenasBase = monthlyBase / 2;
      const kinsenasAllowance = monthlyAllowance / 2;
      const kinsenasGross = kinsenasBase + kinsenasAllowance;

      const kinsenasSSS = monthlySSS / 2;
      const kinsenasPhilHealth = monthlyPhilHealth / 2;
      const kinsenasPagibig = monthlyPagibig / 2;
      const kinsenasTax = 0.00;
      const kinsenasDeductions = kinsenasSSS + kinsenasPhilHealth + kinsenasPagibig + kinsenasTax;
      const kinsenasNet = kinsenasGross - kinsenasDeductions;

      const f = (num) => num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

      const now = new Date();
      const day = now.getDate();
      const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
      const currentMonthYear = `${monthNames[now.getMonth()]} ${now.getFullYear()}`;
      const cutOffPeriod = day <= 15 ? `1st Cut-off (1–15, ${currentMonthYear})` : `2nd Cut-off (16–31, ${currentMonthYear})`;
      const payDateStr = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

      document.getElementById('printArea').innerHTML = `
        <div class="border border-gray-300 p-6 bg-white rounded-xl text-gray-800 text-xs">
          <div class="text-center border-b pb-4 mb-4">
            <h3 class="font-black text-xl tracking-wide uppercase text-gray-900">${emp.company_name || 'PannaKoda Stores Inc.'}</h3>
            <p class="text-[11px] text-gray-500 font-medium">${emp.company_address || '123 Business Corporate Center, Cavite, Philippines'}</p>
            <p class="text-[11px] text-gray-400 font-mono">TIN: 000-123-456-000</p>
            <div class="mt-2 inline-block bg-slate-100 text-slate-800 font-mono text-[11px] font-bold px-3 py-1 rounded">
              PAYSLIP STATEMENT | ${cutOffPeriod}
            </div>
          </div>
          
          <div class="grid grid-cols-2 gap-4 mb-4 border-b pb-4 bg-slate-50/60 p-3 rounded-lg">
             <div>
              <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Employee ID:</span> <span class="font-mono font-bold text-gray-800">${emp.display_emp_id}</span></p>
              <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Employee Name:</span> <span class="font-bold text-gray-800">${emp.full_name}</span></p>
              <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Department:</span> <span class="font-semibold text-gray-800">${emp.department}</span></p>
             </div>
             <div>
              <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Position/Role:</span> <span class="font-bold text-gray-800">${emp.role}</span></p>
              <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Pay Date:</span> <span class="font-mono text-gray-800">${payDateStr}</span></p>
              <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Tax Status:</span> <span class="font-semibold text-emerald-600">MWE Exempt (₱0.00 Tax)</span></p>
             </div>
          </div>

          <div class="grid grid-cols-2 gap-6 items-start mb-4">
            <div>
              <h6 class="font-bold text-xs text-gray-900 border-b pb-1.5 mb-2 uppercase tracking-wide">Earnings Breakdown</h6>
              <div class="space-y-1">
                <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                  <span class="text-gray-600">Basic Pay (Kinsenas)</span> 
                  <span class="font-semibold font-mono">₱${f(kinsenasBase)}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                  <span class="text-gray-600">Allowances (Rice/Meal)</span> 
                  <span class="font-semibold font-mono">₱${f(kinsenasAllowance)}</span>
                </div>
                <div class="flex justify-between py-1.5 font-bold text-gray-900 bg-gray-50 px-2 rounded mt-1">
                  <span>Gross Pay (Period)</span> 
                  <span class="font-mono text-emerald-700">₱${f(kinsenasGross)}</span>
                </div>
              </div>
            </div>

            <div>
              <h6 class="font-bold text-xs text-gray-900 border-b pb-1.5 mb-2 uppercase tracking-wide">Deductions Breakdown</h6>
              <div class="space-y-1">
                <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                  <span class="text-gray-600">SSS Contribution</span> 
                  <span class="font-mono text-red-600">-₱${f(kinsenasSSS)}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                  <span class="text-gray-600">PhilHealth Contribution</span> 
                  <span class="font-mono text-red-600">-₱${f(kinsenasPhilHealth)}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                  <span class="text-gray-600">Pag-IBIG Contribution</span> 
                  <span class="font-mono text-red-600">-₱${f(kinsenasPagibig)}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                  <span class="text-gray-600">Withholding Tax (BIR)</span> 
                  <span class="font-mono text-emerald-600 font-semibold">₱0.00</span>
                </div>
                <div class="flex justify-between py-1.5 font-bold text-gray-900 bg-gray-50 px-2 rounded mt-1">
                  <span>Total Deductions</span> 
                  <span class="font-mono text-red-600">-₱${f(kinsenasDeductions)}</span>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-slate-100 p-2.5 rounded-lg mb-4 text-[11px] grid grid-cols-2 gap-2 text-gray-600 border border-slate-200">
            <div><span class="font-semibold">Monthly Base Reference:</span> ₱${f(monthlyBase)}</div>
            <div><span class="font-semibold">Monthly Gross Reference:</span> ₱${f(monthlyGross)}</div>
            <div><span class="font-semibold">Monthly Total Deductions:</span> ₱${f(monthlyDeductions)}</div>
            <div><span class="font-semibold">Monthly Net Reference:</span> ₱${f(monthlyNet)}</div>
          </div>

          <div class="bg-[#212121] text-white p-3.5 rounded-xl flex justify-between items-center shadow-inner">
            <div>
              <h4 class="text-[10px] uppercase tracking-widest text-white/60">Net Pay for this Period</h4>
              <p class="text-[10px] text-white/40">Kinsenas Payout Calculation</p>
            </div>
            <div class="text-right">
              <h2 class="text-xl font-black text-[#FF8C00] font-mono">₱${f(kinsenasNet)}</h2>
            </div>
          </div>
        </div>
      `;

      if (!bsModalInstance) {
        bsModalInstance = new bootstrap.Modal(document.getElementById('payslipModal'));
      }
      bsModalInstance.show();
    }

    function switchTab(activeBtnId, activeTableId) {
      const tabs = ['tabNewlyHired', 'tabPersonal', 'tabPayroll'];
      const tables = ['newlyHiredTable', 'personalTable', 'payrollTable'];

      tabs.forEach(id => {
        const btn = document.getElementById(id);
        if (id === activeBtnId) {
          btn.className = "px-4 py-2 rounded-lg text-sm transition-all flex items-center gap-2 bg-white text-gray-900 shadow-sm font-semibold";
        } else {
          btn.className = "px-4 py-2 rounded-lg text-sm transition-all flex items-center gap-2 text-gray-600 hover:text-gray-900";
        }
      });

      tables.forEach(id => {
        const tbl = document.getElementById(id);
        if (id === activeTableId) {
          tbl.classList.remove('d-none');
        } else {
          tbl.classList.add('d-none');
        }
      });
    }

    document.getElementById('tabNewlyHired').addEventListener('click', () => switchTab('tabNewlyHired', 'newlyHiredTable'));
    document.getElementById('tabPersonal').addEventListener('click', () => switchTab('tabPersonal', 'personalTable'));
    document.getElementById('tabPayroll').addEventListener('click', () => switchTab('tabPayroll', 'payrollTable'));

    document.getElementById('searchInput').addEventListener('input', renderTables);
    document.getElementById('btnPrintStatement').addEventListener('click', () => window.print());

    window.addEventListener('DOMContentLoaded', loadEmployees);
  </script>
</body>
</html>