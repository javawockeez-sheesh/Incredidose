<?php
    $dbserver = "db";
    $dbuser = "root";
    $dbpass = "123";
    $dbname = "incredidose";
    $port = 3306;

    $db = new mysqli($dbserver, $dbuser, $dbpass, $dbname, $port);

     if ($db->connect_error) {
        die("Database connection failed: " . $db->connect_error);
    }
?>