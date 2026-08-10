<?php
ob_start();
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

$current_page = basename($_SERVER['PHP_SELF']);

// ==========================================
// PHPMailer Setup & Professional Email Templates
// ==========================================
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

require '../LIBRARIES/PHPMailer-master/src/Exception.php';
require '../LIBRARIES/PHPMailer-master/src/PHPMailer.php';
require '../LIBRARIES/PHPMailer-master/src/SMTP.php';

function sendAdminApplicantEmail($recipient_email, $recipient_name, $subject, $message_body) {
    if (empty($recipient_email) || !filter_var($recipient_email, FILTER_VALIDATE_EMAIL)) {
        return false;
    }

    $mail = new PHPMailer(true);
    try {
        $mail->isSMTP();
        $mail->Host       = 'smtp.gmail.com';
        $mail->SMTPAuth   = true;
        
        $mail->Username   = 'markjosephlozada251@gmail.com'; 
        $mail->Password   = 'rhjd rqed rhdh qkbd';    

        $mail->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = 587;

        $mail->setFrom('markjosephlozada251@gmail.com', 'Pannakoda Executive Board');
        $mail->addAddress($recipient_email, $recipient_name);

        $mail->isHTML(true);
        $mail->Subject = $subject;
        $mail->Body    = "
            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #e2e8f0; border-radius: 8px; color: #334155;'>
                <h2 style='color: #212121; border-bottom: 2px solid #f1f5f9; padding-bottom: 10px; margin-top: 0;'>Pannakoda Executive Update</h2>
                <p>Dear <b>{$recipient_name}</b>,</p>
                <p>We hope this email finds you well.</p>
                <div style='background-color: #f8fafc; padding: 15px; border-radius: 6px; margin: 20px 0;'>
                    {$message_body}
                </div>
                <p>Should you have any inquiries regarding your application status, please do not hesitate to reach out.</p>
                <br>
                <p>Best regards,</p>
                <p><b>Executive Management Team</b><br>Pannakoda</p>
            </div>
        ";

        $mail->send();
        return true;
    } catch (Exception $e) {
        return false;
    }
}

// ==========================================
// Background Request Continuation Helper
// ==========================================
function respond_now_then_continue() {
    if (session_id()) {
        session_write_close();
    }

    if (function_exists('fastcgi_finish_request')) {
        fastcgi_finish_request();
        return;
    }

    ignore_user_abort(true);
    header('Connection: close');
    $size = ob_get_length();
    if ($size !== false) {
        header('Content-Length: ' . $size);
    }
    @ob_end_flush();
    @flush();
}

// 1. FETCH APPLICANTS
if (isset($_GET['action']) && $_GET['action'] === 'fetch_applicants') {
    header('Content-Type: application/json');
    $conn = new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_error) {
        echo json_encode([]);
        exit;
    }

    $query = "SELECT * FROM applicants ORDER BY id DESC";
    $result = $conn->query($query);
    $applicants = [];
    
    if ($result) {
        while($row = $result->fetch_assoc()) {
            $row['id'] = isset($row['id']) ? intval($row['id']) : 0;
            $status_check = strtolower(trim($row['status'] ?? ''));
            if ($status_check === '' || ($status_check !== 'pending' && $status_check !== 'for final interview' && $status_check !== 'contract')) {
                $row['status'] = 'Pending';
            }
            $applicants[] = $row;
        }
    }
    echo json_encode($applicants);
    $conn->close();
    exit;
}

// 1.5 FETCH FORM SUBMISSIONS (admin_form_applications)
if (isset($_GET['action']) && $_GET['action'] === 'fetch_form_submissions') {
    header('Content-Type: application/json');
    $conn = new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_error) {
        echo json_encode([]);
        exit;
    }

    $table_check = $conn->query("SHOW TABLES LIKE 'admin_form_applications'");
    $form_applicants = [];
    
    if ($table_check && $table_check->num_rows > 0) {
        $query = "SELECT * FROM admin_form_applications WHERE status = 'pending' ORDER BY id DESC";
        $result = $conn->query($query);
        if ($result) {
            while($row = $result->fetch_assoc()) {
                $form_applicants[] = $row;
            }
        }
    }
    echo json_encode($form_applicants);
    $conn->close();
    exit;
}

// 1b. FETCH SALARY PER DEPARTMENT (from Recruitment job postings)
if (isset($_GET['action']) && $_GET['action'] === 'fetch_job_salaries') {
    header('Content-Type: application/json');
    $conn = new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_error) {
        echo json_encode([]);
        exit;
    }

    $salaries = [];
    $cols = $conn->query("SHOW COLUMNS FROM job_openings LIKE 'salary'");
    if ($cols && $cols->num_rows > 0) {
        $result = $conn->query("SELECT department, salary FROM job_openings");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $salaries[] = [
                    'department' => $row['department'],
                    'salary' => $row['salary'] !== null ? floatval($row['salary']) : null
                ];
            }
        }
    }
    echo json_encode($salaries);
    $conn->close();
    exit;
}

// 2. APPROVE APPLICANT
if (isset($_GET['action']) && $_GET['action'] === 'approve_applicant' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id > 0) {
        $conn = new mysqli($host, $user, $pass, $dbname);

        $stmt_get = $conn->prepare("SELECT full_name, email FROM applicants WHERE id = ?");
        $stmt_get->bind_param("i", $id);
        $stmt_get->execute();
        $res_get = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();

        $stmt = $conn->prepare("UPDATE applicants SET status = 'For Final Interview' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $conn->close();

        echo json_encode(['success' => true]);
        respond_now_then_continue();

        if ($res_get) {
            $subject = "Progression to Final Interview";
            $body = "We are pleased to inform you that your profile has been reviewed and approved by management to move forward to the <b>Final Interview</b> stage. Our team will contact you shortly with the finalized schedule details.";
            sendAdminApplicantEmail($res_get['email'], $res_get['full_name'], $subject, $body);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
    }
    exit;
}

// 2.5 APPROVE FORM SUBMISSION & PROCEED TO ONBOARDING
if (isset($_GET['action']) && $_GET['action'] === 'approve_form_submission' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id > 0) {
        $conn = new mysqli($host, $user, $pass, $dbname);
        $stmt = $conn->prepare("SELECT * FROM admin_form_applications WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $full_name = $row['full_name'] ?? '';
            $email = $row['email'] ?? '';
            $phone = $row['phone'] ?? '';
            $address = $row['address'] ?? '';
            $department = $row['department'] ?? 'Unassigned';
            $position = $row['position_applied'] ?? ($row['position'] ?? 'Staff');
            
            $employee_id = "EMP-" . date("Y") . "-" . str_pad($id, 4, "0", STR_PAD_LEFT);
            $status = "onboarding"; 
            $date_hired = date("Y-m-d");
            
            $company_name = "Pannakoda";
            $company_address = "Bagong Bayan Dasmarinas Cavite";
            $contact_number = $phone ?: "09000000000";
            $company_email = "Pannakoda@gmail.com";
            $employment_type = "Probationary";
            $contract_duration_years = 1.0;
            $work_location = "Main Office";
            $employee_gmail = $email;
            $gsis_id = $row['gsis_id'] ?? 'N/A';
            $sss_id = $row['sss_id'] ?? 'N/A';
            $philhealth_id = $row['philhealth_id'] ?? 'N/A';
            $pagibig_id = $row['pagibig_id'] ?? 'N/A';
            $salary = 22000.00;

            $insert_stmt = $conn->prepare("
                INSERT INTO employees (
                    company_name, company_address, contact_number, company_email,
                    employee_id, full_name, address, phone, email,
                    position_title, department, employment_type, date_hired,
                    contract_duration_years, work_location, employee_gmail,
                    gsis_id, sss_id, philhealth_id, pagibig_id, status, position, salary
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $insert_stmt->bind_param(
                "sssssssssssssdssssssssd", 
                $company_name, $company_address, $contact_number, $company_email,
                $employee_id, $full_name, $address, $phone, $email,
                $position, $department, $employment_type, $date_hired,
                $contract_duration_years, $work_location, $employee_gmail,
                $gsis_id, $sss_id, $philhealth_id, $pagibig_id, $status, $position, $salary
            );
            
            if ($insert_stmt->execute()) {
                $insert_stmt->close();

                $del_stmt = $conn->prepare("DELETE FROM admin_form_applications WHERE id = ?");
                $del_stmt->bind_param("i", $id);
                $del_stmt->execute();
                $del_stmt->close();
                $stmt->close();
                $conn->close();

                echo json_encode(['success' => true, 'message' => 'Successfully approved and transferred to onboarding!']);
                respond_now_then_continue();

                $subject = "Welcome to Pannakoda - Onboarding Process";
                $body = "We are thrilled to officially welcome you to the Pannakoda team! Your application has been approved and successfully transferred to our <b>Onboarding System</b>.<br><br><b>Assigned Department:</b> {$department}<br><b>Employee ID:</b> {$employee_id}";
                sendAdminApplicantEmail($email, $full_name, $subject, $body);
            } else {
                $stmt->close();
                $conn->close();
                echo json_encode(['success' => false, 'message' => 'Insert error: ' . $insert_stmt->error]);
                $insert_stmt->close();
            }
        } else {
            $stmt->close();
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'Form submission record not found.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
    }
    exit;
}

// 3. PROCEED TO CONTRACT
if (isset($_GET['action']) && $_GET['action'] === 'proceed_contract' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id > 0) {
        $conn = new mysqli($host, $user, $pass, $dbname);

        $stmt_get = $conn->prepare("SELECT full_name, email FROM applicants WHERE id = ?");
        $stmt_get->bind_param("i", $id);
        $stmt_get->execute();
        $res_get = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();

        $stmt = $conn->prepare("UPDATE applicants SET status = 'Contract' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $conn->close();

        echo json_encode(['success' => true]);
        respond_now_then_continue();

        if ($res_get) {
            $subject = "Employment Contract Stage Reached";
            $body = "Congratulations! Following a successful final evaluation, your application has advanced to the <b>Employment Contract Stage</b>. Please coordinate with our administration desk for contract agreement reviews.";
            sendAdminApplicantEmail($res_get['email'], $res_get['full_name'], $subject, $body);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
    }
    exit;
}

// 4. PROCEED TO ONBOARDING
if (isset($_GET['action']) && $_GET['action'] === 'proceed_onboarding' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;
    $contract_salary = isset($_POST['salary']) && $_POST['salary'] !== '' ? floatval($_POST['salary']) : 0;

    if ($id > 0) {
        $conn = new mysqli($host, $user, $pass, $dbname);
        $stmt = $conn->prepare("SELECT * FROM applicants WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $full_name = $row['full_name'] ?? '';
            $email = $row['email'] ?? '';
            $phone = $row['phone'] ?? '';
            $address = $row['address'] ?? '';
            $department = $row['department'] ?? 'Unassigned';
            $position = $row['position_applied'] ?? ($row['position'] ?? 'Staff');
            
            $employee_id = "EMP-" . date("Y") . "-" . str_pad($id, 4, "0", STR_PAD_LEFT);
            $status = "onboarding"; 
            $date_hired = date("Y-m-d");
            
            $company_name = "Pannakoda";
            $company_address = "Bagong Bayan Dasmarinas Cavite";
            $contact_number = $phone ?: "09000000000";
            $company_email = "Pannakoda@gmail.com";
            $employment_type = "Probationary";
            $contract_duration_years = 1.0;
            $work_location = "Main Office";
            $employee_gmail = $email;
            $gsis_id = $row['gsis_id'] ?? 'N/A';
            $sss_id = $row['sss_id'] ?? 'N/A';
            $philhealth_id = $row['philhealth_id'] ?? 'N/A';
            $pagibig_id = $row['pagibig_id'] ?? 'N/A';
            $salary = $contract_salary > 0 ? $contract_salary : (stripos($position, 'manager') !== false ? 45000.00 : 22000.00);

            $insert_stmt = $conn->prepare("
                INSERT INTO employees (
                    company_name, company_address, contact_number, company_email,
                    employee_id, full_name, address, phone, email,
                    position_title, department, employment_type, date_hired,
                    contract_duration_years, work_location, employee_gmail,
                    gsis_id, sss_id, philhealth_id, pagibig_id, status, position, salary
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $insert_stmt->bind_param(
                "sssssssssssssdssssssssd", 
                $company_name, $company_address, $contact_number, $company_email,
                $employee_id, $full_name, $address, $phone, $email,
                $position, $department, $employment_type, $date_hired,
                $contract_duration_years, $work_location, $employee_gmail,
                $gsis_id, $sss_id, $philhealth_id, $pagibig_id, $status, $position, $salary
            );
            
            if ($insert_stmt->execute()) {
                $insert_stmt->close();

                $del_stmt = $conn->prepare("DELETE FROM applicants WHERE id = ?");
                $del_stmt->bind_param("i", $id);
                $del_stmt->execute();
                $del_stmt->close();
                $stmt->close();
                $conn->close();

                echo json_encode(['success' => true, 'message' => 'Successfully transferred to onboarding and email sent!']);
                respond_now_then_continue();

                $subject = "Welcome to Pannakoda - Onboarding Process";
                $body = "We are thrilled to officially welcome you to the Pannakoda team! Your contract has been fully verified, and your profile has been successfully moved to our <b>Onboarding System</b>.<br><br><b>Assigned Position:</b> {$position}<br><b>Employee ID:</b> {$employee_id}<br><br>Our HR team will send separate instructions regarding your initial documentation and setup requirements.";
                sendAdminApplicantEmail($email, $full_name, $subject, $body);
            } else {
                $stmt->close();
                $conn->close();
                echo json_encode(['success' => false, 'message' => 'Insert error: ' . $insert_stmt->error]);
                $insert_stmt->close();
            }
        } else {
            $stmt->close();
            $conn->close();
            echo json_encode(['success' => false, 'message' => 'Applicant not found.']);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
    }
    exit;
}

// 5. DELETE APPLICANT
if (isset($_GET['action']) && $_GET['action'] === 'delete_applicant' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id > 0) {
        $conn = new mysqli($host, $user, $pass, $dbname);
        $stmt = $conn->prepare("DELETE FROM applicants WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => true]);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin - Applicant Management</title>
  <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
  <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
  <script src="../LIBRARIES/tailwind.js"></script> 
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-[whitesmoke] font-sans antialiased h-screen overflow-hidden">
  <div class="flex h-screen w-full overflow-hidden">
    <?php include 'sidebar.php'; ?>
    <div class="flex-1 h-screen overflow-y-auto p-8 bg-slate-100 min-w-0">
      <div class="flex justify-between items-center mb-6">
        <div>
          <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Applicant Management</h1>
          <p class="text-sm text-gray-500">Manage interviews, review contracts, and transfer newly hired applicants.</p>
        </div>
      </div>
      
      <div class="mb-4 flex items-center gap-3 bg-white p-4 rounded-xl shadow-sm border border-gray-100">
        <div class="relative flex-1 max-w-md">
          <span class="absolute inset-y-0 left-0 flex items-center pl-3 pointer-events-none text-gray-400"><i class="bi bi-search"></i></span>
          <input id="searchInput" type="text" class="form-control pl-10 pr-4 py-2 rounded-xl text-sm border-gray-200" placeholder="Search applicants...">
        </div>
      </div>

      <!-- MAIN APPLICANTS TABLE -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-8">
        <h3 class="text-md font-bold text-gray-800 mb-4 flex items-center gap-2"><i class="bi bi-people-fill text-orange-500"></i> Main Applicants List</h3>
        <div class="table-responsive bg-white rounded-xl overflow-hidden">
          <table id="applicantsTable" class="table table-hover align-middle mb-0 text-sm">
            <thead class="table-dark">
              <tr>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Applicant ID</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Full Name</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Email</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Position Applied</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Status</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0 text-center">Actions</th>
              </tr>
            </thead>
            <tbody id="applicantsBody"></tbody>
          </table>
        </div>
      </div>

      <!-- SECOND CONDITIONAL TABLE: FORM SUBMISSIONS (admin_form_applications) -->
      <div id="formSubmissionsSection" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 hidden">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-md font-bold text-gray-800 flex items-center gap-2">
            <i class="bi bi-file-earmark-person-fill text-amber-500"></i> Direct Form Application Submissions
          </h3>
          <span class="bg-amber-100 text-amber-800 text-xs font-bold px-2.5 py-1 rounded-full">New Incoming Data</span>
        </div>
        <div class="table-responsive bg-white rounded-xl overflow-hidden">
          <table id="formSubmissionsTable" class="table table-hover align-middle mb-0 text-sm">
            <thead class="table-light">
              <tr>
                <th class="py-3 px-4 bg-slate-800 text-white font-semibold border-0">ID</th>
                <th class="py-3 px-4 bg-slate-800 text-white font-semibold border-0">Full Name</th>
                <th class="py-3 px-4 bg-slate-800 text-white font-semibold border-0">Department</th>
                <th class="py-3 px-4 bg-slate-800 text-white font-semibold border-0">Email / Phone</th>
                <th class="py-3 px-4 bg-slate-800 text-white font-semibold border-0 text-center">Actions</th>
              </tr>
            </thead>
            <tbody id="formSubmissionsBody"></tbody>
          </table>
        </div>
      </div>

    </div>
  </div>

  <!-- CONTRACT MODAL -->
  <div class="modal fade" id="contractModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered">
      <div class="modal-content rounded-2xl border-0 shadow-lg">
        <div class="modal-header bg-dark text-white rounded-t-2xl">
          <h5 class="modal-title font-bold text-base"><i class="bi bi-file-earmark-text-fill text-warning me-2"></i> Employment Contract Agreement</h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-6 bg-light">
          <div class="bg-white p-6 rounded-xl border shadow-sm">
            <h4 class="font-bold text-gray-800 text-center mb-3">OFFER OF EMPLOYMENT & CONTRACT TERMS</h4>
            <div class="row g-2 mb-3 text-sm bg-gray-50 p-3 rounded-lg border">
              <div class="col-md-6"><strong>Applicant Name:</strong> <span id="modalApplicantName" class="text-primary font-semibold"></span></div>
              <div class="col-md-6"><strong>Email:</strong> <span id="modalApplicantEmail" class="text-muted"></span></div>
              <div class="col-md-6"><strong>Position:</strong> <span id="modalApplicantPosition" class="text-dark font-semibold"></span></div>
              <div class="col-md-6"><strong>Stage:</strong> <span class="badge bg-primary">Contract Verification</span></div>
            </div>
            <div class="mb-1">
              <label class="form-label text-xs font-bold text-gray-500 uppercase tracking-wider mb-1">Offered Monthly Salary (₱)</label>
              <input type="number" min="0" step="0.01" id="modalContractSalary" class="form-control form-control-sm rounded-lg">
              <div id="modalSalarySourceNote" class="text-[11px] text-gray-400 mt-1"></div>
            </div>
            <div class="form-check bg-amber-50 border border-amber-200 p-3 rounded-xl mt-3">
              <input class="form-check-input mt-1" type="checkbox" id="agreeContractCheck" onchange="toggleOnboardingButton()">
              <label class="form-check-label text-xs font-semibold text-amber-900 cursor-pointer" for="agreeContractCheck">
                I verify that the applicant has reviewed and agreed to the terms of this contract.
              </label>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-white border-0">
          <input type="hidden" id="modalApplicantId">
          <button type="button" class="btn btn-secondary btn-sm rounded-lg" data-bs-dismiss="modal">Close</button>
          <button type="button" id="proceedOnboardingBtn" class="btn btn-success btn-sm rounded-lg px-4" disabled onclick="confirmProceedOnboarding()">
            <i class="bi bi-person-check-fill me-1"></i> Proceed to Onboarding
          </button>
        </div>
      </div>
    </div>
  </div>

  <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>
  <script>
    let allApplicants = [];
    let formSubmissions = [];
    let activeContractModal = null;
    let deptSalaryMap = {};
    const phpEndpoint = "<?php echo $current_page; ?>";

    async function loadApplicants() {
      try {
        const response = await fetch(`${phpEndpoint}?action=fetch_applicants`);
        allApplicants = await response.json();
        renderTable();
      } catch (err) { console.error("Failed to load applicants:", err); }
    }

    async function loadFormSubmissions() {
      try {
        const response = await fetch(`${phpEndpoint}?action=fetch_form_submissions`);
        formSubmissions = await response.json();
        renderFormSubmissionsTable();
      } catch (err) { console.error("Failed to load form submissions:", err); }
    }

    async function loadJobSalaries() {
      try {
        const res = await fetch(`${phpEndpoint}?action=fetch_job_salaries`);
        const data = await res.json();
        deptSalaryMap = {};
        if (Array.isArray(data)) {
          data.forEach(j => {
            if (j.department) deptSalaryMap[j.department.toLowerCase()] = j.salary;
          });
        }
      } catch (err) {
        console.error("Failed to load recruitment salaries:", err);
      }
    }

    function renderTable() {
      const query = document.getElementById('searchInput').value.toLowerCase().trim();
      const tbody = document.getElementById('applicantsBody');
      tbody.innerHTML = '';

      const filtered = allApplicants.filter(app => {
        const idStr = String(app.id || '').toLowerCase();
        const name = String(app.full_name || '').toLowerCase();
        const email = String(app.email || '').toLowerCase();
        const position = String(app.position_applied || app.position || '').toLowerCase();
        return idStr.includes(query) || name.includes(query) || email.includes(query) || position.includes(query);
      });

      if (filtered.length === 0) {
        tbody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-gray-400 italic">No applicant records found.</td></tr>`;
        return;
      }

      filtered.forEach(app => {
        let statusBadge = '';
        let actionButtons = '';
        const rawStatus = (app.status || 'pending').trim().toLowerCase();
        const safeName = (app.full_name || '').replace(/'/g, "\\'");
        const safeEmail = (app.email || '').replace(/'/g, "\\'");
        const safePosition = (app.position_applied || app.position || 'Staff').replace(/'/g, "\\'");
        const safeDept = (app.department || 'Unassigned').replace(/'/g, "\\'");

        if (rawStatus === 'pending') {
          statusBadge = '<span class="bg-amber-50 text-amber-700 border-amber-200 px-2.5 py-1 rounded-md text-xs font-semibold border">Pending Review</span>';
          actionButtons = `<button onclick="approveApplicant(${app.id}, '${safeName}')" class="btn btn-sm btn-success py-1 px-2.5 text-xs font-semibold rounded-lg"><i class="bi bi-check-lg"></i> Approve Final Interview</button>`;
        } else if (rawStatus === 'for final interview') {
          statusBadge = '<span class="bg-indigo-50 text-indigo-700 border-indigo-200 px-2.5 py-1 rounded-md text-xs font-semibold border">For Final Interview</span>';
          actionButtons = `<button onclick="proceedContract(${app.id}, '${safeName}', '${safeEmail}', '${safePosition}', '${safeDept}')" class="btn btn-sm btn-primary py-1 px-2.5 text-xs font-semibold rounded-lg"><i class="bi bi-file-earmark-text"></i> Proceed to Contract</button>`;
        } else if (rawStatus === 'contract') {
          statusBadge = '<span class="bg-blue-50 text-blue-700 border-blue-200 px-2.5 py-1 rounded-md text-xs font-semibold border">Contract Stage</span>';
          actionButtons = `<button onclick="openContractModal(${app.id}, '${safeName}', '${safeEmail}', '${safePosition}', '${safeDept}')" class="btn btn-sm btn-outline-success py-1 px-2.5 text-xs font-semibold rounded-lg"><i class="bi bi-file-earmark-text"></i> Open Contract</button>`;
        } else {
          statusBadge = '<span class="bg-amber-50 text-amber-700 border-amber-200 px-2.5 py-1 rounded-md text-xs font-semibold border">Pending Review</span>';
          actionButtons = `<button onclick="approveApplicant(${app.id}, '${safeName}')" class="btn btn-sm btn-success py-1 px-2.5 text-xs font-semibold rounded-lg"><i class="bi bi-check-lg"></i> Approve Final Interview</button>`;
        }

        const tr = document.createElement('tr');
        tr.className = "border-b border-gray-100 hover:bg-gray-50/50 transition-colors";
        tr.innerHTML = `
          <td class="py-3 px-4 font-mono font-bold text-gray-700">#${app.id}</td>
          <td class="py-3 px-4 font-semibold text-gray-800">${app.full_name || ''}</td>
          <td class="py-3 px-4 text-gray-600">${app.email || ''}</td>
          <td class="py-3 px-4 text-gray-600">${app.position_applied || app.position || 'Staff'}</td>
          <td class="py-3 px-4">${statusBadge}</td>
          <td class="py-3 px-4 text-center flex justify-center items-center gap-2">
            ${actionButtons}
            <button onclick="deleteApplicant(${app.id}, '${safeName}')" class="btn btn-sm btn-outline-danger py-1 px-2 text-xs font-semibold rounded-lg"><i class="bi bi-trash3"></i></button>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }

    // RENDER SECOND CONDITIONAL TABLE
    function renderFormSubmissionsTable() {
      const section = document.getElementById('formSubmissionsSection');
      const tbody = document.getElementById('formSubmissionsBody');
      tbody.innerHTML = '';

      if (!formSubmissions || formSubmissions.length === 0) {
        section.classList.add('hidden');
        return;
      }

      section.classList.remove('hidden');

      formSubmissions.forEach(sub => {
        const safeName = (sub.full_name || '').replace(/'/g, "\\'");
        const tr = document.createElement('tr');
        tr.className = "border-b border-gray-100 hover:bg-gray-50/50 transition-colors";
        tr.innerHTML = `
          <td class="py-3 px-4 font-mono font-bold text-gray-700">#${sub.id}</td>
          <td class="py-3 px-4 font-semibold text-gray-800">${sub.full_name || ''}</td>
          <td class="py-3 px-4 text-gray-600 font-medium"><span class="badge bg-orange-100 text-orange-800">${sub.department || 'N/A'}</span></td>
          <td class="py-3 px-4 text-gray-600 text-xs">${sub.email || ''} <br> <span class="text-gray-400">${sub.phone || ''}</span></td>
          <td class="py-3 px-4 text-center">
            <button onclick="approveFormSubmission(${sub.id}, '${safeName}')" class="btn btn-sm btn-success py-1.5 px-3 text-xs font-bold rounded-lg shadow-sm">
              <i class="bi bi-check-circle-fill me-1"></i> Approve & Proceed to Onboarding
            </button>
          </td>
        `;
        tbody.appendChild(tr);
      });
    }

    async function approveFormSubmission(id, name) {
      Swal.fire({
        title: 'Processing Onboarding...',
        text: `Approving ${name} and moving to onboarding system.`,
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
      });

      const fd = new FormData(); 
      fd.append('id', id);
      
      try {
        const res = await fetch(`${phpEndpoint}?action=approve_form_submission`, { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
          formSubmissions = formSubmissions.filter(sub => String(sub.id) !== String(id));
          renderFormSubmissionsTable();
          Swal.fire({ title: 'Success!', text: data.message, icon: 'success', timer: 1200, showConfirmButton: false });
        } else {
          Swal.fire('Error!', data.message || 'Failed processing request.', 'error');
        }
      } catch (e) {
        Swal.fire('Error!', 'An unexpected error occurred.', 'error');
      }
    }

    async function approveApplicant(id, name) {
      const fd = new FormData(); fd.append('id', id);
      const res = await fetch(`${phpEndpoint}?action=approve_applicant`, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        const app = allApplicants.find(a => String(a.id) === String(id));
        if (app) app.status = 'For Final Interview';
        renderTable();
      }
    }

    async function proceedContract(id, name, email, position, department) {
      const fd = new FormData(); fd.append('id', id);
      const res = await fetch(`${phpEndpoint}?action=proceed_contract`, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        const app = allApplicants.find(a => String(a.id) === String(id));
        if (app) app.status = 'Contract';
        renderTable();
        openContractModal(id, name, email, position, department);
      }
    }

    function openContractModal(id, name, email, position, department) {
      document.getElementById('modalApplicantId').value = id;
      document.getElementById('modalApplicantName').textContent = name;
      document.getElementById('modalApplicantEmail').textContent = email;
      document.getElementById('modalApplicantPosition').textContent = position;

      const salaryField = document.getElementById('modalContractSalary');
      const note = document.getElementById('modalSalarySourceNote');
      const deptKey = (department || '').toLowerCase();
      const suggested = deptSalaryMap[deptKey];
      if (suggested !== undefined && suggested !== null) {
        salaryField.value = suggested;
        note.textContent = `Suggested from the "${department}" job posting — you can adjust it.`;
      } else {
        salaryField.value = '';
        note.textContent = `No matching Recruitment posting found for "${department || 'this department'}" — enter the salary manually.`;
      }

      const checkbox = document.getElementById('agreeContractCheck');
      checkbox.checked = true;
      toggleOnboardingButton();

      activeContractModal = new bootstrap.Modal(document.getElementById('contractModal'));
      activeContractModal.show();
    }

    function toggleOnboardingButton() {
      document.getElementById('proceedOnboardingBtn').disabled = !document.getElementById('agreeContractCheck').checked;
    }

    async function confirmProceedOnboarding() {
      const applicantId = document.getElementById('modalApplicantId').value;
      if (activeContractModal) { activeContractModal.hide(); }

      Swal.fire({
        title: 'Processing Onboarding...',
        text: 'Please wait a moment.',
        allowOutsideClick: false,
        didOpen: () => { Swal.showLoading(); }
      });
      
      const fd = new FormData(); 
      fd.append('id', applicantId);
      fd.append('salary', document.getElementById('modalContractSalary').value || '');
      
      try {
        const res = await fetch(`${phpEndpoint}?action=proceed_onboarding`, { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
          allApplicants = allApplicants.filter(app => String(app.id) !== String(applicantId));
          renderTable();
          Swal.fire({ title: 'Success!', text: data.message, icon: 'success', timer: 1000, showConfirmButton: false });
        } else {
          Swal.fire('Error!', data.message || 'Failed.', 'error');
        }
      } catch (e) {
        Swal.fire('Error!', 'An unexpected error occurred.', 'error');
      }
    }

    async function deleteApplicant(id, name) {
      if (!(await Swal.fire({ title: 'Delete?', text: `Delete ${name}?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' })).isConfirmed) return;
      const fd = new FormData(); fd.append('id', id);
      const res = await fetch(`${phpEndpoint}?action=delete_applicant`, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        allApplicants = allApplicants.filter(app => String(app.id) !== String(id));
        renderTable();
      }
    }

    document.getElementById('searchInput').addEventListener('input', renderTable);
    
    window.addEventListener('DOMContentLoaded', () => {
      loadApplicants();
      loadFormSubmissions();
      loadJobSalaries();
      setInterval(loadFormSubmissions, 10000); 
    });
  </script>
</body>
</html>