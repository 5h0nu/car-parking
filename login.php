<?php
// Include configuration and database connection
require_once 'config.php';

// If user is already logged in, redirect to dashboard
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}

$error_message = '';
$username = '';

// Process Login Form Submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Retrieve and sanitize inputs
    $username = trim(filter_input(INPUT_POST, 'username', FILTER_SANITIZE_SPECIAL_CHARS));
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error_message = 'Please enter both username and password.';
    } else {
        try {
            // Prepare statement to fetch user
            $stmt = $pdo->prepare("SELECT * FROM users WHERE username = :username LIMIT 1");
            $stmt->execute(['username' => $username]);
            $user = $stmt->fetch();

            // Verify username and password
            if ($user && password_verify($password, $user['password'])) {
                // Regenerate session ID for security against session fixation
                session_regenerate_id(true);

                // Set session variables
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['logged_in_time'] = time();

                // Redirect to dashboard
                header("Location: dashboard.php");
                exit;
            } else {
                $error_message = 'Invalid username or password.';
            }
        } catch (PDOException $e) {
            $error_message = 'An unexpected database error occurred. Please try again later.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="description" content="Secure administrative login portal for the Car Parking Management System.">
    <title>Login - Car Parking Management System</title>
    <!-- Stylesheet -->
    <link rel="stylesheet" href="style.css">
</head>
<body>

    <div class="auth-container">
        <!-- Error Alert -->
        <?php if (!empty($error_message)): ?>
            <div class="alert alert-danger" role="alert">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 11-18 0 9 9 0 0118 0zm-9 3.75h.008v.008H12v-.008z" />
                </svg>
                <span><?php echo htmlspecialchars($error_message); ?></span>
            </div>
        <?php endif; ?>

        <!-- Login Card -->
        <div class="auth-card">
            <div class="auth-header">
                <div class="logo-wrapper" style="overflow: hidden; display: flex; align-items: center; justify-content: center;">
                    <img src="logo.jpg" alt="Logo" style="width: 100%; height: 100%; object-fit: cover;">
                </div>
                <h1 class="auth-title">ParkMaster</h1>
                <p class="auth-subtitle">Car Parking Management System</p>
            </div>

            <form class="auth-form" method="POST" action="login.php" autocomplete="off">
                <!-- Username Input Group -->
                <div class="input-group">
                    <input type="text" id="username" name="username" placeholder=" " value="<?php echo htmlspecialchars($username); ?>" required autofocus>
                    <div class="input-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                    </div>
                    <label for="username">Username</label>
                </div>

                <!-- Password Input Group -->
                <div class="input-group">
                    <input type="password" id="password" name="password" placeholder=" " required>
                    <div class="input-icon">
                        <svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0V10.5m-2.25 1.5h13.5c.621 0 1.125.504 1.125 1.125v7.497c0 .621-.504 1.125-1.125 1.125H4.125C3.504 21.75 3 21.246 3 20.622V13.12c0-.621.504-1.125 1.125-1.125Z" />
                        </svg>
                    </div>
                    <label for="password">Password</label>
                    <!-- Password Toggle Button -->
                    <button type="button" class="password-toggle" id="passwordToggle" aria-label="Toggle password visibility">
                        <svg id="eyeIcon" xmlns="http://www.w3.org/2000/svg" width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="1.5">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                        </svg>
                    </button>
                </div>

                <!-- Form Options -->
                <div class="form-options">
                    <label class="remember-me">
                        <input type="checkbox" name="remember">
                        <span>Remember Me</span>
                    </label>
                    <a href="#" class="forgot-pass" onclick="alert('Please contact the system administrator to reset your password.')">Forgot Password?</a>
                </div>

                <!-- Submit Button -->
                <button type="submit" class="btn-submit">Sign In</button>
            </form>
        </div>
    </div>

    <!-- Password visibility toggle script -->
    <script>
        const passwordInput = document.getElementById('password');
        const passwordToggle = document.getElementById('passwordToggle');
        const eyeIcon = document.getElementById('eyeIcon');

        // Path for visible eye
        const eyeOpenPath = `
            <path stroke-linecap="round" stroke-linejoin="round" d="M2.036 12.322a1.012 1.012 0 0 1 0-.639C3.423 7.51 7.36 4.5 12 4.5c4.638 0 8.573 3.007 9.963 7.178.07.207.07.431 0 .639C20.577 16.49 16.64 19.5 12 19.5c-4.638 0-8.573-3.007-9.963-7.178Z" />
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
        `;
        
        // Path for slashed eye (hidden)
        const eyeClosedPath = `
            <path stroke-linecap="round" stroke-linejoin="round" d="M3.98 8.223A10.477 10.477 0 0 0 1.934 12C3.226 16.338 7.244 19.5 12 19.5c.993 0 1.953-.138 2.863-.395M6.228 6.228A10.451 10.451 0 0 1 12 4.5c4.756 0 8.773 3.162 10.065 7.498a10.522 10.522 0 0 1-4.293 5.774M6.228 6.228 3 3m3.228 3.228 3.65 3.65m7.894 7.894L21 21m-3.228-3.228-3.65-3.65m0 0a3 3 0 1 0-4.243-4.243m4.242 4.242L9.88 9.88" />
        `;

        passwordToggle.addEventListener('click', () => {
            const isPassword = passwordInput.getAttribute('type') === 'password';
            passwordInput.setAttribute('type', isPassword ? 'text' : 'password');
            eyeIcon.innerHTML = isPassword ? eyeClosedPath : eyeOpenPath;
            // Retain focus on input
            passwordInput.focus();
        });
    </script>
</body>
</html>
