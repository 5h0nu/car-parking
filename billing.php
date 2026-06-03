<?php
// Include database config and start session
require_once 'config.php';

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

// Fetch checked out tickets to populate billing ledger
$transactions = [];
$error_message = '';
try {
    $stmt = $pdo->query("SELECT * FROM tickets WHERE status = 'checked_out' ORDER BY check_out_time DESC");
    $transactions = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Database query error: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Billing - ParkMaster</title>
    <!-- Stylesheet -->
    <link rel="stylesheet" href="style.css">
    <style>
        .table-responsive {
            overflow-x: auto;
            margin-top: 20px;
        }

        .billing-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 14.5px;
            text-align: left;
        }

        .billing-table th {
            padding: 15px;
            background: rgba(255, 255, 255, 0.03);
            border-bottom: 2px solid var(--card-border);
            color: #ffffff;
            font-weight: 600;
        }

        .billing-table td {
            padding: 15px;
            border-bottom: 1px solid var(--card-border);
            color: var(--text-muted);
        }

        .billing-table tr:hover td {
            color: var(--text-main);
            background: rgba(255, 255, 255, 0.01);
        }

        .status-badge {
            display: inline-block;
            font-size: 11px;
            font-weight: 600;
            text-transform: uppercase;
            padding: 4px 8px;
            border-radius: 6px;
            background: rgba(16, 185, 129, 0.1);
            color: var(--success);
        }
    </style>
</head>
<body>

    <div class="dashboard-layout">
        <!-- Sidebar Navigation -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Billing & Transactions</h1>
            </div>

            <!-- Error Alerts -->
            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert" style="margin-bottom: 25px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <span><?php echo htmlspecialchars($error_message); ?></span>
                </div>
            <?php endif; ?>

            <div class="content-card">
                <h2>Settled Transactions Ledger</h2>
                <p>Track historical invoices cleared during check-out procedures, listing total receipts gathered by the terminal.</p>
                
                <div class="table-responsive">
                    <table class="billing-table">
                        <thead>
                            <tr>
                                <th>Ticket ID</th>
                                <th>Owner Name</th>
                                <th>License Plate</th>
                                <th>Category</th>
                                <th>Check-In</th>
                                <th>Check-Out</th>
                                <th>Payment Mode</th>
                                <th>Amount Paid</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($transactions)): ?>
                                <tr>
                                    <td colspan="9" style="text-align: center; padding: 30px; color: var(--text-muted);">
                                        No settled billing transactions found. Run a check-out in the Exit Vehicle tab to create records.
                                    </td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($transactions as $txn): ?>
                                    <tr>
                                        <td style="color: var(--secondary); font-weight: 600;">
                                            #PM-<?php echo str_pad($txn['id'], 5, '0', STR_PAD_LEFT); ?>
                                        </td>
                                        <td style="color: #fff;"><?php echo htmlspecialchars($txn['owner_name']); ?></td>
                                        <td><?php echo htmlspecialchars($txn['plate_number']); ?></td>
                                        <td><?php echo htmlspecialchars($txn['vehicle_type']); ?></td>
                                        <td><?php echo htmlspecialchars($txn['check_in_time']); ?></td>
                                        <td><?php echo htmlspecialchars($txn['check_out_time']); ?></td>
                                        <td style="font-weight: 600; color: #fff;"><?php echo htmlspecialchars($txn['payment_method'] ?? 'CASH'); ?></td>
                                        <td style="color: var(--success); font-weight: 600;">₹<?php echo htmlspecialchars($txn['amount_paid']); ?></td>
                                        <td><span class="status-badge">Paid</span></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>

</body>
</html>
