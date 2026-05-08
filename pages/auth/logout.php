<?php
session_start();
session_unset();
session_destroy();
header("Location: /SKILLHIVE/index.php");
exit;