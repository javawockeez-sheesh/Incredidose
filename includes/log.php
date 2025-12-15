<?php
function logAction($action, $description, $targetEntityType, $targetId) {
    global $db;
    
    if (!isset($_SESSION['userid'])) return;
    
    $stmt = $db->prepare("
        INSERT INTO log (action, description, timestamp, targetentitytype, targetid, userid) 
        VALUES (?, ?, NOW(), ?, ?, ?)
    ");
    
    $stmt->execute([$action, $description, $targetEntityType, $targetId, $_SESSION['userid']]);
}
?>