<?php
// vhv/rewards.php - Redirect to integrated leaderboard & rewards hub
require_once __DIR__ . '/../config/session.php';

if (!isset($_SESSION['vhv_id'])) {
    header("Location: ../index.php");
    exit();
}

header("Location: leaderboard.php?tab=rewards");
exit();
