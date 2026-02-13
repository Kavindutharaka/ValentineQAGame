<?php
require_once 'db.php';

// Clear old entries first
$pdo->exec("TRUNCATE TABLE `raffle_entries`");

// Sample couples for raffle testing (couple format: "PlayerA - PlayerB")
// You can test with even 1-2 entries — carousel will still work!
$couples = [
    ['Kasun Perera - Nimali Silva', '0771234567 / 0772345678'],
    ['Ruwan Fernando - Chathu Dias', '0773456789 / 0774567890'],
    ['Sampath Kumara - Dilini Jayasinghe', '0775678901 / 0776789012'],
    ['Tharindu Bandara - Hiruni Gamage', '0777890123 / 0778901234'],
    ['Supun Rathnayake - Nadeesha Wijesinghe', '0779012345 / 0710123456'],
    ['Ashan De Silva - Malisha Herath', '0711234567 / 0712345678'],
    ['Dinesh Karunaratne - Sachini Weerasinghe', '0713456789 / 0714567890'],
    ['Lahiru Madushanka - Pawani Samarawickrama', '0715678901 / 0716789012'],
    ['Nuwan Pradeep - Ishara Nanayakkara', '0717890123 / 0718901234'],
    ['Chanaka Rajapaksha - Thilini Abeysekara', '0719012345 / 0720123456'],
    ['Amila Gunasekara - Kavindi Dissanayake', '0721234567 / 0722345678'],
    ['Roshan Wickramasinghe - Sanduni Maduwanthi', '0723456789 / 0724567890'],
    ['Hasitha Lakmal - Nethmi Samarakoon', '0725678901 / 0726789012'],
    ['Janith Seneviratne - Oshadi Perera', '0727890123 / 0728901234'],
    ['Pasan Liyanage - Dulani Fonseka', '0729012345 / 0730123456']
];

$stmt = $pdo->prepare("INSERT INTO `raffle_entries` (`name`, `mobile`, `source`) VALUES (?, ?, 'couple')");

$count = 0;
foreach ($couples as $couple) {
    $stmt->execute([$couple[0], $couple[1]]);
    $count++;
}

echo json_encode(['success' => true, 'inserted' => $count, 'message' => "$count sample couple entries added to raffle_entries"]);
