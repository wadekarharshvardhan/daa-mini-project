<?php
/**
 * display.php — Fetches the last 10 results and renders an HTML table.
 * Included at the bottom of index.php.
 */

declare(strict_types=1);
require_once __DIR__ . '/db.php';

$rows = get_db()
    ->query('SELECT id, input_text, pattern, algorithm, time_ms, comparisons, created_at
             FROM search_results ORDER BY created_at DESC LIMIT 10')
    ->fetchAll();
?>

<?php if ($rows): ?>
<section class="results card">
    <h2>Recent Search History</h2>
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>#</th>
                    <th>Text</th>
                    <th>Pattern</th>
                    <th>Algorithm</th>
                    <th>Time (ms)</th>
                    <th>Comparisons</th>
                    <th>Timestamp</th>
                </tr>
            </thead>
            <tbody>
            <?php foreach ($rows as $r): ?>
                <tr>
                    <td class="col-id">#<?= (int)$r['id'] ?></td>
                    <td class="col-text" title="<?= htmlspecialchars($r['input_text'], ENT_QUOTES) ?>">
                        <?= htmlspecialchars(strlen($r['input_text']) > 50 ? substr($r['input_text'], 0, 47) . '…' : $r['input_text'], ENT_QUOTES) ?>
                    </td>
                    <td><code class="pattern-tag"><?= htmlspecialchars($r['pattern'], ENT_QUOTES) ?></code></td>
                    <td>
                        <span class="badge <?= $r['algorithm'] === 'Naive' ? 'badge-naive' : 'badge-rk' ?>">
                            <?= htmlspecialchars($r['algorithm'], ENT_QUOTES) ?>
                        </span>
                    </td>
                    <td class="col-metric"><?= number_format((float)$r['time_ms'], 4) ?> ms</td>
                    <td class="col-metric font-semibold"><?= number_format((int)$r['comparisons']) ?></td>
                    <td class="col-time"><?= htmlspecialchars($r['created_at'], ENT_QUOTES) ?></td>
                </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
<?php else: ?>
<p class="empty">No search records yet. Run your first search above.</p>
<?php endif; ?>
