<?php
include("db.php");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

function getPrescriptions($patientid) {
	global $db;
    $stmt = $db->prepare("SELECT DISTINCT prescription.*, user.email, user.contactnum from prescription 
	INNER JOIN user ON prescription.patientid = user.userid WHERE patientid = ?");
    $stmt->execute([$patientid]);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
    	$data[] = $row;
	}
	return $data;
}

function addPrescription($validperiod, $patientid, $doctorid) {
	global $db;
	$stmt = $db->prepare("INSERT INTO prescription (validperiod, patientid, doctorid) VALUES (?, ?, ?)");
    $stmt-> execute([$validperiod, $patientid, $doctorid]);
    $id = $stmt->insert_id;
    $data = ["id" => $id];
	return $data;
}


$action = $_GET['action'];

switch ($action) {
	case "getPrescriptions":
		$patientid = $_GET['patientid'];
		header('Content-Type: application/json');
		echo json_encode(getPrescriptions($patientid));
		break;

	case "addPrescription":
		$validperiod = $_GET['validperiod'];
		$doctorid = $_GET['doctorid'];
		$patientid = $_GET['patientid'];
		echo json_encode(addPrescription($validperiod, $patientid, $doctorid));
		break;
}
?>