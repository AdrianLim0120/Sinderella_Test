<?php
session_start();
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $phone = $_POST['phone'];
    $password = $_POST['password'];

    // Database connection
    require_once 'db_connect.php'; //

    if ($conn->connect_error) {
        die("Connection failed: " . $conn->connect_error);
    }

    // Check if user exists and retrieve status
    $stmt = $conn->prepare("SELECT sind_id, sind_pwd, sind_status FROM sinderellas WHERE sind_phno = ?");
    $stmt->bind_param("s", $phone);
    $stmt->execute();
    $stmt->store_result();

    if ($stmt->num_rows > 0) {
        $stmt->bind_result($sind_id, $hashed_password, $sind_status);
        $stmt->fetch();

        if ($sind_status === 'active' || $sind_status === 'pending') {
            if (password_verify($password, $hashed_password)) {
                // Correct password, set session variables and update last login date
                $_SESSION['sind_id'] = $sind_id;

                $update_stmt = $conn->prepare("UPDATE sinderellas SET last_login_date = NOW() WHERE sind_id = ?");
                $update_stmt->bind_param("i", $sind_id);
                $update_stmt->execute();
                $update_stmt->close();

                // Fetch additional profile info for redirect logic
                $profile_stmt = $conn->prepare("SELECT sind_phno, sind_upline_id, sind_address, sind_icno, sind_emer_name FROM sinderellas WHERE sind_id = ?");
                $profile_stmt->bind_param("i", $sind_id);
                $profile_stmt->execute();
                $profile_stmt->bind_result($sind_phno, $sind_upline_id, $sind_address, $sind_icno, $sind_emer_name);
                $profile_stmt->fetch();
                $profile_stmt->close();

                // Redirect logic
                if ($sind_upline_id === null || $sind_upline_id === '' || $sind_upline_id === false) {
                    header("Location: rs/select_upline.php?phone=" . urlencode($sind_phno));
                    exit();
                } elseif ($sind_address === null || $sind_address === '') {
                    header("Location: rs/address_info.php?phone=" . urlencode($sind_phno));
                    exit();
                } elseif ($sind_icno === null || $sind_icno === '') {
                    header("Location: rs/verify_identity.php?phone=" . urlencode($sind_phno));
                    exit();
                } elseif ($sind_emer_name === null || $sind_emer_name === '') {
                    header("Location: rs/personal_info.php?phone=" . urlencode($sind_phno));
                    exit();
                } else {
                    header("Location: rs/manage_profile.php");
                    exit();
                }
            } else {
                // Wrong password
                $error_message = "Wrong password";
            }
        } elseif ($sind_status === 'inactive') {
            // Account is inactive
            $error_message = "Your account has been deactivated. <br>Please contact customer service for more info.";
        } else {
            // Unknown status
            $error_message = "Unable to log in. Please try again later.";
        }
    } else {
        // User not found
        $error_message = "User not found";
    }

    $stmt->close();
    // $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Sinderella</title>
    <link rel="icon" href="img/sinderella_favicon.png"/>
    <link rel="stylesheet" href="includes/css/loginstyles.css">
    <script src="includes/js/scripts.js" defer></script>
</head>
<body>

    <!-- // Tentative internal testing password prompt
    <script>
    (function() {
        var allowed = false;
        while (!allowed) {
            var input = prompt("Internal Testing Only\n\nPlease enter the access password:");
            if (input === "8148") {
                allowed = true;
            } else {
                alert("Incorrect password. Please try again.");
            }
        }
    })();
    </script>
    <!-- // Tentative internal testing password prompt --- END -->
    <!--  -->
    <!--  -->

    
    <div class="login-container">
        <div class="login-left">
            <img src="img/sinderella_logo.png" alt="Sinderella">
            <p>Love cleaning? Love flexibility & freedom?</p>
            <p style="font-size:1rem;"><a href="index.php">< Back to Home</a></p>
        </div>
        <div class="login-right">
            <form id="loginForm" action="login_sind.php" method="POST">
                <h2>Sinderella Login</h2>
                <label for="phone">Phone Number:</label>
                <input type="text" id="phone" name="phone" placeholder="Exp: 0123456789" required
                    value="<?php if (isset($_POST['phone'])) echo htmlspecialchars($_POST['phone']); ?>">
                <label for="password">Password:</label>
                <input type="password" id="password" name="password" placeholder="" required>
                <label><input type="checkbox" onclick="togglePassword('password')"> Show Password</label>
                <button type="submit">Sign In</button>
                <p id="error-message"><?php if (isset($error_message)) echo $error_message; ?></p>
                <p><a href="rs/forgot_pwd.php">Forgot Password?</a></p>
                <p><a href="rs/signup.php">Sign Up</a></p>
            </form>
        </div>
    </div>
<script>
    function togglePassword(fieldId) {
        var field = document.getElementById(fieldId);
        if (field.type === "password") {
            field.type = "text";
        } else {
            field.type = "password";
        }
    }
</script>
</body>
</html>