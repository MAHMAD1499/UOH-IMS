<?php
/**
 * Logout Handler
 * 
 * Completely destroys the current user session and its associated cookie,
 * then outputs a minimal HTML page whose sole purpose is to clear client-side
 * localStorage keys (activeTab, activeNavIndex) before redirecting to login.php.
 * 
 * Flow:
 *   1. Clear all server-side session data.
 *   2. Expire the session cookie so the browser drops it.
 *   3. Destroy the session on the server.
 *   4. Emit a <script> to wipe localStorage and redirect to the login page.
 *   5. exit; ensures no further output is sent.
 * 
 * @file    logout.php
 * @project Internship Management System (IMS) — University of Haripur
 */
session_start();

/* Clear all session variables */
$_SESSION = [];

/* If the session uses cookies, expire the cookie immediately */
if (ini_get('session.use_cookies')) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 3600, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
}

/* Destroy the session data on the server */
session_destroy();
?>
<!-- 
  Minimal HTML page used only to execute client-side cleanup.
  Clears localStorage keys that track the active sidebar tab and navigation index,
  then performs a client-side redirect to the login page.
-->
<!DOCTYPE html>
<html>
<head>
    <script>
        /* Remove persisted navigation state so the next login starts fresh */
        localStorage.removeItem('activeTab');
        localStorage.removeItem('activeNavIndex');
        /* Redirect the browser to the login page */
        window.location.href = 'login.php';
    </script>
</head>
<body>
</body>
</html>
<?php
/* Halt all further script execution after the redirect is issued */
exit;
