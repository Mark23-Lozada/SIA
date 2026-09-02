<?php
session_start();

// Allow public access ONLY if the request is trying to fetch job openings for the application form
$is_public_fetch = (isset($_GET['action']) && $_GET['action'] === 'fetch');

if (!$is_public_fetch) {
    // 1. Siguraduhin muna na may naka-login na user
    if (!isset($_SESSION['role'])) {
        header("Location: login.php");
        exit();
    }

    // 2. Kunin ang role at gawing lowercase para iwas sa error sa malaki/maliit na titik
    $current_role = strtolower(trim($_SESSION['role']));

    // 3. Harangin kung HINDI siya admin at HINDI rin hr
    if ($current_role !== 'admin' && $current_role !== 'hr') {
        header("Location: login.php"); 
        exit();
    }
}

$host = 'localhost';
$db   = 'pos';
$user = 'root';
$pass = '';
$charset = 'utf8mb4';

$dsn = "mysql:host=$host;dbname=$db;charset=$charset";
$options = [
    PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES   => false,
];

try {
     $pdo = new PDO($dsn, $user, $pass, $options);
     
    // --- AUTOMATIC DATABASE SEEDER ---
    $check = $pdo->query("SELECT COUNT(*) FROM job_openings")->fetchColumn();
    if ($check == 0) {
        $default_depts = [
            ['Manager', 1, 'Active'],
            ['Finance', 1, 'Active'],
            ['Staff', 2, 'Active'],
            ['HR', 2, 'Active']
        ];
        $seed_stmt = $pdo->prepare("INSERT INTO job_openings (department, openings, status) VALUES (?, ?, ?)");
        foreach ($default_depts as $dept) {
            $seed_stmt->execute($dept);
        }
    }

    // --- COLUMN MIGRATIONS: add new columns if they don't exist yet ---
    $existingCols = $pdo->query("SHOW COLUMNS FROM job_openings")->fetchAll(PDO::FETCH_COLUMN);
    if (!in_array('requirements', $existingCols)) {
        $pdo->exec("ALTER TABLE job_openings ADD COLUMN requirements TEXT NULL AFTER status");
    }
    if (!in_array('salary', $existingCols)) {
        $pdo->exec("ALTER TABLE job_openings ADD COLUMN salary DECIMAL(10,2) NULL AFTER requirements");
    }
    if (!in_array('notes', $existingCols)) {
        $pdo->exec("ALTER TABLE job_openings ADD COLUMN notes TEXT NULL AFTER salary");
    }
} catch (\PDOException $e) {
     die("Database connection failed: " . $e->getMessage());
}

// --- BACKEND API ENDPOINTS (AJAX HANDLERS) ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];

    try {
        // 1. FETCH ALL OPENINGS
        if ($action === 'fetch') {
            $stmt = $pdo->query("SELECT id, department, openings, status, requirements, salary, notes FROM job_openings ORDER BY id ASC");
            echo json_encode($stmt->fetchAll());
            exit;
        }

        // 2. MANUAL UPDATE
        if ($action === 'update' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = intval($_POST['id'] ?? 0);
            $department = trim($_POST['department'] ?? '');
            $openings = intval($_POST['openings'] ?? 0);
            $status = $_POST['status'] ?? 'Active';
            $salary = ($_POST['salary'] ?? '') !== '' ? floatval($_POST['salary']) : null;
            $requirements = trim($_POST['requirements'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if (!$id) throw new Exception("Missing required fields");
            if (empty($department)) throw new Exception("Store department name is required.");

            if ($openings < 0) $openings = 0;
            if ($openings > 10) $openings = 10;

            if ($openings === 0) {
                $status = 'Closed';
            }

            $stmt = $pdo->prepare("UPDATE job_openings SET department = ?, openings = ?, status = ?, salary = ?, requirements = ?, notes = ? WHERE id = ?");
            $stmt->execute([$department, $openings, $status, $salary, $requirements, $notes, $id]);
            echo json_encode(['success' => true]);
            exit;
        }

        // --- CREATE A NEW JOB POSTING ---
        if ($action === 'create' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $department = trim($_POST['department'] ?? '');
            $openings = intval($_POST['openings'] ?? 0);
            $status = $_POST['status'] ?? 'Active';
            $salary = ($_POST['salary'] ?? '') !== '' ? floatval($_POST['salary']) : null;
            $requirements = trim($_POST['requirements'] ?? '');
            $notes = trim($_POST['notes'] ?? '');

            if (empty($department)) throw new Exception("Store department name is required.");

            if ($openings < 0) $openings = 0;
            if ($openings > 10) $openings = 10;
            if ($openings === 0) $status = 'Closed';

            $stmt = $pdo->prepare("INSERT INTO job_openings (department, openings, status, salary, requirements, notes) VALUES (?, ?, ?, ?, ?, ?)");
            $stmt->execute([$department, $openings, $status, $salary, $requirements, $notes]);
            echo json_encode(['success' => true, 'id' => $pdo->lastInsertId()]);
            exit;
        }

        // 3. APPLICATION GATEKEEPER TRIGGER
        if ($action === 'apply' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = intval($_POST['id'] ?? 0);
            if (!$id) throw new Exception("Invalid Job ID");

            $stmt = $pdo->prepare("SELECT department, openings, status FROM job_openings WHERE id = ?");
            $stmt->execute([$id]);
            $job = $stmt->fetch();

            if (!$job) throw new Exception("Job position not found.");

            if ($job['openings'] <= 0 || $job['status'] !== 'Active') {
                throw new Exception("Application closed! Recruitment is currently paused or closed for " . $job['department'] . ".");
            }

            $new_openings = $job['openings'] - 1;
            $new_status = ($new_openings === 0) ? 'Closed' : 'Active';

            $update_stmt = $pdo->prepare("UPDATE job_openings SET openings = ?, status = ? WHERE id = ?");
            $update_stmt->execute([$new_openings, $new_status, $id]);

            echo json_encode(['success' => true, 'openings' => $new_openings, 'status' => $new_status]);
            exit;
        }

        // 4. UNIQUE EMPLOYEE ID DUPLICATE CHECKER
        if ($action === 'check_duplicate') {
            $employee_id = trim($_GET['employee_id'] ?? '');
            
            if (empty($employee_id)) {
                echo json_encode(['exists' => false]);
                exit;
            }

            $stmt = $pdo->prepare("SELECT COUNT(*) FROM applicants WHERE employee_id = ?");
            $stmt->execute([$employee_id]);
            $count = $stmt->fetchColumn();

            echo json_encode(['exists' => $count > 0]);
            exit;
        }

        // 5. UPDATE STATUS (WITH REJECTION SOFT-DELETE ERASE FUNCTION)
        if ($action === 'update_status' && $_SERVER['REQUEST_METHOD'] === 'POST') {
            $id = intval($_POST['id'] ?? 0);
            $status = trim($_POST['status'] ?? '');

            if (!$id || empty($status)) {
                throw new Exception("Missing identification keys or status directives.");
            }

            if (in_array($status, ['Rejected', 'Failed', 'Declined'])) {
                $file_stmt = $pdo->prepare("SELECT resume_path FROM applicants WHERE id = ? LIMIT 1");
                $file_stmt->execute([$id]);
                $applicant = $file_stmt->fetch();

                if ($applicant && !empty($applicant['resume_path'])) {
                    if (file_exists($applicant['resume_path'])) {
                        unlink($applicant['resume_path']);
                    }
                }

                $stmt = $pdo->prepare("UPDATE applicants SET status = ?, resume_path = NULL, employee_id = NULL WHERE id = ?");
                $stmt->execute([$status, $id]);
            } else {
                $stmt = $pdo->prepare("UPDATE applicants SET status = ? WHERE id = ?");
                $stmt->execute([$status, $id]);
            }

            echo json_encode(['status' => 'success', 'message' => 'Applicant workflow data updated successfully.']);
            exit;
        }

    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        exit;
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="h-full bg-white">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>PannaKoda - Pancake House Recruitment</title>
  <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
  <script src="../LIBRARIES/tailwind.js"></script>
  <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
  <style>
      #sidebar-container { transition: margin-left 0.3s ease-in-out; }
      .sidebar-hidden #sidebar-container { margin-left: -16rem; }
  </style>
</head>

<body class="h-full text-slate-800 font-sans antialiased">

  <div class="flex h-screen w-full overflow-hidden bg-white">
    
    <div id="sidebar-container" class="flex-shrink-0 w-64 h-full bg-slate-900 shadow-xl transition-all duration-300">
        <?php include 'sidebar.php'; ?>
    </div>
    
    <!-- MAIN CONTENT -->
    <div id="main-wrapper" class="flex-1 flex flex-col h-full overflow-hidden">
      
      <header class="flex items-center justify-between px-6 bg-white border-b border-amber-500/25 shadow-sm shrink-0" style="height: 60px;">
        <div class="flex items-center gap-4">
          <button id="burgerToggle" type="button" class="inline-flex items-center justify-center p-2 rounded-xl text-white bg-amber-500 hover:bg-blue-900 focus:outline-none transition-all transform hover:scale-105 active:scale-95 shadow-md">
              <i class="bi bi-list text-xl leading-none"></i>
          </button>
          <div>
            <h1 class="text-xl font-black text-amber-500 tracking-wide">Pancake Store Recruitment</h1>
          </div>
        </div>
      </header>

      <main class="flex-1 overflow-x-hidden overflow-y-auto p-6 bg-gradient-to-br from-blue-50/40 via-white to-slate-50">

        <div class="flex flex-wrap gap-4 mb-6">
          <div class="flex items-center bg-white flex-col h-28 justify-center px-8 shadow-md rounded-2xl border border-amber-500/20 w-full sm:w-64">
            <h1 class="font-black text-slate-400 text-xs tracking-wider uppercase mb-1">Active Store Posts</h1>
            <span id="active-posts-count" class="font-black text-3xl text-amber-500">0</span>
          </div>
        </div>

        <div class="grid grid-cols-1 gap-6 mb-6">
          <div class="bg-white p-6 rounded-2xl shadow-xl border border-amber-500/20">
            <div class="flex justify-between items-center mb-4 flex-wrap gap-3">
              <h2 class="font-black text-lg text-slate-900 flex items-center gap-2">
                <i class="bi bi-egg-fried text-amber-500"></i> Fixed Department Status
              </h2>
              <button onclick="openAddJobModal()" class="bg-amber-500 hover:bg-blue-900 text-white text-xs sm:text-sm font-bold px-5 py-2.5 rounded-xl shadow-lg shadow-amber-500/10 inline-flex items-center gap-2 border-0 cursor-pointer transition-all transform hover:-translate-y-0.5 active:translate-y-0">
                <i class="bi bi-plus-lg"></i> Add Job Posting
              </button>
            </div>
            
            <div class="overflow-x-auto">
              <table class="w-full text-left border-collapse">
                <thead>
                  <tr class="border-b border-blue-50 bg-blue-50/70 text-amber-500 text-xs uppercase tracking-wider">
                    <th class="px-6 py-4 font-black">Store Department / Section</th>
                    <th class="px-6 py-4 font-black text-center">Target Slots</th>
                    <th class="px-6 py-4 font-black text-center">Status</th>
                    <th class="px-6 py-4 font-black text-right">Actions</th>
                  </tr>
                </thead>
                <tbody id="job-posts-table-body" class="text-sm divide-y divide-blue-50 text-slate-600">
                  <!-- Loaded via AJAX -->
                </tbody>
              </table>
            </div>
          </div>
        </div>

      </main>
    </div>
  </div>

  <script>
    let localJobOpenings = [];

    // Auto reload interval set to 120 seconds
    setInterval(function() {
        location.reload();
    }, 120000); 

    document.addEventListener("DOMContentLoaded", function () {
      loadJobPosts();

      const burgerToggle = document.getElementById('burgerToggle');
      if (burgerToggle) {
          burgerToggle.addEventListener('click', function () {
              document.body.classList.toggle('sidebar-hidden');
          });
      }

      const currentPath = window.location.pathname;
      const navLinks = document.querySelectorAll(".sidebar-link, .nav-link");
      
      navLinks.forEach(link => {
          const linkPath = link.getAttribute("href");
          if (linkPath && currentPath.endsWith(linkPath)) {
              link.classList.remove("text-white/80", "hover:bg-white/10", "hover:text-white", "text-inherit");
              link.classList.add("bg-amber-500", "!text-[white]", "shadow-md", "font-semibold");
          }
      });

      const logoutBtn = document.getElementById('logoutBtn');
      if (logoutBtn) {
          logoutBtn.addEventListener('click', function(e) {
              e.preventDefault(); 
              Swal.fire({
                  title: 'Log out',
                  text: "Are you sure you want to Log out?",
                  icon: 'warning',
                  showConfirmButton: true,
                  showCancelButton: true,
                  confirmButtonColor: '#090d16', 
                  cancelButtonColor: '#d33',
                  confirmButtonText: 'Yes',
                  cancelButtonText: 'Cancel',
                  background: '#ffffff',
                  color: '#212121'
              }).then((result) => {
                  if (result.isConfirmed) {
                      window.location.href = "logout.php"; 
                  }
              });
          });
      }
    });

    function loadJobPosts() {
      fetch('recruitment.php?action=fetch')
        .then(res => res.json())
        .then(data => {
          if (Array.isArray(data)) {
            localJobOpenings = data;
            renderJobPosts();
          } else if (data.error) {
            Swal.fire('Database Error', data.error, 'error');
          }
        })
        .catch(err => {
          console.error("Fetch error:", err);
        });
    }
    window.loadJobPosts = loadJobPosts;

    function renderJobPosts() {
      const tableBody = document.getElementById("job-posts-table-body");
      tableBody.innerHTML = "";

      localJobOpenings.forEach(job => {
        let statusClass = 'bg-emerald-100 text-emerald-700';
        let displayStatus = 'Active';

        if (job.openings <= 0 || job.status === 'Closed') {
          statusClass = 'bg-rose-100 text-rose-700';
          displayStatus = 'Closed';
        } else if (job.status === 'Paused') {
          statusClass = 'bg-amber-100 text-amber-700';
          displayStatus = 'Paused';
        }
        
        const jobHTML = `
          <tr class="hover:bg-blue-50/40 transition-colors duration-150">
            <td class="px-6 py-4 font-bold text-slate-900">${job.department}</td>
            <td class="px-6 py-4 text-center font-bold">${job.openings} Slot${job.openings > 1 ? 's' : ''}</td>
            <td class="px-6 py-4 text-center">
              <span class="text-xs ${statusClass} px-3 py-1.5 rounded-full font-bold">${displayStatus}</span>
            </td>
            <td class="px-6 py-4 text-right">
              <button onclick="editJobPost(${job.id})" class="text-slate-600 hover:text-amber-500 bg-white hover:bg-blue-50 px-3.5 py-2 rounded-xl border border-slate-200 shadow-xs text-xs font-bold inline-flex items-center gap-1.5 transition-all cursor-pointer">
                <i class="bi bi-pencil-square text-sm"></i> Edit Update
              </button>
            </td>
          </tr>
        `;
        tableBody.insertAdjacentHTML('beforeend', jobHTML);
      });

      const activeCount = localJobOpenings.filter(j => j.status === 'Active' && j.openings > 0).length;
      document.getElementById("active-posts-count").innerText = activeCount;
    }

    function serializeData(obj) {
      return Object.keys(obj).map(k => encodeURIComponent(k) + '=' + encodeURIComponent(obj[k] ?? '')).join('&');
    }

    window.openAddJobModal = function() {
      Swal.fire({
        title: '<div class="text-lg font-black text-slate-900 pt-2">Add Job Posting</div>',
        html: buildJobFormHTML(null),
        showCancelButton: true,
        confirmButtonText: 'Create Posting',
        cancelButtonText: 'Cancel',
        customClass: swalCustomClasses(),
        buttonsStyling: false,
        preConfirm: () => validateJobForm()
      }).then((result) => {
        if (result.isConfirmed) {
          fetch('recruitment.php?action=create', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: serializeData(result.value)
          })
          .then(res => res.json())
          .then(res => {
            if (res.success) {
              loadJobPosts();
              Swal.fire({
                icon: 'success',
                title: '<div class="text-slate-900 font-bold text-base">Job Posting Created!</div>',
                customClass: { popup: 'rounded-2xl p-4', confirmButton: 'bg-amber-500 text-white px-5 py-2 rounded-xl font-bold text-sm border-0' },
                buttonsStyling: false,
                confirmButtonColor: '#090d16'
              });
            } else {
              Swal.fire('Error', res.error, 'error');
            }
          });
        }
      });
    };

    window.editJobPost = function(id) {
      const job = localJobOpenings.find(j => j.id == id);
      if (!job) return;

      Swal.fire({
        title: '<div class="text-lg font-black text-slate-900 flex items-center gap-2 pt-2"><i class="bi bi-pencil-square text-amber-500"></i> Update Job Posting</div>',
        html: buildJobFormHTML(job),
        showCancelButton: true,
        confirmButtonText: 'Save Changes',
        cancelButtonText: 'Cancel',
        customClass: swalCustomClasses(),
        buttonsStyling: false,
        preConfirm: () => validateJobForm()
      }).then((result) => {
        if (result.isConfirmed) {
          const updatedData = { id, ...result.value };
          fetch('recruitment.php?action=update', {
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
            body: serializeData(updatedData)
          })
          .then(res => res.json())
          .then(res => {
            if (res.success) {
              loadJobPosts();
              Swal.fire({
                icon: 'success',
                title: '<div class="text-slate-900 font-bold text-base">Changes Saved Successfully!</div>',
                customClass: { popup: 'rounded-2xl p-4', confirmButton: 'bg-amber-500 text-white px-5 py-2 rounded-xl font-bold text-sm border-0' },
                buttonsStyling: false,
                confirmButtonColor: '#090d16'
              });
            } else {
              Swal.fire('Error', res.error, 'error');
            }
          });
        }
      });
    };

    function buildJobFormHTML(job) {
      const isEdit = !!job;
      const dept = isEdit ? job.department : '';
      const openings = isEdit ? job.openings : 1;
      const status = isEdit ? job.status : 'Active';
      const salary = isEdit && job.salary !== null && job.salary !== undefined ? job.salary : '';
      const requirements = isEdit && job.requirements ? job.requirements : '';
      const notes = isEdit && job.notes ? job.notes : '';
      const isZero = isEdit && job.openings <= 0;

      return `
        <div class="text-left p-1 space-y-4 font-sans">
          <div>
            <label class="block text-xs font-black text-slate-600 uppercase tracking-wider mb-1">Store Department</label>
            <input id="swal-job-dept" type="text" value="${dept}" placeholder="e.g. Dishwasher" class="w-full text-sm px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none">
          </div>
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-black text-slate-600 uppercase tracking-wider mb-1">Target Manpower Slots</label>
              <input id="swal-job-openings" type="number" min="0" max="10" value="${openings}" class="w-full text-sm px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none" oninput="toggleStatusOptions(this.value)">
            </div>
            <div>
              <label class="block text-xs font-black text-slate-600 uppercase tracking-wider mb-1">Status</label>
              <select id="swal-job-status" class="w-full text-sm px-3.5 py-2.5 border border-slate-300 rounded-xl bg-white focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none">
                ${isZero ? `
                  <option value="Closed" selected>Closed</option>
                ` : `
                  <option value="Active" ${status === 'Active' ? 'selected' : ''}>Active</option>
                  <option value="Paused" ${status === 'Paused' ? 'selected' : ''}>Paused</option>
                `}
              </select>
            </div>
          </div>
          <div>
            <label class="block text-xs font-black text-slate-600 uppercase tracking-wider mb-1">Monthly Salary (₱)</label>
            <input id="swal-job-salary" type="number" min="0" step="0.01" value="${salary}" placeholder="e.g. 18000" class="w-full text-sm px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none">
          </div>
          <div>
            <label class="block text-xs font-black text-slate-600 uppercase tracking-wider mb-1">Requirements</label>
            <textarea id="swal-job-requirements" rows="3" placeholder="e.g. At least high school graduate, can work weekends, 1 year experience preferred" class="w-full text-sm px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none resize-none">${requirements}</textarea>
          </div>
          <div>
            <label class="block text-xs font-black text-slate-600 uppercase tracking-wider mb-1">Things to Know Before Applying</label>
            <textarea id="swal-job-notes" rows="3" placeholder="e.g. Rotating shifts, standing for long periods, walk-in interviews only" class="w-full text-sm px-3.5 py-2.5 border border-slate-300 rounded-xl focus:ring-2 focus:ring-amber-500 focus:border-amber-500 outline-none resize-none">${notes}</textarea>
          </div>
        </div>
      `;
    }

    window.toggleStatusOptions = function(val) {
      const statusSelect = document.getElementById('swal-job-status');
      if (!statusSelect) return;
      const openings = parseInt(val) || 0;
      if (openings === 0) {
        statusSelect.innerHTML = '<option value="Closed" selected>Closed</option>';
      } else {
        if(statusSelect.value === 'Closed') {
           statusSelect.innerHTML = `
            <option value="Active" selected>Active</option>
            <option value="Paused">Paused</option>
           `;
        }
      }
    }

    function validateJobForm() {
      const department = document.getElementById('swal-job-dept').value.trim();
      const openingsInput = document.getElementById('swal-job-openings');
      const openings = parseInt(openingsInput.value) || 0;
      const status = document.getElementById('swal-job-status').value;
      const salaryRaw = document.getElementById('swal-job-salary').value.trim();
      const requirements = document.getElementById('swal-job-requirements').value.trim();
      const notes = document.getElementById('swal-job-notes').value.trim();

      if (!department) {
        Swal.showValidationMessage('Store department name is required.');
        return false;
      }

      if (openings < 0 || openings > 10) {
        Swal.showValidationMessage('The available slots must be between 0 and 10 only.');
        return false; 
      }

      if (openings > 0 && status === 'Closed') {
        Swal.showValidationMessage('Cannot set status to Closed if slots are greater than 0.');
        return false;
      }

      if (salaryRaw && (isNaN(salaryRaw) || parseFloat(salaryRaw) < 0)) {
        Swal.showValidationMessage('Monthly salary must be a valid positive number.');
        return false;
      }

      return { department, openings, status, salary: salaryRaw, requirements, notes };
    }

    function swalCustomClasses() {
      return {
        popup: 'rounded-2xl shadow-2xl border border-amber-500/20 p-4',
        confirmButton: 'bg-amber-500 hover:bg-blue-900 text-white px-5 py-2.5 rounded-xl font-bold text-sm border-0 focus:outline-none shadow-lg shadow-amber-500/10 cursor-pointer',
        cancelButton: 'bg-slate-100 hover:bg-slate-200 text-slate-600 px-5 py-2.5 rounded-xl font-semibold text-sm border-0 focus:outline-none cursor-pointer'
      };
    }
  </script>

  <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>
</body>
</html>