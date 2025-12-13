<?php
// patient_dashboard

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: POST, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type");
header('Content-Type: application/json; charset=utf-8');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/db.php'; 
if (!isset($db) || !($db instanceof mysqli)) {
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Database connection ($db) not found or not mysqli.']);
    exit;
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) $input = $_POST;

function req($name, $default = null) {
    global $input;
    return isset($input[$name]) ? $input[$name] : $default;
}

function respond($payload, $code = 200) {
    http_response_code($code);
    echo json_encode($payload);
    exit;
}


function phoneToString($num) {
    if ($num === null) return null;
  
    if ($num == 2147483647) return "";
    return (string)$num;
}


function handleDashboard($db) {
    $patientid = intval(req('patientid', 0));
    if ($patientid <= 0) {
        respond(['success' => false, 'error' => 'patientid required and must be > 0'], 400);
    }

   
    $sql = "SELECT p.prescriptionid, p.dateprescribed, p.validperiod,
                   d.userid AS doctorid,
                   du.firstname AS doctor_firstname, du.lastname AS doctor_lastname,
                   du.email AS doctor_email, du.contactnum AS doctor_contact
            FROM prescription p
            JOIN practitioner d ON p.doctorid = d.userid
            JOIN user du ON d.userid = du.userid
            WHERE p.patientid = ?
            ORDER BY p.dateprescribed DESC";
    $stmt = $db->prepare($sql);
    if (!$stmt) respond(['success'=>false,'error'=>'prepare failed: '.$db->error],500);
    $stmt->bind_param('i',$patientid);
    $stmt->execute();
    $stmt->bind_result($presc_id, $dateprescribed, $validperiod, $doctorid, $dfn, $dln, $demail, $dcontact);
    $prescriptions = [];
    while ($stmt->fetch()) {
        $prescriptions[] = [
            'prescriptionid' => (int)$presc_id,
            'dateprescribed' => $dateprescribed,
            'validperiod' => $validperiod,
            'doctor' => [
                'userid' => (int)$doctorid,
                'firstname' => $dfn,
                'lastname' => $dln,
                'email' => $demail,
                'contactnum' => phoneToString($dcontact),
            ]
        ];
    }
    $stmt->close();

    
    $sql2 = "SELECT pu.purchaseid, pu.purchasetimestamp, pu.prescriptionid,
                    ph.userid AS pharmacistid,
                    phu.firstname AS pharmacist_firstname, phu.lastname AS pharmacist_lastname,
                    phu.email AS pharmacist_email, phu.contactnum AS pharmacist_contact
             FROM purchase pu
             JOIN practitioner ph ON pu.pharmacistid = ph.userid
             JOIN user phu ON ph.userid = phu.userid
             WHERE pu.patientid = ?
             ORDER BY pu.purchasetimestamp DESC";
    $stmt2 = $db->prepare($sql2);
    if (!$stmt2) respond(['success'=>false,'error'=>'prepare failed: '.$db->error],500);
    $stmt2->bind_param('i',$patientid);
    $stmt2->execute();
    $stmt2->bind_result($purchaseid, $purchasetimestamp, $prescriptionid, $pharmacistid, $pfn, $pln, $pemail, $pcontact);
    $purchases = [];
    while ($stmt2->fetch()) {
        $purchases[] = [
            'purchaseid' => (int)$purchaseid,
            'purchasetimestamp' => $purchasetimestamp,
            'prescriptionid' => (int)$prescriptionid,
            'pharmacist' => [
                'userid' => (int)$pharmacistid,
                'firstname' => $pfn,
                'lastname' => $pln,
                'email' => $pemail,
                'contactnum' => phoneToString($pcontact),
            ]
        ];
    }
    $stmt2->close();

    respond(['success'=>true, 'data'=>['prescriptions'=>$prescriptions, 'purchases'=>$purchases]]);
}


function handleGetPrescription($db) {
    $prescriptionid = intval(req('prescriptionid', 0));
    if ($prescriptionid <= 0) respond(['success'=>false,'error'=>'prescriptionid required'],400);

    $sql = "SELECT p.prescriptionid, p.dateprescribed, p.validperiod,
                   d.userid AS doctorid, du.firstname, du.lastname, du.email, du.contactnum
            FROM prescription p
            JOIN practitioner d ON p.doctorid = d.userid
            JOIN user du ON d.userid = du.userid
            WHERE p.prescriptionid = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) respond(['success'=>false,'error'=>'prepare failed: '.$db->error],500);
    $stmt->bind_param('i',$prescriptionid);
    $stmt->execute();
    $stmt->bind_result($pid,$dateprescribed,$validperiod,$doctorid,$dfn,$dln,$demail,$dcontact);
    if (!$stmt->fetch()) {
        $stmt->close();
        respond(['success'=>false,'error'=>'prescription not found'],404);
    }
    $meta = [
        'prescriptionid' => (int)$pid,
        'dateprescribed' => $dateprescribed,
        'validperiod' => $validperiod,
        'doctor' => [
            'userid' => (int)$doctorid,
            'firstname' => $dfn,
            'lastname' => $dln,
            'email' => $demail,
            'contactnum' => phoneToString($dcontact),
        ]
    ];
    $stmt->close();

  
    $sql2 = "SELECT prescriptionitemid, name, brand, quantity, dosage, frequency, description, substitutions
             FROM prescriptionitem
             WHERE prescriptionid = ?
             ORDER BY prescriptionitemid";
    $stmt2 = $db->prepare($sql2);
    if (!$stmt2) respond(['success'=>false,'error'=>'prepare failed: '.$db->error],500);
    $stmt2->bind_param('i',$prescriptionid);
    $stmt2->execute();
    $stmt2->bind_result($itemid,$name,$brand,$quantity,$dosage,$frequency,$description,$substitutions);
    $items = [];
    while ($stmt2->fetch()) {
        $items[] = [
            'prescriptionitemid' => (int)$itemid,
            'name' => $name,
            'brand' => $brand,
            'quantity' => (int)$quantity,
            'dosage' => $dosage,
            'frequency' => (int)$frequency,
            'description' => $description,
            'substitutions' => (bool)$substitutions
        ];
    }
    $stmt2->close();

    respond(['success'=>true,'data'=>['meta'=>$meta,'items'=>$items]]);
}


function handleGetPrescriptionItem($db) {
    $itemid = intval(req('prescriptionitemid', 0));
    if ($itemid <= 0) respond(['success'=>false,'error'=>'prescriptionitemid required'],400);

    $sql = "SELECT pi.prescriptionitemid, pi.name, pi.brand, pi.quantity, pi.dosage, pi.frequency, pi.description, pi.substitutions,
                   p.prescriptionid, p.validperiod
            FROM prescriptionitem pi
            JOIN prescription p ON pi.prescriptionid = p.prescriptionid
            WHERE pi.prescriptionitemid = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) respond(['success'=>false,'error'=>'prepare failed: '.$db->error],500);
    $stmt->bind_param('i',$itemid);
    $stmt->execute();
    $stmt->bind_result($pid,$name,$brand,$quantity,$dosage,$frequency,$description,$substitutions,$prescriptionid,$validperiod);
    if (!$stmt->fetch()) {
        $stmt->close();
        respond(['success'=>false,'error'=>'prescription item not found'],404);
    }
    $stmt->close();

    $out = [
        'prescriptionitemid' => (int)$pid,
        'name' => $name,
        'brand' => $brand,
        'quantity' => (int)$quantity,
        'dosage' => $dosage,
        'frequency' => (int)$frequency,
        'description' => $description,
        'substitutions' => (bool)$substitutions,
        'prescriptionid' => (int)$prescriptionid,
        'validperiod' => $validperiod
    ];
    respond(['success'=>true,'data'=>$out]);
}


function handleGetPurchase($db) {
    $purchaseid = intval(req('purchaseid',0));
    if ($purchaseid <= 0) respond(['success'=>false,'error'=>'purchaseid required'],400);

  
    $sql = "SELECT pu.purchaseid, pu.purchasetimestamp, pu.patientid, pu.prescriptionid,
                   ph.userid AS pharmacistid, phu.firstname, phu.lastname, phu.email, phu.contactnum
            FROM purchase pu
            JOIN practitioner ph ON pu.pharmacistid = ph.userid
            JOIN user phu ON ph.userid = phu.userid
            WHERE pu.purchaseid = ?";
    $stmt = $db->prepare($sql);
    if (!$stmt) respond(['success'=>false,'error'=>'prepare failed: '.$db->error],500);
    $stmt->bind_param('i',$purchaseid);
    $stmt->execute();
    $stmt->bind_result($pid,$purchasetimestamp,$patientid,$prescriptionid,$phid,$pfn,$pln,$pemail,$pcontact);
    if (!$stmt->fetch()) { $stmt->close(); respond(['success'=>false,'error'=>'purchase not found'],404); }
    $meta = [
        'purchaseid' => (int)$pid,
        'purchasetimestamp' => $purchasetimestamp,
        'patientid' => (int)$patientid,
        'prescriptionid' => (int)$prescriptionid,
        'pharmacist' => [
            'userid' => (int)$phid,
            'firstname' => $pfn,
            'lastname' => $pln,
            'email' => $pemail,
            'contactnum' => phoneToString($pcontact),
        ]
    ];
    $stmt->close();

   
    $sql2 = "SELECT pi.purchaseitemid, pi.unitprice, pi.quantity, pi.totalprice, pi.precriptionitemid,
                    psi.name, psi.brand, psi.dosage
             FROM purchaseitem pi
             LEFT JOIN prescriptionitem psi ON pi.precriptionitemid = psi.prescriptionitemid
             WHERE pi.purchaseid = ?";
    $stmt2 = $db->prepare($sql2);
    if (!$stmt2) respond(['success'=>false,'error'=>'prepare failed: '.$db->error],500);
    $stmt2->bind_param('i',$purchaseid);
    $stmt2->execute();
    $stmt2->bind_result($purchaseitemid,$unitprice,$quantity,$totalprice,$precriptionitemid,$mname,$mbrand,$mdosage);
    $items = [];
    while ($stmt2->fetch()) {
        $items[] = [
            'purchaseitemid' => (int)$purchaseitemid,
            'precriptionitemid' => (int)$precriptionitemid,
            'name' => $mname,
            'brand' => $mbrand,
            'dosage' => $mdosage,
            'unitprice' => (int)$unitprice,
            'quantity' => (int)$quantity,
            'totalprice' => (int)$totalprice,
        ];
    }
    $stmt2->close();

    respond(['success'=>true,'data'=>['meta'=>$meta,'items'=>$items]]);
}


function handleGetPayments($db) {
    $purchaseid = intval(req('purchaseid',0));
    if ($purchaseid <= 0) respond(['success'=>false,'error'=>'purchaseid required'],400);

    $sql = "SELECT paymentid, amount, paymentdate, paymentmethod, status, purchaseid
            FROM payment
            WHERE purchaseid = ?
            ORDER BY paymentdate DESC";
    $stmt = $db->prepare($sql);
    if (!$stmt) respond(['success'=>false,'error'=>'prepare failed: '.$db->error],500);
    $stmt->bind_param('i',$purchaseid);
    $stmt->execute();
    $stmt->bind_result($paymentid,$amount,$paymentdate,$paymentmethod,$status,$purchaseid_r);
    $payments = [];
    while ($stmt->fetch()) {
        $payments[] = [
            'paymentid' => (int)$paymentid,
            'amount' => (int)$amount,
            'paymentdate' => $paymentdate,
            'paymentmethod' => $paymentmethod,
            'status' => $status,
        ];
    }
    $stmt->close();

    respond(['success'=>true,'data'=>['payments'=>$payments]]);
}

function handleViewReport($db) { // for testing (Postman)

    respond(['success'=>true,'data'=>['message'=>'report placeholder', 'available' => false]]);
}


$action = strtolower(trim(req('action','')));

switch ($action) {
    case 'dashboard':
        handleDashboard($db);
        break;
    case 'getprescription':
        handleGetPrescription($db);
        break;
    case 'getprescriptionitem':
        handleGetPrescriptionItem($db);
        break;
    case 'getpurchase':
        handleGetPurchase($db);
        break;
    case 'getpayments':
        handleGetPayments($db);
        break;
    case 'viewreport':
        handleViewReport($db);
        break;
    default:
        respond(['success'=>false,'error'=>'unknown action'],400);
}
