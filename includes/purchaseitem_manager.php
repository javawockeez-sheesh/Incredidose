<?php
include("db.php");

header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, OPTIONS, PUT, DELETE");
header("Access-Control-Allow-Headers: Content-Type");
header("Access-Control-Allow-Credentials: true");

session_start();

// =============== AUTHENTICATION FUNCTIONS ===============
function isAuthenticated() {
    return isset($_SESSION['userid']) && isset($_SESSION['role']) && isset($_SESSION['email']);
}

function hasRequiredRole($requiredRoles) {
    if (!isAuthenticated()) {
        return false;
    }
    
    if (!is_array($requiredRoles)) {
        $requiredRoles = [$requiredRoles];
    }
    
    return in_array($_SESSION['role'], $requiredRoles);
}

// Check if user is pharmacist (type = 'pharmacist' in practitioner table)
function isPharmacist() {
    if (!isAuthenticated() || $_SESSION['role'] !== 'pcr') {
        return false;
    }
    
    // Check practitioner type if not already in session
    if (!isset($_SESSION['practitioner_type'])) {
        global $db;
        $stmt = $db->prepare("SELECT type FROM practitioner WHERE userid = ?");
        $stmt->execute([$_SESSION['userid']]);
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $row = $result->fetch_assoc();
            $_SESSION['practitioner_type'] = $row['type'];
        } else {
            return false;
        }
    }
    
    return $_SESSION['practitioner_type'] === 'pharmacist';
}

function isAdminOnly() {
    return isAuthenticated() && $_SESSION['role'] === 'admn';
}

// =============== PURCHASE ITEM FUNCTIONS ===============

// Get all purchase items for a purchase
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

// Get purchase item by ID
function getPurchaseItemById($purchaseitemid) {
    global $db;
    $stmt = $db->prepare("
        SELECT 
            pi.*,
            presi.name as medication_name,
            presi.brand as medication_brand,
            presi.dosage,
            presi.quantity as prescribed_quantity,
            presi.description,
            p.patientid,
            p.pharmacistid,
            pat.firstname as patient_firstname,
            pat.lastname as patient_lastname
        FROM purchaseitem pi
        JOIN prescriptionitem presi ON pi.precriptionitemid = presi.prescriptionitemid
        JOIN purchase p ON pi.purchaseid = p.purchaseid
        JOIN user pat ON p.patientid = pat.userid
        WHERE pi.purchaseitemid = ?
    ");
    $stmt->execute([$purchaseitemid]);
    $result = $stmt->get_result();
    if ($result->num_rows === 0) {
        return null;
    }
    return $result->fetch_assoc();
}

// Add purchase item to a purchase
function addPurchaseItem($purchaseitemData) {
    global $db;
    
    // Validate required fields
    $requiredFields = ['purchaseid', 'precriptionitemid', 'unitprice', 'quantity'];
    foreach ($requiredFields as $field) {
        if (!isset($purchaseitemData[$field]) || $purchaseitemData[$field] === '') {
            return ['success' => false, 'error' => "Missing required field: $field"];
        }
    }
    
    // Check if purchase exists and get pharmacist ID
    $purchaseCheck = $db->prepare("SELECT purchaseid, pharmacistid FROM purchase WHERE purchaseid = ?");
    $purchaseCheck->execute([$purchaseitemData['purchaseid']]);
    $purchase = $purchaseCheck->get_result()->fetch_assoc();
    
    if (!$purchase) {
        return ['success' => false, 'error' => 'Purchase not found'];
    }
    
    // Check if current user is the pharmacist who created this purchase
    if ($purchase['pharmacistid'] != $_SESSION['userid']) {
        return ['success' => false, 'error' => 'You can only add items to your own purchases'];
    }
    
    // Check if prescription item exists
    $prescriptionItemCheck = $db->prepare("
        SELECT prescriptionitemid, quantity as max_quantity 
        FROM prescriptionitem 
        WHERE prescriptionitemid = ?
    ");
    $prescriptionItemCheck->execute([$purchaseitemData['precriptionitemid']]);
    $prescriptionItem = $prescriptionItemCheck->get_result()->fetch_assoc();
    
    if (!$prescriptionItem) {
        return ['success' => false, 'error' => 'Prescription item not found'];
    }
    
    // Check if quantity exceeds prescribed quantity
    if ($purchaseitemData['quantity'] > $prescriptionItem['max_quantity']) {
        return ['success' => false, 'error' => 'Quantity exceeds prescribed amount'];
    }
    
    // Calculate total price
    $totalprice = $purchaseitemData['unitprice'] * $purchaseitemData['quantity'];
    
    // Check if this prescription item is already in the purchase
    $existingCheck = $db->prepare("
        SELECT purchaseitemid 
        FROM purchaseitem 
        WHERE purchaseid = ? AND precriptionitemid = ?
    ");
    $existingCheck->execute([$purchaseitemData['purchaseid'], $purchaseitemData['precriptionitemid']]);
    if ($existingCheck->get_result()->num_rows > 0) {
        return ['success' => false, 'error' => 'This medication is already in the purchase'];
    }
    
    // Insert purchase item
    $stmt = $db->prepare("
        INSERT INTO purchaseitem (purchaseid, unitprice, quantity, totalprice, precriptionitemid) 
        VALUES (?, ?, ?, ?, ?)
    ");
    
    $stmt->execute([
        $purchaseitemData['purchaseid'],
        $purchaseitemData['unitprice'],
        $purchaseitemData['quantity'],
        $totalprice,
        $purchaseitemData['precriptionitemid']
    ]);
    
    if ($stmt->affected_rows > 0) {
        $purchaseItemId = $db->insert_id;
        
        // Update prescription item quantity (reduce available quantity)
        $updatePrescription = $db->prepare("
            UPDATE prescriptionitem 
            SET quantity = quantity - ? 
            WHERE prescriptionitemid = ?
        ");
        $updatePrescription->execute([$purchaseitemData['quantity'], $purchaseitemData['precriptionitemid']]);
        
        return [
            'success' => true,
            'message' => 'Purchase item added successfully',
            'purchaseitemid' => $purchaseItemId,
            'totalprice' => $totalprice
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to add purchase item'];
}

// Update purchase item
function updatePurchaseItem($purchaseitemid, $purchaseitemData) {
    global $db;
    
    // Get current purchase item data
    $currentItem = getPurchaseItemById($purchaseitemid);
    if (!$currentItem) {
        return ['success' => false, 'error' => 'Purchase item not found'];
    }
    
    // Check if current user is the pharmacist who owns this purchase
    if ($currentItem['pharmacistid'] != $_SESSION['userid']) {
        return ['success' => false, 'error' => 'You can only update items in your own purchases'];
    }
    
    // Validate required fields
    $requiredFields = ['unitprice', 'quantity'];
    foreach ($requiredFields as $field) {
        if (!isset($purchaseitemData[$field]) || $purchaseitemData[$field] === '') {
            return ['success' => false, 'error' => "Missing required field: $field"];
        }
    }
    
    // Calculate quantity difference
    $quantityDiff = $purchaseitemData['quantity'] - $currentItem['quantity'];
    
    // Check prescription item quantity limits
    $prescriptionItemCheck = $db->prepare("
        SELECT quantity as available_quantity 
        FROM prescriptionitem 
        WHERE prescriptionitemid = ?
    ");
    $prescriptionItemCheck->execute([$currentItem['precriptionitemid']]);
    $prescriptionItem = $prescriptionItemCheck->get_result()->fetch_assoc();
    
    // If increasing quantity, check if enough is available
    if ($quantityDiff > 0 && $quantityDiff > $prescriptionItem['available_quantity']) {
        return ['success' => false, 'error' => 'Insufficient available medication quantity'];
    }
    
    // Calculate new total price
    $totalprice = $purchaseitemData['unitprice'] * $purchaseitemData['quantity'];
    
    // Update purchase item
    $stmt = $db->prepare("
        UPDATE purchaseitem 
        SET unitprice = ?, quantity = ?, totalprice = ? 
        WHERE purchaseitemid = ?
    ");
    
    $stmt->execute([
        $purchaseitemData['unitprice'],
        $purchaseitemData['quantity'],
        $totalprice,
        $purchaseitemid
    ]);
    
    if ($stmt->affected_rows > 0) {
        // Update prescription item quantity
        if ($quantityDiff != 0) {
            $updatePrescription = $db->prepare("
                UPDATE prescriptionitem 
                SET quantity = quantity - ? 
                WHERE prescriptionitemid = ?
            ");
            $updatePrescription->execute([$quantityDiff, $currentItem['precriptionitemid']]);
        }
        
        return [
            'success' => true,
            'message' => 'Purchase item updated successfully',
            'totalprice' => $totalprice
        ];
    }
    
    return ['success' => false, 'error' => 'No changes made or failed to update purchase item'];
}

// Delete purchase item
function deletePurchaseItem($purchaseitemid) {
    global $db;
    
    // Get current purchase item data
    $currentItem = getPurchaseItemById($purchaseitemid);
    if (!$currentItem) {
        return ['success' => false, 'error' => 'Purchase item not found'];
    }
    
    // Check if current user is the pharmacist who owns this purchase
    if ($currentItem['pharmacistid'] != $_SESSION['userid']) {
        return ['success' => false, 'error' => 'You can only delete items from your own purchases'];
    }
    
    // Check if purchase is already paid
    $paymentCheck = $db->prepare("
        SELECT status FROM payment WHERE purchaseid = ?
    ");
    $paymentCheck->execute([$currentItem['purchaseid']]);
    $payment = $paymentCheck->get_result()->fetch_assoc();
    
    if ($payment && $payment['status'] === 'Completed') {
        return ['success' => false, 'error' => 'Cannot delete items from a completed purchase'];
    }
    
    // Delete purchase item
    $stmt = $db->prepare("DELETE FROM purchaseitem WHERE purchaseitemid = ?");
    $stmt->execute([$purchaseitemid]);
    
    if ($stmt->affected_rows > 0) {
        // Return quantity to prescription item
        $updatePrescription = $db->prepare("
            UPDATE prescriptionitem 
            SET quantity = quantity + ? 
            WHERE prescriptionitemid = ?
        ");
        $updatePrescription->execute([$currentItem['quantity'], $currentItem['precriptionitemid']]);
        
        return [
            'success' => true,
            'message' => 'Purchase item deleted successfully'
        ];
    }
    
    return ['success' => false, 'error' => 'Failed to delete purchase item'];
}

// Calculate purchase total
function calculatePurchaseTotal($purchaseid) {
    global $db;
    $stmt = $db->prepare("
        SELECT SUM(totalprice) as total_amount 
        FROM purchaseitem 
        WHERE purchaseid = ?
    ");
    $stmt->execute([$purchaseid]);
    $result = $stmt->get_result();
    $row = $result->fetch_assoc();
    return $row['total_amount'] ?: 0;
}

// =============== REQUEST HANDLING ===============

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Helper function to get JSON body
function getJsonBody() {
    $input = file_get_contents('php://input');
    if (!$input) return [];
    $data = json_decode($input, true);
    return is_array($data) ? $data : [];
}

switch ($action) {
    case "getPurchaseItemsByPurchase":
        // Check authentication - pharmacist, admin, and patient can access
        if (!isAuthenticated()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized access. Please login first.']);
            break;
        }
        
        if (!isset($_GET['purchaseid'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'purchaseid parameter is required']);
            break;
        }
        
        $purchaseid = $_GET['purchaseid'];
        $items = getPurchaseItemsByPurchase($purchaseid);
        
        // Get purchase info for permission check
        $purchaseCheck = $db->prepare("SELECT patientid FROM purchase WHERE purchaseid = ?");
        $purchaseCheck->execute([$purchaseid]);
        $purchase = $purchaseCheck->get_result()->fetch_assoc();
        
        if (!$purchase) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Purchase not found']);
            break;
        }
        
        // Check if user has permission to view these items
        if ($_SESSION['role'] === 'ptnt' && $_SESSION['userid'] != $purchase['patientid'] && 
            !hasRequiredRole(['pcr', 'admn'])) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Access denied']);
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode($items);
        break;

    case "getPurchaseItemById":
        // Check authentication - pharmacist and admin can access
        if (!isAuthenticated() || !hasRequiredRole(['pcr', 'admn'])) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized access. Please login first.']);
            break;
        }
        
        if (!isset($_GET['purchaseitemid'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'purchaseitemid parameter is required']);
            break;
        }
        
        $purchaseitemid = $_GET['purchaseitemid'];
        $item = getPurchaseItemById($purchaseitemid);
        
        if (!$item) {
            http_response_code(404);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Purchase item not found']);
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode($item);
        break;
        
    case "addPurchaseItem":
        // EXCLUSIVE: Only pharmacists can add purchase items
        if (!isPharmacist()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'error' => 'Access denied. Only pharmacists can add purchase items.'
            ]);
            break;
        }
        
        // Get POST data
        $data = getJsonBody();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Method not allowed. Use POST']);
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode(addPurchaseItem($data));
        break;
        
    case "updatePurchaseItem":
        // EXCLUSIVE: Only pharmacists can update purchase items
        if (!isPharmacist()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'error' => 'Access denied. Only pharmacists can update purchase items.'
            ]);
            break;
        }
        
        // Get PUT/POST data
        $data = getJsonBody();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'PUT' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Method not allowed. Use PUT or POST']);
            break;
        }
        
        $purchaseitemid = isset($_GET['purchaseitemid']) ? $_GET['purchaseitemid'] : (isset($data['purchaseitemid']) ? $data['purchaseitemid'] : null);
        
        if (!$purchaseitemid) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Purchase Item ID is required']);
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode(updatePurchaseItem($purchaseitemid, $data));
        break;
        
    case "deletePurchaseItem":
        // EXCLUSIVE: Only pharmacists can delete purchase items
        if (!isPharmacist()) {
            http_response_code(403);
            header('Content-Type: application/json');
            echo json_encode([
                'success' => false, 
                'error' => 'Access denied. Only pharmacists can delete purchase items.'
            ]);
            break;
        }
        
        // Get DELETE data
        $data = getJsonBody();
        
        if ($_SERVER['REQUEST_METHOD'] !== 'DELETE' && $_SERVER['REQUEST_METHOD'] !== 'POST') {
            http_response_code(405);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Method not allowed. Use DELETE or POST']);
            break;
        }
        
        $purchaseitemid = isset($_GET['purchaseitemid']) ? $_GET['purchaseitemid'] : (isset($data['purchaseitemid']) ? $data['purchaseitemid'] : null);
        
        if (!$purchaseitemid) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Purchase Item ID is required']);
            break;
        }
        
        header('Content-Type: application/json');
        echo json_encode(deletePurchaseItem($purchaseitemid));
        break;
        
    case "calculatePurchaseTotal":
        // Check authentication - pharmacist, admin, and patient can access
        if (!isAuthenticated()) {
            http_response_code(401);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'Unauthorized access. Please login first.']);
            break;
        }
        
        if (!isset($_GET['purchaseid'])) {
            http_response_code(400);
            header('Content-Type: application/json');
            echo json_encode(['success' => false, 'error' => 'purchaseid parameter is required']);
            break;
        }
        
        $purchaseid = $_GET['purchaseid'];
        $total = calculatePurchaseTotal($purchaseid);
        
        header('Content-Type: application/json');
        echo json_encode(['success' => true, 'total_amount' => $total]);
        break;
        
    default:
        header('Content-Type: application/json');
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'Invalid action']);
        break;
}
?>