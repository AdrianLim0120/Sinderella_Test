<?php
    session_start();
    if (!isset($_SESSION['adm_id'])) {
        header("Location: ../login_adm.php");
        exit();
    }

    if ($_SERVER['REQUEST_METHOD'] == 'POST') {
        $adm_id = $_SESSION['adm_id'];
        $old_password = $_POST['old_password'];
        $new_password = $_POST['new_password'];
        $confirm_password = $_POST['confirm_password'];

        // Database connection
        require_once '../db_connect.php'; //

        if ($conn->connect_error) {
            die("Connection failed: " . $conn->connect_error);
        }

        // Check if old password matches the database
        $stmt = $conn->prepare("SELECT adm_pwd FROM admins WHERE adm_id = ?");
        if (!$stmt) {
            echo "<script>document.getElementById('error-message').innerText = 'Database error: " . $conn->error . "';</script>";
            exit();
        }
        $stmt->bind_param("i", $adm_id);
        $stmt->execute();
        $stmt->bind_result($hashed_password);
        $stmt->fetch();
        $stmt->close();

        if (!password_verify($old_password, $hashed_password)) {
            echo "<script>document.getElementById('error-message').innerText = 'Old password is incorrect.';</script>";
            // $conn->close();
            exit();
        }

        // Check if new password matches confirm password
        if ($new_password !== $confirm_password) {
            echo "<script>document.getElementById('error-message').innerText = 'New password and confirm password must match.';</script>";
            // $conn->close();
            exit();
        }

        // Hash the new password
        $new_hashed_password = password_hash($new_password, PASSWORD_DEFAULT);

        // Update admin's password in the database
        $stmt = $conn->prepare("UPDATE admins SET adm_pwd = ? WHERE adm_id = ?");
        if (!$stmt) {
            echo "<script>document.getElementById('error-message').innerText = 'Database error: " . $conn->error . "';</script>";
            exit();
        }
        $stmt->bind_param("si", $new_hashed_password, $adm_id);
        $stmt->execute();
        $stmt->close();
        // $conn->close();

        echo "<script>alert('Password changed successfully.'); window.location.href = 'manage_profile.php';</script>";
    }
    ?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Sinderella</title>
    <link rel="icon" href="../img/sinderella_favicon.png"/>
    <link rel="stylesheet" href="../includes/css/loginstyles.css">
    <script src="../includes/js/forgot_pwd.js" defer></script>
</head>
<body>
    <div class="login-container">
        <div class="login-left">
            <img src="../img/sinderella_logo.png" alt="Sinderella">
            <p style="font-size:1rem;"><a href="manage_profile.php">< Back to Profile</a></p>
        </div>
        <div class="login-right">
            <form id="resetPwdForm" action="reset_pwd.php" method="POST">
                <h2>Reset Password</h2>
                <label for="old_password">Old Password:</label>
                <input type="password" id="old_password" name="old_password" required>
                <label for="new_password">New Password:</label>
                <input type="password" id="new_password" name="new_password" required>
                <label for="confirm_password">Confirm Password:</label>
                <input type="password" id="confirm_password" name="confirm_password" required>
                <button type="submit">Change Password</button>
                <p id="error-message"></p>
            </form>
        </div>
    </div>
</body>
</html>