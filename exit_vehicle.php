<?php
// Include database config and start session
require_once 'config.php';

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$error_message = '';
$success_message = '';
$search_plate = '';
$ticket = null;
$amount_due = 0;
$duration_hours = 0;
$duration_text = '';

// Fetch all master slots from database configuration
$all_slots = [];
try {
    $all_slots = $pdo->query("SELECT slot_number FROM slots ORDER BY slot_number")->fetchAll(PDO::FETCH_COLUMN);
} catch (PDOException $e) {
    // Fail silently
}

// Fetch active check-ins to map slot details
$occupied_map = [];
try {
    $stmt = $pdo->query("SELECT * FROM tickets WHERE status = 'parked'");
    $active_tickets = $stmt->fetchAll();
    foreach ($active_tickets as $ticket_row) {
        $occupied_map[$ticket_row['slot_number']] = [
            'plate' => $ticket_row['plate_number'],
            'owner' => $ticket_row['owner_name'],
            'type' => $ticket_row['vehicle_type']
        ];
    }
} catch (PDOException $e) {
    // Fail silently
}

// Fetch settings for pricing rates
$rates = [];
try {
    $stmt = $pdo->query("SELECT key_name, val_value FROM settings");
    $rates = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    // Fail silently
}
$rate_2_wheel = (int)($rates['rate_2_wheel'] ?? 50);
$rate_4_wheel = (int)($rates['rate_4_wheel'] ?? 100);
$rate_truck_heavy = (int)($rates['rate_truck_heavy'] ?? 150);

// Step 1: Handle Search Submit
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['search_vehicle'])) {
    $search_plate = trim(filter_input(INPUT_POST, 'plate_number', FILTER_SANITIZE_SPECIAL_CHARS));
    
    if (empty($search_plate)) {
        $error_message = "Please enter a license plate or slot number.";
    } else {
        try {
            // Find active parked vehicle by plate or slot
            $stmt = $pdo->prepare("
                SELECT * FROM tickets 
                WHERE (plate_number = :search1 OR slot_number = :search2) 
                  AND status = 'parked' 
                LIMIT 1
            ");
            $stmt->execute([
                'search1' => strtoupper($search_plate),
                'search2' => strtoupper($search_plate)
            ]);
            $ticket = $stmt->fetch();
            
            if (!$ticket) {
                $error_message = "No active check-in found for license plate or slot number: " . htmlspecialchars($search_plate);
            } else {
                // Calculate parking duration and rates
                $in_time = new DateTime($ticket['check_in_time']);
                $out_time = new DateTime(); // Current time
                $interval = $in_time->diff($out_time);
                
                // Calculate total duration in hours (minimum 1 hour)
                $duration_seconds = $out_time->getTimestamp() - $in_time->getTimestamp();
                $duration_hours = ceil($duration_seconds / 3600);
                if ($duration_hours <= 0) {
                    $duration_hours = 1;
                }
                
                // Build human readable duration string
                $days = $interval->d;
                $hours = $interval->h + ($days * 24);
                $minutes = $interval->i;
                $duration_text = "{$hours} hr(s) {$minutes} min(s)";
                
                // Base rates on vehicle category:
                $rate_per_hour = $rate_4_wheel; // default
                if ($ticket['vehicle_type'] === '2-Wheel') {
                    $rate_per_hour = $rate_2_wheel;
                } elseif ($ticket['vehicle_type'] === 'Truck/Heavy') {
                    $rate_per_hour = $rate_truck_heavy;
                }
                
                $amount_due = $duration_hours * $rate_per_hour;
            }
        } catch (PDOException $e) {
            $error_message = "Database error: " . $e->getMessage();
        }
    }
}

// Step 2: Handle Checkout Confirmation
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_checkout'])) {
    $ticket_id = filter_input(INPUT_POST, 'ticket_id', FILTER_VALIDATE_INT);
    $final_amount = filter_input(INPUT_POST, 'amount_due', FILTER_VALIDATE_FLOAT);
    $payment_method = filter_input(INPUT_POST, 'payment_method', FILTER_SANITIZE_SPECIAL_CHARS);
    
    if (!$ticket_id || $final_amount === false || empty($payment_method)) {
        $error_message = "Invalid transaction parameters.";
    } else {
        try {
            // Update ticket in database
            $stmt = $pdo->prepare("
                UPDATE tickets 
                SET status = 'checked_out', check_out_time = NOW(), amount_paid = :amount, payment_method = :method
                WHERE id = :id AND status = 'parked'
            ");
            $stmt->execute([
                'amount' => $final_amount,
                'method' => $payment_method,
                'id' => $ticket_id
            ]);
            
            if ($stmt->rowCount() > 0) {
                // Fetch completed ticket for receipt generation
                $stmt = $pdo->prepare("SELECT * FROM tickets WHERE id = :id");
                $stmt->execute(['id' => $ticket_id]);
                $completed_ticket = $stmt->fetch();
                
                $success_message = "Checkout completed successfully!";
            } else {
                $error_message = "Failed to update record. The ticket might already be checked out.";
            }
        } catch (PDOException $e) {
            $error_message = "Database checkout error: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Exit Vehicle - ParkMaster</title>
    <!-- Stylesheet -->
    <link rel="stylesheet" href="style.css">
    <style>
        /* CSS print layout to hide everything except receipt block */
        @media print {
            body {
                background: white !important;
                color: black !important;
            }
            body::before, body::after, .sidebar, .page-header, .content-card:not(.printable-receipt), .alert {
                display: none !important;
            }
            .main-content {
                padding: 0 !important;
                margin: 0 !important;
                width: 100% !important;
            }
            .printable-receipt {
                border: 2px dashed #000 !important;
                box-shadow: none !important;
                background: white !important;
                color: black !important;
                width: 100% !important;
                max-width: 420px !important;
                margin: 0 auto !important;
                padding: 25px !important;
                border-radius: 8px !important;
            }
            .receipt-title {
                color: black !important;
            }
            .receipt-subtitle {
                color: #555 !important;
            }
            .receipt-value {
                color: black !important;
            }
            .receipt-label {
                color: #444 !important;
            }
            .receipt-total-val {
                color: black !important;
                font-size: 22px !important;
            }
            .receipt-barcode {
                color: black !important;
            }
            .receipt-barcode-txt, .receipt-thankyou {
                color: #444 !important;
            }
            .receipt-header-divider, .receipt-footer-divider {
                border-bottom-color: #000 !important;
                border-top-color: #000 !important;
            }
            .receipt-row-divider {
                border-bottom-color: #ddd !important;
            }
            .receipt-total-divider {
                border-top-color: #000 !important;
            }
            .print-actions {
                display: none !important;
            }
            .receipt-logo {
                box-shadow: none !important;
                border: 1px solid #333 !important;
                filter: grayscale(100%);
            }
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
                <h1 class="page-title">Exit Vehicle (Check-Out)</h1>
            </div>

            <!-- Alerts -->
            <?php if (!empty($success_message)): ?>
                <div class="alert alert-success" role="alert" style="margin-bottom: 25px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                    </svg>
                    <span><?php echo $success_message; ?></span>
                </div>
            <?php endif; ?>

            <?php if (!empty($error_message)): ?>
                <div class="alert alert-danger" role="alert" style="margin-bottom: 25px;">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0 1 18 0zm-9 3.75h.008v.008H12v-.008z" />
                    </svg>
                    <span><?php echo $error_message; ?></span>
                </div>
            <?php endif; ?>

            <!-- Completed Checkout Receipt Display -->
            <?php if (isset($completed_ticket) && $completed_ticket): ?>
                <div class="content-card printable-receipt" style="border: 2px dashed rgba(255, 255, 255, 0.15); max-width: 460px; margin: 0 auto; padding: 30px; border-radius: 16px; background: rgba(17, 19, 36, 0.65); backdrop-filter: blur(12px); box-shadow: 0 15px 35px rgba(0, 0, 0, 0.4);">
                    
                    <!-- Receipt Header -->
                    <div style="text-align: center; margin-bottom: 25px; border-bottom: 1px dashed rgba(255, 255, 255, 0.15); padding-bottom: 20px;" class="receipt-header-divider">
                        <img src="logo.jpg" alt="ParkMaster Logo" class="receipt-logo" style="width: 64px; height: 64px; border-radius: 14px; object-fit: cover; margin-bottom: 12px; box-shadow: 0 8px 16px rgba(0, 0, 0, 0.25); border: 2px solid rgba(255, 255, 255, 0.1);">
                        <h2 style="margin: 0; color: #fff; font-size: 22px; font-weight: 700; letter-spacing: 0.5px;" class="receipt-title">PARKMASTER</h2>
                        <span style="display: block; font-size: 11px; color: var(--text-muted); text-transform: uppercase; letter-spacing: 1.5px; margin-top: 4px;" class="receipt-subtitle">Official Parking Receipt</span>
                    </div>
                    
                    <!-- Receipt Details Grid -->
                    <div style="display: flex; flex-direction: column; gap: 12px; font-size: 14px; margin-bottom: 25px;">
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255, 255, 255, 0.08); padding-bottom: 8px;" class="receipt-row-divider">
                            <span style="color: var(--text-muted);" class="receipt-label">Ticket ID:</span>
                            <strong style="color: #fff;" class="receipt-value">#PM-<?php echo str_pad($completed_ticket['id'], 5, '0', STR_PAD_LEFT); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255, 255, 255, 0.08); padding-bottom: 8px;" class="receipt-row-divider">
                            <span style="color: var(--text-muted);" class="receipt-label">Owner Name:</span>
                            <strong style="color: #fff;" class="receipt-value"><?php echo htmlspecialchars($completed_ticket['owner_name']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255, 255, 255, 0.08); padding-bottom: 8px;" class="receipt-row-divider">
                            <span style="color: var(--text-muted);" class="receipt-label">License Plate:</span>
                            <strong style="color: #fff;" class="receipt-value"><?php echo htmlspecialchars($completed_ticket['plate_number']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255, 255, 255, 0.08); padding-bottom: 8px;" class="receipt-row-divider">
                            <span style="color: var(--text-muted);" class="receipt-label">Vehicle Type:</span>
                            <strong style="color: #fff;" class="receipt-value"><?php echo htmlspecialchars($completed_ticket['vehicle_type']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255, 255, 255, 0.08); padding-bottom: 8px;" class="receipt-row-divider">
                            <span style="color: var(--text-muted);" class="receipt-label">Slot Number:</span>
                            <strong style="color: #fff;" class="receipt-value"><?php echo htmlspecialchars($completed_ticket['slot_number']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255, 255, 255, 0.08); padding-bottom: 8px;" class="receipt-row-divider">
                            <span style="color: var(--text-muted);" class="receipt-label">Check-In Time:</span>
                            <strong style="color: #fff;" class="receipt-value"><?php echo htmlspecialchars($completed_ticket['check_in_time']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255, 255, 255, 0.08); padding-bottom: 8px;" class="receipt-row-divider">
                            <span style="color: var(--text-muted);" class="receipt-label">Check-Out Time:</span>
                            <strong style="color: #fff;" class="receipt-value"><?php echo htmlspecialchars($completed_ticket['check_out_time']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-bottom: 1px dashed rgba(255, 255, 255, 0.08); padding-bottom: 8px;" class="receipt-row-divider">
                            <span style="color: var(--text-muted);" class="receipt-label">Payment Mode:</span>
                            <strong style="color: #fff;" class="receipt-value"><?php echo htmlspecialchars($completed_ticket['payment_method']); ?></strong>
                        </div>
                        <div style="display: flex; justify-content: space-between; border-top: 2px dashed rgba(255, 255, 255, 0.15); padding-top: 15px; margin-top: 10px;" class="receipt-total-divider">
                            <span style="color: var(--text-muted); font-weight: 600;" class="receipt-label">Amount Paid:</span>
                            <strong style="color: var(--success); font-size: 22px; font-weight: 800;" class="receipt-total-val">₹<?php echo htmlspecialchars($completed_ticket['amount_paid']); ?></strong>
                        </div>
                    </div>

                    <!-- Receipt Barcode & Footer -->
                    <div style="text-align: center; margin-top: 20px; border-top: 1px dashed rgba(255, 255, 255, 0.15); padding-top: 15px;" class="receipt-footer-divider">
                        <div class="receipt-barcode" style="display: inline-flex; height: 35px; gap: 2px; opacity: 0.85; margin-bottom: 8px; color: #fff;">
                            <!-- Fake CSS Barcode -->
                            <div style="width: 3px; background: currentColor; height: 100%;"></div>
                            <div style="width: 1px; background: currentColor; height: 100%;"></div>
                            <div style="width: 2px; background: currentColor; height: 100%;"></div>
                            <div style="width: 1px; background: currentColor; height: 100%;"></div>
                            <div style="width: 4px; background: currentColor; height: 100%;"></div>
                            <div style="width: 1px; background: currentColor; height: 100%;"></div>
                            <div style="width: 2px; background: currentColor; height: 100%;"></div>
                            <div style="width: 3px; background: currentColor; height: 100%;"></div>
                            <div style="width: 1px; background: currentColor; height: 100%;"></div>
                            <div style="width: 2px; background: currentColor; height: 100%;"></div>
                            <div style="width: 1px; background: currentColor; height: 100%;"></div>
                            <div style="width: 3px; background: currentColor; height: 100%;"></div>
                            <div style="width: 4px; background: currentColor; height: 100%;"></div>
                            <div style="width: 1px; background: currentColor; height: 100%;"></div>
                            <div style="width: 2px; background: currentColor; height: 100%;"></div>
                            <div style="width: 3px; background: currentColor; height: 100%;"></div>
                        </div>
                        <div style="font-family: monospace; font-size: 11px; color: var(--text-muted); letter-spacing: 2px; margin-bottom: 15px;" class="receipt-barcode-txt">
                            *PM-<?php echo str_pad($completed_ticket['id'], 5, '0', STR_PAD_LEFT); ?>*
                        </div>
                        <p style="font-size: 11.5px; color: var(--text-muted); margin: 0; line-height: 1.4;" class="receipt-thankyou">
                            Thank you for parking with us!<br>
                            Please keep this invoice for your records.
                        </p>
                    </div>
                    
                    <div style="display: flex; gap: 12px; justify-content: center; margin-top: 30px;" class="print-actions">
                        <button onclick="window.print()" class="btn-primary" style="background: linear-gradient(135deg, var(--primary) 0%, #1e40af 100%);">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M6.72 13.821V7.5a3.75 3.75 0 0 1 7.5 0v6.321m-9 0H16.5m-9-3.75h9m-9 3.75h9M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h12.75A2.25 2.25 0 0 0 21 18.75V16.5" /></svg>
                            Print Receipt
                        </button>
                        <button id="btnDownload" class="btn-secondary">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M3 16.5v2.25A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75V16.5M16.5 12 12 16.5m0 0L7.5 12m4.5 4.5V3" /></svg>
                            Download TXT
                        </button>
                    </div>
                </div>

                <!-- Receipt Download JS Generator -->
                <script>
                    document.getElementById('btnDownload').addEventListener('click', function() {
                        const receiptText = `----------------------------------------
       PARKMASTER INVOICE RECEIPT
----------------------------------------
Ticket ID      : #PM-<?php echo str_pad($completed_ticket['id'], 5, '0', STR_PAD_LEFT); ?>\r\n
Owner Name     : <?php echo htmlspecialchars($completed_ticket['owner_name']); ?>\r\n
License Plate  : <?php echo htmlspecialchars($completed_ticket['plate_number']); ?>\r\n
Vehicle Type   : <?php echo htmlspecialchars($completed_ticket['vehicle_type']); ?>\r\n
Slot Number    : <?php echo htmlspecialchars($completed_ticket['slot_number']); ?>\r\n
Check-In Time  : <?php echo htmlspecialchars($completed_ticket['check_in_time']); ?>\r\n
Check-Out Time : <?php echo htmlspecialchars($completed_ticket['check_out_time']); ?>\r\n
Payment Mode   : <?php echo htmlspecialchars($completed_ticket['payment_method']); ?>\r\n
----------------------------------------
Total Paid     : INR <?php echo htmlspecialchars($completed_ticket['amount_paid']); ?>\r\n
----------------------------------------
   Thank you for parking with ParkMaster!
----------------------------------------`;
                        
                        const blob = new Blob([receiptText], { type: 'text/plain;charset=utf-8' });
                        const link = document.createElement('a');
                        link.href = URL.createObjectURL(blob);
                        link.download = 'invoice_PM_<?php echo $completed_ticket['id']; ?>.txt';
                        link.style.display = 'none';
                        document.body.appendChild(link);
                        link.click();
                        document.body.removeChild(link);
                    });
                </script>
            <?php else: ?>

                <!-- Form Search Check-In Card -->
                <div class="content-card">
                    <h2>Search Active Check-In</h2>
                    <p>Enter the license plate or assigned slot of the checked-in vehicle to calculate parking duration and compile the final billing amount based on category rates.</p>
                    <p style="font-size: 13.5px; color: var(--text-muted); margin-bottom: 20px;">
                        Rates: 2-Wheeler (<strong>₹<?php echo $rate_2_wheel; ?>/hr</strong>) | 4-Wheeler (<strong>₹<?php echo $rate_4_wheel; ?>/hr</strong>) | Trucks & Other (<strong>₹<?php echo $rate_truck_heavy; ?>/hr</strong>)
                    </p>
                    
                    <form method="POST" action="exit_vehicle.php">
                        <input type="hidden" name="search_vehicle" value="1">
                        <div class="form-grid" style="align-items: flex-end;">
                            <div class="form-group">
                                <label for="plate_number">License Plate or Slot Number</label>
                                <input type="text" id="plate_number" name="plate_number" class="form-control" placeholder="e.g. DL 3C AB 1234 or A105" value="<?php echo htmlspecialchars($search_plate); ?>" required autofocus>
                            </div>
                            
                            <div>
                                <button type="submit" class="btn-primary">
                                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.637 10.637Z" /></svg>
                                    Find & Calculate Bill
                                </button>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Ground Floor Slots Status Indicator -->
                <div class="content-card" style="margin-top: 25px;">
                    <h2>Active Slot Occupancy</h2>
                    <p style="font-size: 13.5px; color: var(--text-muted); margin-bottom: 20px;">
                        Click on an occupied (red) slot to automatically fill its details for check-out.
                    </p>
                    <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 12px;">
                        <?php foreach ($all_slots as $slot): ?>
                            <?php if (isset($occupied_map[$slot])): 
                                $info = $occupied_map[$slot];
                            ?>
                                <div onclick="selectSlot('<?php echo $slot; ?>')" style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 12px; padding: 15px 10px; text-align: center; cursor: pointer; transition: all 0.2s; border-top: 3px solid var(--danger);" onmouseover="this.style.background='rgba(239, 68, 68, 0.15)', this.style.borderColor='var(--danger)'" onmouseout="this.style.background='rgba(239, 68, 68, 0.08)', this.style.borderColor='rgba(239, 68, 68, 0.25)'">
                                    <span style="display: block; font-weight: 700; font-size: 14px; color: #fff; margin-bottom: 4px;"><?php echo $slot; ?></span>
                                    <span style="display: inline-block; font-size: 9px; font-weight: 600; text-transform: uppercase; padding: 2px 6px; border-radius: 4px; background: rgba(239, 68, 68, 0.1); color: var(--danger); margin-bottom: 6px;">Parked</span>
                                    <span style="display: block; font-size: 11px; color: #fff; font-weight: 600;"><?php echo htmlspecialchars($info['plate']); ?></span>
                                    <span style="display: block; font-size: 10px; color: var(--text-muted);">by <?php echo htmlspecialchars($info['owner']); ?></span>
                                </div>
                            <?php else: ?>
                                <div style="background: rgba(16, 185, 129, 0.03); border: 1px solid rgba(16, 185, 129, 0.15); border-radius: 12px; padding: 15px 10px; text-align: center; opacity: 0.6; border-top: 3px solid var(--success);">
                                    <span style="display: block; font-weight: 700; font-size: 14px; color: var(--text-muted); margin-bottom: 4px;"><?php echo $slot; ?></span>
                                    <span style="display: inline-block; font-size: 9px; font-weight: 600; text-transform: uppercase; padding: 2px 6px; border-radius: 4px; background: rgba(16, 185, 129, 0.1); color: var(--success); margin-bottom: 6px;">Empty</span>
                                    <span style="display: block; font-size: 11px; color: var(--text-muted); opacity: 0.5;">Available</span>
                                    <span style="display: block; font-size: 10px; visibility: hidden;">None</span>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Click-to-Fill Script -->
                <script>
                    function selectSlot(slotNumber) {
                        const plateInput = document.getElementById('plate_number');
                        plateInput.value = slotNumber;
                        plateInput.focus();
                        // Scroll search container into view smoothly
                        plateInput.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                </script>

                <!-- Billing Receipt Summary Pending Checkout Confirmation -->
                <?php if ($ticket): ?>
                    <div class="content-card" style="margin-top: 30px; border-color: var(--primary);">
                        <h2 style="color: var(--secondary);">🧾 Pending Checkout: <?php echo htmlspecialchars($ticket['plate_number']); ?></h2>
                        
                        <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 20px 0; font-size: 14.5px;">
                            <div>
                                <span style="color: var(--text-muted);">Owner:</span>
                                <strong style="color: #fff; display: block; margin-top: 4px;"><?php echo htmlspecialchars($ticket['owner_name']); ?></strong>
                            </div>
                            <div>
                                <span style="color: var(--text-muted);">Slot Assigned:</span>
                                <strong style="color: #fff; display: block; margin-top: 4px;"><?php echo htmlspecialchars($ticket['slot_number']); ?></strong>
                            </div>
                            <div>
                                <span style="color: var(--text-muted);">Category / Vehicle Type:</span>
                                <strong style="color: #fff; display: block; margin-top: 4px;"><?php echo htmlspecialchars($ticket['vehicle_type']); ?></strong>
                            </div>
                            <div>
                                <span style="color: var(--text-muted);">Entry Timestamp:</span>
                                <strong style="color: #fff; display: block; margin-top: 4px;"><?php echo htmlspecialchars($ticket['check_in_time']); ?></strong>
                            </div>
                            <div>
                                <span style="color: var(--text-muted);">Calculated Duration:</span>
                                <strong style="color: #fff; display: block; margin-top: 4px;"><?php echo $duration_text; ?> (charged as <?php echo $duration_hours; ?> hr)</strong>
                            </div>
                            <div>
                                <span style="color: var(--text-muted);">Total Charges Due:</span>
                                <strong style="color: var(--success); font-size: 22px; display: block; margin-top: 4px;">₹<?php echo $amount_due; ?></strong>
                            </div>
                        </div>
                        
                        <form method="POST" action="exit_vehicle.php">
                            <input type="hidden" name="confirm_checkout" value="1">
                            <input type="hidden" name="ticket_id" value="<?php echo $ticket['id']; ?>">
                            <input type="hidden" name="amount_due" value="<?php echo $amount_due; ?>">
                            
                            <div class="form-group" style="margin-bottom: 20px; max-width: 300px;">
                                <label for="payment_method" style="display: block; margin-bottom: 8px; font-weight: 500; color: var(--text-muted);">Payment Mode</label>
                                <select id="payment_method" name="payment_method" class="form-control" required style="background-color: #111322;">
                                    <option value="UPI">UPI (GPay/PhonePe)</option>
                                    <option value="CARD">Debit / Credit Card</option>
                                    <option value="CASH" selected>Cash</option>
                                </select>
                            </div>
                            
                            <button type="submit" class="btn-primary" style="background: linear-gradient(135deg, var(--success) 0%, #059669 100%);">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                                Process Payment & Release Slot
                            </button>
                        </form>
                    </div>
                <?php endif; ?>

            <?php endif; ?>
        </main>
    </div>

</body>
</html>
