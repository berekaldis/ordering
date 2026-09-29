<?php
/**
 * Kaldis Coffee ECA Branch Admin Index Redirect
 */
require_once '../config.php';

if (isAdminLoggedIn()) {
    header('Location: dashboard.php');
} else {
    header('Location: login.php');
}
exit;
