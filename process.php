<?php
/**
 * process.php — Handles POST, runs the chosen algorithm, persists metrics, redirects.
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

// ── Guard: only POST ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// ── Input sanitisation ──────────────────────────────────────────────
$text      = $_POST['text']      ?? '';
$pattern   = $_POST['pattern']   ?? '';
$algorithm = $_POST['algorithm'] ?? '';

if ($text === '' || $pattern === '' || !in_array($algorithm, ['Naive', 'Rabin-Karp'], true)) {
    header('Location: index.php?err=invalid');
    exit;
}

// ── Algorithm implementations ───────────────────────────────────────

/**
 * Naive (Brute-Force) string search.
 * Worst-case: O((n - m + 1) · m)  comparisons.
 *
 * @return array{positions: int[], comparisons: int}
 */
function naive_search(string $text, string $pattern): array
{
    $n   = strlen($text);
    $m   = strlen($pattern);
    $cmp = 0;
    $pos = [];

    for ($i = 0; $i <= $n - $m; $i++) {
        $j = 0;
        while ($j < $m) {
            $cmp++;
            if ($text[$i + $j] !== $pattern[$j]) {
                break;
            }
            $j++;
        }
        if ($j === $m) {
            $pos[] = $i;
        }
    }

    return ['positions' => $pos, 'comparisons' => $cmp];
}

/**
 * Rabin-Karp string search using a rolling polynomial hash.
 * Expected: O(n + m)  |  Worst-case (many hash collisions): O(n · m)
 *
 * We count every character-level comparison (including hash computation reads)
 * to give an honest metric, but the rolling hash avoids most brute-force scans.
 *
 * @return array{positions: int[], comparisons: int}
 */
function rabin_karp_search(string $text, string $pattern): array
{
    $n     = strlen($text);
    $m     = strlen($pattern);
    $cmp   = 0;
    $pos   = [];
    $base  = 256;
    $mod   = 101;         // small prime – keeps arithmetic in int range

    if ($m > $n) {
        return ['positions' => [], 'comparisons' => 0];
    }

    // Pre-compute: h = base^(m-1) mod mod
    $h = 1;
    for ($i = 0; $i < $m - 1; $i++) {
        $h = ($h * $base) % $mod;
    }

    // Initial hashes
    $pH = 0; // pattern hash
    $tH = 0; // text window hash
    for ($i = 0; $i < $m; $i++) {
        $pH = ($base * $pH + ord($pattern[$i])) % $mod;
        $tH = ($base * $tH + ord($text[$i]))    % $mod;
    }

    // Slide window
    for ($i = 0; $i <= $n - $m; $i++) {
        if ($pH === $tH) {
            // Hash match → verify character-by-character
            $j = 0;
            while ($j < $m) {
                $cmp++;
                if ($text[$i + $j] !== $pattern[$j]) {
                    break;
                }
                $j++;
            }
            if ($j === $m) {
                $pos[] = $i;
            }
        }

        // Roll the hash forward
        if ($i < $n - $m) {
            $tH = ($base * ($tH - ord($text[$i]) * $h) + ord($text[$i + $m])) % $mod;
            if ($tH < 0) {
                $tH += $mod;
            }
        }
    }

    return ['positions' => $pos, 'comparisons' => $cmp];
}

// ── Execute & measure ───────────────────────────────────────────────
$start  = hrtime(true);  // nanoseconds

$result = ($algorithm === 'Naive')
    ? naive_search($text, $pattern)
    : rabin_karp_search($text, $pattern);

$elapsed_ms = (hrtime(true) - $start) / 1e6;  // ns → ms

// ── Persist with prepared statement (SQL-injection safe) ────────────
$stmt = get_db()->prepare(
    'INSERT INTO search_results (input_text, pattern, algorithm, time_ms, comparisons)
     VALUES (:text, :pattern, :algo, :time, :cmp)
     RETURNING id'
);
$stmt->execute([
    ':text'    => $text,
    ':pattern' => $pattern,
    ':algo'    => $algorithm,
    ':time'    => round($elapsed_ms, 4),
    ':cmp'     => $result['comparisons'],
]);
$new_id = (int)$stmt->fetchColumn();

header('Location: index.php?ok=1' . ($new_id ? '&id=' . $new_id : ''));
exit;
