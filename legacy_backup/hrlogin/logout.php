<?php
if (isset($_GET['type']) && $_GET['type'] === 'employee') {
    session_name('HRSESSID');
    session_start();
    session_destroy();
    header('Location: index1.html');
    exit;
} else {
    session_start();
    session_destroy();
    header('Location: /');
    exit;
}
?>