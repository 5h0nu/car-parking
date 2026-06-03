<?php
// Include database config and start session
require_once 'config.php';

// Check if user is logged in, if not redirect to login page
if (!isset($_SESSION['user_id'])) {
    header("Location: login.php");
    exit;
}

$success_message = '';
$error_message = '';

// Fetch all master slots from database configuration
$all_slots_data = [];
try {
    $stmt = $pdo->query("SELECT slot_number, floor FROM slots ORDER BY slot_number");
    $all_slots_data = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $error_message = "Error fetching slots configuration: " . $e->getMessage();
}
$all_slots = array_keys($all_slots_data);

// Fetch settings for pricing rates
$rates = [];
try {
    $stmt = $pdo->query("SELECT key_name, val_value FROM settings");
    $rates = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    // Fail silently
}
$rate_2_wheel = $rates['rate_2_wheel'] ?? 50;
$rate_4_wheel = $rates['rate_4_wheel'] ?? 100;
$rate_truck_heavy = $rates['rate_truck_heavy'] ?? 150;

// Fetch occupied slots from database
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
    $error_message = "Error fetching slot statuses: " . $e->getMessage();
}
$occupied_slots = array_keys($occupied_map);

// Calculate available slots
$available_slots = array_diff($all_slots, $occupied_slots);

// Process check-in form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $owner_name = trim(filter_input(INPUT_POST, 'owner_name', FILTER_SANITIZE_SPECIAL_CHARS));
    $plate_number = trim(filter_input(INPUT_POST, 'plate_number', FILTER_SANITIZE_SPECIAL_CHARS));
    $vehicle_type = filter_input(INPUT_POST, 'vehicle_type', FILTER_SANITIZE_SPECIAL_CHARS);
    $slot_number = filter_input(INPUT_POST, 'slot_number', FILTER_SANITIZE_SPECIAL_CHARS);

    if (empty($owner_name) || empty($plate_number) || empty($vehicle_type) || empty($slot_number)) {
        $error_message = "All fields are required.";
    } elseif (!in_array($slot_number, $all_slots)) {
        $error_message = "Invalid slot selected.";
    } elseif (in_array($slot_number, $occupied_slots)) {
        $error_message = "Slot {$slot_number} is already occupied.";
    } else {
        try {
            // Save vehicle entry with current timestamp
            $stmt = $pdo->prepare("
                INSERT INTO tickets (owner_name, plate_number, vehicle_type, slot_number, check_in_time, status)
                VALUES (:owner_name, :plate_number, :vehicle_type, :slot_number, NOW(), 'parked')
            ");
            $stmt->execute([
                'owner_name' => $owner_name,
                'plate_number' => strtoupper($plate_number),
                'vehicle_type' => $vehicle_type,
                'slot_number' => $slot_number
            ]);
            
            $success_message = "Vehicle <strong>" . htmlspecialchars(strtoupper($plate_number)) . "</strong> checked in successfully to slot <strong>" . htmlspecialchars($slot_number) . "</strong>!";
            
            // Refresh list of occupied slots after check-in
            $stmt = $pdo->query("SELECT * FROM tickets WHERE status = 'parked'");
            $active_tickets = $stmt->fetchAll();
            $occupied_map = [];
            foreach ($active_tickets as $ticket_row) {
                $occupied_map[$ticket_row['slot_number']] = [
                    'plate' => $ticket_row['plate_number'],
                    'owner' => $ticket_row['owner_name'],
                    'type' => $ticket_row['vehicle_type']
                ];
            }
            $occupied_slots = array_keys($occupied_map);
            $available_slots = array_diff($all_slots, $occupied_slots);
        } catch (PDOException $e) {
            $error_message = "Database error: Failed to check-in vehicle. " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Enter Vehicle - ParkMaster</title>
    <!-- Stylesheet -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="dashboard-layout">
        <!-- Sidebar Navigation -->
        <?php include 'sidebar.php'; ?>

        <!-- Main Content -->
        <main class="main-content">
            <div class="page-header">
                <h1 class="page-title">Enter Vehicle</h1>
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

            <!-- Form Card -->
            <div class="content-card">
                <h2>Check-in New Vehicle</h2>
                <p>Fill out the form details below to register the check-in and assign an available ground floor slot (A101–A115).</p>
                
                <form method="POST" action="enter_vehicle.php" autocomplete="off">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="owner_name">Owner Name</label>
                            <input type="text" id="owner_name" name="owner_name" class="form-control" placeholder="e.g. John Doe" required>
                        </div>

                        <div class="form-group">
                            <label for="plate_number">License Plate Number</label>
                            <input type="text" id="plate_number" name="plate_number" class="form-control" placeholder="e.g. DL 3C AB 1234" required>
                        </div>
                        
                        <div class="form-group">
                            <label for="vehicle_type">Vehicle Category</label>
                            <select id="vehicle_type" name="vehicle_type" class="form-control" required style="background-color: #111322;">
                                <option value="4-Wheel">4-Wheeler (Car/SUV) - ₹<?php echo htmlspecialchars($rate_4_wheel); ?>/hr</option>
                                <option value="2-Wheel">2-Wheeler (Bike/Scooter) - ₹<?php echo htmlspecialchars($rate_2_wheel); ?>/hr</option>
                                <option value="Truck/Heavy">Truck / Heavy Vehicle - ₹<?php echo htmlspecialchars($rate_truck_heavy); ?>/hr</option>
                            </select>
                        </div>

                        <div class="form-group">
                            <label for="slot_number">Assign Slot</label>
                            <select id="slot_number" name="slot_number" class="form-control" required style="background-color: #111322;">
                                <?php if (empty($available_slots)): ?>
                                    <option value="" disabled selected>No Slots Available (Full)</option>
                                <?php else: ?>
                                    <option value="" disabled selected>Select Available Slot</option>
                                    <?php foreach ($available_slots as $slot): 
                                        $floor_info = $all_slots_data[$slot] ?? 'General';
                                    ?>
                                        <option value="<?php echo htmlspecialchars($slot); ?>"><?php echo htmlspecialchars($slot); ?> (<?php echo htmlspecialchars($floor_info); ?>)</option>
                                    <?php endforeach; ?>
                                <?php endif; ?>
                            </select>
                        </div>
                    </div>
                    
                    <div style="margin-top: 30px;">
                        <button type="submit" class="btn-primary" <?php echo empty($available_slots) ? 'disabled' : ''; ?>>
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v6m3-3H9m12 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" /></svg>
                            Check-In Vehicle
                        </button>
                    </div>
                </form>
            </div>

            <!-- Ground Floor Slots Status Indicator -->
            <div class="content-card" style="margin-top: 25px;">
                <h2>Ground Floor Slots Status Map</h2>
                <p style="font-size: 13.5px; color: var(--text-muted); margin-bottom: 20px;">
                    Click on an available (green) slot to automatically select it in the form dropdown above.
                </p>
                <div style="display: grid; grid-template-columns: repeat(auto-fill, minmax(130px, 1fr)); gap: 12px;">
                    <?php foreach ($all_slots as $slot): ?>
                        <?php if (isset($occupied_map[$slot])): 
                            $info = $occupied_map[$slot];
                        ?>
                            <div style="background: rgba(239, 68, 68, 0.08); border: 1px solid rgba(239, 68, 68, 0.25); border-radius: 12px; padding: 15px 10px; text-align: center; border-top: 3px solid var(--danger);">
                                <div style="margin-bottom: 8px;">
                                    <img src="images.png" alt="Car Icon" style="width: 24px; height: 24px; object-fit: contain; filter: drop-shadow(0 0 3px rgba(239, 68, 68, 0.4));">
                                </div>
                                <span style="display: block; font-weight: 700; font-size: 14px; color: #fff; margin-bottom: 4px;"><?php echo $slot; ?></span>
                                <span style="display: inline-block; font-size: 9px; font-weight: 600; text-transform: uppercase; padding: 2px 6px; border-radius: 4px; background: rgba(239, 68, 68, 0.1); color: var(--danger); margin-bottom: 6px;">Parked</span>
                                <span style="display: block; font-size: 11px; color: #fff; font-weight: 600;"><?php echo htmlspecialchars($info['plate']); ?></span>
                                <span style="display: block; font-size: 10px; color: var(--text-muted);">by <?php echo htmlspecialchars($info['owner']); ?></span>
                            </div>
                        <?php else: ?>
                            <div onclick="selectAvailableSlot('<?php echo $slot; ?>')" style="background: rgba(16, 185, 129, 0.04); border: 1px solid rgba(16, 185, 129, 0.15); border-radius: 12px; padding: 15px 10px; text-align: center; border-top: 3px solid var(--success); cursor: pointer; transition: all 0.2s;" onmouseover="this.style.background='rgba(16, 185, 129, 0.1)', this.style.borderColor='var(--success)'" onmouseout="this.style.background='rgba(16, 185, 129, 0.04)', this.style.borderColor='rgba(16, 185, 129, 0.15)'">
                                <div style="margin-bottom: 8px; opacity: 0.15;">
                                    <img src="images.png" alt="Car Icon" style="width: 24px; height: 24px; object-fit: contain; filter: grayscale(100%);">
                                </div>
                                <span style="display: block; font-weight: 700; font-size: 14px; color: #fff; margin-bottom: 4px;"><?php echo $slot; ?></span>
                                <span style="display: inline-block; font-size: 9px; font-weight: 600; text-transform: uppercase; padding: 2px 6px; border-radius: 4px; background: rgba(16, 185, 129, 0.1); color: var(--success); margin-bottom: 6px;">Empty</span>
                                <span style="display: block; font-size: 11px; color: var(--text-muted);">Available</span>
                            </div>
                        <?php endif; ?>
                    <?php endforeach; ?>
                </div>
            </div>

            <!-- Click-to-Select Script -->
            <script>
                function selectAvailableSlot(slotNumber) {
                    const slotDropdown = document.getElementById('slot_number');
                    slotDropdown.value = slotNumber;
                    slotDropdown.focus();
                    // Scroll dropdown into view smoothly
                    slotDropdown.scrollIntoView({ behavior: 'smooth', block: 'center' });
                }
            </script>
        </main>
    </div>

</body>
</html>
