<?php
session_start();

require __DIR__ . '/includes/db.php';

$loginError = '';

if (isset($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $userType = trim($_POST['user_type'] ?? 'STD');

    if ($username === '' || $password === '' || $userType === '') {
        $loginError = 'Please fill in all fields.';
    } else {
        $isValid = true;
        if ($userType === 'STD' && !preg_match('/^[sS]\d{2}-\d{4}$/', $username)) {
            $isValid = false;
            $loginError = 'Invalid username format for Student. Expected format: S23-1234';
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

                        header('Location: index.php');
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
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Internship Management System</title>
    <!-- FontAwesome for Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="assets/css/login.css">
</head>

<body>

    <div class="login-shell">
        <!-- Login Card Container -->
        <div class="login-card">
            <div class="login-card-header">
                <img src="assets/img/uoh logo.svg" alt="UOH Logo" class="login-logo">
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
                        <span class="input-hint">Format: S23-1234 / FP-0001 / FSP-0001</span>
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

                    <!-- Submit Button -->
                    <button type="submit" class="btn-login">Login</button>

                </form>
            </div>
        </div>
    </div>

    <script>
    function validateLoginForm() {
        const userType = document.getElementById('user_type').value;
        const username = document.getElementById('username').value.trim();
        const errorDiv = document.getElementById('client-error');
        const errorText = document.getElementById('client-error-text');
        
        // Hide previous errors
        errorDiv.style.display = 'none';
        
        let isValid = true;
        let errorMessage = '';
        
        if (userType === 'STD') {
            const stdRegex = /^[sS]\d{2}-\d{4}$/;
            if (!stdRegex.test(username)) {
                isValid = false;
                errorMessage = 'Student Username must match the format: S23-1234';
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
        return true;
    }
    </script>
</body>

</html>