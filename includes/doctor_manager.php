<?php
include("db.php");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Content-Type: application/json");

function getAllDoctors() {
    global $db;
    $stmt = $db->prepare("
        SELECT u.userid, u.firstname, u.lastname, u.email, u.contactnum, 
        p.licensenum, p.specialization, p.affiliation
        FROM user u
        JOIN practitioner p ON u.userid = p.userid
        WHERE p.type = 'doctor'
        ORDER BY u.lastname, u.firstname");
    $stmt->execute();
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}
?>