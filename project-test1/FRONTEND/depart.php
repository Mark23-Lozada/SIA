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
//   NOW() >= sales.ready_at AND served_at IS NULL  -> shows here (Ready)
//   served_at is set below when staff marks it served, which then
//   removes it from this list on the next refresh.
// ============================================================

// POST: mark an order as served/picked up
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['mark_served'])) {
    header('Content-Type: application/json');
    $sale_id = isset($_POST['sale_id']) ? (int)$_POST['sale_id'] : 0;
    if ($sale_id > 0) {
        $stmt = $conn->prepare("UPDATE sales SET served_at = NOW() WHERE id = ? AND served_at IS NULL");
        $stmt->bind_param("i", $sale_id);
        $stmt->execute();
        $stmt->close();
    }
    echo json_encode(["status" => "success"]);
    exit();
}

if (isset($_GET['ajax']) && $_GET['ajax'] == '1') {
    header('Content-Type: text/html');

    $orders_result = $conn->query(
        "SELECT id, ready_at
         FROM sales
         WHERE ready_at IS NOT NULL
           AND ready_at <= NOW()
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
        echo '
        <div class="col-span-full">
            <div class="text-center py-12 text-gray-400">
                <i class="bi bi-inbox text-5xl"></i>
                <p class="mt-3 text-lg font-medium">No orders ready right now.</p>
            </div>
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
        ?>
        <div class="w-full">
            <div class="bg-white border-2 border-orange-500 rounded-xl p-5 shadow-sm h-full flex flex-col justify-between transition hover:shadow-md">
                <div>
                    <div class="flex justify-between items-center mb-4">
                        <span class="font-bold text-orange-600 tracking-wide">#TXN-<?php echo str_pad($order['id'], 5, '0', STR_PAD_LEFT); ?></span>
                        <span class="inline-flex items-center gap-1 bg-orange-100 text-orange-800 text-xs font-semibold px-2.5 py-1 rounded-full border border-orange-200">
                            <i class="bi bi-check-circle-fill text-orange-500"></i> Ready
                        </span>
                    </div>
                    <ul class="text-sm text-gray-600 space-y-1 mb-5 pl-1 list-none">
                        <?php foreach ($item_lines as $line): ?>
                            <li class="flex items-center gap-2">
                                <span class="inline-block w-1.5 h-1.5 bg-orange-400 rounded-full"></span>
                                <?php echo $line; ?>
                            </li>
                        <?php endforeach; ?>
                    </ul>
                </div>
                <button type="button" class="w-full bg-orange-500 hover:bg-orange-600 text-white font-medium text-sm py-2 px-4 rounded-lg transition-colors flex items-center justify-center gap-2 shadow-sm mark-served-btn" data-sale-id="<?php echo $order['id']; ?>">
                    <i class="bi bi-box-arrow-right"></i> Mark as Served
                </button>
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
    <title>PannaKoda - Kitchen (Ready / Depart)</title>
    <script src="../LIBRARIES/tailwind.js"></script>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">
</head>

<body class="bg-gray-50 text-gray-800 antialiased font-sans">

    <div class="flex h-screen w-full overflow-hidden">
        
        <div class="flex-shrink-0 h-full">
            <?php include '../../PAGES/sidebar.php'; ?>
        </div>

        <div class="flex-1 flex flex-col overflow-y-auto">
            
            <header class="bg-white border-b border-gray-200 px-6 py-4 flex items-center justify-between">
                <h1 class="text-xl font-bold text-gray-900 tracking-tight flex items-center gap-2">
                    <span class="w-3 h-3 bg-orange-500 rounded-full animate-pulse"></span>
                    Ready for Departure
                </h1>
                <span class="text-xs font-medium text-gray-500 bg-gray-100 px-2.5 py-1 rounded-md">Kitchen Console</span>
            </header>

            <main class="p-6">
                <div id="orders-container" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
                    <div class="col-span-full text-center py-20 text-gray-400">
                        <div class="inline-block w-8 h-8 border-4 border-orange-500 border-t-transparent rounded-full animate-spin" role="status"></div>
                        <p class="mt-3 font-medium text-gray-500">Loading orders...</p>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <script>
        function fetchOrders() {
            fetch('depart.php?ajax=1')
                .then(res => res.text())
                .then(html => {
                    document.getElementById('orders-container').innerHTML = html;
                    attachServedHandlers();
                })
                .catch(err => console.error('Error fetching ready orders:', err));
        }

        function attachServedHandlers() {
            document.querySelectorAll('.mark-served-btn').forEach(function (btn) {
                btn.addEventListener('click', function () {
                    const saleId = this.getAttribute('data-sale-id');
                    const formData = new FormData();
                    formData.append('mark_served', '1');
                    formData.append('sale_id', saleId);

                    fetch('depart.php', { method: 'POST', body: formData })
                        .then(res => res.json())
                        .then(data => {
                            if (data.status === 'success') fetchOrders(); // refresh immediately
                        })
                        .catch(err => console.error('Error marking order served:', err));
                });
            });
        }

        // Initial fetch
        fetchOrders();
        // Regular 10 seconds cadence interval
        setInterval(fetchOrders, 10000);
    </script>
</body>
</html>
<?php $conn->close(); ?>