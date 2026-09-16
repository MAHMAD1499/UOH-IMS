<?php
/**
 * Login Controller
 * 
 * Handles user authentication, reCAPTCHA verification, and role-based routing 
 * for Students, Focal Persons, and Faculty Supervisors.
 * 
 * Supported user types:
 *   - STD (Student)           — username format: e.g. S23-1234
 *   - FP  (Focal Person)      — username format: FP-0001
 *   - FSP (Faculty Supervisor) — username format: FSP-0001
 * 
 * On successful login the user is redirected to index.php which routes
 * them to the appropriate role-specific dashboard. If a student's password
 * still matches their roll number (default password), a forced password-change
 * flag is set in the session.
 * 
 * @file    login.php
 * @project Internship Management System (IMS) — University of Haripur
 */
session_start();

require __DIR__ . '/../config/db.php';

$loginError = '';

if (isset($_SESSION['user_id'])) {
    header('Location: ../index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $userType = trim($_POST['user_type'] ?? 'STD');
    $recaptchaResponse = $_POST['g-recaptcha-response'] ?? '';

    // Validate Google reCAPTCHA v2 response via server-side request
    if ($recaptchaResponse === '') {
        $loginError = 'Please complete the CAPTCHA verification.';
    } else {
        $secretKey = '6LeIxAcTAAAAAGG-vFI1TnRWxMZNFuojJ4WifJWe';
        $verifyContext = stream_context_create([
            'http' => [
                'method' => 'POST',
                'header' => "Content-Type: application/x-www-form-urlencoded\r\n",
                'content' => http_build_query([
                    'secret' => $secretKey,
                    'response' => $recaptchaResponse,
                    'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
                ]),
                'timeout' => 10,
                'ignore_errors' => true,
            ],
        ]);
        $verifyResponse = @file_get_contents(
            'https://www.google.com/recaptcha/api/siteverify',
            false,
            $verifyContext
        );
        $responseData = json_decode($verifyResponse ?: '', true);

        if (!is_array($responseData) || empty($responseData['success'])) {
            $loginError = 'CAPTCHA verification failed. Please try again.';
        }
    }

    // Check if any required fields are empty
    if ($loginError === '' && ($username === '' || $password === '' || $userType === '')) {
        $loginError = 'Please fill in all fields.';
    } elseif ($loginError === '') {
        $isValid = true;
        if ($userType === 'STD' && !preg_match('/^[a-zA-Z]\d{2}-\d{4}$/', $username)) {
            $isValid = false;
            $loginError = 'Invalid username format for Student. Expected format: e.g. S23-1234 or F26-0001';
        } elseif ($userType === 'FP' && !preg_match('/^[fF][pP]-\d{4}$/', $username)) {
            $isValid = false;
            $loginError = 'Invalid username format for Focal Person. Expected format: FP-0001';
        } elseif ($userType === 'FSP' && !preg_match('/^[fF][sS][pP]-\d{4}$/', $username)) {
            $isValid = false;
            $loginError = 'Invalid username format for Faculty Supervisor. Expected format: FSP-0001';
        }

        if ($isValid) {
            $sql = 'SELECT u_id, u_name, u_pass, u_type, status FROM user WHERE u_name = ? AND u_type = ? LIMIT 1';
            $stmt = mysqli_prepare($conn, $sql);

            if ($stmt) {
                mysqli_stmt_bind_param($stmt, 'ss', $username, $userType);
                mysqli_stmt_execute($stmt);
                $result = mysqli_stmt_get_result($stmt);
                $user = $result ? mysqli_fetch_assoc($result) : null;

                if ($user && (int) ($user['status'] ?? 1) === 1) {
                    $storedPassword = (string) ($user['u_pass'] ?? '');
                    $passwordMatches = password_verify($password, $storedPassword) || hash_equals($storedPassword, $password);

                    if ($passwordMatches) {
                        $_SESSION['user_id'] = $user['u_id'];
                        $_SESSION['username'] = $user['u_name'];
                        $_SESSION['user_type'] = $user['u_type'];

                        if ($user['u_type'] === 'STD') {
                            if (password_verify($user['u_name'], $storedPassword) || hash_equals($storedPassword, $user['u_name'])) {
                                $_SESSION['must_change_password'] = true;
                            }
                        }

                        header('Location: ../index.php');
                        exit;
                    }
                }

                $loginError = 'Invalid credentials or incorrect user role selected.';
                mysqli_stmt_close($stmt);
            } else {
                $loginError = 'Unable to start the login query.';
            }
        }
    }
}
?>
<!-- 
  Frontend HTML for Login Page
  Includes role selection, format hints, and reCAPTCHA widget.
-->
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Internship Management System</title>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="../assets/css/login.css">
    <!-- Google reCAPTCHA v2 API -->
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
</head>

<body>

    <div class="login-shell">
        <!-- Login Card Container -->
        <div class="login-card">
            <div class="login-card-header">
                <img src="../assets/img/internship_management_system.svg" alt="UOH Logo" class="login-logo">
            </div>
            <div class="login-card-body">
                <form action="" method="POST" onsubmit="return validateLoginForm();">
                    <div id="client-error" class="error-message" style="display: none;">
                        <i class="fa-solid fa-circle-exclamation"></i>
                        <span id="client-error-text"></span>
                    </div>

                    <?php if ($loginError !== ''): ?>
                        <div class="error-message">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <span><?php echo htmlspecialchars($loginError); ?></span>
                        </div>
                    <?php endif; ?>

                    <!-- Role Selection Dropdown -->
                    <div class="input-container">
                        <div class="input-group">
                            <div class="input-icon">
                                <i class="fa-solid fa-user-gear"></i>
                            </div>
                            <select id="user_type" name="user_type" required>
                                <option value="STD" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'STD') ? 'selected' : ''; ?>>Student</option>
                                <option value="FP" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'FP') ? 'selected' : ''; ?>>Focal Person</option>
                                <option value="FSP" <?php echo (isset($_POST['user_type']) && $_POST['user_type'] === 'FSP') ? 'selected' : ''; ?>>Faculty Supervisor</option>
                            </select>
                        </div>
                    </div>

                    <!-- Username Input -->
                    <div class="input-container">
                        <div class="input-group">
                            <div class="input-icon">
                                <i class="fa-solid fa-user"></i>
                            </div>
                            <input type="text" id="username" name="username"
                                value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                placeholder="Username / ID" required>
                        </div>
                        <span class="input-hint">Format: e.g. S23-1234 or F26-0001 / FP-0001 / FSP-0001</span>
                    </div>

                    <!-- Password Input -->
                    <div class="input-container">
                        <div class="input-group">
                            <div class="input-icon">
                                <i class="fa-solid fa-lock"></i>
                            </div>
                            <input type="password" id="password" name="password" placeholder="Password" required>
                        </div>
                    </div>

                    <!-- Google reCAPTCHA Widget (Test Keys) -->
                    <!-- Bypassed for offline mode -->
                    
                    <div class="input-container" style="display: flex; justify-content: center; margin-bottom: 10px;">
                        <div class="g-recaptcha" data-sitekey="6LeIxAcTAAAAAJcZVRqyHh71UMIEGNQ_MXjiZKhI"></div>
                    </div>

                    <!-- Submit Button -->
                    <button type="submit" class="btn-login">Login</button>

                </form>
            </div>
        </div>
    </div>

    <script>
        /**
         * Client-side form validation before submission
         * Validates role-based username formats and checks if reCAPTCHA is completed.
         */
        function validateLoginForm() {
            const userType = document.getElementById('user_type').value;
            const username = document.getElementById('username').value.trim();
            const errorDiv = document.getElementById('client-error');
            const errorText = document.getElementById('client-error-text');

            // Hide previous errors
            errorDiv.style.display = 'none';

            let isValid = true;
            let errorMessage = '';

            // Instructions Regarding User's Format
            if (userType === 'STD') {
                const stdRegex = /^[a-zA-Z]\d{2}-\d{4}$/;
                if (!stdRegex.test(username)) {
                    isValid = false;
                    errorMessage = 'Student Username must match the format: e.g. S23-1234 or F26-0001';
                }
            } else if (userType === 'FP') {
                const fpRegex = /^[fF][pP]-\d{4}$/;
                if (!fpRegex.test(username)) {
                    isValid = false;
                    errorMessage = 'Focal Person Username must match the format: FP-0001';
                }
            } else if (userType === 'FSP') {
                const fspRegex = /^[fF][sS][pP]-\d{4}$/;
                if (!fspRegex.test(username)) {
                    isValid = false;
                    errorMessage = 'Faculty Supervisor Username must match the format: FSP-0001';
                }
            }

            if (!isValid) {
                errorText.textContent = errorMessage;
                errorDiv.style.display = 'flex';
                return false;
            }

            // Validate reCAPTCHA
            
            const recaptchaResponse = typeof grecaptcha !== 'undefined' ? grecaptcha.getResponse() : '';
            if (recaptchaResponse.length === 0) {
                errorText.textContent = "Please complete the CAPTCHA verification.";
                errorDiv.style.display = 'flex';
                return false;
            }
            

            return true;
        }
    </script>
</body>

</html>