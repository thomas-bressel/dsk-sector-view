<div id="tab-infos" class="tab-panel">

    <div class="info-section" style="border-left-color:var(--accent3)">
        <h3><?= $t['tape_infos_format_title'] ?></h3>
        <p><?= $t['tape_infos_format_desc'] ?></p>
        <table class="fdc-table" style="margin-top:8px">
            <tr><td>0–6</td><td><code>ZXTape!</code></td><td><?= $t['tape_infos_format_row0'] ?></td></tr>
            <tr><td>7</td><td><code>0x1A</code></td><td><?= $t['tape_infos_format_row1'] ?></td></tr>
            <tr><td>8</td><td>Major Version</td><td><?= $t['tape_infos_format_row2'] ?></td></tr>
            <tr><td>9</td><td>Minor Version</td><td><?= $t['tape_infos_format_row3'] ?></td></tr>
        </table>
        <p style="margin-top:8px"><?= $t['tape_infos_format_after'] ?></p>
        <p style="margin-top:6px"><?= $t['tape_infos_format_clock'] ?></p>
    </div>

    <div class="info-section" style="border-left-color:var(--accent)">
        <h3><?= $t['tape_infos_blocks_title'] ?></h3>
        <div class="fdc-grid">
            <div class="fdc-col">
                <div class="fdc-title"><?= $t['tape_infos_blocks_data'] ?></div>
                <table class="fdc-table">
                    <?php $dataBlkNames = ['0x10'=>'Standard Loading Data','0x11'=>'Turbo Loading Data','0x12'=>'Pure Tone','0x13'=>'Sequence of Pulses','0x14'=>'Pure Data','0x15'=>'Direct Recording','0x18'=>'CSW Recording','0x19'=>'Generalized Data'];
                    foreach ($dataBlkNames as $code => $name): ?>
                    <tr><td><code><?= $code ?></code></td><td><?= $name ?></td><td><?= $t['tape_infos_blk'][$code] ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
            <div class="fdc-col">
                <div class="fdc-title"><?= $t['tape_infos_blocks_ctrl'] ?></div>
                <table class="fdc-table">
                    <?php $ctrlBlkNames = ['0x20'=>'Pause / Stop','0x21'=>'Group Start','0x22'=>'Group End','0x30'=>'Text Description','0x31'=>'Message Block','0x32'=>'Archive Info','0x33'=>'Hardware Type','0x35'=>'Custom Info','0x5A'=>'Glue Block'];
                    foreach ($ctrlBlkNames as $code => $name): ?>
                    <tr><td><code><?= $code ?></code></td><td><?= $name ?></td><td><?= $t['tape_infos_blk'][$code] ?></td></tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </div>

    <div class="info-section" style="border-left-color:var(--green)">
        <h3><?= $t['tape_infos_cpc_title'] ?></h3>
        <p><?= $t['tape_infos_cpc_desc'] ?></p>
        <table class="fdc-table" style="margin-top:8px">
            <?php foreach ($t['tape_infos_cpc_rows'] as $row): ?>
            <tr><td><?= htmlspecialchars($row[0]) ?></td><td><code><?= htmlspecialchars($row[1]) ?></code></td><td><?= htmlspecialchars($row[2]) ?></td></tr>
            <?php endforeach; ?>
        </table>
        <p style="margin-top:8px"><?= $t['tape_infos_cpc_after'] ?></p>
    </div>

    <div class="info-section" style="border-left-color:#5AF7CE">
        <h3><?= $t['tape_infos_zx_title'] ?></h3>
        <p><?= $t['tape_infos_zx_desc'] ?></p>
        <table class="fdc-table" style="margin-top:8px">
            <?php foreach ($t['tape_infos_zx_rows'] as $row): ?>
            <tr><td><?= htmlspecialchars($row[0]) ?></td><td><code><?= htmlspecialchars($row[1]) ?></code></td><td><?= htmlspecialchars($row[2]) ?></td></tr>
            <?php endforeach; ?>
        </table>
        <p style="margin-top:8px"><?= $t['tape_infos_zx_after'] ?></p>
    </div>

    <div class="info-section" style="border-left-color:var(--accent2)">
        <h3><?= $t['tape_infos_turbo_title'] ?></h3>
        <table class="fdc-table">
            <?php foreach ($t['tape_infos_turbo_rows'] as $row): ?>
            <tr><td><?= htmlspecialchars($row[0]) ?></td><td><?= htmlspecialchars($row[1]) ?></td><td><?= htmlspecialchars($row[2]) ?></td></tr>
            <?php endforeach; ?>
        </table>
        <p style="margin-top:8px"><?= $t['tape_infos_turbo_calc'] ?></p>
    </div>

    <div class="info-section" style="border-left-color:var(--text-dim)">
        <h3><?= $t['tape_infos_sum_title'] ?></h3>
        <p><?= $t['tape_infos_sum_desc'] ?></p>
        <p style="margin-top:6px"><?= $t['tape_infos_sum_warning'] ?></p>
    </div>

</div>
