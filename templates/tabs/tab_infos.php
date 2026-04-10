    <div id="tab-infos" class="tab-panel">

        <div class="info-section" style="border-left-color:#FF0000">
            <h3><span class="info-swatch" style="background:#FF0000"></span> <?= $t['infos_weak_title'] ?></h3>
            <div class="info-color-row">
                <span class="info-swatch-block" style="background:#FF0000;color:#fff"><?= $t['infos_weak_used'] ?></span>
                <span class="info-swatch-block" style="background:#A00000;color:#fff"><?= $t['infos_weak_empty'] ?></span>
            </div>
            <p><?= $t['infos_weak_desc'] ?></p>
            <p style="margin-top:8px"><?= $t['infos_weak_warning'] ?></p>
        </div>

        <div class="info-section" style="border-left-color:#84CFEF">
            <h3><span class="info-swatch" style="background:#84CFEF"></span> <?= $t['infos_erased_title'] ?></h3>
            <div class="info-color-row">
                <span class="info-swatch-block" style="background:#84CFEF"><?= $t['infos_erased_used'] ?></span>
                <span class="info-swatch-block" style="background:#0073DF;color:#fff"><?= $t['infos_erased_empty'] ?></span>
            </div>
            <p><?= $t['infos_erased_desc'] ?></p>
        </div>

        <div class="info-section" style="border-left-color:#FF00FF">
            <h3><span class="info-swatch" style="background:#FF00FF"></span> <?= $t['infos_erased_weak_title'] ?></h3>
            <div class="info-color-row">
                <span class="info-swatch-block" style="background:#FF00FF;color:#fff"><?= $t['infos_erased_weak_used'] ?></span>
                <span class="info-swatch-block" style="background:#BA00BA;color:#fff"><?= $t['infos_erased_weak_empty'] ?></span>
            </div>
            <p><?= $t['infos_erased_weak_desc'] ?></p>
        </div>

        <div class="info-section" style="border-left-color:var(--orange)">
            <h3><?= $t['infos_incomplete_title'] ?></h3>
            <div class="info-color-row">
                <span class="info-swatch-block" style="background:#fff;color:#333;border:2px dashed green"><?= $t['infos_incomplete_label'] ?></span>
            </div>
            <p><?= $t['infos_incomplete_desc'] ?></p>
        </div>

        <div class="info-section" style="border-left-color:var(--orange)">
            <h3><?= $t['infos_prot6_title'] ?></h3>
            <p><?= $t['infos_prot6_cpc'] ?></p>
            <p style="margin-top:8px"><?= $t['infos_prot6_copy'] ?></p>
            <p style="margin-top:8px"><?= $t['infos_prot6_mastering'] ?></p>
        </div>

        <div class="info-section" style="border-left-color:var(--text-dim)">
            <h3><?= $t['infos_fdc_errors_title'] ?></h3>
            <p><?= $t['infos_fdc_errors_desc'] ?></p>
        </div>

        <div class="info-section" style="border-left-color:var(--accent2)">
            <h3><?= $t['infos_gaps_title'] ?></h3>
            <p><?= $t['infos_gaps_desc'] ?></p>
        </div>

        <div class="info-section" style="border-left-color:var(--accent)">
            <h3><?= $t['infos_fdc_flags_title'] ?></h3>
            <div class="fdc-grid">
                <div class="fdc-col">
                    <div class="fdc-title"><?= $t['infos_sr1_title'] ?></div>
                    <table class="fdc-table">
                        <?php foreach ($t['infos_sr1_bits'] as $row): ?>
                        <tr><td><?= $row[0] ?></td><td><strong><?= $row[1] ?></strong></td><td><?= $row[2] ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                </div>
                <div class="fdc-col">
                    <div class="fdc-title"><?= $t['infos_sr2_title'] ?></div>
                    <table class="fdc-table">
                        <?php foreach ($t['infos_sr2_bits'] as $row): ?>
                        <tr><td><?= $row[0] ?></td><td><strong><?= $row[1] ?></strong></td><td><?= $row[2] ?></td></tr>
                        <?php endforeach; ?>
                    </table>
                </div>
            </div>
        </div>

        <div class="info-section" style="border-left-color:var(--green)">
            <h3><?= $t['infos_fat_title'] ?></h3>
            <p><?= $t['infos_fat_desc'] ?></p>
            <p style="margin-top:8px"><?= $t['infos_fat_default'] ?></p>
            <ul>
                <?php foreach ($t['infos_fat_items'] as $item): ?>
                <li><?= $item ?></li>
                <?php endforeach; ?>
            </ul>
            <p style="margin-top:8px"><?= $t['infos_fat_user229'] ?></p>
        </div>

        <div class="info-section" style="border-left-color:var(--text-dim)">
            <h3><?= $t['infos_sum_data_title'] ?></h3>
            <p><?= $t['infos_sum_data_desc'] ?></p>
        </div>
    </div>
