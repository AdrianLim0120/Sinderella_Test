<?php
// $conn = new mysqli("localhost", "root", "", "sinderella_db");
 $conn = new mysqli("sql212.infinityfree.com", "if0_39899896", "j5yaUd1JfBFji", "if0_39899896_sinderella");
// $conn = new mysqli("mysql-sinderella.alwaysdata.net", "429732", "Adrian@0120", "sinderella_db");

$conn->set_charset('utf8mb4');

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>