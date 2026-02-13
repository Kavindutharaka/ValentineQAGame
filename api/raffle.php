<?php
require_once 'db.php';

$action = isset($_GET['action']) ? $_GET['action'] : '';

// Helper: get or create active session
function getActiveSession($pdo) {
    $stmt = $pdo->query("SELECT * FROM `raffle_sessions` WHERE `status` = 'active' ORDER BY id DESC LIMIT 1");
    $session = $stmt->fetch(PDO::FETCH_ASSOC);
    if (!$session) {
        // Auto-create a session
        $pdo->exec("INSERT INTO `raffle_sessions` (`status`) VALUES ('active')");
        $stmt = $pdo->query("SELECT * FROM `raffle_sessions` WHERE `status` = 'active' ORDER BY id DESC LIMIT 1");
        $session = $stmt->fetch(PDO::FETCH_ASSOC);
    }
    return $session;
}

switch ($action) {

    // Get entries for the raffle draw carousel (all if <=30, random 30 if more)
    case 'get_entries':
        $countStmt = $pdo->query("SELECT COUNT(*) as total FROM `raffle_entries`");
        $total = (int)$countStmt->fetch(PDO::FETCH_ASSOC)['total'];

        if ($total <= 30) {
            $stmt = $pdo->query("SELECT id, name, mobile FROM `raffle_entries` ORDER BY RAND()");
        } else {
            $stmt = $pdo->query("SELECT id, name, mobile FROM `raffle_entries` ORDER BY RAND() LIMIT 30");
        }
        $entries = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($entries);
        break;

    // Draw a winner: pick 1 random from entries, save to raffle_winners
    case 'draw_winner':
        $stmt = $pdo->query("SELECT id, name, mobile FROM `raffle_entries` ORDER BY RAND() LIMIT 1");
        $winner = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($winner) {
            // Get active session
            $session = getActiveSession($pdo);

            $sessionLabel = date('Y-m-d H:i');
            $insert = $pdo->prepare("INSERT INTO `raffle_winners` (`name`, `mobile`, `session_id`, `session_label`) VALUES (?, ?, ?, ?)");
            $insert->execute([$winner['name'], $winner['mobile'], $session['id'], $sessionLabel]);

            // Remove winner from entries so they can't win twice in this session
            $del = $pdo->prepare("DELETE FROM `raffle_entries` WHERE `id` = ?");
            $del->execute([$winner['id']]);

            echo json_encode([
                'success' => true,
                'winner' => $winner,
                'winner_id' => $pdo->lastInsertId()
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'No entries available']);
        }
        break;

    // Get all winners grouped by session (with start/end times)
    case 'get_winners':
        // Get all sessions that have winners
        $sessionsStmt = $pdo->query("
            SELECT rs.id, rs.started_at, rs.ended_at, rs.status
            FROM `raffle_sessions` rs
            WHERE rs.id IN (SELECT DISTINCT session_id FROM `raffle_winners` WHERE session_id IS NOT NULL)
            ORDER BY rs.id DESC
        ");
        $sessions = $sessionsStmt->fetchAll(PDO::FETCH_ASSOC);

        // Get winners for each session
        $result = [];
        foreach ($sessions as $sess) {
            $wStmt = $pdo->prepare("SELECT id, name, mobile, won_at FROM `raffle_winners` WHERE `session_id` = ? ORDER BY won_at ASC");
            $wStmt->execute([$sess['id']]);
            $winners = $wStmt->fetchAll(PDO::FETCH_ASSOC);

            $result[] = [
                'session_id' => $sess['id'],
                'started_at' => $sess['started_at'],
                'ended_at' => $sess['ended_at'],
                'status' => $sess['status'],
                'winners' => $winners
            ];
        }

        // Also get any winners without a session (old data)
        $oldStmt = $pdo->query("SELECT id, name, mobile, won_at FROM `raffle_winners` WHERE `session_id` IS NULL ORDER BY won_at ASC");
        $oldWinners = $oldStmt->fetchAll(PDO::FETCH_ASSOC);
        if (count($oldWinners) > 0) {
            $result[] = [
                'session_id' => null,
                'started_at' => null,
                'ended_at' => null,
                'status' => 'ended',
                'winners' => $oldWinners
            ];
        }

        echo json_encode($result);
        break;

    // Reset raffle session: end current session, clear entries
    case 'reset_session':
        // End current active session
        $session = getActiveSession($pdo);
        $endStmt = $pdo->prepare("UPDATE `raffle_sessions` SET `ended_at` = NOW(), `status` = 'ended' WHERE `id` = ?");
        $endStmt->execute([$session['id']]);

        // Clear entries
        $pdo->exec("TRUNCATE TABLE `raffle_entries`");
        echo json_encode(['success' => true]);
        break;

    // Get entry count
    case 'get_count':
        $stmt = $pdo->query("SELECT COUNT(*) as total FROM `raffle_entries`");
        $count = $stmt->fetch(PDO::FETCH_ASSOC);
        echo json_encode($count);
        break;

    default:
        echo json_encode(['error' => 'Invalid action']);
        break;
}
