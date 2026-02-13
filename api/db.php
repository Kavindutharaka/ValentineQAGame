<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

$host = 'localhost';
$dbname = 'temptation_game';
$username = 'root';
$password = 'Abc@1234';

try {
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    $pdo->exec("CREATE DATABASE IF NOT EXISTS `$dbname`");
    $pdo->exec("USE `$dbname`");

    $pdo->exec("CREATE TABLE IF NOT EXISTS `game_session` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `status` INT DEFAULT 0 COMMENT '0=idle, 1=started, 2=names_filled, 3=playing, 4=finished',
        `player_a_name` VARCHAR(100) DEFAULT NULL,
        `player_a_mobile` VARCHAR(20) DEFAULT NULL,
        `player_a_ready` TINYINT DEFAULT 0,
        `player_b_name` VARCHAR(100) DEFAULT NULL,
        `player_b_mobile` VARCHAR(20) DEFAULT NULL,
        `player_b_ready` TINYINT DEFAULT 0,
        `questions` TEXT DEFAULT NULL,
        `player_a_answers` TEXT DEFAULT NULL,
        `player_b_answers` TEXT DEFAULT NULL,
        `player_a_done` TINYINT DEFAULT 0,
        `player_b_done` TINYINT DEFAULT 0,
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Permanent reports table — never truncated
    $pdo->exec("CREATE TABLE IF NOT EXISTS `game_reports` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `player_a_name` VARCHAR(100) DEFAULT NULL,
        `player_a_mobile` VARCHAR(20) DEFAULT NULL,
        `player_b_name` VARCHAR(100) DEFAULT NULL,
        `player_b_mobile` VARCHAR(20) DEFAULT NULL,
        `questions` TEXT DEFAULT NULL,
        `player_a_answers` TEXT DEFAULT NULL,
        `player_b_answers` TEXT DEFAULT NULL,
        `match_count` INT DEFAULT 0,
        `total_questions` INT DEFAULT 5,
        `played_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");

    // Helper function: save completed game to reports before clearing session
    function saveGameReport($pdo) {
        $stmt = $pdo->query("SELECT * FROM `game_session` ORDER BY id DESC LIMIT 1");
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        // Only save if both players have names and answers
        if ($session && $session['player_a_name'] && $session['player_b_name']
            && $session['player_a_answers'] && $session['player_b_answers']) {

            // Calculate match count
            $questions = json_decode($session['questions'], true);
            $aAnswers = json_decode($session['player_a_answers'], true);
            $bAnswers = json_decode($session['player_b_answers'], true);
            $matchCount = 0;

            if ($aAnswers && $bAnswers) {
                for ($i = 0; $i < count($aAnswers); $i++) {
                    if (isset($aAnswers[$i]['answer']) && isset($bAnswers[$i]['answer'])
                        && $aAnswers[$i]['answer'] === $bAnswers[$i]['answer']) {
                        $matchCount++;
                    }
                }
            }

            $insert = $pdo->prepare("INSERT INTO `game_reports`
                (`player_a_name`, `player_a_mobile`, `player_b_name`, `player_b_mobile`,
                 `questions`, `player_a_answers`, `player_b_answers`, `match_count`, `total_questions`)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $insert->execute([
                $session['player_a_name'],
                $session['player_a_mobile'],
                $session['player_b_name'],
                $session['player_b_mobile'],
                $session['questions'],
                $session['player_a_answers'],
                $session['player_b_answers'],
                $matchCount,
                count($aAnswers ?: [])
            ]);

            // Add couple as one raffle entry: "PlayerA - PlayerB"
            $coupleName = $session['player_a_name'] . ' - ' . $session['player_b_name'];
            $coupleMobile = $session['player_a_mobile'] . ' / ' . $session['player_b_mobile'];
            $raffleInsert = $pdo->prepare("INSERT INTO `raffle_entries` (`name`, `mobile`, `source`) VALUES (?, ?, 'couple')");
            $raffleInsert->execute([$coupleName, $coupleMobile]);

            return true;
        }
        return false;
    }

    // Raffle draw entries — session-based, gets truncated on reset session
    $pdo->exec("CREATE TABLE IF NOT EXISTS `raffle_entries` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(200) NOT NULL,
        `mobile` VARCHAR(50) DEFAULT NULL,
        `source` VARCHAR(10) DEFAULT 'couple' COMMENT 'couple entry',
        `created_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    // Widen mobile column if it was created with old size
    $pdo->exec("ALTER TABLE `raffle_entries` MODIFY `mobile` VARCHAR(50) DEFAULT NULL");

    // Raffle sessions — tracks each raffle session start/end
    $pdo->exec("CREATE TABLE IF NOT EXISTS `raffle_sessions` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `started_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        `ended_at` TIMESTAMP NULL DEFAULT NULL,
        `status` VARCHAR(20) DEFAULT 'active' COMMENT 'active or ended'
    )");

    // Raffle winners — permanent record, never truncated
    $pdo->exec("CREATE TABLE IF NOT EXISTS `raffle_winners` (
        `id` INT AUTO_INCREMENT PRIMARY KEY,
        `name` VARCHAR(200) NOT NULL,
        `mobile` VARCHAR(50) DEFAULT NULL,
        `session_id` INT DEFAULT NULL,
        `session_label` VARCHAR(100) DEFAULT NULL,
        `won_at` TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    // Add session_id column if missing (upgrade from old schema)
    try { $pdo->exec("ALTER TABLE `raffle_winners` ADD COLUMN `session_id` INT DEFAULT NULL AFTER `mobile`"); } catch(PDOException $e) {}
    // Widen mobile column if it was created with old size
    $pdo->exec("ALTER TABLE `raffle_winners` MODIFY `mobile` VARCHAR(50) DEFAULT NULL");

} catch (PDOException $e) {
    echo json_encode(['error' => $e->getMessage()]);
    exit();
}
