<?php
session_start();
if (!isset($_SESSION['adm_id'])) { header("Location: ../login_adm.php"); exit(); }
require_once '../db_connect.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = $_POST['booking_id'];
    // Fetch booking details if not already posted
    if (isset($_POST['booking_date'])) {
        // Coming from view_booking_details.php (first load)
        $booking_date = $_POST['booking_date'];
        $booking_from_time = $_POST['booking_from_time'];
        $booking_to_time = $_POST['booking_to_time'];
        $full_address = $_POST['full_address'];
        $sind_id = $_POST['sind_id'];
    } else {
        // Coming from this form (save changes), fetch from DB
        $stmt = $conn->prepare("SELECT booking_date, booking_from_time, booking_to_time, full_address, sind_id FROM bookings WHERE booking_id = ?");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $stmt->bind_result($booking_date, $booking_from_time, $booking_to_time, $full_address, $sind_id);
        $stmt->fetch();
        $stmt->close();
    }

    // Fetch booking details for display
    $stmt = $conn->prepare("
        SELECT 
            b.booking_status, 
            b.service_id, 
            b.cust_id,
            b.booking_type, 
            s.service_name, 
            c.cust_name, 
            p.total_price,
            b.bp_total
        FROM bookings b
        JOIN services s ON b.service_id = s.service_id
        JOIN customers c ON b.cust_id = c.cust_id
        LEFT JOIN pricings p ON b.service_id = p.service_id AND p.service_type = b.booking_type
        WHERE b.booking_id = ?
    ");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $stmt->bind_result($booking_status, $service_id, $cust_id, $booking_type, $service_name, $cust_name, $service_price, $bp_total);
    $stmt->fetch();
    $stmt->close();

    // Fetch add-on details
    $addon_details = [];
    $total_addon_price = 0;
    $stmt = $conn->prepare("SELECT ao.ao_desc, 
        CASE 
            WHEN ? = 'a' THEN ao.ao_price 
            ELSE ao.ao_price_recurring 
        END AS ao_price
        FROM booking_addons ba
        JOIN addon ao ON ba.ao_id = ao.ao_id
        WHERE ba.booking_id = ?");
    $stmt->bind_param("si", $booking_type, $booking_id);
    $stmt->execute();
    $stmt->bind_result($ao_desc, $ao_price);
    while ($stmt->fetch()) {
        $addon_details[] = ['desc' => $ao_desc, 'price' => $ao_price];
        $total_addon_price += $ao_price;
    }
    $stmt->close();

    if ($booking_status == 'pending') {
        $total_price = $total_addon_price + $service_price;
    } elseif ($booking_status == 'cancel') {
        if ($bp_total > 0) {
            $total_price = $bp_total;
        } else {
            $total_price = $total_addon_price + $service_price;
        }
    }else {
        $total_price = $bp_total;
    }

    // Fetch service duration
    $stmt = $conn->prepare("SELECT s.service_duration FROM bookings b JOIN services s ON b.service_id = s.service_id WHERE b.booking_id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $stmt->bind_result($service_duration);
    $stmt->fetch();
    $stmt->close();

    // Fetch add-on durations
    $total_addon_duration = 0;
    $stmt = $conn->prepare("SELECT ao.ao_duration FROM booking_addons ba JOIN addon ao ON ba.ao_id = ao.ao_id WHERE ba.booking_id = ?");
    $stmt->bind_param("i", $booking_id);
    $stmt->execute();
    $stmt->bind_result($ao_duration);
    while ($stmt->fetch()) {
        $total_addon_duration += $ao_duration;
    }
    $stmt->close();

    $total_duration = $service_duration + $total_addon_duration;

    // If saving changes
    if (isset($_POST['save_changes'])) {
        $new_date = $_POST['new_booking_date'];
        $new_from_time = $_POST['new_booking_from_time'];
        $new_to_time = $_POST['new_booking_to_time'];
        $new_address = $_POST['new_full_address'];
        $new_sind_id = $_POST['new_sind_id'];

        $stmt = $conn->prepare("UPDATE bookings SET booking_date=?, booking_from_time=?, booking_to_time=?, full_address=?, sind_id=? WHERE booking_id=?");
        $stmt->bind_param("ssssii", $new_date, $new_from_time, $new_to_time, $new_address, $new_sind_id, $booking_id);
        $stmt->execute();
        $stmt->close();

        header("Location: view_booking_details.php?booking_id=$booking_id");
        exit();
    }

    // Fetch all sinderellas for dropdown
    $sind_list = [];
    $res = $conn->query("SELECT sind_id, sind_name FROM sinderellas WHERE sind_status='active' AND acc_approved='approve'");
    while ($row = $res->fetch_assoc()) $sind_list[] = $row;

    // ========= PAYMENT RECEIVED HANDLER ====================================
    if (isset($_POST['mark_paid'])) {
        // 0) Transaction
        $conn->begin_transaction();

        // 1) Lock & read booking essentials
        $stmt = $conn->prepare("
            SELECT b.booking_status, b.booking_type, b.service_id, b.sind_id
            FROM bookings b
            WHERE b.booking_id = ?
            FOR UPDATE
        ");
        $stmt->bind_param("i", $booking_id);
        $stmt->execute();
        $stmt->bind_result($b_status_now, $b_type, $b_service_id, $maid_sind_id);
        if (!$stmt->fetch()) { $stmt->close(); $conn->rollback(); die('Booking not found'); }
        $stmt->close();

        // Already paid? exit cleanly
        if (strtolower((string)$b_status_now) === 'paid') {
            $conn->commit();
            header("Location: view_booking_details.php?booking_id=" . urlencode($booking_id));
            exit();
        }

        // 2) Base pricing from pricings (by service_id + booking_type)
        $base_total = $base_platform = $base_sind = 0.0;
        $p_lvl1 = $p_lvl2 = 0.0;

        $stmt = $conn->prepare("
            SELECT total_price, platform, sinderella, lvl1, lvl2
            FROM pricings
            WHERE service_id = ? AND service_type = ?
            LIMIT 1
        ");
        $stmt->bind_param("is", $b_service_id, $b_type);
        $stmt->execute();
        $stmt->bind_result($p_total, $p_platform, $p_sind, $p_lvl1_raw, $p_lvl2_raw);
        if ($stmt->fetch()) {
            $base_total    = (float)$p_total;
            $base_platform = (float)$p_platform;
            $base_sind     = (float)$p_sind;
            $p_lvl1        = (float)$p_lvl1_raw;  // pricing lvl1 amount
            $p_lvl2        = (float)$p_lvl2_raw;  // pricing lvl2 amount
        }
        $stmt->close();

        // 3) Sum add-ons (type-aware)
        $add_total = $add_platform = $add_sind = 0.0;

        $stmt = $conn->prepare("
            SELECT
                COALESCE(SUM(CASE WHEN ?='a' THEN ao.ao_price             ELSE ao.ao_price_recurring           END), 0) AS add_total,
                COALESCE(SUM(CASE WHEN ?='a' THEN ao.ao_platform         ELSE ao.ao_platform_recurring       END), 0) AS add_platform,
                COALESCE(SUM(CASE WHEN ?='a' THEN ao.ao_sind             ELSE ao.ao_sind_recurring           END), 0) AS add_sind
            FROM booking_addons ba
            JOIN addon ao ON ba.ao_id = ao.ao_id
            WHERE ba.booking_id = ?
        ");
        $stmt->bind_param("sssi", $b_type, $b_type, $b_type, $booking_id);
        $stmt->execute();
        $stmt->bind_result($s_add_total, $s_add_platform, $s_add_sind);
        if ($stmt->fetch()) {
            $add_total    = (float)$s_add_total;
            $add_platform = (float)$s_add_platform;
            $add_sind     = (float)$s_add_sind;
        }
        $stmt->close();

        // 4) Initial breakdown
        $bp_total    = $base_total    + $add_total;
        $bp_platform = $base_platform + $add_platform;
        $bp_sind     = $base_sind     + $add_sind;

        // 5) Resolve uplines from sinderellas (A = immediate, B = upline-of-A)
        //    lvl1 = A, lvl2 = B
        $lvl1_sind_id = null;
        $lvl2_sind_id = null;

        // A: immediate upline of maid
        $stmt = $conn->prepare("SELECT sind_upline_id FROM sinderellas WHERE sind_id = ? LIMIT 1");
        $stmt->bind_param("i", $maid_sind_id);
        $stmt->execute();
        $stmt->bind_result($A);
        if ($stmt->fetch()) {
            if (!is_null($A) && (int)$A > 0) { $lvl1_sind_id = (int)$A; }
        }
        $stmt->close();

        // B: upline of A (if A exists)
        if ($lvl1_sind_id !== null) {
            $stmt = $conn->prepare("SELECT sind_upline_id FROM sinderellas WHERE sind_id = ? LIMIT 1");
            $stmt->bind_param("i", $lvl1_sind_id);
            $stmt->execute();
            $stmt->bind_result($B);
            if ($stmt->fetch()) {
                if (!is_null($B) && (int)$B > 0) { $lvl2_sind_id = (int)$B; }
            }
            $stmt->close();
        }

        // 6) Level amounts + platform top-up when missing
        $bp_lvl1_amount = ($lvl1_sind_id === null) ? null : $p_lvl1;
        $bp_lvl2_amount = ($lvl2_sind_id === null) ? null : $p_lvl2;

        if ($lvl1_sind_id === null) { $bp_platform += $p_lvl1; }
        if ($lvl2_sind_id === null) { $bp_platform += $p_lvl2; }

        // 7) Persist
        $stmt = $conn->prepare("
            UPDATE bookings
            SET booking_status   = 'paid',
                bp_total         = ?,
                bp_platform      = ?,
                bp_sind          = ?,
                bp_lvl1_sind_id  = ?,
                bp_lvl1_amount   = ?,
                bp_lvl2_sind_id  = ?,
                bp_lvl2_amount   = ?
            WHERE booking_id = ?
        ");
        // NULL is allowed for *_sind_id and *_amount
        $stmt->bind_param(
            "dddididi",
            $bp_total,
            $bp_platform,
            $bp_sind,
            $lvl1_sind_id,
            $bp_lvl1_amount,
            $lvl2_sind_id,
            $bp_lvl2_amount,
            $booking_id
        );
        $ok = $stmt->execute();
        $stmt->close();

        if ($ok) {
            $conn->commit();
        } else {
            $conn->rollback();
            die('Failed to mark payment.');
        }

        header("Location: view_booking_details.php?booking_id=" . urlencode($booking_id));
        exit();
    }
    // ======== END PAYMENT RECEIVED HANDLER =================================

} else {
    header("Location: view_bookings.php");
    exit();
}
?>
<!DOCTYPE html>
<html>
<head>
    <title>Edit Booking</title>
    <link rel="icon" href="../img/sinderella_favicon.png"/>
    <link rel="stylesheet" href="../includes/css/styles_user.css">
<style>
            .profile-container {
        /* display: flex; */
    }
    .profile-container .left, .profile-container .right {
        flex: 1;
        padding: 20px;
    }
    .profile-container label {
        display: inline-block;
        margin-top: 10px;
        font-weight: bold;
    }
    .profile-container input[type="text"],
    .profile-container input[type="number"],
    .profile-container select,
    .profile-container textarea {
        width: 80%;
        padding: 5px;
        margin-right: 10px;
    }
    .profile-container button {
        /* margin-top: 20px; */
        padding: 10px 20px;
        background-color: #007bff;
        color: white;
        border: none;
        border-radius: 5px;
        cursor: pointer;
    }
    .profile-container button:hover {
        background-color: #0056b3;
    }
    td {
        padding: 8px;
    }
</style>

<script>
function pad(num) { return num.toString().padStart(2, '0'); }
function calcEndTime() {
    var start = document.querySelector('[name="new_booking_from_time"]').value;
    var duration = parseFloat(document.getElementById('total_duration').value);
    if (!start || isNaN(duration)) return;
    var parts = start.split(':');
    var d = new Date();
    d.setHours(parseInt(parts[0]), parseInt(parts[1]), 0, 0);
    d.setHours(d.getHours() + duration);
    var end = pad(d.getHours()) + ':' + pad(d.getMinutes()) + ':00';
    document.querySelector('[name="new_booking_to_time"]').value = end;
}
window.addEventListener('DOMContentLoaded', function() {
    calcEndTime();
    document.querySelector('[name="new_booking_from_time"]').addEventListener('change', calcEndTime);
});
</script>
</head>
<body>
<div class="main-container">
    <?php include '../includes/menu/menu_adm.php'; ?>
    <div class="content-container">
        <?php include '../includes/header_adm.php'; ?>
        <div class="profile-container">
            <h2>Edit Booking</h2>
            <form method="POST">
                <input type="hidden" name="booking_id" value="<?php echo htmlspecialchars($booking_id); ?>">
                <table>
                    <tr>
                        <td><label>Date</label></td>
                        <td>: <input type="date" name="new_booking_date" 
                            value="<?php echo htmlspecialchars($booking_date); ?>" required></td>
                    </tr>
                    <tr>
                        <td><label>Start Time</label></td>
                        <td>: <input type="time" name="new_booking_from_time" 
                            value="<?php echo htmlspecialchars($booking_from_time); ?>" required></td>
                    </tr>
                    <tr>
                        <td><label>End Time</label></td>
                        <td>: <input type="time" name="new_booking_to_time" 
                            value="<?php echo htmlspecialchars($booking_to_time); ?>" readonly required></td>
                    </tr>
                    <tr>
                        <td><label>Address</label></td>
                        <td>: <input type="textarea" name="new_full_address" 
                            value="<?php echo htmlspecialchars($full_address); ?>" required></td>
                    </tr>
                    <tr>
                        <td><label>Sinderella</label></td>
                        <td>: 
                            <select name="new_sind_id" required>
                                <?php foreach ($sind_list as $s): ?>
                                    <option value="<?php echo $s['sind_id']; ?>" <?php if ($s['sind_id'] == $sind_id) echo 'selected'; ?>>
                                        <?php echo htmlspecialchars($s['sind_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Service</strong></td>
                        <td>: <?php echo htmlspecialchars($service_name); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Customer</strong></td>
                        <td>: <?php echo htmlspecialchars($cust_name); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Add-ons</strong></td>
                        <td>
                            <ul>
                            <?php if (empty($addon_details)): ?>
                                <li>N/A</li>
                            <?php else: ?>
                                <?php foreach ($addon_details as $addon): ?>
                                    <li><?php echo htmlspecialchars($addon['desc']); ?></li>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            </ul>
                        </td>
                    </tr>
                    <tr>
                        <td><strong>Total</strong></td>
                        <td>: RM <?php echo number_format($total_price, 2); ?></td>
                    </tr>
                    <tr>
                        <td><strong>Status</strong></td>
                        <td>: <?php echo htmlspecialchars(ucfirst($booking_status)); ?></td>
                    </tr>
                </table>
                <button type="submit" name="save_changes">Save Changes</button>
                <button type="button" onclick="window.location.href='view_booking_details.php?booking_id=<?php echo $booking_id; ?>'">Cancel</button>
                <button type="submit" name="mark_paid" style="background:#28a745">Payment Received</button>
                <input type="hidden" id="total_duration" value="<?php echo htmlspecialchars($total_duration); ?>">
            </form>
        </div>
    </div>
</div>
</body>
</html>