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

$current_page = basename($_SERVER['PHP_SELF']);

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
            
            // SIGURADUHING PENDING SA SIMULA ANG BAGONG DATA O KUNG WALANG STATUS
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

// 2. APPROVE APPLICANT (Mula Pending papuntang For Final Interview)
if (isset($_GET['action']) && $_GET['action'] === 'approve_applicant' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id > 0) {
        $conn = new mysqli($host, $user, $pass, $dbname);
        $stmt = $conn->prepare("UPDATE applicants SET status = 'For Final Interview' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => true]);
    }
    exit;
}

// 3. PROCEED TO CONTRACT (Mula For Final Interview papuntang Contract)
if (isset($_GET['action']) && $_GET['action'] === 'proceed_contract' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id > 0) {
        $conn = new mysqli($host, $user, $pass, $dbname);
        $stmt = $conn->prepare("UPDATE applicants SET status = 'Contract' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
        $conn->close();
        echo json_encode(['success' => true]);
    }
    exit;
}

// 4. PROCEED TO ONBOARDING (Kopyahin sa employees at i-delete sa applicants)
if (isset($_GET['action']) && $_GET['action'] === 'proceed_onboarding' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id > 0) {
        $conn = new mysqli($host, $user, $pass, $dbname);
        if ($conn->connect_error) {
            echo json_encode(['success' => false, 'message' => 'DB Connection failed.']);
            exit;
        }

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

            $insert_stmt = $conn->prepare("
                INSERT INTO employees (
                    company_name, company_address, contact_number, company_email,
                    employee_id, full_name, address, phone, email,
                    position_title, department, employment_type, date_hired,
                    contract_duration_years, work_location, employee_gmail,
                    gsis_id, sss_id, philhealth_id, pagibig_id, status, position
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            
            $insert_stmt->bind_param(
                "sssssssssssssdssssssss", 
                $company_name, $company_address, $contact_number, $company_email,
                $employee_id, $full_name, $address, $phone, $email,
                $position, $department, $employment_type, $date_hired,
                $contract_duration_years, $work_location, $employee_gmail,
                $gsis_id, $sss_id, $philhealth_id, $pagibig_id, $status, $position
            );
            
            if ($insert_stmt->execute()) {
                $insert_stmt->close();

                // DIREKTANG TANGGALIN SA APPLICANTS KASI NAKAPASOK NA SA ONBOARDING
                $del_stmt = $conn->prepare("DELETE FROM applicants WHERE id = ?");
                $del_stmt->bind_param("i", $id);
                $del_stmt->execute();
                $del_stmt->close();

                echo json_encode(['success' => true, 'message' => 'Successfully transferred to onboarding and removed from applicants!']);
            } else {
                echo json_encode(['success' => false, 'message' => 'Insert error: ' . $insert_stmt->error]);
                $insert_stmt->close();
            }
        } else {
            echo json_encode(['success' => false, 'message' => 'Applicant not found.']);
        }
        $stmt->close();
        $conn->close();
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
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
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
            <div class="text-sm text-gray-700 space-y-3 mb-4 max-h-52 overflow-y-auto p-4 border rounded bg-white shadow-inner">
              <p><strong>1. Position & Commencement:</strong> Employment commences immediately upon completion of onboarding requirements.</p>
              <p><strong>2. Compensation & Allowances:</strong> Standard corporate compensation and benefits apply.</p>
            </div>
            <div class="form-check bg-amber-50 border border-amber-200 p-3 rounded-xl">
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
    let activeContractModal = null;
    const phpEndpoint = "<?php echo $current_page; ?>";

    async function loadApplicants() {
      try {
        const response = await fetch(`${phpEndpoint}?action=fetch_applicants`);
        const text = await response.text();
        allApplicants = JSON.parse(text);
        renderTable();
      } catch (err) {
        console.error("Failed to load applicants:", err);
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

        // TAMANG DALOY NG MGA ACTION BUTTONS BATAY SA STATUS
        if (rawStatus === 'pending') {
          statusBadge = '<span class="bg-amber-50 text-amber-700 border-amber-200 px-2.5 py-1 rounded-md text-xs font-semibold border">Pending Review</span>';
          actionButtons = `<button onclick="approveApplicant(${app.id}, '${safeName}')" class="btn btn-sm btn-success py-1 px-2.5 text-xs font-semibold rounded-lg"><i class="bi bi-check-lg"></i> Approve Final Interview</button>`;
        } else if (rawStatus === 'for final interview') {
          statusBadge = '<span class="bg-indigo-50 text-indigo-700 border-indigo-200 px-2.5 py-1 rounded-md text-xs font-semibold border">For Final Interview</span>';
          actionButtons = `<button onclick="proceedContract(${app.id}, '${safeName}')" class="btn btn-sm btn-primary py-1 px-2.5 text-xs font-semibold rounded-lg"><i class="bi bi-file-earmark-text"></i> Proceed to Contract</button>`;
        } else if (rawStatus === 'contract') {
          statusBadge = '<span class="bg-blue-50 text-blue-700 border-blue-200 px-2.5 py-1 rounded-md text-xs font-semibold border">Contract Stage</span>';
          actionButtons = `<button onclick="openContractModal(${app.id}, '${safeName}', '${safeEmail}', '${safePosition}')" class="btn btn-sm btn-outline-success py-1 px-2.5 text-xs font-semibold rounded-lg"><i class="bi bi-pencil-square"></i> Review Contract</button>`;
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

    async function approveApplicant(id, name) {
      if (!(await Swal.fire({ title: 'Approve Final Interview?', text: `Mark ${name} as For Final Interview?`, icon: 'question', showCancelButton: true })).isConfirmed) return;
      const fd = new FormData(); fd.append('id', id);
      const res = await fetch(`${phpEndpoint}?action=approve_applicant`, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        loadApplicants();
      }
    }

    async function proceedContract(id, name) {
      if (!(await Swal.fire({ title: 'Proceed to Contract?', text: `Move ${name} to contract stage?`, icon: 'question', showCancelButton: true })).isConfirmed) return;
      const fd = new FormData(); fd.append('id', id);
      const res = await fetch(`${phpEndpoint}?action=proceed_contract`, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) {
        loadApplicants();
      }
    }

    function openContractModal(id, name, email, position) {
      document.getElementById('modalApplicantId').value = id;
      document.getElementById('modalApplicantName').textContent = name;
      document.getElementById('modalApplicantEmail').textContent = email;
      document.getElementById('modalApplicantPosition').textContent = position;
      document.getElementById('agreeContractCheck').checked = false;
      document.getElementById('proceedOnboardingBtn').disabled = true;
      activeContractModal = new bootstrap.Modal(document.getElementById('contractModal'));
      activeContractModal.show();
    }

    function toggleOnboardingButton() {
      document.getElementById('proceedOnboardingBtn').disabled = !document.getElementById('agreeContractCheck').checked;
    }

    async function confirmProceedOnboarding() {
      const applicantId = document.getElementById('modalApplicantId').value;
      if (!(await Swal.fire({ title: 'Proceed to Onboarding?', text: "Transfer this applicant and delete from table?", icon: 'warning', showCancelButton: true })).isConfirmed) return;
      
      const fd = new FormData(); 
      fd.append('id', applicantId);
      
      try {
        const res = await fetch(`${phpEndpoint}?action=proceed_onboarding`, { method: 'POST', body: fd });
        const data = await res.json();
        
        if (data.success) {
          if (activeContractModal) {
            activeContractModal.hide();
          }
          
          // INSTANT NA TANGGALIN SA TABLE VIEW KASI NALIPAT NA SA EMPLOYEES
          allApplicants = allApplicants.filter(app => String(app.id) !== String(applicantId));
          renderTable();

          Swal.fire({ title: 'Success!', text: data.message, icon: 'success', timer: 1200, showConfirmButton: false });
        } else {
          Swal.fire('Error!', data.message || 'Failed.', 'error');
        }
      } catch (e) {
        console.error("Error:", e);
        Swal.fire('Error', 'May nangyaring problema sa koneksyon.', 'error');
      }
    }

    async function deleteApplicant(id, name) {
      if (!(await Swal.fire({ title: 'Delete?', text: `Delete ${name}?`, icon: 'warning', showCancelButton: true, confirmButtonColor: '#d33' })).isConfirmed) return;
      const fd = new FormData(); fd.append('id', id);
      const res = await fetch(`${phpEndpoint}?action=delete_applicant`, { method: 'POST', body: fd });
      const data = await res.json();
      if (data.success) loadApplicants();
    }

    document.getElementById('searchInput').addEventListener('input', renderTable);
    window.addEventListener('DOMContentLoaded', loadApplicants);
  </script>
</body>
</html>