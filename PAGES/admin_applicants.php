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
            // Updated allowed status schema: Pending, Contract, Rejected
            if ($status_check === '' || ($status_check !== 'pending' && $status_check !== 'contract' && $status_check !== 'rejected')) {
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

// REJECT APPLICANT
if (isset($_GET['action']) && $_GET['action'] === 'reject_applicant' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id > 0) {
        $conn = new mysqli($host, $user, $pass, $dbname);

        $stmt_get = $conn->prepare("SELECT full_name, email FROM applicants WHERE id = ?");
        $stmt_get->bind_param("i", $id);
        $stmt_get->execute();
        $res_get = $stmt_get->get_result()->fetch_assoc();
        $stmt_get->close();

        $stmt = $conn->prepare("UPDATE applicants SET status = 'Rejected' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $conn->close();

        echo json_encode(['success' => true]);
        respond_now_then_continue();

        if ($res_get) {
            $subject = "Application Status Update";
            $body = "Thank you for your interest in joining Pannakoda. After careful review, we regret to inform you that we will not be moving forward with your application at this time. We wish you the best in your professional endeavors.";
            sendAdminApplicantEmail($res_get['email'], $res_get['full_name'], $subject, $body);
        }
    } else {
        echo json_encode(['success' => false, 'message' => 'Invalid ID.']);
    }
    exit;
}

// PROCEED DIRECTLY TO CONTRACT STAGE (Approve Applicant & Open Contract Agreement Modal)
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
            $body = "Congratulations! Your application has been approved and advanced directly to the <b>Employment Contract Stage</b>. Please coordinate with our administration desk for contract agreement reviews.";
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

// 4. PROCEED TO ONBOARDING (From Contract Modal)
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
            $position = $row['position_applied'] ?? ($row['position'] ?? 'Staff');
            
            $employee_id = "EMP-" . date("Y") . "-" . str_pad($id, 4, "0", STR_PAD_LEFT);
            $status = "onboarding"; 
            
            $company_email = "Pannakoda@gmail.com";
            $employee_gmail = $email;
            $gsis_id = $row['gsis_id'] ?? 'N/A';
            $sss_id = $row['sss_id'] ?? 'N/A';
            $philhealth_id = $row['philhealth_id'] ?? 'N/A';
            $pagibig_id = $row['pagibig_id'] ?? 'N/A';

            $company_name = trim($_POST['company_name'] ?? '') ?: 'Pannakoda';
            $company_address = trim($_POST['company_address'] ?? '') ?: 'Bagong Bayan, Dasmarinas, Cavite';
            $contact_number = trim($_POST['contact_number'] ?? '') ?: ($phone ?: '09000000000');
            $department = trim($_POST['department'] ?? '') ?: ($row['department'] ?? 'Unassigned');
            $role_tier = trim($_POST['role'] ?? '') ?: (stripos($position, 'manager') !== false ? 'Manager' : 'Staff');
            $employment_type = trim($_POST['employment_type'] ?? '') ?: 'Probationary';
            $date_hired = trim($_POST['date_hired'] ?? '') ?: date('Y-m-d');
            $contract_duration_years = ($_POST['contract_duration_years'] ?? '') !== '' ? floatval($_POST['contract_duration_years']) : 1.0;
            $contract_start_date = trim($_POST['contract_start_date'] ?? '') ?: null;
            $contract_end_date = trim($_POST['contract_end_date'] ?? '') ?: null;
            $work_location = trim($_POST['work_location'] ?? '') ?: 'Main Office';
            $date_of_birth = trim($_POST['date_of_birth'] ?? '') ?: null;
            $civil_status = trim($_POST['civil_status'] ?? '') ?: 'Single';
            $nationality = trim($_POST['nationality'] ?? '') ?: 'Filipino';
            $gender = trim($_POST['gender'] ?? '') ?: null;
            $immediate_supervisor = trim($_POST['immediate_supervisor'] ?? '') ?: null;

            $salary = $contract_salary > 0 ? $contract_salary : ($role_tier === 'Manager' ? 45000.00 : 22000.00);

            $insert_stmt = $conn->prepare("
                INSERT INTO employees (
                    company_name, company_address, contact_number, company_email,
                    employee_id, full_name, address, phone, email,
                    position_title, department, employment_type, date_hired,
                    contract_duration_years, contract_start_date, contract_end_date, work_location, employee_gmail,
                    gsis_id, sss_id, philhealth_id, pagibig_id, status, position, salary,
                    date_of_birth, civil_status, nationality, gender, immediate_supervisor
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $bind_types = str_repeat('s', 13) . 'd' . str_repeat('s', 10) . 'd' . str_repeat('s', 5);
            $insert_stmt->bind_param(
                $bind_types, 
                $company_name, $company_address, $contact_number, $company_email,
                $employee_id, $full_name, $address, $phone, $email,
                $role_tier, $department, $employment_type, $date_hired,
                $contract_duration_years, $contract_start_date, $contract_end_date, $work_location, $employee_gmail,
                $gsis_id, $sss_id, $philhealth_id, $pagibig_id, $status, $position, $salary,
                $date_of_birth, $civil_status, $nationality, $gender, $immediate_supervisor
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
  
  <!-- AOS Library CSS & JS -->
  <link href="../LIBRARIES/AOS/aos.css" rel="stylesheet">
  <script src="../LIBRARIES/AOS/AOS.js"></script>

  <style>
      @keyframes floatSlow {
          0%, 100% { transform: translateY(0px); }
          50% { transform: translateY(-5px); }
      }
      @keyframes pulseGlow {
          0%, 100% { box-shadow: 0 0 15px rgba(255, 107, 74, 0.15); }
          50% { box-shadow: 0 0 25px rgba(255, 107, 74, 0.35); }
      }
      .animate-float-1 { animation: floatSlow 4s ease-in-out infinite; }
      .animate-float-2 { animation: floatSlow 5s ease-in-out infinite 1s; }
      .animate-float-3 { animation: floatSlow 6s ease-in-out infinite 2s; }
      .feature-box-glow:hover {
          animation: pulseGlow 2s infinite;
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
<body class="bg-white text-slate-800 font-sans antialiased h-screen overflow-hidden">
  <div class="flex h-screen w-full overflow-hidden">
    <?php include 'sidebar.php'; ?>
    <div class="flex-1 h-screen overflow-y-auto p-8 bg-white min-w-0">
      
      <!-- Top Header & Live Philippine Time Clock Widget -->
      <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 gap-4" data-aos="fade-down" data-aos-duration="800">
        <div>
          <h1 class="text-2xl font-extrabold text-amber-500 tracking-tight">APPLICANT MANAGEMENT</h1>
          <p class="text-sm text-slate-500 mt-1">Select an applicant from the table below to trigger pipeline actions, review contracts, and manage hiring stages.</p>
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

      <!-- FLOATING ACTION TOOLBAR -->
      <div id="actionToolbar" class="mb-6 bg-slate-50 p-4 rounded-3xl shadow-sm border border-slate-200/80 admin-card-glow flex flex-col md:flex-row items-center justify-between gap-4" data-aos="fade-up" data-aos-duration="850">
        <div class="flex items-center gap-3 px-2">
          <span class="relative flex h-3 w-3">
            <span id="toolbarDot" class="relative inline-flex rounded-full h-3 w-3 bg-amber-500"></span>
          </span>
          <span id="toolbarSelectionText" class="text-xs font-extrabold uppercase tracking-wider text-slate-700">NO APPLICANT SELECTED</span>
        </div>
        <div class="flex items-center gap-2 w-full md:w-auto justify-end flex-wrap">
          <!-- Note: Interview Button removed per user procedure guidelines -->
          <button id="toolbarApproveBtn" class="btn btn-sm rounded-xl px-4 py-2 font-semibold text-xs border bg-slate-200 text-slate-400 border-slate-300 cursor-not-allowed shadow-none" disabled onclick="executeToolbarAction('approve')">
            <i class="bi bi-check-circle me-1"></i> Approve / Contract
          </button>
          <button id="toolbarRejectBtn" class="btn btn-sm rounded-xl px-4 py-2 font-semibold text-xs border bg-slate-200 text-slate-400 border-slate-300 cursor-not-allowed shadow-none" disabled onclick="executeToolbarAction('reject')">
            <i class="bi bi-x-circle me-1"></i> Reject
          </button>
        </div>
      </div>
      
      <!-- Search & Filter Bar -->
      <div class="mb-6 flex items-center gap-3 bg-slate-50 p-4 rounded-3xl shadow-sm border border-slate-200/80 admin-card-glow" data-aos="fade-up" data-aos-duration="950">
        <div class="relative flex-1">
          <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-slate-400"><i class="bi bi-search"></i></span>
          <input id="searchInput" type="text" class="w-full pl-11 pr-4 py-2.5 bg-white border border-slate-200 rounded-2xl text-sm text-slate-800 focus:outline-none focus:ring-2 focus:ring-[#ff6b4a]/30 focus:border-[#ff6b4a] transition-all duration-300" placeholder="Search applicants by name, email, or position...">
        </div>
      </div>

      <!-- MAIN APPLICANTS TABLE -->
      <div class="bg-slate-50 rounded-3xl shadow-sm border border-slate-200/80 p-6 mb-8 admin-card-glow" data-aos="fade-up" data-aos-duration="1000">
        <h3 class="text-md font-bold text-slate-800 mb-4 flex items-center gap-2">
          <div class="p-2 bg-amber-500 text-white rounded-xl border border-amber-500">
            <i class="bi bi-people-fill"></i>
          </div> 
          Main Applicants List (Click row to select)
        </h3>
        <div class="table-responsive  text-white rounded-2xl overflow-hidden border border-slate-100">
          <table id="applicantsTable" class="table table-hover align-middle mb-0 text-sm">
            <thead class="table-dark">
              <tr>
                <th class="py-3 px-4  font-semibold border-0">Applicant ID</th>
                <th class="py-3 px-4  font-semibold border-0">Full Name</th>
                <th class="py-3 px-4  font-semibold border-0">Email</th>
                <th class="py-3 px-4  font-semibold border-0">Position Applied</th>
                <th class="py-3 px-4  font-semibold border-0">Status</th>
              </tr>
            </thead>
            <tbody id="applicantsBody"></tbody>
          </table>
        </div>
      </div>

      <!-- SECOND CONDITIONAL TABLE: FORM SUBMISSIONS (admin_form_applications) -->
      <div id="formSubmissionsSection" class="bg-slate-50 rounded-3xl shadow-sm border border-slate-200/80 p-6 mb-8 admin-card-glow hidden" data-aos="fade-up" data-aos-duration="1100">
        <div class="flex items-center justify-between mb-4">
          <h3 class="text-md font-bold text-slate-800 flex items-center gap-2">
            <div class="p-2 bg-amber-500/10 text-amber-600 rounded-xl border border-amber-500/20">
              <i class="bi bi-file-earmark-person-fill"></i>
            </div>
            Direct Form Application Submissions
          </h3>
          <span class="bg-amber-100 text-amber-800 text-xs font-bold px-3 py-1 rounded-full border border-amber-200">New Incoming Data</span>
        </div>
        <div class="table-responsive bg-white rounded-2xl overflow-hidden border border-slate-100">
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
    <div class="modal-dialog modal-xl modal-dialog-centered">
      <div class="modal-content rounded-3xl border-0 shadow-2xl overflow-hidden">
        <div class="modal-header bg-gradient-to-r from-[#1a1010] via-[#1f1212] to-[#09090b] text-white px-6 py-4">
          <h5 class="modal-title font-bold text-base flex items-center gap-2">
            <i class="bi bi-file-earmark-text-fill text-amber-500"></i> Employment Contract Agreement
          </h5>
          <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
        </div>
        <div class="modal-body p-6 bg-white max-h-[75vh] overflow-y-auto">
          <div class="bg-slate-50 p-6 rounded-2xl border border-slate-200/80 shadow-sm">
            <h4 class="font-extrabold text-slate-800 text-center mb-4 tracking-tight">OFFER OF EMPLOYMENT & CONTRACT TERMS</h4>
            <div class="row g-2 mb-4 text-sm bg-white p-4 rounded-2xl border border-slate-200">
              <div class="col-md-6"><strong>Applicant Name:</strong> <span id="modalApplicantName" class="text-amber-500 font-semibold"></span></div>
              <div class="col-md-6"><strong>Email:</strong> <span id="modalApplicantEmail" class="text-slate-500"></span></div>
              <div class="col-md-6"><strong>Position Applied:</strong> <span id="modalApplicantPosition" class="text-slate-800 font-semibold"></span></div>
              <div class="col-md-6"><strong>Stage:</strong> <span class="badge bg-[#ff6b4a]">Contract Verification</span></div>
            </div>

            <div class="row g-3">
              <div class="col-md-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Contact Number</label>
                <input type="text" id="modalContactNumber" class="form-control form-control-sm rounded-xl py-2">
              </div>
              <div class="col-md-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Company Name</label>
                <input type="text" id="modalCompanyName" class="form-control form-control-sm rounded-xl py-2">
              </div>
              <div class="col-md-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Company Address</label>
                <input type="text" id="modalCompanyAddress" class="form-control form-control-sm rounded-xl py-2">
              </div>

              <div class="col-md-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Date of Birth</label>
                <input type="date" id="modalDob" class="form-control form-control-sm rounded-xl py-2">
              </div>
              <div class="col-md-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Civil Status</label>
                <select id="modalCivilStatus" class="form-control form-control-sm rounded-xl py-2">
                  <option value="Single">Single</option>
                  <option value="Married">Married</option>
                  <option value="Widowed">Widowed</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Nationality</label>
                <input type="text" id="modalNationality" class="form-control form-control-sm rounded-xl py-2">
              </div>

              <div class="col-md-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Gender</label>
                <select id="modalGender" class="form-control form-control-sm rounded-xl py-2">
                  <option value="Male">Male</option>
                  <option value="Female">Female</option>
                  <option value="Other">Other</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Work Location</label>
                <input type="text" id="modalWorkLocation" class="form-control form-control-sm rounded-xl py-2">
              </div>
              <div class="col-md-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Department</label>
                <input type="text" id="modalContractDepartment" class="form-control form-control-sm rounded-xl py-2">
              </div>

              <div class="col-md-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Role / Position Tier</label>
                <select id="modalRole" class="form-control form-control-sm rounded-xl py-2" onchange="suggestSalaryForRole()">
                  <option value="Staff">Staff</option>
                  <option value="Manager">Manager</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Employment Type</label>
                <select id="modalEmploymentType" class="form-control form-control-sm rounded-xl py-2">
                  <option value="Probationary">Probationary</option>
                  <option value="Regular">Regular</option>
                  <option value="Part-Time">Part-Time</option>
                  <option value="Full-Time">Full-Time</option>
                </select>
              </div>
              <div class="col-md-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Immediate Supervisor</label>
                <input type="text" id="modalSupervisor" class="form-control form-control-sm rounded-xl py-2" placeholder="Supervisor name">
              </div>

              <div class="col-md-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Date Hired</label>
                <input type="date" id="modalDateHired" class="form-control form-control-sm rounded-xl py-2">
              </div>
              <div class="col-md-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Contract Start Date</label>
                <input type="date" id="modalContractStart" class="form-control form-control-sm rounded-xl py-2">
              </div>
              <div class="col-md-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Contract End Date</label>
                <input type="date" id="modalContractEnd" class="form-control form-control-sm rounded-xl py-2">
              </div>

              <div class="col-md-4">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Contract Duration (Years)</label>
                <input type="number" step="0.1" min="0" id="modalContractDuration" class="form-control form-control-sm rounded-xl py-2">
              </div>
              <div class="col-md-8">
                <label class="block text-xs font-bold uppercase tracking-wider text-slate-600 mb-1.5">Offered Monthly Salary (₱)</label>
                <input type="number" min="0" step="0.01" id="modalContractSalary" class="form-control form-control-sm rounded-xl py-2">
                <div id="modalSalarySourceNote" class="text-[11px] text-slate-400 mt-1"></div>
              </div>
            </div>

            <div class="form-check bg-orange-50 border border-orange-200 p-3.5 rounded-2xl mt-4">
              <input class="form-check-input mt-1" type="checkbox" id="agreeContractCheck" onchange="toggleOnboardingButton()">
              <label class="form-check-label text-xs font-semibold text-orange-900 cursor-pointer" for="agreeContractCheck">
                I verify that the applicant has reviewed and agreed to the terms of this contract.
              </label>
            </div>
          </div>
        </div>
        <div class="modal-footer bg-slate-50 border-0 px-6 py-4">
          <input type="hidden" id="modalApplicantId">
          <button type="button" class="btn btn-secondary btn-sm rounded-xl px-4" data-bs-dismiss="modal">Close</button>
          <button type="button" id="proceedOnboardingBtn" class="btn btn-success btn-sm rounded-xl px-5 font-semibold bg-emerald-600 hover:bg-emerald-700 border-0 shadow-lg shadow-emerald-600/20" disabled onclick="confirmProceedOnboarding()">
            <i class="bi bi-person-check-fill me-1"></i> Proceed to Onboarding
          </button>
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

    let allApplicants = [];
    let formSubmissions = [];
    let selectedApplicantId = null;
    let activeContractModal = null;
    let deptSalaryMap = {};
    const phpEndpoint = "<?php echo $current_page; ?>";

    async function loadApplicants() {
      try {
        const response = await fetch(`${phpEndpoint}?action=fetch_applicants`);
        allApplicants = await response.json();
        renderTable();
        if (selectedApplicantId) {
          const stillExists = allApplicants.find(a => String(a.id) === String(selectedApplicantId));
          if (!stillExists) {
            clearSelection();
          }
        }
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

    function selectApplicant(id) {
      selectedApplicantId = id;
      renderTable();
      updateToolbar();
    }

    function clearSelection() {
      selectedApplicantId = null;
      renderTable();
      updateToolbar();
    }

    function updateToolbar() {
      const dot = document.getElementById('toolbarDot');
      const text = document.getElementById('toolbarSelectionText');
      const approveBtn = document.getElementById('toolbarApproveBtn');
      const rejectBtn = document.getElementById('toolbarRejectBtn');

      if (!selectedApplicantId) {
        dot.className = "relative inline-flex rounded-full h-3 w-3 bg-amber-500";
        text.textContent = "NO APPLICANT SELECTED";

        approveBtn.className = "btn btn-sm rounded-xl px-4 py-2 font-semibold text-xs border bg-slate-200 text-slate-400 border-slate-300 cursor-not-allowed shadow-none";
        approveBtn.disabled = true;

        rejectBtn.className = "btn btn-sm rounded-xl px-4 py-2 font-semibold text-xs border bg-slate-200 text-slate-400 border-slate-300 cursor-not-allowed shadow-none";
        rejectBtn.disabled = true;
        return;
      }

      const app = allApplicants.find(a => String(a.id) === String(selectedApplicantId));
      if (!app) {
        clearSelection();
        return;
      }

      const rawStatus = (app.status || 'pending').trim().toLowerCase();
      dot.className = "relative inline-flex rounded-full h-3 w-3 bg-emerald-500";
      text.textContent = `SELECTED: #${app.id} - ${app.full_name} (${app.status || 'Pending'})`;

      // Direct Approval / Contract flow (Pending -> Contract -> Onboarding)
      if (rawStatus === 'pending' || rawStatus === 'contract') {
        approveBtn.className = "btn btn-sm rounded-xl px-4 py-2 font-semibold text-xs border bg-emerald-600 hover:bg-emerald-700 text-white border-emerald-600 shadow-sm";
        approveBtn.disabled = false;
        approveBtn.innerHTML = rawStatus === 'contract' ? `<i class="bi bi-file-earmark-text me-1"></i> Open Contract` : `<i class="bi bi-check-circle me-1"></i> Approve / Contract`;
      } else {
        approveBtn.className = "btn btn-sm rounded-xl px-4 py-2 font-semibold text-xs border bg-slate-200 text-slate-400 border-slate-300 cursor-not-allowed shadow-none";
        approveBtn.disabled = true;
      }

      if (rawStatus !== 'rejected') {
        rejectBtn.className = "btn btn-sm rounded-xl px-4 py-2 font-semibold text-xs border bg-red-600 hover:bg-red-700 text-white border-red-600 shadow-sm";
        rejectBtn.disabled = false;
      } else {
        rejectBtn.className = "btn btn-sm rounded-xl px-4 py-2 font-semibold text-xs border bg-slate-200 text-slate-400 border-slate-300 cursor-not-allowed shadow-none";
        rejectBtn.disabled = true;
      }
    }

    async function executeToolbarAction(actionType) {
      if (!selectedApplicantId) return;
      const app = allApplicants.find(a => String(a.id) === String(selectedApplicantId));
      if (!app) return;

      const rawStatus = (app.status || '').trim().toLowerCase();

      if (actionType === 'approve') {
        if (rawStatus === 'pending') {
          await proceedContract(app.id, app.full_name, app.email, app.position_applied || app.position, app.department);
        } else if (rawStatus === 'contract') {
          openContractModal(app.id, app.full_name, app.email, app.position_applied || app.position, app.department);
        }
      } else if (actionType === 'reject') {
        await rejectApplicant(app.id, app.full_name);
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
        tbody.innerHTML = `<tr><td colspan="5" class="text-center py-8 text-slate-400 italic">No applicant records found.</td></tr>`;
        return;
      }

      filtered.forEach(app => {
        let statusBadge = '';
        const rawStatus = (app.status || 'pending').trim().toLowerCase();
        const isSelected = String(app.id) === String(selectedApplicantId);

        if (rawStatus === 'pending') {
          statusBadge = '<span class="bg-amber-50 text-amber-700 border-amber-200 px-3 py-1 rounded-lg text-xs font-semibold border">Pending Review</span>';
        } else if (rawStatus === 'contract') {
          statusBadge = '<span class="bg-blue-50 text-blue-700 border-blue-200 px-3 py-1 rounded-lg text-xs font-semibold border">Contract Stage</span>';
        } else if (rawStatus === 'rejected') {
          statusBadge = '<span class="bg-red-50 text-red-700 border-red-200 px-3 py-1 rounded-lg text-xs font-semibold border">Rejected</span>';
        } else {
          statusBadge = '<span class="bg-amber-50 text-amber-700 border-amber-200 px-3 py-1 rounded-lg text-xs font-semibold border">Pending Review</span>';
        }

        const tr = document.createElement('tr');
        tr.className = `border-b border-slate-100 transition-colors cursor-pointer ${isSelected ? 'bg-orange-50/70 border-l-4 border-l-[#ff6b4a]' : 'hover:bg-orange-50/20'}`;
        tr.onclick = () => selectApplicant(app.id);
        tr.innerHTML = `
          <td class="py-3.5 px-4 font-mono font-bold text-slate-700">#${app.id}</td>
          <td class="py-3.5 px-4 font-semibold text-slate-800">${app.full_name || ''}</td>
          <td class="py-3.5 px-4 text-slate-600">${app.email || ''}</td>
          <td class="py-3.5 px-4 text-slate-600">${app.position_applied || app.position || 'Staff'}</td>
          <td class="py-3.5 px-4">${statusBadge}</td>
        `;
        tbody.appendChild(tr);
      });
    }

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
        tr.className = "border-b border-slate-100 hover:bg-amber-50/20 transition-colors";
        tr.innerHTML = `
          <td class="py-3.5 px-4 font-mono font-bold text-slate-700">#${sub.id}</td>
          <td class="py-3.5 px-4 font-semibold text-slate-800">${sub.full_name || ''}</td>
          <td class="py-3.5 px-4 text-slate-600 font-medium"><span class="badge bg-orange-100 text-orange-800 px-2.5 py-1 rounded-lg">${sub.department || 'N/A'}</span></td>
          <td class="py-3.5 px-4 text-slate-600 text-xs">${sub.email || ''} <br> <span class="text-slate-400">${sub.phone || ''}</span></td>
          <td class="py-3.5 px-4 text-center">
            <button onclick="approveFormSubmission(${sub.id}, '${safeName}')" class="btn btn-sm btn-success py-1.5 px-3 text-xs font-bold rounded-xl shadow-sm bg-emerald-600 hover:bg-emerald-700 border-0">
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
        background: '#09090b',
        color: '#ffffff',
        customClass: { popup: 'rounded-3xl border border-[#ff6b4a]/30 shadow-2xl backdrop-blur-xl' },
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
            text: data.message || 'Failed processing request.',
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
          text: 'An unexpected error occurred.',
          icon: 'error',
          confirmButtonColor: '#ff6b4a',
          background: '#09090b',
          color: '#ffffff',
          customClass: { popup: 'rounded-3xl border border-[#ff6b4a]/30 shadow-2xl' }
        });
      }
    }

    async function rejectApplicant(id, name) {
      if (!(await Swal.fire({ 
        title: 'Reject Applicant?', 
        text: `Are you sure you want to reject ${name}?`, 
        icon: 'warning', 
        showCancelButton: true, 
        confirmButtonColor: '#ef4444',
        cancelButtonColor: '#64748b',
        confirmButtonText: 'Yes, Reject',
        background: '#09090b',
        color: '#ffffff',
        customClass: { popup: 'rounded-3xl border border-[#ff6b4a]/30 shadow-2xl' }
      })).isConfirmed) return;

      const fd = new FormData(); fd.append('id', id);
      const res = await fetch(`${phpEndpoint}?action=reject_applicant`, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        const app = allApplicants.find(a => String(a.id) === String(id));
        if (app) app.status = 'Rejected';
        renderTable();
        updateToolbar();
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
        updateToolbar();
        openContractModal(id, name, email, position, department);
      }
    }

    function openContractModal(id, name, email, position, department) {
      document.getElementById('modalApplicantId').value = id;
      document.getElementById('modalApplicantName').textContent = name;
      document.getElementById('modalApplicantEmail').textContent = email;
      document.getElementById('modalApplicantPosition').textContent = position;

      const app = allApplicants.find(a => String(a.id) === String(id)) || {};

      document.getElementById('modalContactNumber').value = app.phone || '';
      document.getElementById('modalCompanyName').value = 'Pannakoda';
      document.getElementById('modalCompanyAddress').value = 'Bagong Bayan, Dasmarinas, Cavite';
      document.getElementById('modalDob').value = '';
      document.getElementById('modalCivilStatus').value = 'Single';
      document.getElementById('modalNationality').value = 'Filipino';
      document.getElementById('modalGender').value = 'Male';
      document.getElementById('modalWorkLocation').value = 'Main Office';
      document.getElementById('modalContractDepartment').value = department || app.department || 'Unassigned';

      const guessedRole = /manager/i.test(position || '') ? 'Manager' : 'Staff';
      document.getElementById('modalRole').value = guessedRole;
      document.getElementById('modalEmploymentType').value = 'Probationary';
      document.getElementById('modalSupervisor').value = '';

      const today = new Date().toISOString().split('T')[0];
      document.getElementById('modalDateHired').value = today;
      document.getElementById('modalContractStart').value = today;
      document.getElementById('modalContractEnd').value = '';
      document.getElementById('modalContractDuration').value = '1.0';

      const salaryField = document.getElementById('modalContractSalary');
      const note = document.getElementById('modalSalarySourceNote');
      const deptKey = (department || '').toLowerCase();
      const suggested = deptSalaryMap[deptKey];
      if (suggested !== undefined && suggested !== null) {
        salaryField.value = suggested;
        note.textContent = `Suggested from the "${department}" job posting — you can adjust it.`;
      } else {
        salaryField.value = guessedRole === 'Manager' ? 45000 : 22000;
        note.textContent = `No matching Recruitment posting found for "${department || 'this department'}" — using a role-based default, adjust as needed.`;
      }

      const checkbox = document.getElementById('agreeContractCheck');
      checkbox.checked = true;
      toggleOnboardingButton();

      activeContractModal = new bootstrap.Modal(document.getElementById('contractModal'));
      activeContractModal.show();
    }

    function suggestSalaryForRole() {
      const role = document.getElementById('modalRole').value;
      const deptKey = document.getElementById('modalContractDepartment').value.toLowerCase();
      if (deptSalaryMap[deptKey] !== undefined && deptSalaryMap[deptKey] !== null) return;
      document.getElementById('modalContractSalary').value = role === 'Manager' ? 45000 : 22000;
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
        background: '#09090b',
        color: '#ffffff',
        customClass: { popup: 'rounded-3xl border border-[#ff6b4a]/30 shadow-2xl backdrop-blur-xl' },
        didOpen: () => { Swal.showLoading(); }
      });
      
      const fd = new FormData(); 
      fd.append('id', applicantId);
      fd.append('salary', document.getElementById('modalContractSalary').value || '');
      fd.append('contact_number', document.getElementById('modalContactNumber').value);
      fd.append('company_name', document.getElementById('modalCompanyName').value);
      fd.append('company_address', document.getElementById('modalCompanyAddress').value);
      fd.append('date_of_birth', document.getElementById('modalDob').value);
      fd.append('civil_status', document.getElementById('modalCivilStatus').value);
      fd.append('nationality', document.getElementById('modalNationality').value);
      fd.append('gender', document.getElementById('modalGender').value);
      fd.append('work_location', document.getElementById('modalWorkLocation').value);
      fd.append('department', document.getElementById('modalContractDepartment').value);
      fd.append('role', document.getElementById('modalRole').value);
      fd.append('employment_type', document.getElementById('modalEmploymentType').value);
      fd.append('immediate_supervisor', document.getElementById('modalSupervisor').value);
      fd.append('date_hired', document.getElementById('modalDateHired').value);
      fd.append('contract_start_date', document.getElementById('modalContractStart').value);
      fd.append('contract_end_date', document.getElementById('modalContractEnd').value);
      fd.append('contract_duration_years', document.getElementById('modalContractDuration').value);
      
      try {
        const res = await fetch(`${phpEndpoint}?action=proceed_onboarding`, { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
          allApplicants = allApplicants.filter(app => String(app.id) !== String(applicantId));
          if (String(selectedApplicantId) === String(applicantId)) {
            clearSelection();
          }
          renderTable();
          Swal.fire({ 
            title: 'Success!', 
            text: data.message, 
            icon: 'success', 
            timer: 1000, 
            showConfirmButton: false,
            background: '#09090b',
            color: '#ffffff',
            customClass: { popup: 'rounded-3xl border border-[#ff6b4a]/30 shadow-2xl' }
          });
        } else {
          Swal.fire({
            title: 'Error!',
            text: data.message || 'Failed.',
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
          text: 'An unexpected error occurred.',
          icon: 'error',
          confirmButtonColor: '#ff6b4a',
          background: '#09090b',
          color: '#ffffff',
          customClass: { popup: 'rounded-3xl border border-[#ff6b4a]/30 shadow-2xl' }
        });
      }
    }

    document.getElementById('searchInput').addEventListener('input', renderTable);
    
    window.addEventListener('DOMContentLoaded', () => {
      loadApplicants();
      loadFormSubmissions();
      loadJobSalaries();
      setInterval(loadFormSubmissions, 10000); 
    });

    document.addEventListener('click', function(e) {
        const logoutBtn = e.target.closest('#sidebarLogoutBtn, .logout-btn, a[href*="logout.php"]');
        
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

    AOS.init({
        once: true,
        offset: 50,
        duration: 800,
    });
  </script>
</body>
</html>