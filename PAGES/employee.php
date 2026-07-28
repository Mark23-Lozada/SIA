<?php
session_start();

// 1. Siguraduhin muna na may naka-login na user
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

// 2. Kunin ang role at gawing lowercase para iwas sa error sa malaki/maliit na titik
$current_role = strtolower($_SESSION['role']);

// 3. Harangin kung HINDI siya admin at HINDI rin hr
if ($current_role !== 'admin' && $current_role !== 'hr') {
    header("Location: login.php"); // Pwedeng palitan ng unauthorized.php
    exit();
}

$host = "localhost";
$user = "root"; 
$pass = ""; 
$dbname = "pos";

// 1. FETCH EMPLOYEES ENDPOINT
if (isset($_GET['action']) && $_GET['action'] === 'fetch_employees') {
    header('Content-Type: application/json');
    
    $conn = new mysqli($host, $user, $pass, $dbname);
    if ($conn->connect_error) {
        echo json_encode([]);
        exit;
    }

    $query = "SELECT * FROM employees ORDER BY id DESC";
    $result = $conn->query($query);
    $employees = [];
    
    while($row = $result->fetch_assoc()) {
        $row['id'] = isset($row['id']) ? intval($row['id']) : 0;
        
        // Fallback para sa Display ID kung walang nakalaang employee_id column
        $row['display_emp_id'] = isset($row['employee_id']) && !empty($row['employee_id']) ? $row['employee_id'] : $row['id'];
        
        if (!isset($row['department']) || empty($row['department'])) {
            $row['department'] = 'Unassigned'; 
        }

        if (strcasecmp($row['department'], 'Manager') === 0) {
            $row['role'] = 'Manager';
        } else {
            $row['role'] = 'Staff'; 
        }
        
        $employees[] = $row;
    }
    
    echo json_encode($employees);
    $conn->close();
    exit;
}

// 2. DELETE EMPLOYEE ENDPOINT (KORREKSYON: Binabago para magbura sa 'employees' table)
if (isset($_GET['action']) && $_GET['action'] === 'delete_employee' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    $id = isset($_POST['id']) ? intval($_POST['id']) : 0;

    if ($id > 0) {
        $conn = new mysqli($host, $user, $pass, $dbname);
        if ($conn->connect_error) {
            echo json_encode(['success' => false, 'message' => 'Database connection failed.']);
            exit;
        }

        // Dito tinatarget ang tamang table ('employees') gamit ang primary key 'id'
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
          <h1 class="text-2xl font-bold text-gray-800 tracking-tight">Employee Directory</h1>
          <p class="text-sm text-gray-500">Manage profiles, credentials, and structural tier payroll rates.</p>
        </div>
        
        <div class="bg-gray-200/80 p-1 rounded-xl flex gap-1 shadow-inner">
          <button id="tabPersonal" class="px-4 py-2 rounded-lg text-sm transition-all flex items-center gap-2 bg-white text-gray-900 shadow-sm font-semibold">
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
          
          <table id="personalTable" class="table table-hover align-middle mb-0 text-sm">
            <thead class="table-dark">
              <tr>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Full Name</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Role</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Department</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0 text-center">Action</th>
              </tr>
            </thead>
            <tbody id="personalBody"></tbody>
          </table>

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

  </div>

  <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>

  <script>
    // Magre-refresh ang buong pahina tuwing 30 segundo
    setInterval(function() {
        location.reload();
    }, 30000); 

    let allEmployees = [];
    let bsModalInstance = null;

    async function loadEmployees() {
      try {
        const response = await fetch(`${window.location.pathname}?action=fetch_employees`);
        allEmployees = response.ok ? await response.json() : [];
        renderTables();
      } catch (err) {
        console.error("Pipeline failure:", err);
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

      const personalBody = document.getElementById('personalBody');
      const payrollBody = document.getElementById('payrollBody');

      personalBody.innerHTML = '';
      payrollBody.innerHTML = '';

      if (filtered.length === 0) {
        const emptyTrPersonal = `<tr><td colspan="4" class="text-center py-8 text-gray-400 italic">No operational records matched.</td></tr>`;
        const emptyTrPayroll = `<tr><td colspan="8" class="text-center py-8 text-gray-400 italic">No operational records matched.</td></tr>`;
        personalBody.innerHTML = emptyTrPersonal;
        payrollBody.innerHTML = emptyTrPayroll;
        return;
      }

      filtered.forEach(emp => {
        const baseSalary = emp.role === 'Manager' ? 20000 : 15000;
        const roleClass = emp.role === 'Manager' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-blue-50 text-blue-700 border-blue-200';

        // Row 1: Personal Details View
        const trPersonal = document.createElement('tr');
        trPersonal.className = "border-b border-gray-100 hover:bg-gray-50/50 transition-colors";
        trPersonal.innerHTML = `
          <td class="py-3 px-4 font-semibold text-gray-800">${emp.full_name || ''}</td>
          <td class="py-3 px-4"><span class="${roleClass} px-2.5 py-1 rounded-md text-xs font-semibold border">${emp.role}</span></td>
          <td class="py-3 px-4 text-gray-600">${emp.department}</td>
          <td class="py-3 px-4 text-center">
            <button onclick="triggerDelete(${emp.id}, '${emp.full_name.replace(/'/g, "\\'")}')" class="btn btn-sm btn-outline-danger py-1 px-2 text-xs font-semibold rounded-lg flex items-center gap-1 mx-auto">
              <i class="bi bi-trash3"></i> Delete
            </button>
          </td>
        `;
        personalBody.appendChild(trPersonal);

        // Row 2: Payroll Profile View
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
      });
    }

    // SWEETALERT & NAVIGATION MODULE
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

        const logoutBtn = document.getElementById('logoutBtn');
        if (logoutBtn) {
            logoutBtn.addEventListener('click', function(e) {
                e.preventDefault(); 
                Swal.fire({
                    title: 'Log out?',
                    text: "Are you sure you want to Log out",
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#FF8C00', 
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

    // 3. IMPROVED DELETE HANDLER WITH SWEETALERT2
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

    // 4. PAYSLIP MODAL MODULE (Updated with Philippine MWE Standards & Kinsenas/Monthly Breakdown)
    function triggerPayslip(dbId) {
        const emp = allEmployees.find(e => e.id == dbId);
        if (!emp) return;

        // Monthly Base & Standard Allowances
        const monthlyBase = emp.role === 'Manager' ? 20000 : 15000;
        const monthlyAllowance = 1000; // Halimbawa ng Rice/Meal allowance
        const monthlyGross = monthlyBase + monthlyAllowance;

        // Mandatory Contributions & Tax (Monthly Breakdown)
        const monthlySSS = emp.role === 'Manager' ? 900 : 675;
        const monthlyPhilHealth = emp.role === 'Manager' ? 400 : 300;
        const monthlyPagibig = 200;
        const monthlyTax = 0.00; // MWE / Tax Exempt threshold compliance
        const monthlyDeductions = monthlySSS + monthlyPhilHealth + monthlyPagibig + monthlyTax;
        const monthlyNet = monthlyGross - monthlyDeductions;

        // Kinsenas (Hati sa dalawa: 1st Cut-off / 2nd Cut-off)
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

        // Dynamic Period Determination
        const now = new Date();
        const day = now.getDate();
        const monthNames = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
        const currentMonthYear = `${monthNames[now.getMonth()]} ${now.getFullYear()}`;
        const cutOffPeriod = day <= 15 ? `1st Cut-off (1–15, ${currentMonthYear})` : `2nd Cut-off (16–31, ${currentMonthYear})`;
        const payDateStr = now.toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' });

        document.getElementById('printArea').innerHTML = `
            <div class="border border-gray-300 p-6 bg-white rounded-xl text-gray-800 text-xs">
              <!-- Company Header -->
              <div class="text-center border-b pb-4 mb-4">
                <h3 class="font-black text-xl tracking-wide uppercase text-gray-900">PannaKoda Stores Inc.</h3>
                <p class="text-[11px] text-gray-500 font-medium">123 Business Corporate Center, Cavite, Philippines</p>
                <p class="text-[11px] text-gray-400 font-mono">TIN: 000-123-456-000</p>
                <div class="mt-2 inline-block bg-slate-100 text-slate-800 font-mono text-[11px] font-bold px-3 py-1 rounded">
                  PAYSLIP STATEMENT | ${cutOffPeriod}
                </div>
              </div>
              
              <!-- Employee & Payroll Information Grid -->
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

              <!-- Earnings & Deductions Breakdown Tables -->
              <div class="grid grid-cols-2 gap-6 items-start mb-4">
                <!-- Earnings Section -->
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

                <!-- Deductions Section -->
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

              <!-- Summary Reference Box (Monthly vs Kinsenas Reference) -->
              <div class="bg-slate-100 p-2.5 rounded-lg mb-4 text-[11px] grid grid-cols-2 gap-2 text-gray-600 border border-slate-200">
                <div><span class="font-semibold">Monthly Base Reference:</span> ₱${f(monthlyBase)}</div>
                <div><span class="font-semibold">Monthly Gross Reference:</span> ₱${f(monthlyGross)}</div>
                <div><span class="font-semibold">Monthly Total Deductions:</span> ₱${f(monthlyDeductions)}</div>
                <div><span class="font-semibold">Monthly Net Reference:</span> ₱${f(monthlyNet)}</div>
              </div>

              <!-- Net Pay Display Banner -->
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

    // 5. INTERFACE TAB SWITCHING
    document.getElementById('tabPersonal').addEventListener('click', function() {
      this.className = "px-4 py-2 rounded-lg text-sm transition-all flex items-center gap-2 bg-white text-gray-900 shadow-sm font-semibold";
      document.getElementById('tabPayroll').className = "px-4 py-2 rounded-lg text-sm transition-all flex items-center gap-2 text-gray-600 hover:text-gray-900";
      document.getElementById('personalTable').classList.remove('d-none');
      document.getElementById('payrollTable').classList.add('d-none');
    });

    document.getElementById('tabPayroll').addEventListener('click', function() {
      this.className = "px-4 py-2 rounded-lg text-sm transition-all flex items-center gap-2 bg-white text-gray-900 shadow-sm font-semibold";
      document.getElementById('tabPersonal').className = "px-4 py-2 rounded-lg text-sm transition-all flex items-center gap-2 text-gray-600 hover:text-gray-900";
      document.getElementById('payrollTable').classList.remove('d-none');
      document.getElementById('personalTable').classList.add('d-none');
    });

    document.getElementById('searchInput').addEventListener('input', renderTables);
    document.getElementById('btnPrintStatement').addEventListener('click', () => window.print());

    window.addEventListener('DOMContentLoaded', loadEmployees);
  </script>
</body>
</html>