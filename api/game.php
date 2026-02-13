<?php
require_once 'db.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ($action) {

    // Index.html calls this to start a new game
    case 'start':
        // Save any completed game before clearing
        saveGameReport($pdo);
        $pdo->exec("TRUNCATE TABLE `game_session`");

        // Pick 5 random question indices from 0-49
        $allIndices = range(0, 49);
        shuffle($allIndices);
        $selected = array_slice($allIndices, 0, 5);
        $questionsJson = json_encode($selected);

        $stmt = $pdo->prepare("INSERT INTO `game_session` (`status`, `questions`) VALUES (1, ?)");
        $stmt->execute([$questionsJson]);

        echo json_encode(['success' => true, 'session_id' => $pdo->lastInsertId(), 'questions' => $selected]);
        break;

    // a.html / b.html poll this once on load to check if game started
    case 'check_status':
        $stmt = $pdo->query("SELECT * FROM `game_session` ORDER BY id DESC LIMIT 1");
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($session) {
            if ($session['questions']) {
                $session['questions'] = json_decode($session['questions']);
            }
            if ($session['player_a_answers']) {
                $session['player_a_answers'] = json_decode($session['player_a_answers']);
            }
            if ($session['player_b_answers']) {
                $session['player_b_answers'] = json_decode($session['player_b_answers']);
            }
            echo json_encode($session);
        } else {
            echo json_encode(['status' => 0]);
        }
        break;

    // Tablet submits both players at once, sets status to 2 (ready for questions)
    case 'save_both_players':
        $data = json_decode(file_get_contents('php://input'), true);

        $stmt = $pdo->prepare("UPDATE `game_session` SET
            `player_a_name` = ?, `player_a_mobile` = ?, `player_a_ready` = 1,
            `player_b_name` = ?, `player_b_mobile` = ?, `player_b_ready` = 1,
            `status` = 2
            WHERE id = (SELECT id FROM (SELECT id FROM `game_session` ORDER BY id DESC LIMIT 1) AS t)");
        $stmt->execute([
            $data['player_a_name'],
            $data['player_a_mobile'],
            $data['player_b_name'],
            $data['player_b_mobile']
        ]);

        echo json_encode(['success' => true]);
        break;

    // Save answers when a player finishes all 5 questions
    case 'save_answers':
        $data = json_decode(file_get_contents('php://input'), true);
        $player = $data['player'];
        $answers = json_encode($data['answers']);

        if ($player === 'a') {
            $stmt = $pdo->prepare("UPDATE `game_session` SET `player_a_answers` = ?, `player_a_done` = 1 WHERE id = (SELECT id FROM (SELECT id FROM `game_session` ORDER BY id DESC LIMIT 1) AS t)");
        } else {
            $stmt = $pdo->prepare("UPDATE `game_session` SET `player_b_answers` = ?, `player_b_done` = 1 WHERE id = (SELECT id FROM (SELECT id FROM `game_session` ORDER BY id DESC LIMIT 1) AS t)");
        }
        $stmt->execute([$answers]);

        // Check if both done
        $check = $pdo->query("SELECT player_a_done, player_b_done FROM `game_session` ORDER BY id DESC LIMIT 1")->fetch(PDO::FETCH_ASSOC);
        $bothDone = ($check['player_a_done'] == 1 && $check['player_b_done'] == 1);

        if ($bothDone) {
            $pdo->exec("UPDATE `game_session` SET `status` = 4 WHERE id = (SELECT id FROM (SELECT id FROM `game_session` ORDER BY id DESC LIMIT 1) AS t)");
        }

        echo json_encode(['success' => true, 'both_done' => $bothDone]);
        break;

    // Check if partner has finished answering
    case 'check_partner_done':
        $player = isset($_GET['player']) ? $_GET['player'] : '';
        $stmt = $pdo->query("SELECT * FROM `game_session` ORDER BY id DESC LIMIT 1");
        $session = $stmt->fetch(PDO::FETCH_ASSOC);

        $partnerDone = false;
        if ($player === 'a') {
            $partnerDone = $session['player_b_done'] == 1;
        } else {
            $partnerDone = $session['player_a_done'] == 1;
        }

        $result = ['partner_done' => $partnerDone, 'status' => (int)$session['status']];
        if ($partnerDone && $session['status'] == 4) {
            $result['player_a_answers'] = json_decode($session['player_a_answers']);
            $result['player_b_answers'] = json_decode($session['player_b_answers']);
            $result['questions'] = json_decode($session['questions']);
        }

        echo json_encode($result);
        break;

    // Reset game
    case 'reset':
        // Save completed game to reports before clearing
        saveGameReport($pdo);
        $pdo->exec("TRUNCATE TABLE `game_session`");
        echo json_encode(['success' => true]);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
