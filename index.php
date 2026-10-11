<?php
/**
 * index.php — User-friendly Light Theme with Circular Tree Nodes & Smooth Slow Animation.
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

// Determine which search to visualize (specific ID, latest from DB, or fallback demo)
$active_record = null;
$search_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

try {
    $db = get_db();
    if ($search_id > 0) {
        $stmt = $db->prepare('SELECT * FROM search_results WHERE id = :id LIMIT 1');
        $stmt->execute([':id' => $search_id]);
        $active_record = $stmt->fetch();
    }
    if (!$active_record) {
        $active_record = $db->query('SELECT * FROM search_results ORDER BY created_at DESC LIMIT 1')->fetch();
    }
} catch (Exception $e) {
    $active_record = null;
}

// Fallback demo if database is empty
if (!$active_record) {
    $viz_text = 'AABAACAADAABAABA';
    $viz_pattern = 'AABA';
    $viz_algo = 'Naive';
    $viz_comparisons = 0;
    $viz_time = 0.0;
} else {
    $viz_text = (string)$active_record['input_text'];
    $viz_pattern = (string)$active_record['pattern'];
    $viz_algo = (string)$active_record['algorithm'];
    $viz_comparisons = (int)$active_record['comparisons'];
    $viz_time = (float)$active_record['time_ms'];
}

/**
 * Builds the node-by-node search tree trace with circular node metadata.
 */
function build_search_tree(string $text, string $pattern, string $algorithm): array
{
    $n = strlen($text);
    $m = strlen($pattern);
    $branches = [];

    if ($m === 0 || $m > $n) {
        return $branches;
    }

    // Limit window shifts to 14 for clean visual breathing room
    $max_shifts = min($n - $m + 1, 14);

    if ($algorithm === 'Naive') {
        for ($i = 0; $i < $max_shifts; $i++) {
            $nodes = [];
            $all_matched = true;

            for ($j = 0; $j < $m; $j++) {
                $tc = $text[$i + $j];
                $pc = $pattern[$j];

                if ($tc === $pc) {
                    $nodes[] = [
                        'type'   => 'match',
                        'symbol' => '✓',
                        'char'   => $tc,
                        'label'  => "T[" . ($i + $j) . "] '{$tc}' == P[{$j}] '{$pc}'",
                        'desc'   => 'Char Match'
                    ];
                } else {
                    $nodes[] = [
                        'type'   => 'mismatch',
                        'symbol' => '✕',
                        'char'   => $tc,
                        'label'  => "T[" . ($i + $j) . "] '{$tc}' ≠ P[{$j}] '{$pc}'",
                        'desc'   => 'Mismatch ➜ Stop'
                    ];
                    $all_matched = false;
                    break;
                }
            }

            if ($all_matched) {
                $nodes[] = [
                    'type'   => 'found',
                    'symbol' => '★',
                    'char'   => 'Match',
                    'label'  => "★ Pattern Found at Shift #{$i}",
                    'desc'   => 'All Characters Matched!'
                ];
            }

            $branches[] = [
                'shift'   => $i,
                'window'  => substr($text, $i, $m),
                'matched' => $all_matched,
                'nodes'   => $nodes
            ];
        }
    } else {
        // Rabin-Karp algorithm tree trace
        $base = 256;
        $mod  = 101;
        $h    = 1;
        for ($k = 0; $k < $m - 1; $k++) {
            $h = ($h * $base) % $mod;
        }

        $pH = 0;
        $tH = 0;
        for ($k = 0; $k < $m; $k++) {
            $pH = ($base * $pH + ord($pattern[$k])) % $mod;
            $tH = ($base * $tH + ord($text[$k]))    % $mod;
        }

        for ($i = 0; $i < $max_shifts; $i++) {
            $nodes = [];
            $hash_match = ($pH === $tH);

            if (!$hash_match) {
                $nodes[] = [
                    'type'   => 'hash-mismatch',
                    'symbol' => '≠',
                    'char'   => (string)$tH,
                    'label'  => "Hash({$tH}) ≠ PatternHash({$pH})",
                    'desc'   => 'Hash Mismatch ➜ Window Skipped'
                ];
                $all_matched = false;
            } else {
                $nodes[] = [
                    'type'   => 'hash-match',
                    'symbol' => '#',
                    'char'   => (string)$tH,
                    'label'  => "Hash({$tH}) == PatternHash({$pH})",
                    'desc'   => 'Hash Match ➜ Verify Chars'
                ];

                $all_matched = true;
                for ($j = 0; $j < $m; $j++) {
                    $tc = $text[$i + $j];
                    $pc = $pattern[$j];
                    if ($tc === $pc) {
                        $nodes[] = [
                            'type'   => 'match',
                            'symbol' => '✓',
                            'char'   => $tc,
                            'label'  => "T[" . ($i + $j) . "] '{$tc}' == P[{$j}] '{$pc}'",
                            'desc'   => 'Char Match'
                        ];
                    } else {
                        $nodes[] = [
                            'type'   => 'mismatch',
                            'symbol' => '✕',
                            'char'   => $tc,
                            'label'  => "T[" . ($i + $j) . "] '{$tc}' ≠ P[{$j}] '{$pc}'",
                            'desc'   => 'Collision ➜ Stop'
                        ];
                        $all_matched = false;
                        break;
                    }
                }

                if ($all_matched) {
                    $nodes[] = [
                        'type'   => 'found',
                        'symbol' => '★',
                        'char'   => 'Match',
                        'label'  => "★ Pattern Found at Shift #{$i}",
                        'desc'   => 'All Characters Matched!'
                    ];
                }
            }

            $branches[] = [
                'shift'   => $i,
                'window'  => substr($text, $i, $m),
                'matched' => $all_matched,
                'nodes'   => $nodes
            ];

            // Roll hash
            if ($i < $n - $m) {
                $tH = ($base * ($tH - ord($text[$i]) * $h) + ord($text[$i + $m])) % $mod;
                if ($tH < 0) {
                    $tH += $mod;
                }
            }
        }
    }

    return $branches;
}

$tree_branches = build_search_tree($viz_text, $viz_pattern, $viz_algo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>String Matching Tree — Naive vs Rabin-Karp</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@400;500;600&display=swap" rel="stylesheet">
    <style>
        /* ── Design System & Theme ─────────────────────────── */
        :root {
            --bg-page: #f8fafc;
            --bg-card: #ffffff;
            --text-main: #0f172a;
            --text-muted: #64748b;
            --border-light: #e2e8f0;
            --border-subtle: #f1f5f9;
            
            --primary: #4f46e5;
            --primary-hover: #4338ca;
            --primary-light: #eef2ff;

            /* Circular Node Colors */
            --match-bg: #ecfdf5;
            --match-border: #10b981;
            --match-text: #065f46;
            --match-glow: rgba(16, 185, 129, 0.2);

            --mismatch-bg: #fef2f2;
            --mismatch-border: #ef4444;
            --mismatch-text: #991b1b;
            --mismatch-glow: rgba(239, 68, 68, 0.2);

            --hash-match-bg: #eff6ff;
            --hash-match-border: #3b82f6;
            --hash-match-text: #1e40af;
            --hash-match-glow: rgba(59, 130, 246, 0.2);

            --hash-skip-bg: #fffbeb;
            --hash-skip-border: #f59e0b;
            --hash-skip-text: #92400e;
            --hash-skip-glow: rgba(245, 158, 11, 0.2);

            --found-bg: linear-gradient(135deg, #10b981, #047857);
            --found-border: #34d399;
            --found-text: #ffffff;
            --found-glow: 0 0 18px rgba(16, 185, 129, 0.45);

            /* Animation speed variable (seconds per step) */
            --step-duration: 0.18s;
        }

        *, *::before, *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, sans-serif;
            background-color: var(--bg-page);
            background-image: 
                radial-gradient(circle at 12% 8%, rgba(99, 102, 241, 0.06) 0px, transparent 40%),
                radial-gradient(circle at 88% 92%, rgba(14, 165, 233, 0.06) 0px, transparent 40%);
            background-attachment: fixed;
            color: var(--text-main);
            line-height: 1.5;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 2.5rem 1rem 4rem;
        }

        /* ── Header ────────────────────────────────────────── */
        .app-header {
            text-align: center;
            margin-bottom: 2rem;
        }

        .app-badge {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .25rem .8rem;
            font-size: .75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--primary);
            background: var(--primary-light);
            border: 1px solid #c7d2fe;
            border-radius: 9999px;
            margin-bottom: .6rem;
        }

        h1 {
            font-size: 1.85rem;
            font-weight: 800;
            color: #0f172a;
            letter-spacing: -0.025em;
        }

        .subtitle {
            font-size: .95rem;
            color: var(--text-muted);
            margin-top: .3rem;
        }

        /* ── Card Base ─────────────────────────────────────── */
        .card {
            background: var(--bg-card);
            border: 1px solid var(--border-light);
            border-radius: 16px;
            box-shadow: 0 4px 20px -2px rgba(15, 23, 42, 0.04), 0 2px 6px -1px rgba(15, 23, 42, 0.02);
            padding: 1.75rem;
            width: 100%;
            max-width: 980px;
            margin-bottom: 2rem;
        }

        .card-title {
            font-size: 1.15rem;
            font-weight: 700;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 1.25rem;
        }

        /* ── Form ──────────────────────────────────────────── */
        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1.5fr 1fr;
            gap: 1rem;
        }

        @media (max-width: 640px) {
            .form-row { grid-template-columns: 1fr; }
        }

        label {
            display: block;
            font-size: .8rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--text-muted);
            margin-bottom: .35rem;
        }

        textarea, input[type="text"], select {
            width: 100%;
            padding: .65rem .85rem;
            background: #ffffff;
            border: 1px solid var(--border-light);
            border-radius: 10px;
            color: var(--text-main);
            font-family: inherit;
            font-size: .95rem;
            transition: all .15s ease;
        }

        textarea:focus, input[type="text"]:focus, select:focus {
            outline: none;
            border-color: var(--primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.12);
        }

        textarea {
            resize: vertical;
            min-height: 75px;
            font-family: 'JetBrains Mono', monospace;
            font-size: .9rem;
        }

        input[type="text"] {
            font-family: 'JetBrains Mono', monospace;
        }

        button {
            margin-top: .5rem;
            width: 100%;
            padding: .75rem 1.25rem;
            background: linear-gradient(135deg, #4f46e5, #6366f1);
            border: none;
            border-radius: 10px;
            color: #ffffff;
            font-size: .95rem;
            font-weight: 700;
            cursor: pointer;
            box-shadow: 0 4px 14px rgba(79, 70, 229, 0.25);
            transition: transform .15s ease, box-shadow .15s ease, opacity .15s ease;
        }

        button:hover {
            opacity: .95;
            transform: translateY(-1px);
            box-shadow: 0 6px 18px rgba(79, 70, 229, 0.32);
        }

        .msg {
            margin-top: .85rem;
            padding: .65rem .9rem;
            border-radius: 8px;
            font-size: .85rem;
            font-weight: 600;
            text-align: center;
        }
        .msg.ok  { background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; }
        .msg.err { background: #fef2f2; color: #991b1b; border: 1px solid #fecaca; }

        /* ── Tree Section Controls ─────────────────────────── */
        .tree-header {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            justify-content: space-between;
            gap: 1rem;
            padding-bottom: 1.1rem;
            border-bottom: 1px solid var(--border-light);
            margin-bottom: 1.25rem;
        }

        .tree-meta {
            display: flex;
            flex-wrap: wrap;
            align-items: center;
            gap: .5rem;
            font-size: .85rem;
            color: var(--text-muted);
        }

        .tree-meta strong {
            color: var(--text-main);
        }

        /* Animation Playback Toolbar */
        .tree-playback-bar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            flex-wrap: wrap;
            gap: .8rem;
            background: #f8fafc;
            border: 1px solid var(--border-light);
            border-radius: 12px;
            padding: .65rem 1rem;
            margin-bottom: 1.5rem;
        }

        .playback-actions {
            display: flex;
            align-items: center;
            gap: .6rem;
        }

        .btn-playback {
            padding: .4rem .85rem;
            font-size: .8rem;
            font-weight: 700;
            border-radius: 8px;
            border: 1px solid var(--border-light);
            background: #ffffff;
            color: #334155;
            cursor: pointer;
            box-shadow: 0 1px 3px rgba(0,0,0,0.04);
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            transition: all .15s ease;
            width: auto;
            margin-top: 0;
        }

        .btn-playback:hover {
            background: #f1f5f9;
            color: var(--primary);
            border-color: #cbd5e1;
            transform: translateY(-1px);
        }

        .speed-control {
            display: flex;
            align-items: center;
            gap: .4rem;
            font-size: .78rem;
            font-weight: 600;
            color: var(--text-muted);
        }

        .speed-control select {
            padding: .3rem .55rem;
            font-size: .78rem;
            border-radius: 6px;
            border: 1px solid var(--border-light);
            background: #ffffff;
            width: auto;
        }

        .tree-legend {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
            font-size: .75rem;
            font-weight: 700;
        }

        .legend-tag {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .25rem .6rem;
            border-radius: 9999px;
            border: 1px solid transparent;
        }
        .legend-tag .legend-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
        }
        .legend-tag.match       { background: var(--match-bg); border-color: #a7f3d0; color: var(--match-text); }
        .legend-tag.match .legend-dot { background: var(--match-border); }

        .legend-tag.mismatch    { background: var(--mismatch-bg); border-color: #fecaca; color: var(--mismatch-text); }
        .legend-tag.mismatch .legend-dot { background: var(--mismatch-border); }

        .legend-tag.hash-match  { background: var(--hash-match-bg); border-color: #bfdbfe; color: var(--hash-match-text); }
        .legend-tag.hash-match .legend-dot { background: var(--hash-match-border); }

        .legend-tag.hash-skip   { background: var(--hash-skip-bg); border-color: #fde68a; color: var(--hash-skip-text); }
        .legend-tag.hash-skip .legend-dot { background: var(--hash-skip-border); }

        .legend-tag.found       { background: #ecfdf5; border-color: #34d399; color: #065f46; font-weight: 800; }
        .legend-tag.found .legend-dot { background: #10b981; }

        /* ── Tree Viewport ─────────────────────────────────── */
        .tree-viewport {
            overflow-x: auto;
            padding: 1.5rem .5rem 2rem;
            display: flex;
            flex-direction: column;
            align-items: center;
            min-height: 380px;
        }

        /* ── Root Node ─────────────────────────────────────── */
        .tree-root {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
        }

        .root-circle {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background: #ffffff;
            border: 3px solid var(--primary);
            box-shadow: 0 6px 18px rgba(79, 70, 229, 0.18);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.4rem;
            z-index: 5;
            transition: transform .25s ease;
        }

        .root-circle:hover {
            transform: scale(1.08);
        }

        .root-label {
            margin-top: .4rem;
            background: var(--primary-light);
            color: var(--primary);
            padding: .2rem .75rem;
            border-radius: 9999px;
            font-weight: 700;
            font-size: .8rem;
            font-family: 'JetBrains Mono', monospace;
            border: 1px solid #c7d2fe;
            box-shadow: 0 2px 6px rgba(79, 70, 229, 0.08);
        }

        /* Vertical stem dropping down from Root to Horizontal Bus */
        .root-stem {
            width: 3px;
            height: 32px;
            background: #cbd5e1;
            transform-origin: top;
            animation: growStem 0.4s ease-out both;
        }

        /* ── Branches Container ────────────────────────────── */
        .tree-branches {
            display: flex;
            gap: 1.5rem;
            position: relative;
            padding-top: 18px;
        }

        /* Continuous horizontal bus line connecting all shift branches */
        .tree-branches::before {
            content: '';
            position: absolute;
            top: 0;
            left: 35px;
            right: 35px;
            height: 3px;
            background: #cbd5e1;
            border-radius: 2px;
            animation: expandBus 0.5s ease-out both;
        }

        .branch-column {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            min-width: 130px;
        }

        /* Vertical drop line from horizontal bus to shift circle header */
        .branch-drop {
            width: 3px;
            height: 18px;
            background: #cbd5e1;
            margin-top: -18px;
            transform-origin: top;
        }

        /* Shift header: circular badge + window preview */
        .shift-circle-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            margin-bottom: .6rem;
            width: 100%;
        }

        .shift-circle-badge {
            width: 38px;
            height: 38px;
            border-radius: 50%;
            background: #f1f5f9;
            border: 2px solid #94a3b8;
            color: #334155;
            font-weight: 700;
            font-size: .78rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 2px 6px rgba(0,0,0,0.04);
            transition: transform .2s ease;
        }

        .shift-window-str {
            margin-top: .3rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: .75rem;
            font-weight: 600;
            color: var(--primary);
            background: #ffffff;
            border: 1px solid var(--border-light);
            padding: .1rem .45rem;
            border-radius: 6px;
        }

        /* ── Circular Step Nodes ───────────────────────────── */
        .step-node-list {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 100%;
            gap: .5rem;
            position: relative;
        }

        /* The circular node item */
        .circle-node-wrapper {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            width: 100%;
            cursor: pointer;
        }

        .circle-node {
            width: 48px;
            height: 48px;
            border-radius: 50%;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            position: relative;
            border: 2.5px solid;
            background: #ffffff;
            transition: transform .25s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow .25s ease;
            box-shadow: 0 4px 10px rgba(0,0,0,0.06);
            z-index: 2;
        }

        .circle-node:hover {
            transform: scale(1.18);
            z-index: 10;
        }

        .circle-symbol {
            font-size: 1.15rem;
            font-weight: 800;
            line-height: 1;
        }

        .circle-subchar {
            font-family: 'JetBrains Mono', monospace;
            font-size: .62rem;
            font-weight: 700;
            margin-top: 1px;
            line-height: 1;
        }

        /* Circular Themes */
        .circle-node.match {
            background: var(--match-bg);
            border-color: var(--match-border);
            color: var(--match-text);
            box-shadow: 0 4px 12px var(--match-glow);
        }

        .circle-node.mismatch {
            background: var(--mismatch-bg);
            border-color: var(--mismatch-border);
            color: var(--mismatch-text);
            box-shadow: 0 4px 12px var(--mismatch-glow);
        }

        .circle-node.hash-match {
            background: var(--hash-match-bg);
            border-color: var(--hash-match-border);
            color: var(--hash-match-text);
            box-shadow: 0 4px 12px var(--hash-match-glow);
        }

        .circle-node.hash-mismatch {
            background: var(--hash-skip-bg);
            border-color: var(--hash-skip-border);
            color: var(--hash-skip-text);
            box-shadow: 0 4px 12px var(--hash-skip-glow);
        }

        .circle-node.found {
            background: var(--found-bg);
            border-color: var(--found-border);
            color: var(--found-text);
            box-shadow: var(--found-glow);
            animation: pulseGlow 2.5s infinite ease-in-out;
        }

        /* Sleek Info Pill below Circle */
        .circle-info-pill {
            margin-top: .35rem;
            background: #ffffff;
            border: 1px solid var(--border-light);
            border-radius: 8px;
            padding: .25rem .45rem;
            text-align: center;
            width: 100%;
            max-width: 115px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.03);
            transition: all .2s ease;
        }

        .circle-info-expr {
            display: block;
            font-family: 'JetBrains Mono', monospace;
            font-size: .68rem;
            font-weight: 600;
            color: #1e293b;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }

        .circle-info-desc {
            display: block;
            font-size: .62rem;
            color: var(--text-muted);
            font-weight: 500;
            margin-top: 1px;
        }

        /* Stem Connector between circular nodes */
        .node-stem {
            width: 2.5px;
            height: 14px;
            background: #cbd5e1;
            margin: -4px 0;
            transform-origin: top;
            border-radius: 2px;
        }

        /* ── Slow and Smooth CSS Animations ────────────────── */
        .animated-node {
            animation: circleDrop 0.55s cubic-bezier(0.34, 1.56, 0.64, 1) both;
            animation-delay: calc(var(--step, 0) * var(--step-duration));
        }

        .animated-stem {
            animation: growStem 0.4s ease-out both;
            animation-delay: calc(var(--step, 0) * var(--step-duration));
        }

        @keyframes circleDrop {
            0% {
                opacity: 0;
                transform: scale(0.3) translateY(-16px);
            }
            60% {
                opacity: 1;
                transform: scale(1.12) translateY(2px);
            }
            100% {
                opacity: 1;
                transform: scale(1) translateY(0);
            }
        }

        @keyframes growStem {
            0% {
                opacity: 0;
                transform: scaleY(0);
            }
            100% {
                opacity: 1;
                transform: scaleY(1);
            }
        }

        @keyframes expandBus {
            0% {
                transform: scaleX(0);
                opacity: 0;
            }
            100% {
                transform: scaleX(1);
                opacity: 1;
            }
        }

        @keyframes pulseGlow {
            0%, 100% {
                transform: scale(1);
                box-shadow: 0 0 16px rgba(16, 185, 129, 0.4);
            }
            50% {
                transform: scale(1.08);
                box-shadow: 0 0 24px rgba(16, 185, 129, 0.65);
            }
        }

        /* Inspector Banner for Interactive Clicking */
        .inspector-banner {
            display: none;
            background: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 10px;
            padding: .65rem 1rem;
            margin-top: 1rem;
            font-size: .85rem;
            align-items: center;
            justify-content: space-between;
        }
        .inspector-banner.visible {
            display: flex;
        }

        /* ── Results Table Styling (display.php) ───────────── */
        .table-wrap {
            overflow-x: auto;
            border: 1px solid var(--border-light);
            border-radius: 12px;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: .85rem;
            text-align: left;
        }

        th {
            background: #f8fafc;
            color: #475569;
            font-weight: 700;
            font-size: .75rem;
            text-transform: uppercase;
            letter-spacing: .04em;
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--border-light);
            white-space: nowrap;
        }

        td {
            padding: .75rem 1rem;
            border-bottom: 1px solid var(--border-subtle);
            color: #334155;
            white-space: nowrap;
        }

        tr:last-child td {
            border-bottom: none;
        }

        tr:hover td {
            background: #f8fafc;
        }

        .col-id {
            font-weight: 700;
            color: var(--text-muted);
            font-size: .8rem;
        }

        .col-text {
            font-family: 'JetBrains Mono', monospace;
            font-size: .8rem;
            color: #1e293b;
        }

        .pattern-tag {
            font-family: 'JetBrains Mono', monospace;
            background: #f1f5f9;
            color: var(--primary);
            padding: .15rem .45rem;
            border-radius: 6px;
            font-size: .8rem;
            font-weight: 700;
        }

        .badge {
            display: inline-block;
            padding: .2rem .65rem;
            border-radius: 9999px;
            font-size: .75rem;
            font-weight: 700;
        }

        .badge-naive {
            background: #e0f2fe;
            color: #0369a1;
            border: 1px solid #bae6fd;
        }

        .badge-rk {
            background: #ede9fe;
            color: #6d28d9;
            border: 1px solid #ddd6fe;
        }

        .col-metric {
            font-family: 'JetBrains Mono', monospace;
            font-size: .82rem;
        }

        .font-semibold {
            font-weight: 700;
        }

        .col-time {
            color: var(--text-muted);
            font-size: .8rem;
        }

        .empty {
            text-align: center;
            color: var(--text-muted);
            padding: 2rem;
            font-size: .9rem;
        }
    </style>
</head>
<body>

<header class="app-header">
    <div class="app-badge">Algorithm Comparison Analyzer</div>
    <h1>String Matching &amp; Decision Tree</h1>
    <p class="subtitle">Comparing Naive vs. Rabin-Karp with Smooth Circular Node-by-Node Visualization</p>
</header>

<!-- Input Form Card -->
<section class="card">
    <div class="card-title">
        <span>Search Query Configuration</span>
    </div>

    <form action="process.php" method="POST">
        <div class="form-grid">
            <div>
                <label for="text">Target Text</label>
                <textarea id="text" name="text" placeholder="Enter target text string..." required><?= htmlspecialchars($viz_text, ENT_QUOTES) ?></textarea>
            </div>

            <div class="form-row">
                <div>
                    <label for="pattern">Pattern to Match</label>
                    <input type="text" id="pattern" name="pattern" value="<?= htmlspecialchars($viz_pattern, ENT_QUOTES) ?>" placeholder="e.g. AABA" required>
                </div>
                <div>
                    <label for="algorithm">Algorithm</label>
                    <select id="algorithm" name="algorithm" required>
                        <option value="Naive" <?= $viz_algo === 'Naive' ? 'selected' : '' ?>>Naive (Brute-Force)</option>
                        <option value="Rabin-Karp" <?= $viz_algo === 'Rabin-Karp' ? 'selected' : '' ?>>Rabin-Karp (Rolling Hash)</option>
                    </select>
                </div>
            </div>

            <button type="submit">Run Search &amp; Visualize Tree</button>
        </div>
    </form>

    <?php if (isset($_GET['ok'])): ?>
        <p class="msg ok">✓ Search complete &amp; saved to PostgreSQL. Watch the decision tree unfold below.</p>
    <?php elseif (isset($_GET['err'])): ?>
        <p class="msg err">✗ Error: Please fill in all required fields.</p>
    <?php endif; ?>
</section>

<!-- Node-by-Node Pattern Finding Tree Card -->
<section class="card" id="tree-section">
    <div class="card-title">
        <span>Pattern Finding Decision Tree</span>
        <span class="badge <?= $viz_algo === 'Naive' ? 'badge-naive' : 'badge-rk' ?>"><?= htmlspecialchars($viz_algo, ENT_QUOTES) ?></span>
    </div>

    <div class="tree-header">
        <div class="tree-meta">
            <span>Pattern: <strong>&ldquo;<?= htmlspecialchars($viz_pattern, ENT_QUOTES) ?>&rdquo;</strong> (len: <?= strlen($viz_pattern) ?>)</span>
            <span>&bull;</span>
            <span>Text Length: <strong><?= strlen($viz_text) ?></strong></span>
            <?php if ($viz_comparisons > 0): ?>
                <span>&bull;</span>
                <span>Comparisons: <strong><?= $viz_comparisons ?></strong></span>
                <span>&bull;</span>
                <span>Time: <strong><?= $viz_time ?> ms</strong></span>
            <?php endif; ?>
        </div>

        <div class="tree-legend">
            <span class="legend-tag match"><span class="legend-dot"></span> ✓ Match</span>
            <span class="legend-tag mismatch"><span class="legend-dot"></span> ✕ Mismatch</span>
            <?php if ($viz_algo === 'Rabin-Karp'): ?>
                <span class="legend-tag hash-match"><span class="legend-dot"></span> # Hash Match</span>
                <span class="legend-tag hash-skip"><span class="legend-dot"></span> ≠ Hash Skip</span>
            <?php endif; ?>
            <span class="legend-tag found"><span class="legend-dot"></span> ★ Match Found</span>
        </div>
    </div>

    <!-- User-Friendly Interactive Playback Toolbar -->
    <div class="tree-playback-bar">
        <div class="playback-actions">
            <button type="button" class="btn-playback" id="btn-replay" onclick="replayAnimation()">
                <span>⟳</span> Replay Slow Animation
            </button>
        </div>

        <div class="speed-control">
            <label for="speed-select" style="margin:0; font-size:.78rem;">Animation Speed:</label>
            <select id="speed-select" onchange="changeSpeed(this.value)">
                <option value="0.25s">Slow &amp; Smooth</option>
                <option value="0.18s" selected>Smooth</option>
                <option value="0.08s">Fast</option>
                <option value="0s">Instant</option>
            </select>
        </div>
    </div>

    <?php if (empty($tree_branches)): ?>
        <p class="empty">Pattern length is greater than text length. No search tree branches to display.</p>
    <?php else: ?>
        <?php $step_counter = 0; ?>
        <div class="tree-viewport" id="tree-canvas">
            <!-- Root Circle Hub -->
            <div class="tree-root">
                <div class="root-circle animated-node" style="--step: <?= $step_counter++ ?>;">
                    <span>🔍</span>
                </div>
                <div class="root-label animated-node" style="--step: <?= $step_counter++ ?>;">
                    &ldquo;<?= htmlspecialchars($viz_pattern, ENT_QUOTES) ?>&rdquo;
                </div>
                <div class="root-stem animated-stem" style="--step: <?= $step_counter++ ?>;"></div>
            </div>

            <!-- Branches connecting window shifts -->
            <div class="tree-branches">
                <?php foreach ($tree_branches as $branch): ?>
                    <div class="branch-column">
                        <div class="branch-drop animated-stem" style="--step: <?= $step_counter++ ?>;"></div>
                        
                        <div class="shift-circle-header animated-node" style="--step: <?= $step_counter++ ?>;">
                            <div class="shift-circle-badge">#<?= $branch['shift'] ?></div>
                            <span class="shift-window-str">&ldquo;<?= htmlspecialchars($branch['window'], ENT_QUOTES) ?>&rdquo;</span>
                        </div>

                        <!-- Circular Step Nodes in this branch -->
                        <div class="step-node-list">
                            <?php foreach ($branch['nodes'] as $idx => $node): ?>
                                <?php if ($idx > 0): ?>
                                    <div class="node-stem animated-stem" style="--step: <?= $step_counter++ ?>;"></div>
                                <?php endif; ?>

                                <div class="circle-node-wrapper animated-node" 
                                     style="--step: <?= $step_counter++ ?>;"
                                     onclick="inspectNode('<?= htmlspecialchars($node['label'], ENT_QUOTES) ?>', '<?= htmlspecialchars($node['desc'], ENT_QUOTES) ?>', '<?= htmlspecialchars($node['type'], ENT_QUOTES) ?>')">
                                    
                                    <div class="circle-node <?= htmlspecialchars($node['type'], ENT_QUOTES) ?>">
                                        <span class="circle-symbol"><?= htmlspecialchars($node['symbol'], ENT_QUOTES) ?></span>
                                        <span class="circle-subchar"><?= htmlspecialchars($node['char'], ENT_QUOTES) ?></span>
                                    </div>

                                    <div class="circle-info-pill">
                                        <span class="circle-info-expr"><?= htmlspecialchars($node['label'], ENT_QUOTES) ?></span>
                                        <span class="circle-info-desc"><?= htmlspecialchars($node['desc'], ENT_QUOTES) ?></span>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Inspector details bar on clicking any circle -->
        <div class="inspector-banner" id="inspector-bar">
            <div>
                <strong>Selected Node:</strong> <span id="inspect-label">-</span> &bull; <span id="inspect-desc" style="color:var(--text-muted);">-</span>
            </div>
            <button type="button" class="btn-playback" onclick="closeInspector()" style="padding:.2rem .5rem; font-size:.75rem;">✕ Close</button>
        </div>
    <?php endif; ?>
</section>

<!-- Recent Results Table from PostgreSQL -->
<?php include __DIR__ . '/display.php'; ?>

<script>
/**
 * Smooth Interactive Animation Controller
 */
function replayAnimation() {
    const canvas = document.getElementById('tree-canvas');
    if (!canvas) return;
    
    // Temporarily clone or toggle animation class to re-trigger smoothly
    const nodes = canvas.querySelectorAll('.animated-node, .animated-stem');
    nodes.forEach(el => {
        el.style.animation = 'none';
        el.offsetHeight; // trigger reflow
        el.style.animation = '';
    });
}

function changeSpeed(val) {
    document.documentElement.style.setProperty('--step-duration', val);
    replayAnimation();
}

function inspectNode(label, desc, type) {
    const bar = document.getElementById('inspector-bar');
    const labelEl = document.getElementById('inspect-label');
    const descEl = document.getElementById('inspect-desc');
    if (bar && labelEl && descEl) {
        labelEl.textContent = label;
        descEl.textContent = desc;
        bar.classList.add('visible');
    }
}

function closeInspector() {
    const bar = document.getElementById('inspector-bar');
    if (bar) bar.classList.remove('visible');
}
</script>

</body>
</html>
