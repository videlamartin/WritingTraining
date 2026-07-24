<?php
/**
 * FCE Writing Trainer — Form Actions (POST handlers)
 */
require_once __DIR__ . '/config.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

$action = $_POST['action'] ?? '';

switch ($action) {

    // ── Save Training Session ──────────────────────────────────
    case 'save_session':
        $modelId       = (int)($_POST['model_id'] ?? 0);
        $dayNumber     = (int)($_POST['day_number'] ?? 1);
        $copyTime      = (int)($_POST['copy_time_seconds'] ?? 0) ?: null;
        $draftTime     = (int)($_POST['draft_time_seconds'] ?? 0) ?: null;
        $errors        = isset($_POST['errors']) ? (int)$_POST['errors'] : null;
        $feeling       = isset($_POST['feeling']) ? (int)$_POST['feeling'] : null;
        $newConnectors = trim($_POST['new_connectors'] ?? '');
        $favoritePhrase = trim($_POST['favorite_phrase'] ?? '');
        $reflection    = trim($_POST['reflection'] ?? '');

        if ($modelId > 0) {
            $stmt = $pdo->prepare(
                "INSERT INTO training_sessions 
                 (model_id, session_date, day_number, copy_time_seconds, draft_time_seconds, 
                  errors, feeling, new_connectors, favorite_phrase, reflection)
                 VALUES (?, CURDATE(), ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute([
                $modelId, $dayNumber, $copyTime, $draftTime,
                $errors, $feeling, $newConnectors, $favoritePhrase, $reflection
            ]);
        }

        header('Location: index.php?p=home&saved=1');
        exit;

    // ── Delete Session ─────────────────────────────────────────
    case 'delete_session':
        $sessionId = (int)($_POST['session_id'] ?? 0);
        if ($sessionId > 0) {
            $stmt = $pdo->prepare("DELETE FROM training_sessions WHERE id = ?");
            $stmt->execute([$sessionId]);
        }
        $redirect = $_POST['redirect'] ?? 'index.php?p=progress';
        header('Location: ' . $redirect);
        exit;

    // ── Set Active Week ────────────────────────────────────────
    case 'set_week':
        $weekNum = (int)($_POST['week_number'] ?? 1);
        if ($weekNum >= 1 && $weekNum <= 5) {
            $pdo->exec("UPDATE weekly_progress SET is_active = 0");
            $stmt = $pdo->prepare(
                "UPDATE weekly_progress SET is_active = 1, start_date = CURDATE() WHERE week_number = ?"
            );
            $stmt->execute([$weekNum]);
        }
        header('Location: index.php?p=home');
        exit;

    // ── Complete Week ──────────────────────────────────────────
    case 'complete_week':
        $weekNum = (int)($_POST['week_number'] ?? 0);
        if ($weekNum >= 1 && $weekNum <= 5) {
            $pdo->prepare(
                "UPDATE weekly_progress SET is_completed = 1, is_active = 0 WHERE week_number = ?"
            )->execute([$weekNum]);

            // Activate next week
            $nextWeek = $weekNum + 1;
            if ($nextWeek <= 5) {
                $pdo->prepare(
                    "UPDATE weekly_progress SET is_active = 1, start_date = CURDATE() WHERE week_number = ?"
                )->execute([$nextWeek]);
            }
        }
        header('Location: index.php?p=home');
        exit;

    // ── Reset All Progress ─────────────────────────────────────
    case 'reset_progress':
        $pdo->exec("DELETE FROM training_sessions");
        $pdo->exec("UPDATE weekly_progress SET is_active = 0, is_completed = 0, start_date = NULL");
        $pdo->exec("UPDATE weekly_progress SET is_active = 1, start_date = CURDATE() WHERE week_number = 1");
        header('Location: index.php?p=home&reset=1');
        exit;

    default:
        header('Location: index.php');
        exit;
}
