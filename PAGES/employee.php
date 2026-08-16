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
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}

// 1. HANDLE ONBOARDING SUBMISSION / UPDATE VIA POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'complete_onboarding') {
    if (ob_get_length()) ob_clean();
    header('Content-Type: application/json');

    $id = intval($_POST['id']);
    $password_input = $_POST['password'] ?? '@Lozada23';
    
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

    $employee_id = 'EMP-' . date('Y') . '-' . str_pad($id, 4, '0', STR_PAD_LEFT);
    $password_hash = password_hash($password_input, PASSWORD_DEFAULT);

    $emp_check_query = "SELECT * FROM employees WHERE id = $id LIMIT 1";
    $emp_result = $conn->query($emp_check_query);
    
    if ($emp_result && $emp_result->num_rows > 0) {
        $emp_data = $emp_result->fetch_assoc();
        
        if ($salary <= 0) {
            $salary = isset($emp_data['salary']) && floatval($emp_data['salary']) > 0 ? floatval($emp_data['salary']) : (($role === 'Manager') ? 45000.00 : 22000.00);
        }

        $full_name = trim($emp_data['full_name']);
        
        $name_parts = explode(' ', $full_name);
        $firstname = strtolower(preg_replace('/[^a-z]/', '', $name_parts[0]));
        $lastname = count($name_parts) > 1 ? strtolower(preg_replace('/[^a-z]/', '', end($name_parts))) : $firstname;
        
        // Pinalitan/ginamit ang 'email' column sa halip na 'employee_gmail'
        $email = !empty($emp_data['email']) ? $emp_data['email'] : ($lastname . '.' . $firstname . '@gmail.com');

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

        $update_sql = "UPDATE employees SET 
            employee_id = '$employee_id', 
            employee_password = '$password_hash', 
            email = '$email',
            company_gmail = '$company_gmail',
            department = '$department', 
            position_title = '$role',
            salary = $salary,
            employment_type = '$employment_type',
            immediate_supervisor = '$immediate_supervisor',
            date_hired = '$date_hired',
            contract_start_date = '$contract_start_date',
            contract_end_date = '$contract_end_date',
            contract_duration_years = $contract_duration_years,
            work_location = '$work_location',
            status = 'hired' 
            WHERE id = $id";
        
        if ($conn->query($update_sql)) {
            echo json_encode(["success" => true, "message" => "Employee successfully fully hired with fixed salary and contract saved!"]);
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

        $resolved_role = trim($row['position_title'] ?? ($row['position'] ?? ($row['role'] ?? '')));
        if (empty($resolved_role)) {
            $resolved_role = 'Staff';
        }
        $row['role'] = $resolved_role;

        if (!isset($row['date_hired']) || empty($row['date_hired'])) {
            $row['date_hired'] = date('Y-m-d'); 
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
  <title>Employee Management & Payroll (PH Standards)</title>
  
  <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
  <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
  <script src="../LIBRARIES/tailwind.js"></script> 
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <link href="../LIBRARIES/AOS/aos.css" rel="stylesheet">
  <script src="../LIBRARIES/AOS/AOS.js"></script>

  <style>
    @media print {
      body * { visibility: hidden; }
      #printArea, #printArea * { visibility: visible; }
      #printArea { position: absolute; left: 0; top: 0; width: 100%; padding: 0; margin: 0; background: white !important; color: black !important; }
      .no-print { display: none !important; }
    }
    
    .table tbody tr {
      transition: all 0.25s cubic-bezier(0.4, 0, 0.2, 1);
    }

    .table tbody tr:hover {
      transform: scale(1.004) translateY(-1px);
      background-color: rgba(255, 107, 74, 0.05) !important;
      box-shadow: 0 4px 12px rgba(0,0,0,0.03);
    }

    @keyframes fadeInScale {
      from { opacity: 0; transform: scale(0.97); }
      to { opacity: 1; transform: scale(1); }
    }
    .animate-fade-in {
      animation: fadeInScale 0.3s ease-out forwards;
    }

    body.dark-mode {
      background-color: #0f172a !important;
      color: #f8fafc !important;
    }
    body.dark-mode .bg-white {
      background-color: #1e293b !important;
      color: #f8fafc !important;
      border-color: #334155 !important;
    }
    body.dark-mode .bg-slate-50 {
      background-color: #111827 !important;
      border-color: #374151 !important;
      color: #f8fafc !important;
    }
    body.dark-mode .text-slate-800, body.dark-mode .text-slate-700 {
      color: #f1f5f9 !important;
    }
    body.dark-mode .text-slate-400, body.dark-mode .text-slate-500 {
      color: #94a3b8 !important;
    }
    body.dark-mode .form-control, body.dark-mode select {
      background-color: #1f2937 !important;
      color: #f8fafc !important;
      border-color: #374151 !important;
    }

    ::-webkit-scrollbar {
      width: 6px;
      height: 6px;
    }
    ::-webkit-scrollbar-track {
      background: #f1f5f9;
    }
    ::-webkit-scrollbar-thumb {
      background: #cbd5e1;
      border-radius: 4px;
    }
    ::-webkit-scrollbar-thumb:hover {
      background: #94a3b8;
    }
  </style>
</head>
<body class="bg-white font-sans antialiased h-screen overflow-hidden transition-colors duration-300">

  <div class="flex h-screen w-full overflow-hidden">
    
    <?php include 'sidebar.php'; ?>
    <div class="flex-1 h-screen overflow-y-auto p-8 bg-white min-w-0 transition-colors duration-300" id="mainContentArea">
      
      <!-- BANNER HEADER -->
      <div data-aos="fade-down" class="relative overflow-hidden bg-gradient-to-r from-[#1a1010] via-[#1f1212] to-[#09090b] rounded-3xl shadow-xl p-8 mb-8 text-white border border-[#ff6b4a]/30 transition-all duration-300 hover:shadow-2xl">
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-[#ff6b4a]/20 rounded-full blur-3xl pointer-events-none animate-pulse"></div>
        <div class="absolute left-1/3 -top-20 w-48 h-48 bg-orange-500/10 rounded-full blur-2xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
          <div>
            <div class="inline-flex items-center gap-2 px-3.5 py-1.5 rounded-full bg-[#ff6b4a]/20 backdrop-blur-md border border-[#ff6b4a]/30 text-xs font-semibold uppercase tracking-wider text-[white] mb-3 shadow-sm">
              <i class="bi bi-people-fill"></i> HR & Admin Department
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight text-[white] mb-2">Employee Directory & PH Payroll</h1>
            <p class="text-sm text-slate-300 max-w-2xl leading-relaxed">
              Manage candidates, onboarding profiles, statutory numbers, department metrics, and Philippine-compliant payroll statements seamlessly.
            </p>
          </div>

          <div class="flex items-center gap-3 flex-wrap">
            <div class="bg-white/10 backdrop-blur-md border border-white/15 px-5 py-3 rounded-2xl flex items-center gap-4 shrink-0 shadow-inner">
              <div class="w-10 h-10 rounded-xl bg-[#ff6b4a]/30 flex items-center justify-center text-[#ff6b4a]">
                <i class="bi bi-shield-lock-fill text-xl"></i>
              </div>
              <div>
                <span class="block text-xs text-slate-300 font-medium">Total Workforce</span>
                <span id="activeCountBadge" class="text-lg font-bold text-white">Loading...</span>
              </div>
            </div>
          </div>
        </div>

        <div class="relative z-10 mt-6 pt-6 border-t border-white/10 flex flex-wrap justify-between items-center gap-4">
          <div class="text-xs text-slate-400 font-medium hidden sm:block">
            <i class="bi bi-sliders mr-1"></i> Switch active directory view below
          </div>
          <div class="bg-black/30 backdrop-blur-md p-1.5 rounded-xl flex gap-1.5 border border-[#ff6b4a]/20 ml-auto shadow-inner">
            <button id="tabNewlyHired" class="px-4 py-2 rounded-lg text-sm transition-all duration-300 flex items-center gap-2 bg-[#ff6b4a] text-white shadow-md font-semibold transform hover:scale-[1.02]">
              <i class="bi bi-person-plus"></i> Newly Hired <span id="badgeNewlyHired" class="badge bg-white/25 text-white rounded-pill px-2">0</span>
            </button>
            <button id="tabPersonal" class="px-4 py-2 rounded-lg text-sm transition-all duration-300 flex items-center gap-2 text-slate-300 hover:text-white hover:bg-[#ff6b4a]/20">
              <i class="bi bi-person-bounding-box"></i> Personal Details
            </button>
            <button id="tabPayroll" class="px-4 py-2 rounded-lg text-sm transition-all duration-300 flex items-center gap-2 text-slate-300 hover:text-white hover:bg-[#ff6b4a]/20">
              <i class="bi bi-cash-stack"></i> Payroll Profile
            </button>
          </div>
        </div>
      </div>

      <!-- METRIC CARDS -->
      <div data-aos="fade-up" data-aos-delay="100" class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <div class="bg-white-50 p-5 rounded-2xl shadow-sm border border-slate-200/80 flex items-center gap-4 transition-all duration-300 hover:shadow-md hover:border-[#ff6b4a]/50 group transform hover:-translate-y-1">
          <div class="w-12 h-12 rounded-2xl bg-[#ff6b4a]/10 text-[#ff6b4a] flex items-center justify-center text-xl font-bold transition-transform duration-300 group-hover:scale-110 group-hover:rotate-6">
            <i class="bi bi-people-fill"></i>
          </div>
          <div>
            <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Total Hired</p>
            <h3 id="statTotalHired" class="text-2xl font-black text-slate-800 transition-all duration-300">0</h3>
          </div>
        </div>

        <div class="bg-white-50 p-5 rounded-2xl shadow-sm border border-slate-200/80 flex items-center gap-4 transition-all duration-300 hover:shadow-md hover:border-[#ff6b4a]/50 group transform hover:-translate-y-1">
          <div class="w-12 h-12 rounded-2xl bg-[#ff6b4a]/10 text-[#ff6b4a] flex items-center justify-center text-xl font-bold transition-transform duration-300 group-hover:scale-110 group-hover:rotate-6">
            <i class="bi bi-person-plus-fill"></i>
          </div>
          <div>
            <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider">Onboarding</p>
            <h3 id="statOnboarding" class="text-2xl font-black text-slate-800 transition-all duration-300">0</h3>
          </div>
        </div>

        <div class="bg-white-50 p-5 rounded-2xl shadow-sm border border-slate-200/80 md:col-span-2 flex flex-col justify-between transition-all duration-300 hover:shadow-md hover:border-[#ff6b4a]/50">
          <p class="text-xs text-slate-400 font-semibold uppercase tracking-wider mb-2">Employees per Department</p>
          <div id="deptBreakdownContainer" class="flex flex-wrap gap-2">
            <span class="text-xs text-slate-400 italic">Calculating breakdown...</span>
          </div>
        </div>
      </div>

      <!-- FEATURE BAR -->
      <div data-aos="fade-up" data-aos-delay="200" class="mb-6 space-y-4">
        <div class="flex flex-col md:flex-row items-center gap-3 bg-white-50 p-4 rounded-2xl shadow-sm border border-slate-200/80">
          <div class="relative flex-1 w-full">
            <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-slate-400">
              <i class="bi bi-search"></i>
            </span>
            <input id="searchInput" type="text" class="form-control pl-10 pr-10 py-2.5 rounded-xl text-sm border-slate-200 focus:border-[#ff6b4a] focus:ring-0 shadow-none bg-white transition-all" placeholder="Search by ID, name, department, or role...">
            <button onclick="clearSearchInput()" id="clearSearchBtn" class="absolute inset-y-0 right-0 pr-3 flex items-center text-slate-400 hover:text-[#ff6b4a] d-none transition-colors" title="Clear Search">
              <i class="bi bi-x-circle-fill text-base"></i>
            </button>
          </div>

          <div class="flex items-center gap-2 w-full md:w-auto justify-end flex-wrap">
            <button onclick="exportTableToCSV()" class="btn btn-sm bg-emerald-50 hover:bg-emerald-100 text-emerald-700 border border-emerald-200 px-3.5 py-2.5 rounded-xl font-semibold text-xs flex items-center gap-1.5 transition-all transform hover:scale-105">
              <i class="bi bi-file-earmark-arrow-down-fill text-emerald-600"></i> Export CSV
            </button>
            <button onclick="window.print()" class="btn btn-sm bg-slate-100 hover:bg-slate-200 text-slate-700 border border-slate-200 px-3.5 py-2.5 rounded-xl font-semibold text-xs flex items-center gap-1.5 transition-all transform hover:scale-105">
              <i class="bi bi-printer-fill text-slate-600"></i> Print List
            </button>
          </div>
        </div>

        <div class="flex flex-wrap items-center justify-between gap-3 px-2">
          <div class="flex items-center gap-2 flex-wrap" id="departmentFilterPills">
            <span class="text-xs font-semibold text-slate-400 uppercase tracking-wide mr-1"><i class="bi bi-funnel-fill text-[#ff6b4a]"></i> Filter Dept:</span>
            <button onclick="filterByDepartment('All')" class="dept-pill px-3 py-1.5 rounded-xl text-xs font-semibold bg-[#ff6b4a] text-white shadow-sm transition-all transform hover:scale-105 active">All Departments</button>
          </div>

          <div class="flex items-center gap-2">
            <div class="flex items-center gap-1.5 text-xs text-slate-500 font-medium">
              <span>Show:</span>
              <select id="rowsPerPageSelect" onchange="changeRowsPerPage()" class="form-control form-control-sm text-xs rounded-lg py-1 px-2 border-slate-200 w-auto">
                <option value="5">5 rows</option>
                <option value="10" selected>10 rows</option>
                <option value="25">25 rows</option>
                <option value="all">All rows</option>
              </select>
            </div>
          </div>
        </div>
      </div>

      <!-- TABLES SECTION -->
      <div class="bg-white-50 rounded-2xl shadow-sm border border-slate-200/80 p-6 overflow-hidden transition-all duration-300">
        
        <div class="table-responsive bg-white rounded-xl overflow-hidden border border-slate-200/60 p-2 relative">
          
          <div id="tableLoadingOverlay" class="absolute inset-0 bg-white/80 backdrop-blur-xs z-20 flex flex-col items-center justify-center transition-opacity d-none">
            <div class="w-8 h-8 border-3 border-[#ff6b4a] border-t-transparent rounded-full animate-spin mb-2"></div>
            <span class="text-xs font-semibold text-slate-600">Updating table view...</span>
          </div>

          <!-- 1. NEWLY HIRED TABLE -->
          <table id="newlyHiredTable" class="table table-hover align-middle mb-0 text-sm animate-fade-in">
            <thead>
              <tr class="border-b border-slate-200">
                <th class="py-3 px-4 bg-transparent text-slate-700 font-bold border-0">Full Name</th>
                <th class="py-3 px-4 bg-transparent text-slate-700 font-bold border-0">Position/Role</th>
                <th class="py-3 px-4 bg-transparent text-slate-700 font-bold border-0">Status</th>
                <th class="py-3 px-4 bg-transparent text-slate-700 font-bold border-0 text-center">Action (Onboarding Form)</th>
              </tr>
            </thead>
            <tbody id="newlyHiredBody"></tbody>
          </table>

          <!-- 2. PERSONAL DETAILS TABLE -->
          <table id="personalTable" class="table table-hover align-middle mb-0 text-sm d-none animate-fade-in">
            <thead>
              <tr class="border-b border-slate-200">
                <th class="py-3 px-4 bg-transparent text-slate-700 font-bold border-0">Employee ID</th>
                <th class="py-3 px-4 bg-transparent text-slate-700 font-bold border-0">Full Name</th>
                <th class="py-3 px-4 bg-transparent text-slate-700 font-bold border-0">Email</th>
                <th class="py-3 px-4 bg-transparent text-slate-700 font-bold border-0">Role & Dept</th>
                <th class="py-3 px-4 bg-transparent text-slate-700 font-bold border-0 text-center">Contract</th>
                <th class="py-3 px-4 bg-transparent text-slate-700 font-bold border-0 text-center">Action</th>
              </tr>
            </thead>
            <tbody id="personalBody"></tbody>
          </table>

          <!-- 3. PAYROLL PROFILE TABLE -->
          <table id="payrollTable" class="table table-hover align-middle mb-0 text-sm d-none animate-fade-in">
            <thead>
              <tr class="border-b border-slate-200">
                <th class="py-3 px-4 bg-transparent text-slate-700 font-bold border-0">Full Name</th>
                <th class="py-3 px-4 bg-transparent text-slate-700 font-bold border-0">Role</th>
                <th class="py-3 px-4 bg-transparent text-slate-700 font-bold border-0">SSS No.</th>
                <th class="py-3 px-4 bg-transparent text-slate-700 font-bold border-0">PhilHealth</th>
                <th class="py-3 px-4 bg-transparent text-slate-700 font-bold border-0">Pag-IBIG No.</th>
                <th class="py-3 px-4 bg-transparent text-slate-700 font-bold border-0">GSIS No.</th>
                <th class="py-3 px-4 bg-transparent text-slate-700 font-bold border-0 text-end">Base Salary</th>
                <th class="py-3 px-4 bg-transparent text-slate-700 font-bold border-0 text-center">Action</th>
              </tr>
            </thead>
            <tbody id="payrollBody"></tbody>
          </table>

        </div>

        <div class="flex flex-col sm:flex-row items-center justify-between gap-3 mt-4 px-2 text-xs text-slate-500 font-medium">
          <div id="tableRecordCountInfo">Showing 0 entries</div>
          <div class="flex items-center gap-1" id="paginationControlsContainer"></div>
        </div>

      </div>
    </div>

    <!-- PAYSLIP MODAL -->
    <div class="modal fade" id="payslipModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-3xl shadow-2xl border-0 overflow-hidden animate-fade-in">
          <div class="modal-header border-0 bg-slate-50 px-6 py-4 no-print">
            <h5 class="modal-title font-bold text-slate-800 flex items-center gap-2">
              <i class="bi bi-receipt text-[#ff6b4a]"></i> Corporate Payroll Statement (PH Standards)
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <div class="modal-body p-6" id="printArea"></div>
          <div class="modal-footer border-0 bg-slate-50 px-6 py-4 no-print">
            <button type="button" class="btn btn-light font-semibold border text-slate-600 px-4 rounded-xl" data-bs-dismiss="modal">Close</button>
            <button type="button" id="btnPrintStatement" class="btn font-semibold bg-[#ff6b4a] hover:bg-[#fa4b2a] text-white border-0 px-5 rounded-xl flex items-center gap-2 shadow-sm transition-all duration-200">
              <i class="bi bi-printer"></i> Print Statement
            </button>
          </div>
        </div>
      </div>
    </div>

    <!-- ONBOARDING FORM MODAL -->
    <div class="modal fade" id="onboardingModal" data-bs-backdrop="static" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content rounded-3xl shadow-2xl border-0 overflow-hidden animate-fade-in">
          <div class="modal-header border-0 bg-slate-50 px-6 py-4">
            <h5 class="modal-title font-bold text-slate-800 flex items-center gap-2">
              <i class="bi bi-person-check text-[#ff6b4a]"></i> Complete Onboarding Details
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
          </div>
          <form id="onboardingForm" onsubmit="submitOnboarding(event)">
            <div class="modal-body p-6 space-y-4 max-h-[75vh] overflow-y-auto">
              <input type="hidden" id="modalId">
              
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Full Name</label>
                  <input type="text" id="modalName" readonly class="form-control bg-slate-100 text-sm rounded-xl border-slate-200">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Contact Number</label>
                  <input type="text" id="modalContactNumber" class="form-control text-sm rounded-xl border-slate-200 focus:border-[#ff6b4a] focus:ring-0" placeholder="e.g. 09123456789">
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Company Name</label>
                  <input type="text" id="modalCompanyName" class="form-control text-sm rounded-xl border-slate-200 focus:border-[#ff6b4a] focus:ring-0" value="PannaKoda Stores Inc.">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Company Address</label>
                  <input type="text" id="modalCompanyAddress" class="form-control text-sm rounded-xl border-slate-200 focus:border-[#ff6b4a] focus:ring-0" placeholder="e.g. Dasmarinas Cavite">
                </div>
              </div>

              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Date of Birth</label>
                  <input type="date" id="modalDob" class="form-control text-sm rounded-xl border-slate-200 focus:border-[#ff6b4a] focus:ring-0">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Civil Status</label>
                  <select id="modalCivilStatus" class="form-control text-sm rounded-xl border-slate-200 focus:border-[#ff6b4a] focus:ring-0">
                    <option value="Single">Single</option>
                    <option value="Married">Married</option>
                    <option value="Widowed">Widowed</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Nationality</label>
                  <input type="text" id="modalNationality" class="form-control text-sm rounded-xl border-slate-200 focus:border-[#ff6b4a] focus:ring-0" value="Filipino">
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Gender</label>
                  <select id="modalGender" class="form-control text-sm rounded-xl border-slate-200 focus:border-[#ff6b4a] focus:ring-0">
                    <option value="Male">Male</option>
                    <option value="Female">Female</option>
                    <option value="Other">Other</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Work Location</label>
                  <input type="text" id="modalWorkLocation" class="form-control text-sm rounded-xl border-slate-200 focus:border-[#ff6b4a] focus:ring-0" value="Main Office">
                </div>
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Home Address</label>
                <textarea id="modalAddress" readonly rows="2" class="form-control bg-slate-100 text-sm rounded-xl border-slate-200"></textarea>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Email (Initial)</label>
                  <input type="text" id="modalEmployeeGmail" class="form-control text-sm rounded-xl border-slate-200 text-[#ff6b4a] font-medium" placeholder="employee@gmail.com">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Company Gmail</label>
                  <input type="text" id="modalCompanyGmail" readonly class="form-control bg-slate-100 text-sm rounded-xl border-slate-200 text-emerald-700 font-medium">
                </div>
              </div>

              <div>
                <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Set Password</label>
                <input type="text" id="modalPassword" value="@Lozada23" readonly class="form-control bg-slate-100 text-sm rounded-xl border-slate-200 text-[#ff6b4a] font-semibold cursor-not-allowed">
                <div id="passwordFeedback" class="text-[11px] mt-1 text-emerald-600 font-semibold">
                  <i class="bi bi-check-circle-fill"></i> Fixed Default Password
                </div>
              </div>

              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Department</label>
                  <input type="text" id="modalDepartment" required class="form-control text-sm rounded-xl border-slate-200 focus:border-[#ff6b4a] focus:ring-0" placeholder="e.g. HR, Finance, Manager, Staff" oninput="updateModalGmailPreview()">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Role / Position Tier</label>
                  <select id="modalRole" class="form-control text-sm rounded-xl border-slate-200 focus:border-[#ff6b4a] focus:ring-0" onchange="updateDefaultSalary()">
                    <option value="Staff">Staff</option>
                    <option value="Manager">Manager</option>
                  </select>
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Employment Type</label>
                  <select id="modalEmploymentType" class="form-control text-sm rounded-xl border-slate-200 focus:border-[#ff6b4a] focus:ring-0">
                    <option value="Regular">Regular</option>
                    <option value="Probationary">Probationary</option>
                    <option value="Part-Time">Part-Time</option>
                    <option value="Full-Time">Full-Time</option>
                  </select>
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Immediate Supervisor</label>
                  <input type="text" id="modalSupervisor" class="form-control text-sm rounded-xl border-slate-200 focus:border-[#ff6b4a] focus:ring-0" placeholder="Supervisor Name">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Base Salary (PHP)</label>
                  <input type="number" step="0.01" id="modalSalary" required class="form-control text-sm rounded-xl border-slate-200 focus:border-[#ff6b4a] focus:ring-0" placeholder="Base salary">
                </div>
              </div>

              <div class="grid grid-cols-3 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Date Hired</label>
                  <input type="date" id="modalDateHired" class="form-control text-sm rounded-xl border-slate-200 focus:border-[#ff6b4a] focus:ring-0">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Contract Start Date</label>
                  <input type="date" id="modalContractStart" class="form-control text-sm rounded-xl border-slate-200 focus:border-[#ff6b4a] focus:ring-0">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Contract End Date</label>
                  <input type="date" id="modalContractEnd" class="form-control text-sm rounded-xl border-slate-200 focus:border-[#ff6b4a] focus:ring-0">
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Contract Duration (Years)</label>
                  <input type="number" step="0.1" id="modalContractDuration" class="form-control text-sm rounded-xl border-slate-200 focus:border-[#ff6b4a] focus:ring-0" value="1.0">
                </div>
              </div>

              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">SSS No.</label>
                  <input type="text" id="modalSss" readonly class="form-control bg-slate-100 text-sm rounded-xl border-slate-200">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">PhilHealth No.</label>
                  <input type="text" id="modalPhilhealth" readonly class="form-control bg-slate-100 text-sm rounded-xl border-slate-200">
                </div>
              </div>
              <div class="grid grid-cols-2 gap-3">
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">Pag-IBIG No.</label>
                  <input type="text" id="modalPagibig" readonly class="form-control bg-slate-100 text-sm rounded-xl border-slate-200">
                </div>
                <div>
                  <label class="block text-xs font-semibold text-slate-500 uppercase mb-1">GSIS No.</label>
                  <input type="text" id="modalGsis" readonly class="form-control bg-slate-100 text-sm rounded-xl border-slate-200">
                </div>
              </div>
            </div>
            <div class="modal-footer border-0 bg-slate-50 px-6 py-4">
              <button type="button" class="btn btn-light font-semibold border text-slate-600 px-4 rounded-xl" data-bs-dismiss="modal">Cancel</button>
              <button type="submit" class="btn font-semibold bg-[#ff6b4a] hover:bg-[#fa4b2a] text-white border-0 px-5 rounded-xl shadow-sm transition-all duration-200">Fully Hired & Save</button>
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
    
    let selectedDepartmentFilter = 'All';
    let currentPage = 1;
    let rowsPerPage = 10;

    async function loadEmployees() {
      showTableLoading(true);
      try {
        const response = await fetch(`${window.location.pathname}?action=fetch_employees`);
        allEmployees = response.ok ? await response.json() : [];
        updateMetrics();
        buildDepartmentFilterPills();
        renderTables();
      } catch (err) {
        console.error("Pipeline failure:", err);
      } finally {
        showTableLoading(false);
      }
    }

    function showTableLoading(show) {
      const overlay = document.getElementById('tableLoadingOverlay');
      if (overlay) {
        if (show) overlay.classList.remove('d-none');
        else overlay.classList.add('d-none');
      }
    }

    function updateMetrics() {
      let totalHired = 0;
      let onboardingCount = 0;
      let deptCounts = {};

      allEmployees.forEach(emp => {
        const status = String(emp.status || 'onboarding').toLowerCase();
        if (status === 'hired') {
          totalHired++;
          const dept = emp.department || 'Unassigned';
          deptCounts[dept] = (deptCounts[dept] || 0) + 1;
        } else {
          onboardingCount++;
        }
      });

      document.getElementById('statTotalHired').innerText = totalHired;
      document.getElementById('statOnboarding').innerText = onboardingCount;
      document.getElementById('activeCountBadge').innerText = `${totalHired + onboardingCount} Total`;
      document.getElementById('badgeNewlyHired').innerText = onboardingCount;

      const deptContainer = document.getElementById('deptBreakdownContainer');
      deptContainer.innerHTML = '';
      if (Object.keys(deptCounts).length === 0) {
        deptContainer.innerHTML = `<span class="text-xs text-slate-400 italic">No department data.</span>`;
      } else {
        for (const [dept, count] of Object.entries(deptCounts)) {
          const badge = document.createElement('div');
          badge.className = "bg-orange-50 border border-orange-100 text-orange-800 px-3 py-1.5 rounded-xl text-xs font-semibold flex items-center gap-1.5 transition-all duration-200 hover:bg-orange-100 transform hover:scale-105";
          badge.innerHTML = `<span>${dept}:</span> <span class="bg-[#ff6b4a] text-white px-2 py-0.5 rounded-lg text-[10px] font-bold">${count}</span>`;
          deptContainer.appendChild(badge);
        }
      }
    }

    function buildDepartmentFilterPills() {
      const container = document.getElementById('departmentFilterPills');
      const allBtnHTML = `<span class="text-xs font-semibold text-slate-400 uppercase tracking-wide mr-1"><i class="bi bi-funnel-fill text-[#ff6b4a]"></i> Filter Dept:</span>
        <button onclick="filterByDepartment('All')" class="dept-pill px-3 py-1.5 rounded-xl text-xs font-semibold ${selectedDepartmentFilter === 'All' ? 'bg-[#ff6b4a] text-white shadow-sm' : 'bg-slate-200 text-slate-700 hover:bg-slate-300'} transition-all transform hover:scale-105">All Departments</button>`;
      
      let depts = new Set();
      allEmployees.forEach(e => { if(e.department) depts.add(e.department); });

      let pillsHTML = allBtnHTML;
      depts.forEach(d => {
        const isActive = selectedDepartmentFilter === d;
        pillsHTML += `<button onclick="filterByDepartment('${d}')" class="dept-pill px-3 py-1.5 rounded-xl text-xs font-semibold ${isActive ? 'bg-[#ff6b4a] text-white shadow-sm' : 'bg-slate-200 text-slate-700 hover:bg-slate-300'} transition-all transform hover:scale-105">${d}</button>`;
      });
      container.innerHTML = pillsHTML;
    }

    function filterByDepartment(dept) {
      selectedDepartmentFilter = dept;
      currentPage = 1;
      buildDepartmentFilterPills();
      renderTables();
    }

    function clearSearchInput() {
      const input = document.getElementById('searchInput');
      input.value = '';
      document.getElementById('clearSearchBtn').classList.add('d-none');
      renderTables();
    }

    document.getElementById('searchInput').addEventListener('input', (e) => {
      const val = e.target.value.trim();
      const clearBtn = document.getElementById('clearSearchBtn');
      if (val.length > 0) clearBtn.classList.remove('d-none');
      else clearBtn.classList.add('d-none');
      currentPage = 1;
      renderTables();
    });

    function changeRowsPerPage() {
      const val = document.getElementById('rowsPerPageSelect').value;
      rowsPerPage = val === 'all' ? 999999 : parseInt(val);
      currentPage = 1;
      renderTables();
    }

    function updateDefaultSalary() {
      const role = document.getElementById('modalRole').value;
      const salaryInput = document.getElementById('modalSalary');
      if (!salaryInput.value || parseFloat(salaryInput.value) <= 0) {
        salaryInput.value = role === 'Manager' ? 45000 : 22000;
      }
    }

    function updateModalGmailPreview() {
      const deptInput = document.getElementById('modalDepartment').value.trim().toLowerCase();
      const companyEmailField = document.getElementById('modalCompanyGmail');
      const empName = document.getElementById('modalName').value;
      const empId = document.getElementById('modalId').value;

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

    function renderTables() {
      const query = document.getElementById('searchInput').value.toLowerCase().trim();
      
      const filtered = allEmployees.filter(emp => {
        const customId = String(emp.display_emp_id).toLowerCase();
        const name = String(emp.full_name || '').toLowerCase();
        const dept = String(emp.department || '').toLowerCase();
        const role = String(emp.role || '').toLowerCase();
        const gmail = String(emp.email || '').toLowerCase(); // Pinalitan ang employee_gmail ng email
        
        const matchesSearch = customId.includes(query) || name.includes(query) || dept.includes(query) || role.includes(query) || gmail.includes(query);
        const matchesDept = selectedDepartmentFilter === 'All' || emp.department === selectedDepartmentFilter;
        
        return matchesSearch && matchesDept;
      });

      const totalRecords = filtered.length;
      const totalPages = Math.ceil(totalRecords / rowsPerPage) || 1;
      if (currentPage > totalPages) currentPage = totalPages;
      
      const startIndex = (currentPage - 1) * rowsPerPage;
      const paginatedItems = filtered.slice(startIndex, startIndex + rowsPerPage);

      const newlyHiredBody = document.getElementById('newlyHiredBody');
      const personalBody = document.getElementById('personalBody');
      const payrollBody = document.getElementById('payrollBody');

      newlyHiredBody.innerHTML = '';
      personalBody.innerHTML = '';
      payrollBody.innerHTML = '';

      let onboardingCount = 0;
      let hiredPersonalCount = 0;
      let activePayrollCount = 0;

      paginatedItems.forEach(emp => {
        const status = (emp.status || 'onboarding').toLowerCase();
        const baseSalary = emp.salary ? parseFloat(emp.salary) : (emp.role === 'Manager' ? 45000 : 22000);
        
        const displayRole = (emp.role && emp.role.trim() !== '') ? emp.role : 'Staff';
        const roleClass = displayRole === 'Manager' ? 'bg-orange-50 text-orange-700 border-orange-200' : 'bg-slate-100 text-slate-700 border-slate-200';

        if (status !== 'hired') {
          onboardingCount++;
          const trNew = document.createElement('tr');
          trNew.className = "border-b border-slate-100 hover:bg-orange-50/40 transition-all duration-200";
          trNew.innerHTML = `
            <td class="py-3.5 px-4 font-semibold text-slate-800">${emp.full_name || ''}</td>
            <td class="py-3.5 px-4"><span class="${roleClass} px-3 py-1 rounded-lg text-xs font-semibold border">${displayRole}</span></td>
            <td class="py-3.5 px-4"><span class="bg-amber-50 text-amber-700 border-amber-200 px-3 py-1 rounded-lg text-xs font-semibold border">Onboarding</span></td>
            <td class="py-3.5 px-4 text-center">
              <button onclick="openOnboardingModal(${emp.id})" class="btn btn-sm py-1.5 px-3.5 text-xs font-semibold rounded-xl flex items-center gap-1.5 mx-auto bg-[#ff6b4a] hover:bg-[#fa4b2a] text-white border-0 shadow-sm transition-all duration-200 hover:scale-105">
                <i class="bi bi-person-check-fill"></i> Setup Form / Onboarding
              </button>
            </td>
          `;
          newlyHiredBody.appendChild(trNew);
        } else {
          hiredPersonalCount++;
          const trPersonal = document.createElement('tr');
          trPersonal.className = "border-b border-slate-100 hover:bg-orange-50/40 transition-all duration-200";
          trPersonal.innerHTML = `
            <td class="px-6 py-4 font-mono text-slate-600">${emp.display_emp_id || ''}</td>
            <td class="px-6 py-4 font-semibold text-slate-800">${emp.full_name || ''}</td>
            <td class="px-6 py-4 font-mono text-xs text-[#ff6b4a]">${emp.email || 'Not registered'}</td>
            <td class="px-6 py-4">
              <div class="font-semibold text-slate-800">${emp.department || 'Unassigned'}</div>
              <span class="${roleClass} px-2.5 py-0.5 rounded-md text-[11px] font-medium border inline-block mt-1">${displayRole}</span>
            </td>
            <td class="px-6 py-4 text-center">
              <button onclick="viewEmployeeContract(${emp.id})" class="btn btn-sm bg-orange-50 hover:bg-orange-100 text-[#ff6b4a] border border-orange-200 py-1.5 px-3 text-xs font-semibold rounded-xl flex items-center gap-1.5 mx-auto transition-all">
                <i class="bi bi-file-earmark-text-fill"></i> View Contract
              </button>
            </td>
            <td class="px-6 py-4 text-center">
              <button onclick="triggerDelete(${emp.id}, '${(emp.full_name || '').replace(/'/g, "\\'")}')" class="btn btn-sm btn-outline-danger py-1.5 px-3 text-xs font-semibold rounded-xl flex items-center gap-1 mx-auto transition-all duration-200 hover:scale-105">
                <i class="bi bi-trash3"></i> Delete
              </button>
            </td>
          `;
          personalBody.appendChild(trPersonal);

          activePayrollCount++;
          const trPayroll = document.createElement('tr');
          trPayroll.className = "border-b border-slate-100 hover:bg-orange-50/40 transition-all duration-200";
          trPayroll.innerHTML = `
            <td class="py-3.5 px-4 font-semibold text-slate-800">${emp.full_name || ''}</td>
            <td class="py-3.5 px-4 font-semibold text-xs ${displayRole === 'Manager' ? 'text-orange-600' : 'text-slate-700'}">${displayRole}</td>
            <td class="py-3.5 px-4 text-slate-500 font-mono text-xs">${emp.sss_id || '33-1234567-8'}</td>
            <td class="py-3.5 px-4 text-slate-500 font-mono text-xs">${emp.philhealth_id || '12-345678901-2'}</td>
            <td class="py-3.5 px-4 text-slate-500 font-mono text-xs">${emp.pagibig_id || '1210-9876-5432'}</td>
            <td class="py-3.5 px-4 text-slate-500 font-mono text-xs">${emp.gsis_id || '-'}</td>
            <td class="py-3.5 px-4 text-end font-bold text-slate-900">₱${baseSalary.toLocaleString('en-US', {minimumFractionDigits:2})}</td>
            <td class="py-3.5 px-4 text-center">
              <button onclick="triggerPayslip(${emp.id})" class="btn btn-sm py-1.5 px-3 text-xs font-semibold rounded-xl flex items-center gap-1.5 mx-auto bg-orange-50 text-[#ff6b4a] hover:bg-[#ff6b4a] hover:text-white border border-orange-200 transition-all duration-200">
                <i class="bi bi-file-earmark-spreadsheet"></i> Payslip
              </button>
            </td>
          `;
          payrollBody.appendChild(trPayroll);
        }
      });

      if (paginatedItems.length === 0) {
        const emptyHTML = `<tr><td colspan="8" class="text-center py-10 text-slate-400 italic">No records found matching criteria.</td></tr>`;
        newlyHiredBody.innerHTML = emptyHTML;
        personalBody.innerHTML = emptyHTML;
        payrollBody.innerHTML = emptyHTML;
      }

      document.getElementById('tableRecordCountInfo').innerText = `Showing ${totalRecords > 0 ? startIndex + 1 : 0} to ${Math.min(startIndex + rowsPerPage, totalRecords)} of ${totalRecords} entries`;
      renderPaginationControls(totalPages);
    }

    function renderPaginationControls(totalPages) {
      const container = document.getElementById('paginationControlsContainer');
      let html = '';
      
      html += `<button onclick="changePage(${currentPage - 1})" class="px-2.5 py-1 rounded-lg border bg-white text-slate-600 hover:bg-slate-100 text-xs ${currentPage === 1 ? 'disabled opacity-50 pointer-events-none' : ''}"><i class="bi bi-chevron-left"></i></button>`;
      
      for(let i=1; i<=totalPages; i++) {
        if(i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
          html += `<button onclick="changePage(${i})" class="px-3 py-1 rounded-lg border text-xs font-semibold ${currentPage === i ? 'bg-[#ff6b4a] text-white border-[#ff6b4a]' : 'bg-white text-slate-600 hover:bg-slate-100'}">${i}</button>`;
        } else if(i === currentPage - 2 || i === currentPage + 2) {
          html += `<span class="px-2 text-slate-400">...</span>`;
        }
      }

      html += `<button onclick="changePage(${currentPage + 1})" class="px-2.5 py-1 rounded-lg border bg-white text-slate-600 hover:bg-slate-100 text-xs ${currentPage === totalPages ? 'disabled opacity-50 pointer-events-none' : ''}"><i class="bi bi-chevron-right"></i></button>`;
      container.innerHTML = html;
    }

    function changePage(targetPage) {
      currentPage = targetPage;
      renderTables();
    }

    function exportTableToCSV() {
      if(allEmployees.length === 0) {
        Swal.fire('Notice', 'No employee records available to export.', 'info');
        return;
      }

      let csvContent = "data:text/csv;charset=utf-8,";
      csvContent += "Employee ID,Full Name,Email,Department,Position/Role,Status,Employment Type,Base Salary\r\n";

      allEmployees.forEach(emp => {
        let row = [
          `"${emp.display_emp_id || ''}"`,
          `"${emp.full_name || ''}"`,
          `"${emp.email || ''}"`,
          `"${emp.department || ''}"`,
          `"${emp.role || ''}"`,
          `"${emp.status || ''}"`,
          `"${emp.employment_type || ''}"`,
          `"${emp.salary || 0}"`
        ];
        csvContent += row.join(",") + "\r\n";
      });

      const encodedUri = encodeURI(csvContent);
      const link = document.createElement("a");
      link.setAttribute("href", encodedUri);
      link.setAttribute("download", `employee_directory_${new Date().toISOString().split('T')[0]}.csv`);
      document.body.appendChild(link);
      link.click();
      document.body.removeChild(link);

      Swal.fire({
        icon: 'success',
        title: 'Export Successful',
        text: 'Employee database downloaded as CSV file.',
        timer: 2000,
        showConfirmButton: false
      });
    }

    function viewEmployeeContract(id) {
      const emp = allEmployees.find(e => e.id == id);
      if (!emp) return;

      const baseSalary = emp.salary ? parseFloat(emp.salary) : (emp.role === 'Manager' ? 45000 : 22000);
      const formattedSalary = baseSalary.toLocaleString('en-US', {minimumFractionDigits: 2});

      Swal.fire({
        title: `<div class="text-left"><h4 class="font-bold text-slate-900 text-base mb-0"><i class="bi bi-file-earmark-text text-[#ff6b4a]"></i> Employment Contract Agreement</h4><span class="text-xs text-slate-400 font-normal">Reference ID: ${emp.display_emp_id}</span></div>`,
        html: `
          <div class="text-left text-xs space-y-3 bg-slate-50 p-4 rounded-2xl border border-slate-200 max-h-[60vh] overflow-y-auto">
            <div class="border-b border-slate-200 pb-2">
              <p class="font-bold text-slate-800 text-sm mb-1">${emp.full_name}</p>
              <p class="text-slate-500"><strong>Email:</strong> <span class="text-[#ff6b4a] font-mono">${emp.email || 'N/A'}</span></p>
              <p class="text-slate-500"><strong>Company Email:</strong> <span class="text-emerald-700 font-mono">${emp.company_gmail || 'N/A'}</span></p>
            </div>
            
            <div class="grid grid-cols-2 gap-2">
              <div><strong>Department:</strong> ${emp.department || 'Unassigned'}</div>
              <div><strong>Position / Role:</strong> ${emp.role || 'Staff'}</div>
              <div><strong>Employment Type:</strong> ${emp.employment_type || 'Regular'}</div>
              <div><strong>Work Location:</strong> ${emp.work_location || 'Main Office'}</div>
              <div><strong>Immediate Supervisor:</strong> ${emp.immediate_supervisor || 'None specified'}</div>
              <div><strong>Base Salary:</strong> ₱${formattedSalary} / month</div>
            </div>

            <div class="border-t border-slate-200 pt-2 space-y-1">
              <p><strong>Date Hired:</strong> ${emp.date_hired || 'N/A'}</p>
              <p><strong>Contract Start Date:</strong> ${emp.contract_start_date || 'N/A'}</p>
              <p><strong>Contract End Date:</strong> ${emp.contract_end_date || 'N/A'}</p>
              <p><strong>Contract Duration:</strong> ${emp.contract_duration_years || 1.0} Year(s)</p>
            </div>

            <div class="bg-amber-50 border border-amber-200 p-3 rounded-xl text-amber-900 text-[11px]">
              <i class="bi bi-info-circle-fill"></i> This digital contract binds the employee to the company policies of <strong>${emp.company_name || 'PannaKoda Stores Inc.'}</strong> located at <em>${emp.company_address || 'Cavite, Philippines'}</em>, as configured during the onboarding setup.
            </div>
          </div>
        `,
        width: '600px',
        confirmButtonText: 'Close Contract',
        confirmButtonColor: '#ff6b4a',
        customClass: {
          popup: 'rounded-3xl shadow-2xl'
        }
      });
    }

    function openOnboardingModal(id) {
      const emp = allEmployees.find(e => e.id == id);
      if (!emp) return;

      document.getElementById('modalId').value = emp.id;
      document.getElementById('modalName').value = emp.full_name || '';
      document.getElementById('modalContactNumber').value = emp.contact_number || emp.phone || '';
      document.getElementById('modalCompanyName').value = emp.company_name || 'PannaKoda Stores Inc.';
      document.getElementById('modalCompanyAddress').value = emp.company_address || '123 Business Corporate Center, Cavite, Philippines';
      document.getElementById('modalDob').value = emp.date_of_birth || '';
      document.getElementById('modalCivilStatus').value = emp.civil_status || 'Single';
      document.getElementById('modalNationality').value = emp.nationality || 'Filipino';
      document.getElementById('modalGender').value = emp.gender || 'Male';
      document.getElementById('modalWorkLocation').value = emp.work_location || 'Main Office';
      document.getElementById('modalDepartment').value = emp.department || '';

      document.getElementById('modalEmployeeGmail').value = emp.email || '';
      updateModalGmailPreview();

      document.getElementById('modalAddress').value = emp.address || '';
      document.getElementById('modalRole').value = (emp.role && emp.role.trim() !== '') ? emp.role : 'Staff';
      document.getElementById('modalEmploymentType').value = emp.employment_type || 'Regular';
      document.getElementById('modalSupervisor').value = emp.immediate_supervisor || '';
      document.getElementById('modalDateHired').value = emp.date_hired || new Date().toISOString().split('T')[0];
      document.getElementById('modalContractStart').value = emp.contract_start_date || '';
      document.getElementById('modalContractEnd').value = emp.contract_end_date || '';
      document.getElementById('modalContractDuration').value = emp.contract_duration_years || '1.0';

      const defaultSal = (document.getElementById('modalRole').value === 'Manager') ? 45000 : 22000;
      document.getElementById('modalSalary').value = (emp.salary && parseFloat(emp.salary) > 0) ? emp.salary : defaultSal;

      document.getElementById('modalSss').value = emp.sss_id || '33-1234567-8';
      document.getElementById('modalPhilhealth').value = emp.philhealth_id || '12-345678901-2';
      document.getElementById('modalPagibig').value = emp.pagibig_id || '1210-9876-5432';
      document.getElementById('modalGsis').value = emp.gsis_id || '-';

      document.getElementById('modalPassword').value = '@Lozada23';
      document.getElementById('passwordFeedback').className = "text-[11px] mt-1 text-emerald-600 font-semibold";
      document.getElementById('passwordFeedback').innerHTML = '<i class="bi bi-check-circle-fill"></i> Fixed Default Password';

      if (!onboardingModalInstance) {
        onboardingModalInstance = new bootstrap.Modal(document.getElementById('onboardingModal'));
      }
      onboardingModalInstance.show();
    }

    async function submitOnboarding(event) {
      event.preventDefault();

      const formData = new URLSearchParams();
      formData.append('action', 'complete_onboarding');
      formData.append('id', document.getElementById('modalId').value);
      formData.append('password', document.getElementById('modalPassword').value);
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
      formData.append('salary', document.getElementById('modalSalary').value);
      formData.append('date_hired', document.getElementById('modalDateHired').value);
      formData.append('contract_start_date', document.getElementById('modalContractStart').value);
      formData.append('contract_end_date', document.getElementById('modalContractEnd').value);
      formData.append('contract_duration_years', document.getElementById('modalContractDuration').value);
      formData.append('work_location', document.getElementById('modalWorkLocation').value);

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
            confirmButtonText: 'Got it'
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

    function triggerDelete(dbId, name) {
      Swal.fire({
        title: 'Delete Data',
        text: `Are you sure you want to delete this data "${name}"?`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#ff6b4a',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes',
        cancelButtonText: 'Cancel'
      }).then(async (result) => {
        if (result.isConfirmed) {
          try {
            const formData = new URLSearchParams();
            formData.append('id', dbId);

            const response = await fetch(`${window.location.pathname}?action=delete_employee`, {
              method: 'POST',
              body: formData
            });

            const resultData = await response.json();
            if (resultData.success) {
              Swal.fire('Deleted!', resultData.message, 'success');
              allEmployees = allEmployees.filter(emp => emp.id != dbId);
              updateMetrics();
              buildDepartmentFilterPills();
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

    function calculatePHPayroll(monthlyBase) {
      let sss = Math.round(monthlyBase * 0.045 * 100) / 100;
      if (sss < 135) sss = 135;
      if (sss > 1350) sss = 1350;

      let philhealth = Math.round(monthlyBase * 0.025 * 100) / 100;
      if (philhealth < 250) philhealth = 250;
      if (philhealth > 2500) philhealth = 2500;

      let pagibig = Math.round(monthlyBase * 0.02 * 100) / 100;
      if (pagibig > 200) pagibig = 200;

      const totalStatutory = sss + philhealth + pagibig;
      const taxableIncome = Math.max(0, monthlyBase - totalStatutory);
      let tax = 0.00;

      if (taxableIncome > 20833 && taxableIncome <= 33333) {
        tax = (taxableIncome - 20833) * 0.15;
      } else if (taxableIncome > 33333 && taxableIncome <= 66667) {
        tax = 1875 + (taxableIncome - 33333) * 0.20;
      } else if (taxableIncome > 66667 && taxableIncome <= 166667) {
        tax = 8541.80 + (taxableIncome - 66667) * 0.25;
      } else if (taxableIncome > 166667 && taxableIncome <= 666667) {
        tax = 33541.80 + (taxableIncome - 166667) * 0.30;
      } else if (taxableIncome > 666667) {
        tax = 183541.80 + (taxableIncome - 666667) * 0.35;
      }
      tax = Math.round(tax * 100) / 100;

      return { sss, philhealth, pagibig, tax, totalDeductions: totalStatutory + tax };
    }

    function triggerPayslip(dbId) {
      const emp = allEmployees.find(e => e.id == dbId);
      if (!emp) return;

      const monthlyBase = emp.salary ? parseFloat(emp.salary) : (emp.role === 'Manager' ? 45000 : 22000);
      const monthlyAllowance = 2000; 
      const monthlyGross = monthlyBase + monthlyAllowance;

      const ph = calculatePHPayroll(monthlyBase);
      const monthlyNet = monthlyGross - ph.totalDeductions;

      const kinsenasBase = monthlyBase / 2;
      const kinsenasAllowance = monthlyAllowance / 2;
      const kinsenasGross = kinsenasBase + kinsenasAllowance;

      const kinsenasSSS = ph.sss / 2;
      const kinsenasPhilHealth = ph.philhealth / 2;
      const kinsenasPagibig = ph.pagibig / 2;
      const kinsenasTax = ph.tax / 2;
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
        <div class="border border-slate-200 p-6 bg-white rounded-2xl text-slate-800 text-xs shadow-sm">
          <div class="text-center border-b border-slate-100 pb-4 mb-4">
            <h3 class="font-black text-xl tracking-wide uppercase text-slate-900">${emp.company_name || 'PannaKoda Stores Inc.'}</h3>
            <p class="text-[11px] text-slate-400 font-medium">${emp.company_address || '123 Business Corporate Center, Cavite, Philippines'}</p>
            <p class="text-[11px] text-slate-400 font-mono">TIN: 000-123-456-000 &bull; SSS Employer No: 03-9876543-2</p>
            <div class="mt-3 inline-block bg-orange-50 text-orange-800 font-mono text-[11px] font-bold px-3 py-1 rounded-lg border border-orange-100">
              OFFICIAL PAYSLIP STATEMENT | ${cutOffPeriod}
            </div>
          </div>
          
          <div class="grid grid-cols-2 gap-4 mb-4 border-b border-slate-100 pb-4 bg-slate-50/60 p-4 rounded-xl">
             <div>
              <p class="mb-1"><span class="text-slate-400 uppercase font-semibold">Employee ID:</span> <span class="font-mono font-bold text-slate-800">${emp.display_emp_id}</span></p>
              <p class="mb-1"><span class="text-slate-400 uppercase font-semibold">Employee Name:</span> <span class="font-bold text-slate-800">${emp.full_name}</span></p>
              <p class="mb-1"><span class="text-slate-400 uppercase font-semibold">Email:</span> <span class="font-mono text-[#ff6b4a]">${emp.email || 'N/A'}</span></p>
              <p class="mb-1"><span class="text-slate-400 uppercase font-semibold">Department:</span> <span class="font-semibold text-slate-800">${emp.department}</span></p>
             </div>
             <div>
              <p class="mb-1"><span class="text-slate-400 uppercase font-semibold">Position/Role:</span> <span class="font-bold text-slate-800">${emp.role}</span></p>
              <p class="mb-1"><span class="text-slate-400 uppercase font-semibold">Pay Date:</span> <span class="font-mono text-slate-800">${payDateStr}</span></p>
              <p class="mb-1"><span class="text-slate-400 uppercase font-semibold">Employment Type:</span> <span class="font-semibold text-[#ff6b4a]">${emp.employment_type || 'Regular'}</span></p>
              <p class="mb-1"><span class="text-slate-400 uppercase font-semibold">Statutory Ref:</span> <span class="font-mono text-slate-400 text-[10px]">SSS/PH/PAG-IBIG Compliant</span></p>
             </div>
          </div>

          <div class="grid grid-cols-2 gap-6 items-start mb-4">
            <div>
              <h6 class="font-bold text-xs text-slate-800 border-b border-slate-100 pb-1.5 mb-2 uppercase tracking-wide">Earnings (Kinsenas Breakdown)</h6>
              <div class="space-y-1.5">
                <div class="flex justify-between py-1 border-b border-dashed border-slate-100">
                  <span class="text-slate-500">Basic Salary (Semi-Monthly)</span> 
                  <span class="font-semibold font-mono text-slate-800">₱${f(kinsenasBase)}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-dashed border-slate-100">
                  <span class="text-slate-500">Rice & Clothing Allowance</span> 
                  <span class="font-semibold font-mono text-slate-800">₱${f(kinsenasAllowance)}</span>
                </div>
                <div class="flex justify-between py-2 font-bold text-slate-900 bg-slate-50 px-2.5 rounded-lg mt-1">
                  <span>Gross Pay (Period)</span> 
                  <span class="font-mono text-emerald-600">₱${f(kinsenasGross)}</span>
                </div>
              </div>
            </div>

            <div>
              <h6 class="font-bold text-xs text-slate-800 border-b border-slate-100 pb-1.5 mb-2 uppercase tracking-wide">Statutory & Tax Deductions</h6>
              <div class="space-y-1.5">
                <div class="flex justify-between py-1 border-b border-dashed border-slate-100">
                  <span class="text-slate-500">SSS Contribution (Employee)</span> 
                  <span class="font-mono text-rose-500">-₱${f(kinsenasSSS)}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-dashed border-slate-100">
                  <span class="text-slate-500">PhilHealth (Employee)</span> 
                  <span class="font-mono text-rose-500">-₱${f(kinsenasPhilHealth)}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-dashed border-slate-100">
                  <span class="text-slate-500">Pag-IBIG Fund (Employee)</span> 
                  <span class="font-mono text-rose-500">-₱${f(kinsenasPagibig)}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-dashed border-slate-100">
                  <span class="text-slate-500">BIR Withholding Tax</span> 
                  <span class="font-mono text-rose-500">-₱${f(kinsenasTax)}</span>
                </div>
                <div class="flex justify-between py-2 font-bold text-slate-900 bg-slate-50 px-2.5 rounded-lg mt-1">
                  <span>Total Deductions</span> 
                  <span class="font-mono text-rose-500">-₱${f(kinsenasDeductions)}</span>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-slate-50 p-3.5 rounded-xl mb-4 text-[11px] grid grid-cols-2 gap-2 text-slate-600 border border-slate-100">
            <div><span class="font-semibold text-slate-700">Monthly Basic Salary:</span> ₱${f(monthlyBase)}</div>
            <div><span class="font-semibold text-slate-700">Monthly Gross Earnings:</span> ₱${f(monthlyGross)}</div>
            <div><span class="font-semibold text-slate-700">Monthly Total Statutory & Tax:</span> ₱${f(ph.totalDeductions)}</div>
            <div><span class="font-semibold text-slate-700">Monthly Net Pay Reference:</span> ₱${f(monthlyNet)}</div>
          </div>

          <div class="bg-gradient-to-r from-[#1a1010] via-[#1f1212] to-[#09090b] text-white p-4 rounded-2xl flex justify-between items-center shadow-md border border-[#ff6b4a]/30">
            <div>
              <h4 class="text-[10px] uppercase tracking-widest text-[#ff6b4a] font-semibold">Net Pay for this Period</h4>
              <p class="text-[10px] text-slate-400">Kinsenas Payout (15-Day Cycle)</p>
            </div>
            <div class="text-right">
              <h2 class="text-2xl font-black text-[#ff6b4a] font-mono">₱${f(kinsenasNet)}</h2>
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
          btn.className = "px-4 py-2 rounded-lg text-sm transition-all duration-300 flex items-center gap-2 bg-[#ff6b4a] text-white shadow-md font-semibold transform hover:scale-[1.02]";
        } else {
          btn.className = "px-4 py-2 rounded-lg text-sm transition-all duration-300 flex items-center gap-2 text-slate-300 hover:text-white hover:bg-[#ff6b4a]/20";
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

    document.getElementById('btnPrintStatement').addEventListener('click', () => window.print());

    window.addEventListener('DOMContentLoaded', () => {
      loadEmployees();
    });
  </script>

  <script>
    AOS.init({
        once: true,
        offset: 50,
        duration: 800,
    });
  </script>
</body>
</html>