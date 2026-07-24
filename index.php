<?php
/**
 * FCE Writing Trainer — Main Router
 */
require_once __DIR__ . '/config.php';

$page = $_GET['p'] ?? 'home';

switch ($page) {

    // ── Dashboard ──────────────────────────────────────────────
    case 'home':
        // Get active week
        $activeWeek = $pdo->query(
            "SELECT * FROM weekly_progress WHERE is_active = 1 LIMIT 1"
        )->fetch();

        // If no active week, activate week 1
        if (!$activeWeek) {
            $pdo->exec("UPDATE weekly_progress SET is_active = 1, start_date = CURDATE() WHERE week_number = 1");
            $activeWeek = $pdo->query(
                "SELECT * FROM weekly_progress WHERE is_active = 1 LIMIT 1"
            )->fetch();
        }

        // Get models for this week's type
        $weekType = $activeWeek['writing_type'];
        $models = $pdo->prepare(
            "SELECT m.*, 
                    COUNT(ts.id) as session_count,
                    MAX(ts.day_number) as max_day
             FROM models m 
             LEFT JOIN training_sessions ts ON ts.model_id = m.id
             WHERE m.type = ?
             GROUP BY m.id
             ORDER BY m.number"
        );
        $models->execute([$weekType]);
        $weekModels = $models->fetchAll();

        // Add status info to each model
        foreach ($weekModels as &$m) {
            $m['emoji'] = getTypeEmoji($m['type']);
            $m['color'] = getTypeColor($m['type']);
            $m['status_class'] = 'pending';
            $m['status_label'] = 'Pendiente';
            if ($m['session_count'] > 0 && ($m['max_day'] ?? 0) < 3) {
                $m['status_class'] = 'in-progress';
                $m['status_label'] = 'Día ' . $m['max_day'] . '/3';
            } elseif (($m['max_day'] ?? 0) >= 3) {
                $m['status_class'] = 'completed';
                $m['status_label'] = 'Completado';
            }
            $m['next_day'] = min(($m['max_day'] ?? 0) + 1, 3);
        }
        unset($m);

        // Recent sessions
        $recent = $pdo->query(
            "SELECT ts.*, m.title, m.type 
             FROM training_sessions ts 
             JOIN models m ON m.id = ts.model_id 
             ORDER BY ts.created_at DESC 
             LIMIT 5"
        )->fetchAll();
        foreach ($recent as &$r) {
            $r['emoji'] = getTypeEmoji($r['type']);
            $r['copy_formatted'] = formatTime($r['copy_time_seconds']);
            $r['draft_formatted'] = formatTime($r['draft_time_seconds']);
        }
        unset($r);

        // Stats
        $totalSessions = $pdo->query("SELECT COUNT(*) as c FROM training_sessions")->fetch()['c'];
        $avgCopy = $pdo->query("SELECT AVG(copy_time_seconds) as a FROM training_sessions WHERE copy_time_seconds > 0")->fetch()['a'];
        $bestCopy = $pdo->query("SELECT MIN(copy_time_seconds) as b FROM training_sessions WHERE copy_time_seconds > 0")->fetch()['b'];

        // All weeks for progress bar
        $allWeeks = $pdo->query("SELECT * FROM weekly_progress ORDER BY week_number")->fetchAll();
        foreach ($allWeeks as &$w) {
            $w['is_current'] = ($w['week_number'] == $activeWeek['week_number']);
            $w['type_emoji'] = getTypeEmoji($w['writing_type']);
            $w['type_label'] = ucfirst($w['writing_type']) . 's';
        }
        unset($w);

        render($mustache, 'home', [
            'page_title'     => 'Dashboard — FCE Writing Trainer',
            'flash_saved'    => isset($_GET['saved']),
            'active_week'    => $activeWeek,
            'week_number'    => $activeWeek['week_number'],
            'week_type'      => ucfirst($weekType) . 's',
            'week_type_raw'  => $weekType,
            'week_emoji'     => getTypeEmoji($weekType),
            'week_color'     => getTypeColor($weekType),
            'models'         => $weekModels,
            'recent'         => $recent,
            'has_recent'     => count($recent) > 0,
            'total_sessions' => $totalSessions,
            'avg_copy'       => formatTime(round($avgCopy ?? 0)),
            'best_copy'      => formatTime($bestCopy ?? 0),
            'all_weeks'      => $allWeeks,
        ]);
        break;

    // ── Training Session ───────────────────────────────────────
    case 'session':
        $modelId = (int)($_GET['model_id'] ?? 0);
        $dayNum  = (int)($_GET['day'] ?? 1);

        if (!$modelId) {
            header('Location: index.php?p=home');
            exit;
        }

        $model = $pdo->prepare("SELECT * FROM models WHERE id = ?");
        $model->execute([$modelId]);
        $model = $model->fetch();

        if (!$model) {
            header('Location: index.php?p=home');
            exit;
        }

        // Get random expressions for warm-up
        $warmup = $pdo->query(
            "SELECT expression, category FROM useful_expressions ORDER BY RAND() LIMIT 15"
        )->fetchAll();

        // Get this model's useful language
        $ulData = json_decode($model['useful_language'], true) ?: [];
        $ulFormatted = [];
        foreach ($ulData as $cat => $expressions) {
            $ulFormatted[] = [
                'category'    => $cat,
                'expressions' => array_map(function($e) { return ['text' => $e]; }, $expressions),
            ];
        }

        // Previous sessions for this model
        $prevSessions = $pdo->prepare(
            "SELECT * FROM training_sessions WHERE model_id = ? ORDER BY day_number"
        );
        $prevSessions->execute([$modelId]);
        $prevSessions = $prevSessions->fetchAll();
        foreach ($prevSessions as &$ps) {
            $ps['copy_formatted'] = formatTime($ps['copy_time_seconds']);
        }
        unset($ps);

        render($mustache, 'session', [
            'page_title'    => 'Sesión — ' . $model['title'],
            'model'         => $model,
            'model_id'      => $model['id'],
            'model_title'   => $model['title'],
            'model_type'    => ucfirst($model['type']),
            'model_emoji'   => getTypeEmoji($model['type']),
            'model_color'   => getTypeColor($model['type']),
            'model_prompt'  => $model['prompt'],
            'model_content' => $model['content'],
            'model_words'   => $model['word_count'],
            'day_number'    => $dayNum,
            'is_day_1'      => $dayNum === 1,
            'is_day_2'      => $dayNum === 2,
            'is_day_3'      => $dayNum === 3,
            'warmup'        => $warmup,
            'useful_lang'   => $ulFormatted,
            'prev_sessions' => $prevSessions,
            'has_prev'      => count($prevSessions) > 0,
        ]);
        break;

    // ── Models List ────────────────────────────────────────────
    case 'models':
        $filterType = $_GET['type'] ?? 'all';

        $types = ['essay', 'email', 'article', 'review', 'report'];
        $typeFilters = [];
        foreach ($types as $t) {
            $typeFilters[] = [
                'value'    => $t,
                'label'    => ucfirst($t) . 's',
                'emoji'    => getTypeEmoji($t),
                'color'    => getTypeColor($t),
                'is_active' => ($filterType === $t),
            ];
        }

        if ($filterType !== 'all' && in_array($filterType, $types)) {
            $stmt = $pdo->prepare(
                "SELECT m.*, COUNT(ts.id) as session_count, MAX(ts.day_number) as max_day
                 FROM models m 
                 LEFT JOIN training_sessions ts ON ts.model_id = m.id
                 WHERE m.type = ?
                 GROUP BY m.id
                 ORDER BY m.number"
            );
            $stmt->execute([$filterType]);
        } else {
            $stmt = $pdo->query(
                "SELECT m.*, COUNT(ts.id) as session_count, MAX(ts.day_number) as max_day
                 FROM models m 
                 LEFT JOIN training_sessions ts ON ts.model_id = m.id
                 GROUP BY m.id
                 ORDER BY m.type, m.number"
            );
            $filterType = 'all';
        }

        $allModels = $stmt->fetchAll();
        foreach ($allModels as &$am) {
            $am['emoji'] = getTypeEmoji($am['type']);
            $am['color'] = getTypeColor($am['type']);
            $am['type_label'] = ucfirst($am['type']);
            $am['sessions_done'] = $am['session_count'] ?? 0;
            $am['progress_pct'] = min(100, round((($am['max_day'] ?? 0) / 3) * 100));
        }
        unset($am);

        render($mustache, 'models', [
            'page_title'   => 'Modelos — FCE Writing Trainer',
            'models'       => $allModels,
            'type_filters' => $typeFilters,
            'filter_all'   => ($filterType === 'all'),
        ]);
        break;

    // ── Model Detail ───────────────────────────────────────────
    case 'model':
        $modelId = (int)($_GET['id'] ?? 0);
        if (!$modelId) {
            header('Location: index.php?p=models');
            exit;
        }

        $model = $pdo->prepare("SELECT * FROM models WHERE id = ?");
        $model->execute([$modelId]);
        $model = $model->fetch();

        if (!$model) {
            header('Location: index.php?p=models');
            exit;
        }

        $ulData = json_decode($model['useful_language'], true) ?: [];
        $ulFormatted = [];
        foreach ($ulData as $cat => $expressions) {
            $ulFormatted[] = [
                'category'    => $cat,
                'expressions' => array_map(function($e) { return ['text' => $e]; }, $expressions),
            ];
        }

        // Sessions for this model
        $sessions = $pdo->prepare(
            "SELECT * FROM training_sessions WHERE model_id = ? ORDER BY created_at DESC"
        );
        $sessions->execute([$modelId]);
        $sessions = $sessions->fetchAll();
        foreach ($sessions as &$s) {
            $s['copy_formatted'] = formatTime($s['copy_time_seconds']);
            $s['draft_formatted'] = formatTime($s['draft_time_seconds']);
            $s['date_formatted'] = date('d/m/Y', strtotime($s['session_date']));
        }
        unset($s);

        render($mustache, 'model', [
            'page_title'    => $model['title'] . ' — FCE Writing Trainer',
            'model'         => $model,
            'model_title'   => $model['title'],
            'model_type'    => ucfirst($model['type']),
            'model_emoji'   => getTypeEmoji($model['type']),
            'model_color'   => getTypeColor($model['type']),
            'model_prompt'  => $model['prompt'],
            'model_content' => $model['content'],
            'model_words'   => $model['word_count'],
            'useful_lang'   => $ulFormatted,
            'sessions'      => $sessions,
            'has_sessions'  => count($sessions) > 0,
        ]);
        break;

    // ── Progress ───────────────────────────────────────────────
    case 'progress':
        // All sessions with model info
        $allSessions = $pdo->query(
            "SELECT ts.*, m.title, m.type, m.number as model_number
             FROM training_sessions ts 
             JOIN models m ON m.id = ts.model_id 
             ORDER BY ts.created_at DESC"
        )->fetchAll();
        foreach ($allSessions as &$s) {
            $s['emoji'] = getTypeEmoji($s['type']);
            $s['color'] = getTypeColor($s['type']);
            $s['copy_formatted'] = formatTime($s['copy_time_seconds']);
            $s['draft_formatted'] = formatTime($s['draft_time_seconds']);
            $s['date_formatted'] = date('d/m/Y', strtotime($s['session_date']));
            $s['type_label'] = ucfirst($s['type']);
        }
        unset($s);

        // Stats per type
        $typeStats = $pdo->query(
            "SELECT m.type,
                    COUNT(ts.id) as sessions,
                    AVG(ts.copy_time_seconds) as avg_copy,
                    MIN(ts.copy_time_seconds) as best_copy,
                    AVG(ts.feeling) as avg_feeling
             FROM training_sessions ts
             JOIN models m ON m.id = ts.model_id
             WHERE ts.copy_time_seconds > 0
             GROUP BY m.type"
        )->fetchAll();
        foreach ($typeStats as &$ts) {
            $ts['emoji'] = getTypeEmoji($ts['type']);
            $ts['color'] = getTypeColor($ts['type']);
            $ts['type_label'] = ucfirst($ts['type']) . 's';
            $ts['avg_copy_fmt'] = formatTime(round($ts['avg_copy']));
            $ts['best_copy_fmt'] = formatTime($ts['best_copy']);
            $ts['avg_feeling_fmt'] = round($ts['avg_feeling'], 1);
        }
        unset($ts);

        // Global stats
        $totalSessions = $pdo->query("SELECT COUNT(*) as c FROM training_sessions")->fetch()['c'];
        $totalModels = $pdo->query(
            "SELECT COUNT(DISTINCT model_id) as c FROM training_sessions WHERE day_number >= 3"
        )->fetch()['c'];
        $avgCopy = $pdo->query(
            "SELECT AVG(copy_time_seconds) as a FROM training_sessions WHERE copy_time_seconds > 0"
        )->fetch()['a'];
        $bestCopy = $pdo->query(
            "SELECT MIN(copy_time_seconds) as b FROM training_sessions WHERE copy_time_seconds > 0"
        )->fetch()['b'];
        $avgFeeling = $pdo->query(
            "SELECT AVG(feeling) as a FROM training_sessions WHERE feeling > 0"
        )->fetch()['a'];

        render($mustache, 'progress', [
            'page_title'     => 'Progreso — FCE Writing Trainer',
            'sessions'       => $allSessions,
            'has_sessions'   => count($allSessions) > 0,
            'type_stats'     => $typeStats,
            'has_type_stats' => count($typeStats) > 0,
            'total_sessions' => $totalSessions,
            'total_models'   => $totalModels,
            'avg_copy'       => formatTime(round($avgCopy ?? 0)),
            'best_copy'      => formatTime($bestCopy ?? 0),
            'avg_feeling'    => round($avgFeeling ?? 0, 1),
        ]);
        break;

    // ── Useful Language Bank ───────────────────────────────────
    case 'language':
        $filterCat = $_GET['cat'] ?? 'all';
        $filterType = $_GET['type'] ?? 'all';

        // Get all categories
        $categories = $pdo->query(
            "SELECT DISTINCT category FROM useful_expressions ORDER BY category"
        )->fetchAll(PDO::FETCH_COLUMN);

        $catFilters = [];
        foreach ($categories as $c) {
            $catFilters[] = [
                'value'     => $c,
                'label'     => ucfirst(str_replace('_', ' ', $c)),
                'is_active' => ($filterCat === $c),
            ];
        }

        // Build query
        $sql = "SELECT * FROM useful_expressions WHERE 1=1";
        $params = [];

        if ($filterCat !== 'all') {
            $sql .= " AND category = ?";
            $params[] = $filterCat;
        }
        if ($filterType !== 'all') {
            $sql .= " AND writing_types LIKE ?";
            $params[] = "%$filterType%";
        }

        $sql .= " ORDER BY category, expression";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $expressions = $stmt->fetchAll();

        // Group by category
        $grouped = [];
        foreach ($expressions as $exp) {
            $cat = $exp['category'];
            if (!isset($grouped[$cat])) {
                $grouped[$cat] = [
                    'category'    => ucfirst(str_replace('_', ' ', $cat)),
                    'category_raw' => $cat,
                    'items'       => [],
                ];
            }
            $grouped[$cat]['items'][] = [
                'expression'    => $exp['expression'],
                'writing_types' => $exp['writing_types'],
                'id'            => $exp['id'],
            ];
        }
        $grouped = array_values($grouped);

        // Random expression for flashcard
        $random = $pdo->query(
            "SELECT * FROM useful_expressions ORDER BY RAND() LIMIT 1"
        )->fetch();

        render($mustache, 'language', [
            'page_title'    => 'Useful Language — FCE Writing Trainer',
            'groups'        => $grouped,
            'cat_filters'   => $catFilters,
            'filter_all'    => ($filterCat === 'all'),
            'type_filter'   => $filterType,
            'random_expr'   => $random ? $random['expression'] : '',
            'random_cat'    => $random ? ucfirst(str_replace('_', ' ', $random['category'])) : '',
            'random_types'  => $random ? $random['writing_types'] : '',
            'total_expr'    => count($expressions),
        ]);
        break;

    // ── Set Active Week ────────────────────────────────────────
    case 'set_week':
        $weekNum = (int)($_GET['week'] ?? 1);
        if ($weekNum >= 1 && $weekNum <= 5) {
            $pdo->exec("UPDATE weekly_progress SET is_active = 0");
            $stmt = $pdo->prepare("UPDATE weekly_progress SET is_active = 1, start_date = CURDATE() WHERE week_number = ?");
            $stmt->execute([$weekNum]);
        }
        header('Location: index.php?p=home');
        exit;

    // ── Default ────────────────────────────────────────────────
    default:
        header('Location: index.php?p=home');
        exit;
}
