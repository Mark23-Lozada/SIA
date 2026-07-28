<?php
// db_connect.php
$host     = 'localhost';
$username = 'root';
$password = ''; 
$dbname   = 'pos'; 

$conn = new mysqli($host, $username, $password, $dbname);

if ($conn->connect_error) {
    die("Connection failed: " . $conn->connect_error);
}
?>