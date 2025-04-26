<?php
session_start();


if(isset($_SESSION['dev_authenticated'])) {

    unset($_SESSION['dev_authenticated']);
    
    // Optionally destroy the entire session
    // session_destroy();
}


header('Location: ./');
exit();
?>
