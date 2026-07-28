<?php
session_start();

// 1. Siguraduhin muna na may naka-login na user
if (!isset($_SESSION['role'])) {
    header("Location: ../../PAGES/login.php");
    exit();
}

header("Cache-Control: no-store, no-cache, must-revalidate, max-age=0");
require_once __DIR__ . '/../BACKEND/db_inventory.php';

// ============================================================
// STATUS IS NEVER STORED -- it's computed live from timestamps:
//   NOW() < sales.ready_at AND served_at IS NULL  -> shows here (Cooking)
//   NOW() >= sales.ready_at                        -> moves to depart.php automatically
// No cron job needed: this page just re-queries on every refresh.
// ============================================================

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: text/html');

    $orders_result = $conn->query(
        "SELECT id, created_at, ready_at, TIMESTAMPDIFF(SECOND, NOW(), ready_at) AS seconds_remaining
         FROM sales
         WHERE ready_at IS NOT NULL
           AND ready_at > NOW()
           AND served_at IS NULL
         ORDER BY ready_at ASC"
    );

    $orders = [];
    if ($orders_result) {
        while ($row = $orders_result->fetch_assoc()) {
            $orders[] = $row;
        }
    }

    if (empty($orders)) {
        echo '<div class="col-span-full flex flex-col items-center justify-center py-12 text-gray-400">
                <i class="bi bi-cup-hot text-5xl text-orange-400"></i>
                <p class="mt-3 text-lg font-medium">No orders currently cooking.</p>
              </div>';
        exit();
    }

    $item_stmt = $conn->prepare(
        "SELECT items.item_name, sales_items.quantity
         FROM sales_items
         JOIN items ON sales_items.item_id = items.id
         WHERE sales_items.sale_id = ?"
    );

    foreach ($orders as $order) {
        $item_stmt->bind_param("i", $order['id']);
        $item_stmt->execute();
        $items_res = $item_stmt->get_result();
        $item_lines = [];
        while ($line = $items_res->fetch_assoc()) {
            $item_lines[] = htmlspecialchars($line['quantity'] . '× ' . $line['item_name']);
        }

        $seconds_remaining = max(0, (int)$order['seconds_remaining']);
        ?>
        <div class="bg-white border-2 border-orange-200 rounded-xl p-5 shadow-sm hover:shadow-md transition duration-200 flex flex-col justify-between">
            <div>
                <div class="flex justify-between items-center mb-4">
                    <span class="font-bold text-orange-600 text-lg">#TXN-<?php echo str_pad($order['id'], 5, '0', STR_PAD_LEFT); ?></span>
                    <span class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-sm font-semibold bg-orange-100 text-orange-800 countdown-badge" data-seconds="<?php echo $seconds_remaining; ?>">
                        <i class="bi bi-clock-history"></i> <span class="countdown-text">--:--</span>
                    </span>
                </div>
                <ul class="space-y-2 text-gray-700 font-medium">
                    <?php foreach ($item_lines as $line): ?>
                        <li class="flex items-center gap-2">
                            <span class="inline-block w-1.5 h-1.5 bg-orange-500 rounded-full"></span>
                            <?php echo $line; ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
            </div>
        </div>
        <?php
    }
    $item_stmt->close();
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>PannaKoda - Kitchen (Cooking)</title>
    <!-- Tailwind CSS CDN -->
    <script src="../LIBRARIES/tailwind.js"></script>
    <!-- Bootstrap Icons for Icons -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-orange-50/50 min-h-screen text-gray-800 font-sans antialiased">

    <div class="flex h-screen w-full overflow-hidden">
        <?php include '../../PAGES/sidebar.php'; ?>
        
        <!-- Main Content Area -->
        <main class="flex-1 overflow-y-auto p-6 lg:p-8">
            <div class="max-w-7xl mx-auto">
                
                <!-- Page Header -->
                <div class="mb-8 border-b border-orange-100 pb-4 flex justify-between items-center">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">Kitchen Display</h1>
                        <p class="text-sm text-gray-500 mt-1">Live cooking monitor</p>
                    </div>
                    <div class="flex items-center gap-2 text-sm bg-white px-3 py-1.5 rounded-lg shadow-sm border border-orange-100">
                        <span class="flex h-2 w-2 relative">
                          <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-orange-400 opacity-75"></span>
                          <span class="relative inline-flex rounded-full h-2 w-2 bg-orange-500"></span>
                        </span>
                        <span class="text-gray-600 font-medium">Auto-refreshing</span>
                    </div>
                </div>

                <!-- Orders Grid Container -->
                <div id="orders-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="col-span-full flex flex-col items-center justify-center py-12 text-gray-400">
                        <!-- Tailwind Spinner -->
                        <div class="animate-spin rounded-full h-10 w-10 border-4 border-orange-500 border-t-transparent"></div>
                        <p class="mt-3 font-medium">Loading orders...</p>
                    </div>
                </div>

            </div>
        </main>
    </div>

    <script>
        // Refresh the order list itself every 10s
        function fetchOrders() {
            fetch('cooking.php?ajax=1')
                .then(res => res.text())
                .then(html => {
                    document.getElementById('orders-container').innerHTML = html;
                })
                .catch(err => console.error('Error fetching kitchen orders:', err));
        }

        // Tick every countdown badge down once per second client-side
        function tickCountdowns() {
            document.querySelectorAll('.countdown-badge').forEach(function (badge) {
                let seconds = parseInt(badge.getAttribute('data-seconds'), 10);
                if (isNaN(seconds)) return;
                seconds = Math.max(0, seconds - 1);
                badge.setAttribute('data-seconds', seconds);

                const mins = Math.floor(seconds / 60);
                const secs = seconds % 60;
                const text = badge.querySelector('.countdown-text');
                if (text) text.textContent = mins + ':' + String(secs).padStart(2, '0');
            });
        }

        fetchOrders();
        setInterval(fetchOrders, 10000); // re-sync with the server every 10s
        setInterval(tickCountdowns, 1000); // smooth per-second countdown between syncs
    </script>
</body>
</html>
<?php $conn->close(); ?>