<?php
session_start();
if (!isset($_SESSION['role'])) {
    header("Location: login.php");
    exit();
}

$host = "localhost";
$user = "root"; 
$pass = ""; 
$dbname = "pos";

$conn = new mysqli($host, $user, $pass, $dbname);
if ($conn->connect_error) {
    die("Connection Failed: " . $conn->connect_error);
}

// Handle Action
if (isset($_GET['action']) && isset($_GET['id'])) {
    $id = intval($_GET['id']);
    $action = $_GET['action'];

    if ($action === 'reject') {
        // Kapag ni-reject ni finance, 'Rejected' na agad para diretso sa Transaction history
        $stmt = $conn->prepare("UPDATE salary_advances SET status = 'Rejected' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    } elseif ($action === 'approve') {
        // Kapag in-approve ni finance, ipapasa muna kay Admin ('Finance Approved')
        $stmt = $conn->prepare("UPDATE salary_advances SET status = 'Finance Approved' WHERE id = ?");
        $stmt->bind_param("i", $id);
        $stmt->execute();
        $stmt->close();
    }
    header("Location: finance_cash_review.php");
    exit();
}

$query = "SELECT sa.*, e.full_name, e.department FROM salary_advances sa JOIN employees e ON sa.employee_id = e.id WHERE sa.status = 'Pending' ORDER BY sa.created_at DESC";
$result = $conn->query($query);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Finance - Salary Advance Review</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
    <script src="../LIBRARIES/sweetalert2.all.min.js"></script>
</head>
<body class="bg-gray-50 text-gray-800 antialiased font-sans">
    <div class="flex h-screen w-full overflow-hidden">
        <div class="flex-shrink-0 h-full">
            <?php include 'sidebar.php'; ?>
        </div>
        <div class="flex-1 flex flex-col overflow-y-auto">
            <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between shrink-0">
                <h1 class="text-xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
                    <span class="w-3 h-3 bg-cyan-500 rounded-full"></span> Finance Cash Advance Review
                </h1>
            </header>
            <main class="p-6 space-y-6">
                <div class="bg-white border border-gray-200 rounded-xl shadow-sm overflow-hidden p-6">
                    <h3 class="text-sm font-bold text-gray-800 mb-4">Pending Salary Advance Requests</h3>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse text-sm">
                            <thead>
                                <tr class="border-b border-gray-200 text-xs font-semibold text-gray-500 uppercase bg-gray-50">
                                    <th class="py-3 px-4">Employee Name</th>
                                    <th class="py-3 px-4">Department</th>
                                    <th class="py-3 px-4">Amount</th>
                                    <th class="py-3 px-4">Reason</th>
                                    <th class="py-3 px-4 text-center">Action</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                <?php if ($result && $result->num_rows > 0): ?>
                                    <?php while ($row = $result->fetch_assoc()): ?>
                                        <tr class="hover:bg-gray-50">
                                            <td class="py-3 px-4 font-bold text-gray-900"><?= htmlspecialchars($row['full_name']) ?></td>
                                            <td class="py-3 px-4"><?= htmlspecialchars($row['department']) ?></td>
                                            <td class="py-3 px-4 font-mono font-bold text-emerald-600">₱<?= number_format($row['amount'], 2) ?></td>
                                            <td class="py-3 px-4 text-gray-600"><?= htmlspecialchars($row['reason']) ?></td>
                                            <td class="py-3 px-4 text-center space-x-2">
                                                <button onclick="confirmAction(<?= $row['id'] ?>, 'approve')" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-700 text-white rounded-lg text-xs font-bold shadow-sm">Approve to Admin</button>
                                                <button onclick="confirmAction(<?= $row['id'] ?>, 'reject')" class="px-3 py-1.5 bg-rose-600 hover:bg-rose-700 text-white rounded-lg text-xs font-bold shadow-sm">Reject</button>
                                            </td>
                                        </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr><td colspan="5" class="text-center py-8 text-gray-400 italic">No pending salary advance requests.</td></tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </main>
        </div>
    </div>
    <script>
        function confirmAction(id, action) {
            let titleText = action === 'approve' ? 'Proceed to Admin' : 'Reject Request?';
            Swal.fire({
                title: 'Confirmation',
                text: titleText,
                icon: 'warning',
                showCancelButton: true,
                confirmButtonColor: action === 'approve' ? '#059669' : '#e11d48',
                cancelButtonColor: '#6b7280',
                confirmButtonText: 'Yes'
            }).then((result) => {
                if (result.isConfirmed) {
                    window.location.href = `finance_cash_review.php?action=${action}&id=${id}`;
                }
            });
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>