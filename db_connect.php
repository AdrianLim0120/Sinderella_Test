<?php
$conn = new mysqli("localhost", "root", "", "sinderella_db");
// $conn = new mysqli("sql310.infinityfree.com", "if0_39280267", "Sinderella666", "if0_39280267_sinderella_db");

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>