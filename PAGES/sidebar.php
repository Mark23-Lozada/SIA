<?php
// Siguraduhing may session bago basahin ang role nang walang pwedeng mangyaring redirect o pagkawala ng data
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_role = isset($_SESSION['role']) ? strtolower(trim($_SESSION['role'])) : '';
$current_uri = $_SERVER['REQUEST_URI'];
$exact_current_page = basename($_SERVER['PHP_SELF']); 

// Dynamic path handling
$inFrontendFolder = (strpos($current_uri, 'FRONTEND') !== false);
$hrmsPrefix = $inFrontendFolder ? '../../PAGES/' : ''; 
$frontendPrefix = $inFrontendFolder ? '' : '../project-test1/FRONTEND/';
$logoutActionUrl = $hrmsPrefix . 'logout.php';
?>

<!-- AGAD NA SCRIPT PARA SA STATE BAGO MAG-RENDER ANG PAGE -->
<script>
    if (localStorage.getItem('sidebar_collapsed') === 'true') {
        document.documentElement.classList.add('sidebar-collapsed-mode', 'preload-no-transition');
    }
</script>

<style>
    :root {
        --sidebar-bg: #ffffff;
        --text-dark: #1f2937;
        --accent-coral: #ff6b4a; 
        --accent-glow: rgba(255, 107, 74, 0.2);
    }
    
    .nav-item-container {
        position: relative;
        perspective: 1000px;
    }
    
    .nav-link-pill {
        position: relative;
        transition: all 0.25s cubic-bezier(0.16, 1, 0.3, 1);
        backdrop-filter: blur(8px);
        color: #4b5563 !important; 
    }

    .nav-link-pill:not(.nav-link-active):hover {
        color: #ff6b4a !important;
        background: rgba(255, 107, 74, 0.1) !important;
        transform: translateX(3px);
        box-shadow: 0 4px 12px rgba(0, 0, 0, 0.05);
    }

    .nav-link-pill:not(.nav-link-active):hover i {
        color: #ff6b4a !important;
        transform: scale(1.12) rotate(4deg);
    }
  
    .nav-link-active {
        background: #ff6b4a!important; 
        color: white !important; 
        font-weight: 600;
        border-radius: 12px !important;
        margin-right: 4px;
        margin-left: 4px;
        box-shadow: 0 6px 20px rgba(0, 0, 0, 0.08), inset 0 1px 1px rgba(255, 255, 255, 0.9);
        transform: scale(1.01);
    }

    .nav-link-active i {
        color: white !important;
        filter: drop-shadow(0 2px 4px rgba(0,0,0,0.05));
    }

    .section-title { 
        font-size: 13px !important; 
        margin-top: 10px !important; 
        margin-bottom: 3px !important; 
        font-family: ui-monospace, monospace; 
        font-weight: 700 !important; 
        letter-spacing: 0.1em; 
        color: #ff6b4a !important; 
        text-transform: uppercase; 
        display: flex; 
        align-items: center; 
        gap: 5px; 
        padding-left: 4px; 
        opacity: 0.95; 
    }

    .section-title i { 
        font-size: 13px !important; 
        color: #ff6b4a; 
    }

    /* SIDEBAR HOVER ANIMATION & GLOW - White Background */
    #sidebar {
        width: 270px;
        min-width: 80px;
        max-width: 280px;
        height: 100vh;
        overflow: hidden !important;
        position: sticky;
        top: 0;
        will-change: width, box-shadow, border-color;
        transition: width 0.4s cubic-bezier(0.25, 1, 0.5, 1), background 0.4s ease, box-shadow 0.4s ease, border-color 0.4s ease;
        background: #ffffff !important;
        border-right: 1px solid rgba(229, 231, 235, 1);
        box-shadow: 15px 0 35px rgba(0, 0, 0, 0.04);
    }

    #sidebar:hover {
        border-right-color: rgba(209, 213, 219, 1);
        box-shadow: 20px 0 45px rgba(0, 0, 0, 0.08);
    }

    .preload-no-transition #sidebar {
        transition: none !important;
    }

    .sidebar-collapsed-mode #sidebar {
        width: 82px !important;
    }
    
    .sidebar-collapsed-mode #sidebarHeader {
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 8px !important;
        padding: 0 !important;
    }

    .sidebar-collapsed-mode .hide-on-collapse {
        opacity: 0;
        visibility: hidden;
        transition: opacity 0.2s ease;
        display: none !important;
    }

    .sidebar-collapsed-mode .section-title span,
    .sidebar-collapsed-mode .section-title i {
        display: none !important;
    }

    .sidebar-collapsed-mode .section-title {
        border-top: 1px solid rgba(229, 231, 235, 1);
        margin-top: 10px !important;
        padding-top: 0 !important;
        height: 2px;
        content: "";
    }

    .sidebar-collapsed-mode nav a {
        justify-content: center !important;
        padding-left: 0 !important;
        padding-right: 0 !important;
        margin-left: 6px !important;
        margin-right: 6px !important;
        border-radius: 10px !important;
    }

    .sidebar-collapsed-mode .nav-link-active {
        margin-left: 6px !important;
        margin-right: 6px !important;
    }

    @keyframes pulseGlow {
        0%, 100% { opacity: 0.6; transform: scale(1); }
        50% { opacity: 1; transform: scale(1.05); }
    }
    .logo-pulse {
        animation: pulseGlow 3s infinite ease-in-out;
    }
</style>

<div id="sidebar" class="text-gray-800 px-3 py-3.5 flex flex-col flex-shrink-0 z-30">
   
  <div id="sidebarHeader" class="flex items-center justify-between mb-3 px-2 flex-shrink-0 transition-all duration-300">
    <div class="flex items-center gap-2.5 overflow-hidden">
      <div class="w-9 h-9 rounded-xl flex items-center justify-center shadow-lg shadow-orange-950/10 flex-shrink-0 logo-pulse border border-orange-500/20" style="background: linear-gradient(135deg, #ff6b4a 0%, #fa4b2a 100%);">
         <img src="../LIBRARIES/5501d331-f1e5-4dcc-ab9b-8fd56a2b5ea5.png" alt="logo" class="w-7 h-7 rounded-lg object-cover">
      </div>
      <div class="hide-on-collapse whitespace-nowrap">
        <span class="font-bold text-l tracking-wider block text-gray-900 font-sans uppercase">Pannakoda</span>
        <span class="text-[10px] font-mono tracking-widest block font-semibold" style="color: #ff6b4a;">ENTERPRISE OS</span>
      </div>
    </div>
    <button type="button" id="sidebarToggleBtn" class="text-gray-600 hover:text-[#ff6b4a] hover:bg-gray-100 p-1.5 rounded-xl transition-all duration-200 flex-shrink-0 active:scale-95">
      <i class="bi bi-layout-sidebar-inset text-base"></i>
    </button>
  </div>
    
  <nav class="flex-grow overflow-hidden px-1 space-y-1">
    <style>
        .compact-link { font-size: 11.5px !important; padding-top: 6.5px !important; padding-bottom: 6.5px !important; padding-left: 10px !important; font-weight: 500; }
        .compact-link i { transition: transform 0.3s ease, color 0.3s ease; font-size: 14px; }
    </style>

    <?php
      function renderCompactLink($url, $label, $icon, $current) {
          $isActive = (basename($url) === $current);
          $activeClass = $isActive ? 'nav-link-active' : '';
          $iconColor = $isActive ? 'text-[#ff6b4a]' : 'text-gray-600 group-hover:text-[#ff6b4a]'; 
          
          echo "<div class='nav-item-container' title='$label'>
                  <a href='$url' class='compact-link nav-link-pill group flex items-center gap-2.5 rounded-xl transition-all duration-200 $activeClass'>
                    <i class='$icon $iconColor transition-transform'></i> 
                    <span class='truncate tracking-tight hide-on-collapse'>$label</span>
                  </a>
                </div>";
      }

      if ($current_role === 'admin'): 
    ?>
        <div class="section-title">
          <i class="bi bi-shield-lock-fill"></i> <span class="hide-on-collapse">Authorization</span>
        </div>
        <?php
          renderCompactLink($hrmsPrefix . 'admin_home.php', 'Admin Home', 'bi bi-house-door-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'budget_list.php', 'Company Budget', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'all_sales.php', 'Sale Dashboard', 'bi bi-grid-1x2-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'employee.php', 'Employee Management', 'bi bi-people-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'profit_and_loss.php', 'Profit & Loss', 'bi bi-graph-up-arrow', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'branches_management.php', 'Add Branch', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'branch_map.php', 'Branch Map', 'bi bi-map-fill', $exact_current_page);
        ?>
        <div class="section-title">
          <i class="bi bi-sliders"></i> <span class="hide-on-collapse">System Control</span>
        </div>
        <?php
          renderCompactLink($hrmsPrefix . 'admin_budget_approve.php', 'Budget Approval', 'bi bi-cash-stack', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'admin_applicants.php', 'Applicants Management', 'bi bi-person-badge-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'admin_leaves.php', 'Leave Management', 'bi bi-calendar-check-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'admin_cash_advance.php', 'Cash Advance', 'bi bi-cash-coin', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'admin_promotion.php', 'Promotion Management', 'bi bi-award-fill', $exact_current_page);
        ?>
    <?php 
      endif; 

      if ($current_role === 'hr'): 
    ?>
        <div class="section-title">
          <i class="bi bi-people-fill"></i> <span class="hide-on-collapse">Human Resources</span>
        </div>
        <?php
          renderCompactLink($hrmsPrefix . 'dashboard.php', 'Dashboard', 'bi bi-grid-1x2-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'recruitment.php', 'Recruitment', 'bi bi-file-earmark-person-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'employee.php', 'Employees', 'bi bi-people-fill', $exact_current_page);
        ?>
        <div class="section-title">
          <i class="bi bi-clipboard2-check-fill"></i> <span class="hide-on-collapse">HR Compliance</span>
        </div>
        <?php
          renderCompactLink($hrmsPrefix . 'Applicant.php', 'HR Applicants', 'bi bi-person-vcard-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'leave.php', 'HR Leaves', 'bi bi-list-task', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'attendance.php', 'Attendance', 'bi bi-clock-history', $exact_current_page);
        ?>
    <?php 
      endif; 

      if ($current_role === 'manager'): 
    ?>
        <div class="section-title">
          <i class="bi bi-bar-chart-fill"></i> <span class="hide-on-collapse">Inventory Analytics</span>
        </div>
        <?php
          renderCompactLink($frontendPrefix . 'history.php', 'Sales', 'bi bi-bar-chart-line-fill', $exact_current_page);
          renderCompactLink($frontendPrefix . 'sales_day.php', 'Daily Sales', 'bi bi-graph-up-arrow', $exact_current_page);
        ?>
        <div class="section-title">
          <i class="bi bi-cpu-fill"></i> <span class="hide-on-collapse">Plant Operations</span>
        </div>
        <?php
          renderCompactLink($frontendPrefix . 'cooking.php', 'Cooking', 'bi bi-egg-fried', $exact_current_page);
          renderCompactLink($frontendPrefix . 'depart.php', 'Departs', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($frontendPrefix . 'recipe.php', 'Recipes', 'bi bi-journal-text', $exact_current_page);
          renderCompactLink($frontendPrefix . 'add_item.php', 'Add Item', 'bi bi-plus-circle-fill', $exact_current_page);
          renderCompactLink($frontendPrefix . 'items.php', 'Manage Items', 'bi bi-grid-3x3-gap-fill', $exact_current_page);
          renderCompactLink($frontendPrefix . 'inventory.php', 'Refill', 'bi bi-arrow-repeat', $exact_current_page);
          renderCompactLink($frontendPrefix . 'ingredients.php', 'Ingredients', 'bi bi-basket-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'manager_promotion.php', 'Employee Promotion', 'bi bi-award-fill', $exact_current_page);
        ?>
    <?php 
      endif; 

      if ($current_role === 'finance'): 
    ?>
        <div class="section-title">
          <i class="bi bi-wallet2"></i> <span class="hide-on-collapse">Financial Ledgers</span>
        </div>
        <?php
          renderCompactLink($hrmsPrefix . 'all_sales.php', 'Sale Dashboard', 'bi bi-grid-1x2-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'budget_list.php', 'Company Budget', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'salary_deduction.php', 'Salary Deductions', 'bi bi-dash-circle', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'profit_and_loss.php', 'Profit & Loss', 'bi bi-graph-up-arrow', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'transaction.php', 'Transaction', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'tax.php', 'Tax Management', 'bi bi-diagram-3-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'employee_salary.php' , 'Employee Salary', 'bi bi-cash-coin', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'attendance.php', 'Attendance', 'bi bi-clock-history', $exact_current_page);
        ?>
        <div class="section-title">
          <i class="bi bi-file-earmark-medical-fill"></i> <span class="hide-on-collapse">Financial Review</span>
        </div>
        <?php
          renderCompactLink($hrmsPrefix . 'finance_promotion.php', 'Promotion Review', 'bi bi-award-fill', $exact_current_page);
          renderCompactLink($hrmsPrefix . 'finance_cash_review.php', 'Cash Advance Review', 'bi bi-cash-coin', $exact_current_page);
        ?>
    <?php endif; ?>
  </nav>

  <div class="pt-2 mt-auto border-t border-gray-200 flex-shrink-0">
    <button type="button" id="sidebarLogoutBtn" title="Log out" class="w-full flex items-center gap-2.5 px-3 py-2 rounded-xl text-red-600 hover:text-white hover:bg-red-500 transition-all duration-200 text-xs group border border-transparent hover:border-red-600 active:scale-95 shadow-sm">
      <i class="bi bi-box-arrow-right text-sm text-red-500 group-hover:text-white transition-transform group-hover:-translate-x-0.5"></i> 
      <span class="font-mono hide-on-collapse font-semibold tracking-wide">Sign Out</span>
    </button>
  </div>
</div>

<script>
document.addEventListener("DOMContentLoaded", function () {
    setTimeout(function () {
        document.documentElement.classList.remove('preload-no-transition');
    }, 50);

    const sidebarToggleBtn = document.getElementById('sidebarToggleBtn');
    if (sidebarToggleBtn) {
        sidebarToggleBtn.addEventListener('click', function() {
            document.documentElement.classList.toggle('sidebar-collapsed-mode');
            if (document.documentElement.classList.contains('sidebar-collapsed-mode')) {
                localStorage.setItem('sidebar_collapsed', 'true');
            } else {
                localStorage.setItem('sidebar_collapsed', 'false');
            }
        });
    }

    document.addEventListener('click', function(e) {
        const logoutBtn = e.target.closest('#sidebarLogoutBtn, .logout-btn, a[href*="logout.php"]');
        
        if (logoutBtn) {
            e.preventDefault();
            e.stopPropagation();
            if (typeof e.stopImmediatePropagation === 'function') {
                e.stopImmediatePropagation();
            }

            const logoutUrl = logoutBtn.getAttribute('href') || "<?php echo $logoutActionUrl; ?>";
            
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
                    background: '#ffffff',
                    color: '#1f2937',
                    customClass: {
                        popup: 'rounded-2xl border border-gray-200 shadow-2xl backdrop-blur-xl'
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
});
</script>