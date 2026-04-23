<div id="tab-catalogue" class="tab-panel">

<?php if (empty($d['catalogue'])): ?>
    <div class="empty-state">
        <div class="es-icon">📂</div>
        <div>Aucun fichier détecté (catalogue vide ou format non reconnu).</div>
        <div style="margin-top:8px;font-size:12px;color:var(--text-dim)">
            Seuls les fichiers avec un en-tête Amstrad CPC (0x2C) ou ZX Spectrum (0x00) sont identifiés.
        </div>
    </div>
<?php else: ?>

    <?php foreach ($d['catalogue'] as $i => $entry): ?>
    <?php $h = $entry['header']; $isCpc = $h['isCpc'] ?? true; ?>
    <div class="cat-entry">
        <div class="cat-header-bar">
            <span class="cat-icon"><?= $isCpc ? '🖥' : '💻' ?></span>
            <span class="cat-filename"><?= htmlspecialchars($h['name'] !== '' ? $h['name'] : '(sans nom)') ?></span>
            <span class="cat-platform"><?= $isCpc ? 'Amstrad CPC' : 'ZX Spectrum' ?></span>
            <span class="cat-type-badge"><?= htmlspecialchars($h['fileTypeName']) ?></span>
        </div>

        <div class="cat-body">
            <?php if ($isCpc): ?>
            <!-- ── En-tête CPC ────────────────────────────────────────── -->
            <div class="cat-grid">
                <div class="cat-section">
                    <div class="cat-section-title">En-tête (bloc <?= str_pad($entry['headerBlockIndex'], 4, '0', STR_PAD_LEFT) ?>)</div>
                    <table class="cat-table">
                        <tr><td>Nom fichier</td>
                            <td class="mono"><?= htmlspecialchars($h['name']) ?></td></tr>
                        <tr><td>Type</td>
                            <td><?= htmlspecialchars($h['fileTypeName']) ?></td></tr>
                        <tr><td>Numéro de bloc</td>
                            <td class="mono"><?= $h['blockNum'] ?></td></tr>
                        <tr><td>Premier bloc</td>
                            <td><?= FormatHelper::badge($h['firstBlock'], 'OUI', 'yes') ?></td></tr>
                        <tr><td>Dernier bloc</td>
                            <td><?= FormatHelper::badge($h['lastBlock'], 'OUI', 'yes') ?></td></tr>
                        <tr><td>Sum DATA (header)</td>
                            <td class="mono"><?= number_format($entry['headerSumData']) ?></td></tr>
                    </table>
                </div>

                <div class="cat-section">
                    <div class="cat-section-title">Adresses mémoire</div>
                    <table class="cat-table">
                        <tr><td>Adresse de chargement</td>
                            <td class="mono addr-highlight"><?= FormatHelper::addr($h['loadAddr']) ?></td></tr>
                        <tr><td>Longueur des données</td>
                            <td class="mono"><?= FormatHelper::addr($h['dataLen']) ?>
                                <span class="bytes-hint">(<?= number_format($h['dataLen']) ?> octets)</span>
                            </td></tr>
                        <tr><td>Longueur logique</td>
                            <td class="mono"><?= FormatHelper::addr($h['logLen']) ?>
                                <span class="bytes-hint">(<?= number_format($h['logLen']) ?> octets)</span>
                            </td></tr>
                        <tr><td>Adresse d'exécution</td>
                            <td class="mono addr-highlight"><?= FormatHelper::addr($h['execAddr']) ?></td></tr>
                    </table>
                </div>

                <?php if ($entry['dataBlockIndex'] !== null): ?>
                <div class="cat-section">
                    <div class="cat-section-title">Données (bloc <?= str_pad($entry['dataBlockIndex'], 4, '0', STR_PAD_LEFT) ?>)</div>
                    <table class="cat-table">
                        <tr><td>Taille réelle</td>
                            <td class="mono"><?= number_format($entry['dataLen']) ?> octets</td></tr>
                        <tr><td>Sum DATA (données)</td>
                            <td class="mono"><?= number_format($entry['dataSumData']) ?></td></tr>
                    </table>
                    <button class="btn btn-sm" style="margin-top:10px"
                            onclick="jumpToBlock(<?= $entry['dataBlockIndex'] ?>)">
                        📊 Voir hex dump
                    </button>
                </div>
                <?php endif; ?>
            </div>

            <?php else: ?>
            <!-- ── En-tête ZX Spectrum ───────────────────────────────── -->
            <div class="cat-grid">
                <div class="cat-section">
                    <div class="cat-section-title">En-tête ZX (bloc <?= str_pad($entry['headerBlockIndex'], 4, '0', STR_PAD_LEFT) ?>)</div>
                    <table class="cat-table">
                        <tr><td>Nom</td>
                            <td class="mono"><?= htmlspecialchars($h['name']) ?></td></tr>
                        <tr><td>Type</td>
                            <td><?= htmlspecialchars($h['fileTypeName']) ?></td></tr>
                        <tr><td>Longueur</td>
                            <td class="mono"><?= number_format($h['length']) ?> octets</td></tr>
                        <tr><td>Param 1</td>
                            <td class="mono"><?= FormatHelper::addr($h['param1']) ?></td></tr>
                        <tr><td>Param 2</td>
                            <td class="mono"><?= FormatHelper::addr($h['param2']) ?></td></tr>
                    </table>
                </div>

                <?php if ($entry['dataBlockIndex'] !== null): ?>
                <div class="cat-section">
                    <div class="cat-section-title">Données (bloc <?= str_pad($entry['dataBlockIndex'], 4, '0', STR_PAD_LEFT) ?>)</div>
                    <table class="cat-table">
                        <tr><td>Taille</td>
                            <td class="mono"><?= number_format($entry['dataLen']) ?> octets</td></tr>
                        <tr><td>Sum DATA</td>
                            <td class="mono"><?= number_format($entry['dataSumData']) ?></td></tr>
                    </table>
                    <button class="btn btn-sm" style="margin-top:10px"
                            onclick="jumpToBlock(<?= $entry['dataBlockIndex'] ?>)">
                        📊 Voir hex dump
                    </button>
                </div>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        </div>
    </div>
    <?php endforeach; ?>

    <div style="margin-top:16px;font-size:12px;color:var(--text-dim);text-align:right">
        <?= count($d['catalogue']) ?> fichier(s) détecté(s) sur la cassette
    </div>

<?php endif; ?>

</div>