<?php
session_start();
session_unset();
session_destroy();
header("Location: /Skillhive/index.php");
exit;