<!-- MODERN SIDEBAR (White & Purple Theme) -->
<div class="w-64 bg-white text-zinc-700 flex flex-col justify-between border-r border-purple-100 shrink-0 shadow-sm">
    <div>
        <div class="p-6 border-b border-purple-100 bg-gradient-to-br from-purple-900 to-indigo-900 text-white">
            <h2 class="font-black text-lg tracking-wider flex items-center gap-2">
                <i class="bi bi-hexagon-fill text-purple-400"></i> PANNAKODA
            </h2>
            <p class="text-[10px] text-purple-200 uppercase mt-0.5 font-semibold tracking-widest">Employee Portal</p>
        </div>

        <nav class="p-4 space-y-1.5 text-xs font-semibold">
            <a href="info.php" class="flex items-center gap-3 px-4 py-3 rounded-xl bg-purple-600 text-white shadow-md shadow-purple-500/20 transition-all duration-200 hover:scale-[1.02]">
                <i class="bi bi-person-badge text-base"></i> My Profile
            </a>
            
            <button onclick="openAttendanceModal()" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-purple-50 hover:text-purple-700 text-zinc-600 transition-all text-left">
                <i class="bi bi-geo-alt-fill text-base text-emerald-500"></i> Geo Attendance
            </button>

            <button onclick="openLeaveModal()" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-purple-50 hover:text-purple-700 text-zinc-600 transition-all text-left">
                <i class="bi bi-calendar-plus text-base text-amber-500"></i> Request Leave
            </button>

            <button onclick="openAdvanceModal()" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-purple-50 hover:text-purple-700 text-zinc-600 transition-all text-left">
                <i class="bi bi-cash-stack text-base text-cyan-500"></i> Request Salary Advance
            </button>

            <button onclick="openPayslipModal()" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-purple-50 hover:text-purple-700 text-zinc-600 transition-all text-left">
                <i class="bi bi-wallet2 text-base text-indigo-500"></i> Payslip Statement
            </button>

            <!-- CHANGE PASSWORD BUTTON -->
            <button onclick="openChangePasswordModal()" class="w-full flex items-center gap-3 px-4 py-3 rounded-xl hover:bg-purple-50 hover:text-purple-700 text-zinc-600 transition-all text-left">
                <i class="bi bi-shield-lock text-base text-purple-500"></i> Change Password
            </button>
        </nav>
    </div>

    <div class="p-4 border-t border-purple-100 bg-purple-50/20">
        <a href="logout.php" id="logoutBtn" class="flex items-center gap-3 px-4 py-2.5 rounded-xl bg-rose-50 text-rose-600 hover:bg-rose-600 hover:text-white transition-all text-xs font-bold">
            <i class="bi bi-box-arrow-right text-base"></i> Logout
        </a>
    </div>
</div>