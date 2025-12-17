<?php
include("db.php");
include("error_handler.php");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

session_start();


if (!isset($_SESSION['userid'])) {
    sendError(401, "User not logged in.");
    return;
}


function isDoctor() {
    return $_SESSION['role'] == 'doctor';
}

function isAdmin() {
    return $_SESSION['role'] == 'admn';
}

function isPatient(){
    return $_SESSION['role'] == 'ptnt'; 
}

function isPharmacist(){
    return $_SESSION['role'] == 'pharmacist'; 
}


function getPurchaseItemsByPurchase($purchaseid) {
    global $db;
    $stmt = $db->prepare("
        SELECT 
            pi.*,
            presi.name as medication_name,
            presi.brand as medication_brand,
            presi.dosage,
            presi.quantity as prescribed_quantity,
            presi.description
        FROM purchaseitem pi
        JOIN prescriptionitem presi ON pi.precriptionitemid = presi.prescriptionitemid
        WHERE pi.purchaseid = ?
        ORDER BY pi.purchaseitemid
    ");
    $stmt->execute([$purchaseid]);
    $result = $stmt->get_result();
    $data = [];
    while ($row = $result->fetch_assoc()) {
        $data[] = $row;
    }
    return $data;
}

function addPurchaseItem($purchaseitemData) {
    global $db;
    
    $requiredFields = ['purchaseid', 'prescriptionitemid', 'unitprice', 'quantity'];
    foreach ($requiredFields as $field) {
        if (!isset($purchaseitemData[$field]) || $purchaseitemData[$field] === '') {
            return ['success' => false, 'error' => "Missing required field: $field"];
        }
    }
    
    $purchaseCheck = $db->prepare("SELECT purchaseid, pharmacistid FROM purchase WHERE purchaseid = ?");
    $purchaseCheck->execute([$purchaseitemData['purchaseid']]);
    $purchase = $purchaseCheck->get_result()->fetch_assoc();
    
    if (!$purchase) {
        return ['success' => false, 'error' => 'Purchase not found'];
    }
    
    if ($purchase['pharmacistid'] != $_SESSION['userid']) {
        return ['success' => false, 'error' => 'You can only add items to your own purchases'];
    }
    
    $prescriptionItemCheck = $db->prepare("
        SELECT prescriptionitemid, quantity as max_quantity 
        FROM prescriptionitem 
        WHERE prescriptionitemid = ?
    ");
    $prescriptionItemCheck->execute([$purchaseitemData['prescriptionitemid']]);
    $prescriptionItem = $prescriptionItemCheck->get_result()->fetch_assoc();
    
    if (!$prescriptionItem) {
        return ['success' => false, 'error' => 'Prescription item not found'];
    }
    
    if ($purchaseitemData['quantity'] > $prescriptionItem['max_quantity']) {
        return ['success' => false, 'error' => 'Quantity exceeds prescribed amount'];
    }
    
    $totalprice = $purchaseitemData['unitprice'] * $purchaseitemData['quantity'];
    
    $existingCheck = $db->prepare("
        SELECT purchaseitemid 
        FROM purchaseitem 
        WHERE purchaseid = ? AND prescriptionitemid = ?
    ");

    $existingCheck->execute([$purchaseitemData['purchaseid'], $purchaseitemData['prescriptionitemid']]);
    if ($existingCheck->get_result()->num_rows > 0) {
        return ['success' => false, 'error' => 'This medication is already in the purchase'];
    }
    
    $stmt = $db->prepare("
        INSERT INTO purchaseitem (purchaseid, unitprice, quantity, totalprice, prescriptionitemid) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $purchaseitemData['purchaseid'],
        $purchaseitemData['unitprice'],
        $purchaseitemData['quantity'],
        $totalprice,
        $purchaseitemData['prescriptionitemid']
    ]);
    
    if ($stmt->affected_rows > 0) {
        $purchaseItemId = $db->insert_id;
        $logStmt = $db->prepare("INSERT INTO log (action, description, timestamp, targetentitytype, targetid, userid) VALUES (?, ?, NOW(), ?, ?, ?)");
        $logStmt->execute([
            'PHARMACY_DISPENSE',
            'Added purchase item ID: ' . $purchaseItemId . ' to purchase ID: ' . $purchaseitemData['purchaseid'],
            'purchaseitem',
            $purchaseItemId,
            $_SESSION['userid']
        ]);
        
        return [
            'success' => true,
            'message' => 'Purchase item added successfully',
            'purchaseitemid' => $purchaseItemId,
            'totalprice' => $totalprice
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to add purchase item'];
}

$action = isset($_GET['action']) ? $_GET['action'] : '';

function getJsonBody() {
    $input = file_get_contents('php://input');
    if (!$input) return [];
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

switch ($action) {
    case "getPurchaseItemsByPurchase":
        if (!isset($_GET['purchaseid'])) {
            sendError(400, "Purchase id is required");
            break;
        }
        
        $purchaseid = $_GET['purchaseid'];
        $items = getPurchaseItemsByPurchase($purchaseid);
        
        if (isPatient() && $_SESSION['userid'] != $purchase['patientid']) {
            sendError(401, "Unauthorized Access");
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode($items);
        break;

        
    case "addPurchaseItem":

        if(!isPharmacist()){
            sendError(401, "Unauthorized Access");
            break;
        }

        $data = getJsonBody();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            sendError(400, "Invalid Method");
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode(addPurchaseItem($data));
        break;
        
        
    default:
        sendError(400, "Invalid Action");
        break;
}
?>