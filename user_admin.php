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

// Step 1: Process Add User (Registration)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_user'])) {
    $username = trim(filter_input(INPUT_POST, 'reg_username', FILTER_SANITIZE_SPECIAL_CHARS));
    $email = trim(filter_input(INPUT_POST, 'reg_email', FILTER_VALIDATE_EMAIL));
    $password = $_POST['reg_password'] ?? '';
    $role = filter_input(INPUT_POST, 'reg_role', FILTER_SANITIZE_SPECIAL_CHARS);

    if (empty($username) || empty($email) || empty($password) || empty($role)) {
        $error_message = "All registration fields (username, email, password, role) are required.";
    } elseif (!in_array($role, ['staff', 'admin'])) {
        $error_message = "Invalid access role selected.";
    } else {
        try {
            // Check if username already exists
            $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = :user");
            $stmt->execute(['user' => $username]);
            if ($stmt->fetchColumn() > 0) {
                $error_message = "Username '{$username}' already exists in the system.";
            } else {
                // Check if email already exists
                $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE email = :email");
                $stmt->execute(['email' => $email]);
                
                if ($stmt->fetchColumn() > 0) {
                    $error_message = "Email address '{$email}' is already registered.";
                } else {
                    // Hash password using Bcrypt
                    $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                    
                    // Insert new user
                    $stmt = $pdo->prepare("
                        INSERT INTO users (username, email, password, role) 
                        VALUES (:username, :email, :password, :role)
                    ");
                    $stmt->execute([
                        'username' => $username,
                        'email' => $email,
                        'password' => $hashed_password,
                        'role' => $role
                    ]);
                    
                    $success_message = "Account for user <strong>" . htmlspecialchars($username) . "</strong> created successfully!";
                }
            }
        } catch (PDOException $e) {
            $error_message = "Error creating account: " . $e->getMessage();
        }
    }
}

// Step 2: Process Delete User
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_user'])) {
    $delete_id = filter_input(INPUT_POST, 'delete_user_id', FILTER_VALIDATE_INT);

    if (!$delete_id) {
        $error_message = "Invalid user selected for deletion.";
    } elseif ($delete_id === (int)$_SESSION['user_id']) {
        // Prevent deleting active session account
        $error_message = "Deletion blocked: You cannot delete your own logged-in account.";
    } else {
        try {
            // Delete the user record
            $stmt = $pdo->prepare("DELETE FROM users WHERE id = :id");
            $stmt->execute(['id' => $delete_id]);
            
            if ($stmt->rowCount() > 0) {
                $success_message = "User account has been successfully removed from the system.";
            } else {
                $error_message = "Failed to remove account. The user record may not exist.";
            }
        } catch (PDOException $e) {
            $error_message = "Error removing user account: " . $e->getMessage();
        }
    }
}

// Fetch all registered users
$user_list = [];
try {
    $stmt = $pdo->query("SELECT id, username, email, role, created_at FROM users ORDER BY role, username");
    $user_list = $stmt->fetchAll();
} catch (PDOException $e) {
    $error_message = "Failed to load registered accounts directory: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Accounts - ParkMaster</title>
    <!-- Stylesheet -->
    <link rel="stylesheet" href="style.css">
    <style>
        .staff-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
            margin-top: 20px;
        }

        .staff-card {
            background: rgba(255, 255, 255, 0.02);
            border: 1px solid var(--card-border);
            border-radius: 16px;
            padding: 20px;
            display: flex;
            align-items: center;
            gap: 15px;
            position: relative;
            transition: all var(--transition-speed);
        }

        .staff-card:hover {
            background: rgba(255, 255, 255, 0.04);
            border-color: var(--primary-light);
            transform: translateY(-2px);
        }

        .staff-avatar {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            background: rgba(255, 255, 255, 0.05);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
            font-weight: 700;
            color: var(--secondary);
            border: 1px solid var(--card-border);
            text-transform: uppercase;
        }

        .staff-details {
            display: flex;
            flex-direction: column;
            gap: 2px;
            flex-grow: 1;
        }

        .staff-username {
            font-weight: 600;
            color: #ffffff;
            font-size: 15px;
        }

        .staff-email {
            font-size: 12px;
            color: var(--text-muted);
        }

        .staff-role-badge {
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            padding: 3px 6px;
            border-radius: 4px;
            align-self: flex-start;
            margin-top: 5px;
            letter-spacing: 0.5px;
        }

        .staff-role-badge.admin {
            background: rgba(79, 70, 229, 0.15);
            color: var(--primary-light);
        }

        .staff-role-badge.staff {
            background: rgba(6, 182, 212, 0.15);
            color: var(--secondary);
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.1);
            border: 1px solid rgba(239, 68, 68, 0.25);
            color: #fca5a5;
            padding: 6px 12px;
            border-radius: 6px;
            cursor: pointer;
            font-size: 11px;
            font-weight: 600;
            transition: all var(--transition-speed);
            text-transform: uppercase;
        }

        .btn-delete:hover {
            background: var(--danger);
            color: #ffffff;
            box-shadow: 0 0 10px rgba(239, 68, 68, 0.4);
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
                <h1 class="page-title">User Accounts Administration</h1>
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

            <!-- Add Operator Card -->
            <div class="content-card">
                <h2>Register New Account</h2>
                <p>Configure access credentials for terminal administrators or parking check-in operators.</p>
                
                <form method="POST" action="user_admin.php" autocomplete="off">
                    <input type="hidden" name="add_user" value="1">
                    <div class="form-grid">
                        <div class="form-group">
                            <label for="reg_username">Username</label>
                            <input type="text" id="reg_username" name="reg_username" class="form-control" placeholder="e.g. operator_john" required>
                        </div>
                        <div class="form-group">
                            <label for="reg_email">Email Address</label>
                            <input type="email" id="reg_email" name="reg_email" class="form-control" placeholder="john@carpark.com" required>
                        </div>
                        <div class="form-group">
                            <label for="reg_password">Password</label>
                            <input type="password" id="reg_password" name="reg_password" class="form-control" placeholder="••••••••" required>
                        </div>
                        <div class="form-group">
                            <label for="reg_role">System Access Role</label>
                            <select id="reg_role" name="reg_role" class="form-control" required style="background-color: #111322;">
                                <option value="staff">Standard Staff / Operator</option>
                                <option value="admin">System Administrator</option>
                            </select>
                        </div>
                    </div>

                    <div style="margin-top: 25px;">
                        <button type="submit" class="btn-primary">
                            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" style="width: 18px; height: 18px;"><path stroke-linecap="round" stroke-linejoin="round" d="M18 7.5v3m0 0v3m0-3h3m-3 0h-3m-2.25-4.125a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0ZM3 19.235v-.11a6.375 6.375 0 0 1 12.75 0v.109A12.318 12.318 0 0 1 9.374 21c-2.331 0-4.512-.647-6.374-1.766Z" /></svg>
                            Add New Account
                        </button>
                    </div>
                </form>
            </div>

            <!-- Staff Accounts Directory -->
            <div class="content-card" style="margin-top: 30px;">
                <h2>Active System Accounts (<?php echo count($user_list); ?> Accounts)</h2>
                <p>Manage administrative permissions. The active logged-in account cannot be removed.</p>
                
                <div class="staff-grid">
                    <?php foreach ($user_list as $user): ?>
                        <?php 
                            $is_current_session = ((int)$user['id'] === (int)$_SESSION['user_id']);
                        ?>
                        <div class="staff-card">
                            <div class="staff-avatar">
                                <?php echo strtoupper(substr($user['username'], 0, 2)); ?>
                            </div>
                            <div class="staff-details">
                                <span class="staff-username">
                                    <?php echo htmlspecialchars($user['username']); ?>
                                    <?php if ($is_current_session): ?>
                                        <span style="font-size: 11px; color: var(--success); font-weight: 500; font-style: italic;">(You)</span>
                                    <?php endif; ?>
                                </span>
                                <span class="staff-email"><?php echo htmlspecialchars($user['email']); ?></span>
                                <span class="staff-role-badge <?php echo $user['role'] === 'admin' ? 'admin' : 'staff'; ?>">
                                    <?php echo htmlspecialchars($user['role'] === 'admin' ? 'Administrator' : 'Staff'); ?>
                                </span>
                            </div>
                            
                            <!-- Delete Option (Disabled for currently logged-in account) -->
                            <?php if (!$is_current_session): ?>
                                <div style="align-self: flex-start;">
                                    <form method="POST" action="user_admin.php" onsubmit="return confirm('Are you sure you want to delete account: <?php echo htmlspecialchars($user['username']); ?>? This cannot be undone.');">
                                        <input type="hidden" name="delete_user" value="1">
                                        <input type="hidden" name="delete_user_id" value="<?php echo $user['id']; ?>">
                                        <button type="submit" class="btn-delete">Delete</button>
                                    </form>
                                </div>
                            <?php endif; ?>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        </main>
    </div>

</body>
</html>
