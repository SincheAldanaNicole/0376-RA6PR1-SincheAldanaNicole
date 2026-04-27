<?php
/**
 * User Logout Handler
 * 
 * Destroys the user session and removes authentication cookies.
 * 
 */

// Start the session to access session data
session_start();

// Unset all session variables
$_SESSION = [];

// Destroy the session cookie if it exists
if (isset($_COOKIE[session_name()])) {
    setcookie(
        session_name(),
        '',
        time() - 42000,
        '/',
        '',
        false, // set to true in production with HTTPS
        true   // httponly
    );
}

// Destroy the session
session_destroy();

// Remove the username cookie
if (isset($_COOKIE['username'])) {
    setcookie(
        'username',
        '',
        time() - 42000,
        '/',
        '',
        false, // set to true in production with HTTPS
        true   // httponly
    );
}

// Redirect to login page
header('Location: login.php');
exit;