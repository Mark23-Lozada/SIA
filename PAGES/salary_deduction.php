<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$current_role = strtolower($_SESSION['role']);
if ($current_role !== 'admin' && $current_role !== 'hr' && $current_role !== 'finance') {
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

// 1. FETCH EMPLOYEES ENDPOINT FOR FINANCE DEDUCTIONS
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Finance Department - Salary & Statutory Deductions</title>
  
  <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
  <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
  <script src="../LIBRARIES/tailwind.js"></script> 
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body class="bg-[#f8fafc] font-sans antialiased h-screen overflow-hidden">

  <div class="flex h-screen w-full overflow-hidden">
    
    <?php include 'sidebar.php'; ?>
    <div class="flex-1 h-screen overflow-y-auto p-8 min-w-0">
      
      <!-- MODERN DYNAMIC BANNER HEADER -->
      <div class="relative overflow-hidden bg-gradient-to-r from-purple-900 via-indigo-950 to-slate-900 rounded-3xl shadow-lg p-8 mb-8 text-white border border-white/10">
        <!-- Background Glow FX -->
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-purple-600/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute left-1/3 -top-20 w-48 h-48 bg-indigo-600/15 rounded-full blur-2xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md border border-white/15 text-xs font-semibold uppercase tracking-wider text-purple-200 mb-3">
              <i class="bi bi-shield-lock-fill"></i> Finance & Payroll Engine
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white mb-2">Salary & Statutory Deductions</h1>
            <p class="text-sm text-purple-100/80 max-w-2xl leading-relaxed">
              Real-time computation of SSS, PhilHealth, Pag-IBIG, and Withholding Tax deductions mapped dynamically across active employee files.
            </p>
          </div>

          <!-- Quick Action / Summary Indicator Pill -->
          <div class="bg-white/10 backdrop-blur-md border border-white/15 px-5 py-3 rounded-2xl flex items-center gap-4 shrink-0 shadow-inner">
            <div class="w-10 h-10 rounded-xl bg-purple-500/30 flex items-center justify-center text-purple-300">
              <i class="bi bi-receipt text-xl"></i>
            </div>
            <div>
              <span class="block text-xs text-purple-200 font-medium">Active Payroll Registry</span>
              <span id="activeCountBadge" class="text-lg font-bold text-white">Loading...</span>
            </div>
          </div>
        </div>
      </div>

      <!-- SEARCH FILTER TOOLBAR -->
      <div class="mb-6 flex items-center justify-between gap-4 bg-white p-4 rounded-2xl shadow-sm border border-gray-100">
        <div class="relative flex-1 max-w-lg">
          <span class="absolute inset-y-0 left-0 flex items-center pl-4 pointer-events-none text-gray-400">
            <i class="bi bi-search"></i>
          </span>
          <input id="searchInput" type="text" class="w-full pl-11 pr-4 py-2.5 rounded-xl text-sm border border-gray-200 focus:outline-none focus:border-purple-600 focus:ring-2 focus:ring-purple-600/20 bg-gray-50/50 transition-all" placeholder="Search by ID, name, department, or role...">
        </div>
        <div class="text-xs text-gray-400 hidden sm:block font-medium px-2">
          <i class="bi bi-info-circle mr-1"></i> Automatic statutory bracket mapping active
        </div>
      </div>

      <!-- MAIN CONTENT TABLE CONTAINER -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 overflow-hidden">
        <div class="table-responsive bg-white rounded-xl overflow-x-auto">
          
          <!-- FINANCE DEDUCTIONS TABLE -->
          <table id="financeDeductionTable" class="table table-hover align-middle mb-0 text-sm">
            <thead>
              <tr>
                <th class="py-3.5 px-4 bg-purple-950 text-white font-semibold border-0">Employee ID</th>
                <th class="py-3.5 px-4 bg-purple-950 text-white font-semibold border-0">Full Name</th>
                <th class="py-3.5 px-4 bg-purple-950 text-white font-semibold border-0">Department</th>
                <th class="py-3.5 px-4 bg-purple-950 text-white font-semibold border-0 text-end">Base Salary</th>
                <th class="py-3.5 px-4 bg-purple-950 text-white font-semibold border-0 text-end">SSS (4.5%)</th>
                <th class="py-3.5 px-4 bg-purple-950 text-white font-semibold border-0 text-end">PhilHealth</th>
                <th class="py-3.5 px-4 bg-purple-950 text-white font-semibold border-0 text-end">Pag-IBIG</th>
                <th class="py-3.5 px-4 bg-purple-950 text-white font-semibold border-0 text-end">Withholding Tax</th>
                <th class="py-3.5 px-4 bg-purple-950 text-white font-semibold border-0 text-end">Total Deductions</th>
              </tr>
            </thead>
            <tbody id="financeDeductionBody"></tbody>
          </table>

        </div>
      </div>
    </div>

  </div>

  <script src="../LIBRARIES/bootstrap.bundle.min.js"></script>

  <script>
    let allEmployees = [];

    async function loadEmployees() {
      try {
        const response = await fetch(`${window.location.pathname}?action=fetch_employees`);
        allEmployees = response.ok ? await response.json() : [];
        renderFinanceTable();
      } catch (err) {
        console.error("Pipeline failure:", err);
      }
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

    function renderFinanceTable() {
      const query = document.getElementById('searchInput').value.toLowerCase().trim();
      
      const filtered = allEmployees.filter(emp => {
        const customId = String(emp.display_emp_id).toLowerCase();
        const name = String(emp.full_name || '').toLowerCase();
        const dept = String(emp.department || '').toLowerCase();
        const role = String(emp.role || '').toLowerCase();
        return customId.includes(query) || name.includes(query) || dept.includes(query) || role.includes(query);
      });

      const tbody = document.getElementById('financeDeductionBody');
      tbody.innerHTML = '';

      let count = 0;
      const f = (num) => num.toLocaleString('en-US', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

      filtered.forEach(emp => {
        if ((emp.status || '').toLowerCase() === 'hired') {
          count++;
          const baseSalary = emp.salary ? parseFloat(emp.salary) : (emp.role === 'Manager' ? 45000 : 22000);
          const deductions = calculatePHPayroll(baseSalary);

          const tr = document.createElement('tr');
          tr.className = "border-b border-gray-100 hover:bg-gray-50/50 transition-colors";
          tr.innerHTML = `
            <td class="py-3.5 px-4 font-mono font-semibold text-gray-700">${emp.display_emp_id || ''}</td>
            <td class="py-3.5 px-4 font-semibold text-gray-800">${emp.full_name || ''}</td>
            <td class="py-3.5 px-4 text-gray-600">${emp.department}</td>
            <td class="py-3.5 px-4 text-end font-bold text-gray-900">₱${f(baseSalary)}</td>
            <td class="py-3.5 px-4 text-end font-mono text-red-600">-₱${f(deductions.sss)}</td>
            <td class="py-3.5 px-4 text-end font-mono text-red-600">-₱${f(deductions.philhealth)}</td>
            <td class="py-3.5 px-4 text-end font-mono text-red-600">-₱${f(deductions.pagibig)}</td>
            <td class="py-3.5 px-4 text-end font-mono text-red-600">-₱${f(deductions.tax)}</td>
            <td class="py-3.5 px-4 text-end font-mono font-bold text-red-700 bg-red-50/40">-₱${f(deductions.totalDeductions)}</td>
          `;
          tbody.appendChild(tr);
        }
      });

      // Update badge count dynamically
      document.getElementById('activeCountBadge').innerText = `${count} Records`;

      if (count === 0) {
        tbody.innerHTML = `<tr><td colspan="9" class="text-center py-12 text-gray-400 italic">No hired operational records matched for finance deductions.</td></tr>`;
      }
    }

    document.getElementById('searchInput').addEventListener('input', renderFinanceTable);
    window.addEventListener('DOMContentLoaded', loadEmployees);
  </script>
</body>
</html>