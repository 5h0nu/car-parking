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

// Handle Settings Update
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $rate_2_wheel = filter_input(INPUT_POST, 'rate_2_wheel', FILTER_VALIDATE_INT);
    $rate_4_wheel = filter_input(INPUT_POST, 'rate_4_wheel', FILTER_VALIDATE_INT);
    $rate_truck_heavy = filter_input(INPUT_POST, 'rate_truck_heavy', FILTER_VALIDATE_INT);
    $terminal_name = trim(filter_input(INPUT_POST, 'terminal_name', FILTER_SANITIZE_SPECIAL_CHARS));
    $capacity_limit = filter_input(INPUT_POST, 'capacity_limit', FILTER_VALIDATE_INT);

    if ($rate_2_wheel === false || $rate_4_wheel === false || $rate_truck_heavy === false || $capacity_limit === false) {
        $error_message = "Pricing rates and slot capacities must be valid positive integers.";
    } elseif ($rate_2_wheel < 0 || $rate_4_wheel < 0 || $rate_truck_heavy < 0 || $capacity_limit <= 0) {
        $error_message = "Values cannot be negative, and slot capacity must be greater than zero.";
    } else {
        try {
            // Update rates inside settings table
            $stmt = $pdo->prepare("UPDATE settings SET val_value = :val WHERE key_name = :key");
            
            $stmt->execute(['val' => $rate_2_wheel, 'key' => 'rate_2_wheel']);
            $stmt->execute(['val' => $rate_4_wheel, 'key' => 'rate_4_wheel']);
            $stmt->execute(['val' => $rate_truck_heavy, 'key' => 'rate_truck_heavy']);
            
            // Optionally store/update terminal name and capacity in settings too so they persist!
            // Let's check if they exist first, insert if not, update if they do.
            $stmt_check = $pdo->prepare("SELECT COUNT(*) FROM settings WHERE key_name = :key");
            
            // Check terminal name
            $stmt_check->execute(['key' => 'terminal_name']);
            if ($stmt_check->fetchColumn() > 0) {
                $stmt->execute(['val' => $terminal_name, 'key' => 'terminal_name']);
            } else {
                $stmt_insert = $pdo->prepare("INSERT INTO settings (key_name, val_value, description) VALUES ('terminal_name', :val, 'Terminal gate descriptor')");
                $stmt_insert->execute(['val' => $terminal_name]);
            }

            // Check capacity limit
            $stmt_check->execute(['key' => 'capacity_limit']);
            if ($stmt_check->fetchColumn() > 0) {
                $stmt->execute(['val' => $capacity_limit, 'key' => 'capacity_limit']);
            } else {
                $stmt_insert = $pdo->prepare("INSERT INTO settings (key_name, val_value, description) VALUES ('capacity_limit', :val, 'Total slots capacity limit')");
                $stmt_insert->execute(['val' => $capacity_limit]);
            }

            $success_message = "Global settings and pricing rates saved successfully!";
        } catch (PDOException $e) {
            $error_message = "Database update failed: " . $e->getMessage();
        }
    }
}

// Fetch all settings
$settings = [];
try {
    $stmt = $pdo->query("SELECT key_name, val_value FROM settings");
    $settings = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (PDOException $e) {
    $error_message = "Failed to load database settings: " . $e->getMessage();
}

// Extract configuration values with fallbacks
$rate_2_wheel = (int)($settings['rate_2_wheel'] ?? 50);
$rate_4_wheel = (int)($settings['rate_4_wheel'] ?? 100);
$rate_truck_heavy = (int)($settings['rate_truck_heavy'] ?? 150);
$terminal_name = $settings['terminal_name'] ?? 'ParkMaster East Gate Terminal';
$capacity_limit = (int)($settings['capacity_limit'] ?? 150);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Settings - ParkMaster</title>
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
                <h1 class="page-title">Terminal Settings</h1>
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

            <div class="content-card">
                <h2>Global System Configuration</h2>
                <p>Modify parking parameters, rates structure, slots allocation, and base variables below.</p>
                
                <form method="POST" action="settings.php">
                    <h3 style="color: #fff; font-size: 16px; margin: 25px 0 15px 0; border-bottom: 1px solid var(--card-border); padding-bottom: 8px;">
                        Pricing Rates Configuration (per hour)
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="rate_2_wheel">2-Wheeler Rate (Bike/Scooter) (₹)</label>
                            <input type="number" id="rate_2_wheel" name="rate_2_wheel" class="form-control" value="<?php echo $rate_2_wheel; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="rate_4_wheel">4-Wheeler Rate (Car/SUV) (₹)</label>
                            <input type="number" id="rate_4_wheel" name="rate_4_wheel" class="form-control" value="<?php echo $rate_4_wheel; ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="rate_truck_heavy">Truck & Heavy Vehicle Rate (₹)</label>
                            <input type="number" id="rate_truck_heavy" name="rate_truck_heavy" class="form-control" value="<?php echo $rate_truck_heavy; ?>" required>
                        </div>
                    </div>

                    <h3 style="color: #fff; font-size: 16px; margin: 35px 0 15px 0; border-bottom: 1px solid var(--card-border); padding-bottom: 8px;">
                        Terminal Information & Capacity
                    </h3>
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="terminal_name">Terminal Description</label>
                            <input type="text" id="terminal_name" name="terminal_name" class="form-control" value="<?php echo htmlspecialchars($terminal_name); ?>" required>
                        </div>
                        <div class="form-group">
                            <label for="capacity_limit">Maximum Slots Capacity Limit</label>
                            <input type="number" id="capacity_limit" name="capacity_limit" class="form-control" value="<?php echo $capacity_limit; ?>" required>
                        </div>
                    </div>

                    <div style="margin-top: 35px;">
                        <button type="submit" class="btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>
                            Save Settings
                        </button>
                    </div>
                </form>
            </div>
        </main>
    </div>

</body>
</html>
