<?php
include("db.php");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");

function getPatients($doctorid) {
	global $db;
    $stmt = $db->prepare("SELECT u.userid, u.firstname, u.lastname, u.email, u.contactnum, MAX(p.dateprescribed) AS dateprescribed FROM user u JOIN prescription p ON u.userid = p.patientid WHERE p.doctorid = ? GROUP BY u.userid");
    $stmt->execute([$doctorid]);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
    	$data[] = $row;
	}
	return $data;
}

function getPatientByName($doctorid, $patientname) {
	global $db;
	$stmt = $db->prepare("SELECT u.userid, u.firstname, u.lastname, u.email, u.contactnum, MAX(p.dateprescribed) AS dateprescribed FROM user u JOIN prescription p ON u.userid = p.patientid WHERE p.doctorid = ? AND (u.firstname LIKE ? OR u.lastname LIKE ?) GROUP BY u.userid");
    $stmt->execute([$doctorid, "%".$patientname."%", "%".$patientname."%"]);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
    	$data[] = $row;
	}
	return $data;
}

function getPatientByEmail($email) {
	global $db;
    $stmt = $db->prepare("SELECT u.userid, u.firstname, u.lastname, u.email, u.contactnum, MAX(p.dateprescribed) AS dateprescribed FROM user u JOIN prescription p ON u.userid = p.patientid WHERE u.email = ?");
    $stmt->execute([$emails]);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
    	$data[] = $row;
	}
	return $data;
}


function getPatientById($patientid) {
	global $db;
	$stmt = $db->prepare("SELECT * from User where userid = ?");
    $stmt->execute([$patientid]);
    $result = $stmt->get_result();
   	$data = [];
    while ($row = $result->fetch_assoc()) {
    	$data[] = $row;
	}
	return $data;
}

$action = $_GET['action'];

switch ($action) {
	case "getPatients":
		$doctorid = $_GET['doctorid'];
		header('Content-Type: application/json');
		echo json_encode(getPatients($doctorid));
		break;

	case "getPatientByName":
		$doctorid = $_GET['doctorid'];
		$patientname = $_GET['patientname'];
		header('Content-Type: application/json');
		echo json_encode(getPatientByName($doctorid, $patientname));
		break;

	case "getPatientByEmail":
		$email = $_GET['email'];
		header('Content-Type: application/json');
		echo json_encode(getPatients($email));
		break;

	case "getPatientById":
		$patientid = $_GET['patientid'];
		header('Content-Type: application/json');
		echo json_encode(getPatientById($patientid));
		break;
}
?>