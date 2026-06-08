<?php
header('Content-Type: application/json');
echo json_encode([
    'success' => true,
    'message' => 'API funcionando corretamente',
    'timestamp' => date('c')
]);
?>