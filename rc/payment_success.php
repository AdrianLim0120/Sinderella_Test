<?php

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

if (isset($_GET['status_id']) && $_GET['billcode']) {
    $status = $_GET['status_id']; // 1 = successful
    $booking_id = $_GET['order_id'];

    require_once '../db_connect.php'; //
    if ($status == 1) {
        $conn->query("UPDATE bookings SET booking_status='paid' WHERE booking_id=$booking_id");
        $conn->query("UPDATE booking_payments SET payment_status='confirmed' WHERE booking_id=$booking_id");
        // echo "<h2>🎉 Payment Successful! Your booking is confirmed.</h2>";
        
        // Ensure sind_available_time entry exists for this booking
        // Get booking details
        $bookingRes = $conn->query("SELECT sind_id, booking_date FROM bookings WHERE booking_id=$booking_id");
        if ($bookingRes && $bookingRes->num_rows > 0) {
            $booking = $bookingRes->fetch_assoc();
            $sind_id = $booking['sind_id'];
            $booking_date = $booking['booking_date'];
            $day_of_week = date('l', strtotime($booking_date)); 

            // Check if entry exists in sind_available_time
            $checkRes = $conn->query("SELECT schedule_id FROM sind_available_time WHERE sind_id=$sind_id AND available_date='$booking_date'");
            if ($checkRes && $checkRes->num_rows == 0) {
                // Get available_from1 and available_from2 from sind_available_day
                $dayRes = $conn->query("SELECT available_from1, available_from2 FROM sind_available_day WHERE sind_id=$sind_id AND day_of_week='$day_of_week' LIMIT 1");
                if ($dayRes && $dayRes->num_rows > 0) {
                    $day = $dayRes->fetch_assoc();
                    // $from1 = $day['available_from1'] ? "'{$day['available_from1']}'" : "NULL";
                    // $from2 = $day['available_from2'] ? "'{$day['available_from2']}'" : "NULL";
                    $from1 = ($day['available_from1'] && $day['available_from1'] !== '00:00:00') ? "'{$day['available_from1']}'" : "NULL";
                    $from2 = ($day['available_from2'] && $day['available_from2'] !== '00:00:00') ? "'{$day['available_from2']}'" : "NULL";
                    // Insert into sind_available_time
                    $conn->query("INSERT INTO sind_available_time (sind_id, available_date, available_from1, available_from2) VALUES ($sind_id, '$booking_date', $from1, $from2)");
                }
            }
        }

        // Calculate and update booking payment breakdown
        $bookingRes = $conn->query("SELECT sind_id, service_id, booking_id FROM bookings WHERE booking_id=$booking_id");
        if ($bookingRes && $bookingRes->num_rows > 0) {
            $booking = $bookingRes->fetch_assoc();
            $sind_id = $booking['sind_id'];
            $service_id = $booking['service_id'];

            // Get service price
            $serviceRes = $conn->query("SELECT service_price FROM service_pricing WHERE service_id=$service_id");
            $service_price = 0;
            if ($serviceRes && $serviceRes->num_rows > 0) {
                $service_price = floatval($serviceRes->fetch_assoc()['service_price']);
            }

            // Get pricing breakdown
            $pricingRes = $conn->query("SELECT pr_platform, pr_sind, pr_lvl1, pr_lvl2, pr_lvl3, pr_lvl4 FROM pricing WHERE service_id=$service_id");
            $pr_platform = $pr_sind = $pr_lvl1 = $pr_lvl2 = $pr_lvl3 = $pr_lvl4 = 0;
            if ($pricingRes && $pricingRes->num_rows > 0) {
                $pricing = $pricingRes->fetch_assoc();
                $pr_platform = floatval($pricing['pr_platform']);
                $pr_sind = floatval($pricing['pr_sind']);
                $pr_lvl1 = floatval($pricing['pr_lvl1']);
                $pr_lvl2 = floatval($pricing['pr_lvl2']);
                $pr_lvl3 = floatval($pricing['pr_lvl3']);
                $pr_lvl4 = floatval($pricing['pr_lvl4']);
            }

            // Get booking addons
            $addonRes = $conn->query("SELECT ba.ao_id, a.ao_price, a.ao_platform, a.ao_sind FROM booking_addons ba JOIN addon a ON ba.ao_id = a.ao_id WHERE ba.booking_id=$booking_id");
            $ao_price = $ao_platform = $ao_sind = 0;
            if ($addonRes && $addonRes->num_rows > 0) {
                while ($addon = $addonRes->fetch_assoc()) {
                    $ao_price += floatval($addon['ao_price']);
                    $ao_platform += floatval($addon['ao_platform']);
                    $ao_sind += floatval($addon['ao_sind']);
                }
            }

            // Calculate totals
            $bp_total = $service_price + $ao_price;
            $bp_platform = $pr_platform + $ao_platform;
            $bp_sind = $pr_sind + $ao_sind;

            // Update bookings table
            $conn->query("UPDATE bookings SET 
                bp_total = $bp_total,
                bp_platform = $bp_platform,
                bp_sind = $bp_sind,
                bp_lvl1 = $pr_lvl1,
                bp_lvl2 = $pr_lvl2,
                bp_lvl3 = $pr_lvl3,
                bp_lvl4 = $pr_lvl4
                WHERE booking_id = $booking_id
            ");
        }

        echo "<script>
            alert('🎉 Payment Successful! Your booking is paid.');
            window.location.href = 'my_booking.php?search_date=&search_status=paid';
        </script>";
    } else {
        // echo "<h2>❌ Payment Failed or Cancelled.</h2>";
        echo "<script>
            alert('❌ Payment Failed or Cancelled.');
            window.location.href = 'my_booking.php?search_date=&search_status=pending';
        </script>";
    }
    // $conn->close();
} else {
    echo "<h2>Invalid access.</h2>";
}
