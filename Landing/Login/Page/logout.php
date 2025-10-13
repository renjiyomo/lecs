<?php
session_start();
session_unset();
session_destroy();
header("Location: /lecs/Landing/Login/login.php");
exit;
?>
