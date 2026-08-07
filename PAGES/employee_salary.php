<?php
session_start();

if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$current_role = strtolower($_SESSION['role']);
// Pinapayagan ang admin at finance role na ma-access ang pahinang ito
if ($current_role !== 'admin' && $current_role !== 'finance') {
    header("Location: login.php"); 
    exit();
}

$host = "localhost";
$user = "root"; 
$pass = ""; 
$dbname = "pos";

$conn = new mysqli($host, $user, $pass, $dbname);

// 1. FETCH EMPLOYEES ENDPOINT PARA SA FINANCE
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

        // Dummy date para sa hiring graph kung walang created_at o date_hired sa DB
        if (!isset($row['date_hired']) || empty($row['date_hired'])) {
            $row['date_hired'] = date('Y-m-d'); 
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
  <title>Finance - Employee Salary & Payroll Management</title>
  
  <link href="../LIBRARIES/bootstrap.min.css" rel="stylesheet">
  <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
  <script src="../LIBRARIES/tailwind.js"></script> 
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <!-- Chart.js para sa Line Wave Graph -->
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    @media print {
      body * { visibility: hidden; }
      #printArea, #printArea * { visibility: visible; }
      #printArea { position: absolute; left: 0; top: 0; width: 100%; padding: 0; margin: 0; background: white !important; color: black !important; }
      .no-print { display: none !important; }
    }
  </style>
</head>
<body class="bg-[#f8fafc] font-sans antialiased h-screen overflow-hidden">

  <div class="flex h-screen w-full overflow-hidden">
    
    <?php include 'sidebar.php'; ?>
    <div class="flex-1 h-screen overflow-y-auto p-8 min-w-0">
      
      <!-- MODERN DYNAMIC BANNER HEADER -->
      <div class="relative overflow-hidden bg-gradient-to-r from-blue-900 via-indigo-900 to-slate-900 rounded-3xl shadow-lg p-8 mb-8 text-white border border-white/10">
        <!-- Background Glow FX -->
        <div class="absolute -right-10 -bottom-10 w-64 h-64 bg-blue-500/20 rounded-full blur-3xl pointer-events-none"></div>
        <div class="absolute left-1/3 -top-20 w-48 h-48 bg-indigo-500/15 rounded-full blur-2xl pointer-events-none"></div>

        <div class="relative z-10 flex flex-col md:flex-row justify-between items-start md:items-center gap-6">
          <div>
            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-white/10 backdrop-blur-md border border-white/15 text-xs font-semibold uppercase tracking-wider text-blue-200 mb-3">
              <i class="bi bi-cash-stack"></i> Finance Department
            </div>
            <h1 class="text-3xl font-extrabold tracking-tight text-white mb-2">Employee Salary & Payroll Management</h1>
            <p class="text-sm text-blue-100/80 max-w-2xl leading-relaxed">
              Monitor employee base salaries, statutory contributions, department metrics, and generate official corporate payslips seamlessly.
            </p>
          </div>

          <!-- Quick Action / Summary Indicator Pill -->
          <div class="flex items-center gap-3">
            <div class="bg-white/10 backdrop-blur-md border border-white/15 px-5 py-3 rounded-2xl flex items-center gap-4 shrink-0 shadow-inner">
              <div class="w-10 h-10 rounded-xl bg-blue-500/30 flex items-center justify-center text-blue-300">
                <i class="bi bi-people-fill text-xl"></i>
              </div>
              <div>
                <span class="block text-xs text-blue-200 font-medium">Total Workforce</span>
                <span id="activeCountBadge" class="text-lg font-bold text-white">Loading...</span>
              </div>
            </div>
          </div>
        </div>

        <!-- FINANCE TABS NAVIGATION (Integrated inside Header Footer area for clean modern layout) -->
        <div class="relative z-10 mt-6 pt-6 border-t border-white/10 flex flex-wrap justify-between items-center gap-4">
          <div class="text-xs text-blue-200/70 font-medium hidden sm:block">
            <i class="bi bi-sliders mr-1"></i> Switch active directory view below
          </div>
          <div class="bg-black/20 backdrop-blur-md p-1 rounded-xl flex gap-1 border border-white/10 ml-auto">
            <button id="tabPayroll" class="px-4 py-2 rounded-lg text-sm transition-all flex items-center gap-2 bg-white text-gray-900 shadow-sm font-semibold">
              <i class="bi bi-cash-stack text-emerald-600"></i> Payroll & Salary List
            </button>
            <button id="tabHiredList" class="px-4 py-2 rounded-lg text-sm transition-all flex items-center gap-2 text-blue-200 hover:text-white">
              <i class="bi bi-people text-[#FF8C00]"></i> Active Employees Directory
            </button>
          </div>
        </div>
      </div>

      <!-- METRIC CARDS SECTION (Ilang Empleyado, Onboarding, at Dept Breakdown) -->
      <div class="grid grid-cols-1 md:grid-cols-4 gap-4 mb-6">
        <!-- Total Active Employees -->
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl bg-blue-50 text-blue-600 flex items-center justify-center text-xl font-bold">
            <i class="bi bi-people-fill"></i>
          </div>
          <div>
            <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Total Employees</p>
            <h3 id="statTotalEmp" class="text-2xl font-black text-gray-800">0</h3>
          </div>
        </div>

        <!-- Onboarding Count -->
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 flex items-center gap-4">
          <div class="w-12 h-12 rounded-xl bg-amber-50 text-amber-600 flex items-center justify-center text-xl font-bold">
            <i class="bi bi-person-plus-fill"></i>
          </div>
          <div>
            <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider">Onboarding</p>
            <h3 id="statOnboarding" class="text-2xl font-black text-gray-800">0</h3>
          </div>
        </div>

        <!-- Department Breakdown Box -->
        <div class="bg-white p-4 rounded-2xl shadow-sm border border-gray-100 md:col-span-2 flex flex-col justify-between">
          <p class="text-xs text-gray-500 font-semibold uppercase tracking-wider mb-2">Employees per Department</p>
          <div id="deptBreakdownContainer" class="flex flex-wrap gap-2">
            <!-- Dynamic Department Badges lalabas dito -->
            <span class="text-xs text-gray-400 italic">Calculating breakdown...</span>
          </div>
        </div>
      </div>

      <!-- LINE WAVE GRAPH SECTION (Hiring Trends) -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 mb-6">
        <div class="flex justify-between items-center mb-4">
          <div>
            <h3 class="font-bold text-gray-800 text-base">Hiring Trend Analysis</h3>
            <p class="text-xs text-gray-500">Monthly overview of newly hired employees wave graph</p>
          </div>
        </div>
        <div class="relative h-48 w-full">
          <canvas id="hiringWaveChart"></canvas>
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
          
          <!-- 1. PAYROLL & SALARY TABLE -->
          <table id="payrollTable" class="table table-hover align-middle mb-0 text-sm">
            <thead class="table-dark">
              <tr>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Full Name</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Role</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Department</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">SSS No.</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">PhilHealth</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Pag-IBIG No.</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0 text-end">Base Salary</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0 text-center">Action</th>
              </tr>
            </thead>
            <tbody id="payrollBody"></tbody>
          </table>

          <!-- 2. ACTIVE EMPLOYEES DIRECTORY TABLE -->
          <table id="hiredListTable" class="table table-hover align-middle mb-0 text-sm d-none">
            <thead class="table-dark">
              <tr>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Employee ID</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Full Name</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Role</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Department</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0">Employment Type</th>
                <th class="py-3 px-4 bg-[#212121] text-white font-semibold border-0 text-center">Status</th>
              </tr>
            </thead>
            <tbody id="hiredListBody"></tbody>
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
              <i class="bi bi-receipt text-emerald-600"></i> Corporate Payroll Statement (PH Standards)
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
    let allEmployees = [];
    let bsModalInstance = null;
    let hiringChartInstance = null;

    async function loadEmployees() {
      try {
        const response = await fetch(`${window.location.pathname}?action=fetch_employees`);
        allEmployees = response.ok ? await response.json() : [];
        updateMetricsAndGraph();
        renderTables();
      } catch (err) {
        console.error("Pipeline failure:", err);
      }
    }

    function updateMetricsAndGraph() {
      const totalEmp = allEmployees.length;
      let onboardingCount = 0;
      let deptCounts = {};
      let monthlyHires = { 'Jan': 0, 'Feb': 0, 'Mar': 0, 'Apr': 0, 'May': 0, 'Jun': 0, 'Jul': 0, 'Aug': 0, 'Sep': 0, 'Oct': 0, 'Nov': 0, 'Dec': 0 };
      const monthKeys = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];

      allEmployees.forEach(emp => {
        // Status count
        if (String(emp.status).toLowerCase() === 'onboarding') {
          onboardingCount++;
        }

        // Department count
        const dept = emp.department || 'Unassigned';
        deptCounts[dept] = (deptCounts[dept] || 0) + 1;

        // Monthly Hires graph count
        if (emp.date_hired) {
          const d = new Date(emp.date_hired);
          if (!isNaN(d.getTime())) {
            const mStr = monthKeys[d.getMonth()];
            monthlyHires[mStr] = (monthlyHires[mStr] || 0) + 1;
          }
        }
      });

      // Update Metric Values UI
      document.getElementById('statTotalEmp').innerText = totalEmp;
      document.getElementById('statOnboarding').innerText = onboardingCount;
      document.getElementById('activeCountBadge').innerText = `${totalEmp} Active`;

      // Update Department Breakdown Badges
      const deptContainer = document.getElementById('deptBreakdownContainer');
      deptContainer.innerHTML = '';
      if (Object.keys(deptCounts).length === 0) {
        deptContainer.innerHTML = `<span class="text-xs text-gray-400 italic">No department data.</span>`;
      } else {
        for (const [dept, count] of Object.entries(deptCounts)) {
          const badge = document.createElement('div');
          badge.className = "bg-slate-100 border border-slate-200 text-gray-700 px-3 py-1 rounded-lg text-xs font-semibold flex items-center gap-1.5";
          badge.innerHTML = `<span>${dept}:</span> <span class="bg-indigo-600 text-white px-1.5 py-0.5 rounded-full text-[10px]">${count}</span>`;
          deptContainer.appendChild(badge);
        }
      }

      // Render Line Wave Graph
      renderHiringWaveGraph(monthKeys, Object.values(monthlyHires));
    }

    function renderHiringWaveGraph(labels, dataValues) {
      const ctx = document.getElementById('hiringWaveChart').getContext('2d');
      
      if (hiringChartInstance) {
        hiringChartInstance.destroy();
      }

      hiringChartInstance = new Chart(ctx, {
        type: 'line',
        data: {
          labels: labels,
          datasets: [{
            label: 'Hired Employees Wave',
            data: dataValues,
            borderColor: '#FF8C00',
            backgroundColor: 'rgba(255, 140, 0, 0.1)',
            borderWidth: 3,
            tension: 0.4, // Nagbibigay ng wave effect
            fill: true,
            pointBackgroundColor: '#FF8C00',
            pointRadius: 4
          }]
        },
        options: {
          responsive: true,
          maintainAspectRatio: false,
          plugins: {
            legend: { display: false }
          },
          scales: {
            y: {
              beginAtZero: true,
              ticks: { precision: 0 },
              grid: { color: '#f1f5f9' }
            },
            x: {
              grid: { display: false }
            }
          }
        }
      });
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

      const payrollBody = document.getElementById('payrollBody');
      const hiredListBody = document.getElementById('hiredListBody');

      payrollBody.innerHTML = '';
      hiredListBody.innerHTML = '';

      let payrollCount = 0;
      let hiredCount = 0;

      filtered.forEach(emp => {
        const baseSalary = emp.salary ? parseFloat(emp.salary) : (emp.role === 'Manager' ? 45000 : 22000);
        const roleClass = emp.role === 'Manager' ? 'bg-amber-50 text-amber-700 border-amber-200' : 'bg-blue-50 text-blue-700 border-blue-200';

        // 1. Payroll Profile Table Row
        payrollCount++;
        const trPayroll = document.createElement('tr');
        trPayroll.className = "border-b border-gray-100 hover:bg-gray-50/50 transition-colors";
        trPayroll.innerHTML = `
          <td class="py-3 px-4 font-semibold text-gray-800">${emp.full_name || ''}</td>
          <td class="py-3 px-4"><span class="${roleClass} px-2.5 py-1 rounded-md text-xs font-semibold border">${emp.role}</span></td>
          <td class="py-3 px-4 text-gray-600">${emp.department}</td>
          <td class="py-3 px-4 text-gray-600 font-mono">${emp.sss_id || '33-1234567-8'}</td>
          <td class="py-3 px-4 text-gray-600 font-mono">${emp.philhealth_id || '12-345678901-2'}</td>
          <td class="py-3 px-4 text-gray-600 font-mono">${emp.pagibig_id || '1210-9876-5432'}</td>
          <td class="py-3 px-4 text-end font-bold text-gray-900">₱${baseSalary.toLocaleString('en-US', {minimumFractionDigits:2})}</td>
          <td class="py-3 px-4 text-center">
            <button onclick="triggerPayslip(${emp.id})" class="btn btn-sm btn-success py-1 px-2.5 text-xs font-semibold rounded-lg flex items-center gap-1 mx-auto">
              <i class="bi bi-file-earmark-spreadsheet"></i> Payslip
            </button>
          </td>
        `;
        payrollBody.appendChild(trPayroll);

        // 2. Hired Directory Table Row
        hiredCount++;
        const trHired = document.createElement('tr');
        trHired.className = "border-b border-gray-100 hover:bg-gray-50/50 transition-colors";
        trHired.innerHTML = `
          <td class="py-3 px-4 font-mono font-semibold text-gray-700">${emp.display_emp_id || ''}</td>
          <td class="py-3 px-4 font-semibold text-gray-800">${emp.full_name || ''}</td>
          <td class="py-3 px-4"><span class="${roleClass} px-2.5 py-1 rounded-md text-xs font-semibold border">${emp.role}</span></td>
          <td class="py-3 px-4 text-gray-600">${emp.department}</td>
          <td class="py-3 px-4 text-gray-600">${emp.employment_type || 'Regular'}</td>
          <td class="py-3 px-4 text-center"><span class="bg-emerald-50 text-emerald-700 border-emerald-200 px-2.5 py-1 rounded-md text-xs font-semibold border uppercase">${emp.status || 'Active'}</span></td>
        `;
        hiredListBody.appendChild(trHired);
      });

      if (payrollCount === 0) {
        payrollBody.innerHTML = `<tr><td colspan="8" class="text-center py-8 text-gray-400 italic">No operational payroll records matched.</td></tr>`;
      }
      if (hiredCount === 0) {
        hiredListBody.innerHTML = `<tr><td colspan="6" class="text-center py-8 text-gray-400 italic">No employee records found.</td></tr>`;
      }
    }

    // Philippine Payroll & Statutory Computation Function
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
        <div class="border border-gray-300 p-6 bg-white rounded-xl text-gray-800 text-xs shadow-sm">
          <div class="text-center border-b pb-4 mb-4">
            <h3 class="font-black text-xl tracking-wide uppercase text-gray-900">${emp.company_name || 'PannaKoda Stores Inc.'}</h3>
            <p class="text-[11px] text-gray-500 font-medium">${emp.company_address || '123 Business Corporate Center, Cavite, Philippines'}</p>
            <p class="text-[11px] text-gray-400 font-mono">TIN: 000-123-456-000 &bull; SSS Employer No: 03-9876543-2</p>
            <div class="mt-2 inline-block bg-slate-100 text-slate-800 font-mono text-[11px] font-bold px-3 py-1 rounded">
              OFFICIAL PAYSLIP STATEMENT (FINANCE DEPT) | ${cutOffPeriod}
            </div>
          </div>
          
          <div class="grid grid-cols-2 gap-4 mb-4 border-b pb-4 bg-slate-50/60 p-3 rounded-lg">
             <div>
              <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Employee ID:</span> <span class="font-mono font-bold text-gray-800">${emp.display_emp_id}</span></p>
              <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Employee Name:</span> <span class="font-bold text-gray-800">${emp.full_name}</span></p>
              <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Department:</span> <span class="font-semibold text-gray-800">${emp.department}</span></p>
              <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Tax Status:</span> <span class="font-semibold text-gray-800">Single / S / Z</span></p>
             </div>
             <div>
              <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Position/Role:</span> <span class="font-bold text-gray-800">${emp.role}</span></p>
              <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Pay Date:</span> <span class="font-mono text-gray-800">${payDateStr}</span></p>
              <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Employment Type:</span> <span class="font-semibold text-indigo-600">${emp.employment_type || 'Regular'}</span></p>
              <p class="mb-1"><span class="text-gray-500 uppercase font-semibold">Statutory Ref:</span> <span class="font-mono text-gray-600 text-[10px]">SSS/PH/PAG-IBIG Compliant</span></p>
             </div>
          </div>

          <div class="grid grid-cols-2 gap-6 items-start mb-4">
            <div>
              <h6 class="font-bold text-xs text-gray-900 border-b pb-1.5 mb-2 uppercase tracking-wide">Earnings (Kinsenas Breakdown)</h6>
              <div class="space-y-1">
                <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                  <span class="text-gray-600">Basic Salary (Semi-Monthly)</span> 
                  <span class="font-semibold font-mono">₱${f(kinsenasBase)}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                  <span class="text-gray-600">Rice & Clothing Allowance</span> 
                  <span class="font-semibold font-mono">₱${f(kinsenasAllowance)}</span>
                </div>
                <div class="flex justify-between py-1.5 font-bold text-gray-900 bg-gray-50 px-2 rounded mt-1">
                  <span>Gross Pay (Period)</span> 
                  <span class="font-mono text-emerald-700">₱${f(kinsenasGross)}</span>
                </div>
              </div>
            </div>

            <div>
              <h6 class="font-bold text-xs text-gray-900 border-b pb-1.5 mb-2 uppercase tracking-wide">Statutory & Tax Deductions</h6>
              <div class="space-y-1">
                <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                  <span class="text-gray-600">SSS Contribution (Employee)</span> 
                  <span class="font-mono text-red-600">-₱${f(kinsenasSSS)}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                  <span class="text-gray-600">PhilHealth (Employee)</span> 
                  <span class="font-mono text-red-600">-₱${f(kinsenasPhilHealth)}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                  <span class="text-gray-600">Pag-IBIG Fund (Employee)</span> 
                  <span class="font-mono text-red-600">-₱${f(kinsenasPagibig)}</span>
                </div>
                <div class="flex justify-between py-1 border-b border-dashed border-gray-100">
                  <span class="text-gray-600">BIR Withholding Tax</span> 
                  <span class="font-mono text-red-600">-₱${f(kinsenasTax)}</span>
                </div>
                <div class="flex justify-between py-1.5 font-bold text-gray-900 bg-gray-50 px-2 rounded mt-1">
                  <span>Total Deductions</span> 
                  <span class="font-mono text-red-600">-₱${f(kinsenasDeductions)}</span>
                </div>
              </div>
            </div>
          </div>

          <div class="bg-slate-100 p-3 rounded-lg mb-4 text-[11px] grid grid-cols-2 gap-2 text-gray-700 border border-slate-200">
            <div><span class="font-semibold">Monthly Basic Salary:</span> ₱${f(monthlyBase)}</div>
            <div><span class="font-semibold">Monthly Gross Earnings:</span> ₱${f(monthlyGross)}</div>
            <div><span class="font-semibold">Monthly Total Statutory & Tax:</span> ₱${f(ph.totalDeductions)}</div>
            <div><span class="font-semibold">Monthly Net Pay Reference:</span> ₱${f(monthlyNet)}</div>
          </div>

          <div class="bg-[#212121] text-white p-4 rounded-xl flex justify-between items-center shadow-inner">
            <div>
              <h4 class="text-[10px] uppercase tracking-widest text-white/60">Net Pay for this Period</h4>
              <p class="text-[10px] text-white/40">Kinsenas Payout (15-Day Cycle)</p>
            </div>
            <div class="text-right">
              <h2 class="text-2xl font-black text-[#FF8C00] font-mono">₱${f(kinsenasNet)}</h2>
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
      const tabs = ['tabPayroll', 'tabHiredList'];
      const tables = ['payrollTable', 'hiredListTable'];

      tabs.forEach(id => {
        const btn = document.getElementById(id);
        if (id === activeBtnId) {
          btn.className = "px-4 py-2 rounded-lg text-sm transition-all flex items-center gap-2 bg-white text-gray-900 shadow-sm font-semibold";
        } else {
          btn.className = "px-4 py-2 rounded-lg text-sm transition-all flex items-center gap-2 text-blue-200 hover:text-white";
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

    document.getElementById('tabPayroll').addEventListener('click', () => switchTab('tabPayroll', 'payrollTable'));
    document.getElementById('tabHiredList').addEventListener('click', () => switchTab('tabHiredList', 'hiredListTable'));

    document.getElementById('searchInput').addEventListener('input', renderTables);
    document.getElementById('btnPrintStatement').addEventListener('click', () => window.print());

    window.addEventListener('DOMContentLoaded', loadEmployees);
  </script>
</body>
</html>