<div id="tab-files" class="tab-panel">
    <?php if (empty($d['files'])): ?>
    <div class="empty-state">
        <div class="es-icon">📂</div>
        <div><?= htmlspecialchars($t['files_empty']) ?></div>
    </div>
    <?php else: ?>
    <div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr>
                <th><?= htmlspecialchars($t['files_col_user']) ?></th>
                <th><?= htmlspecialchars($t['files_col_name']) ?></th>
                <th><?= htmlspecialchars($t['files_col_ext']) ?></th>
                <th><?= htmlspecialchars($t['files_col_start_block']) ?></th>
                <th><?= htmlspecialchars($t['files_col_readonly']) ?></th>
                <th><?= htmlspecialchars($t['files_col_hidden']) ?></th>
                <th><?= htmlspecialchars($t['files_col_size']) ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($d['files'] as $f): ?>
        <tr>
            <td class="center"><?= $f['user'] ?></td>
            <td><strong><?= htmlspecialchars($f['name']) ?></strong></td>
            <td class="mono"><?= htmlspecialchars($f['ext']) ?></td>
            <td class="mono center">
                <?php
                $blocks = array_values(array_filter($f['allBlocks'] ?? [], fn($b) => $b > 0));
                if (!empty($blocks)):
                    $lastBlock = end($blocks);
                    $sectorId  = '#' . strtoupper(str_pad(dechex(0xC1 + $lastBlock), 2, '0', STR_PAD_LEFT));
                    echo $sectorId;
                else: ?>-<?php endif; ?>
            </td>
            <td class="center"><?= FormatHelper::badge($f['readonly'], $t['files_readonly_yes'], 'ro') ?></td>
            <td class="center"><?= FormatHelper::badge($f['hidden'],   $t['files_hidden_yes'],   'hidden') ?></td>
            <td><?= $f['sizeKo'] ?><?= $t['files_size_unit'] ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" style="color:var(--text-dim)"><?= count($d['files']) ?><?= $t['files_footer'] ?></td>
                <td><?= array_sum(array_column($d['files'], 'sizeKo')) ?><?= $t['files_footer_size'] ?></td>
            </tr>
        </tfoot>
    </table>
    </div>
    <?php endif; ?>
</div>
