<?php
$hosts = ['127.0.0.1', 'localhost', '::1'];
$port = 3306;
$user = 'root';
$pass = ''; // Try empty first
$db = 'gims';
$log = '';

foreach ($hosts as $host) {
    $log .= "Testing host: $host ... ";
    try {
        $dsn = "mysql:host=$host;port=$port;dbname=$db";
        $pdo = new PDO($dsn, $user, $pass);
        $log .= "SUCCESS!\n";
    } catch (PDOException $e) {
        $log .= "FAILED: " . $e->getMessage() . "\n";
    }
}
file_put_contents('db_test.log', $log);
