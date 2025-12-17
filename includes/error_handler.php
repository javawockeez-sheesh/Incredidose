<?php
function sendError($code, $error_message){
    http_response_code($code);
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => $error_message]);
}
?>