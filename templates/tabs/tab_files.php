<div id="tab-files" class="tab-panel">
    <?php if (empty($d['files'])): ?>
    <div class="empty-state">
        <div class="es-icon">📂</div>
        <div>Aucun fichier CP/M trouvé (catalogue vide ou format non standard).</div>
    </div>
    <?php else: ?>
    <div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr>
                <th>User</th>
                <th>Nom</th>
                <th>Extension</th>
                <th>Bloc départ</th>
                <th>Lecture seule</th>
                <th>Caché</th>
                <th>Taille</th>
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
                    // Dernier bloc → ID secteur physique (0xC1 + bloc) comme CPC-Power
                    $lastBlock  = end($blocks);
                    $sectorId   = '#' . strtoupper(str_pad(dechex(0xC1 + $lastBlock), 2, '0', STR_PAD_LEFT));
                    echo $sectorId;
                else: ?>-<?php endif; ?>
            </td>
            <td class="center"><?= FormatHelper::badge($f['readonly'], 'OUI', 'ro') ?></td>
            <td class="center"><?= FormatHelper::badge($f['hidden'],   'OUI', 'hidden') ?></td>
            <td><?= $f['sizeKo'] ?> Ko</td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" style="color:var(--text-dim)"><?= count($d['files']) ?> fichier(s)</td>
                <td><?= array_sum(array_column($d['files'], 'sizeKo')) ?> Ko</td>
            </tr>
        </tfoot>
    </table>
    </div>
    <?php endif; ?>
</div>
