<?php
require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

try {
    $db = getDB();
    $db->exec("ALTER TABLE access_requests MODIFY type ENUM('view_credential', 'add_to_company', 'invite_technician') NOT NULL");
    $db->exec("ALTER TABLE access_requests MODIFY message LONGTEXT");
    echo "Success!";
} catch (Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
