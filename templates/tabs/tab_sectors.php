<div id="tab-sectors" class="tab-panel">
    <div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr>
                <th><?= $t['sectors_col_track'] ?></th>
                <th><?= $t['sectors_col_id'] ?></th>
                <th><?= $t['sectors_col_size'] ?></th>
                <th><?= $t['sectors_col_real_size'] ?></th>
                <th><?= $t['sectors_col_sum_data'] ?></th>
                <th><?= $t['sectors_col_fdc_flags'] ?></th>
                <th><?= $t['sectors_col_gaps'] ?></th>
                <th><?= $t['sectors_col_gap2'] ?></th>
                <th><?= $t['sectors_col_erased'] ?></th>
                <th><?= $t['sectors_col_weak'] ?></th>
                <th><?= $t['sectors_col_used'] ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($d['sectors'] as $s): ?>
        <tr>
            <td class="center"><?= $s['track'] ?></td>
            <td class="mono center"><?= FormatHelper::hex($s['R']) ?></td>
            <td class="center"><?= $s['declSize'] ?></td>
            <td class="center<?= $s['isIncomplete'] ? '" style="color:var(--orange)' : '' ?>"><?= $s['realSize'] ?></td>
            <td class="mono"><?= number_format($s['sumData']) ?></td>
            <td class="mono center"><?= FormatHelper::fdcBinary($s['sr1']) ?></td>
            <td class="mono center">-</td>
            <td class="mono center">-</td>
            <td class="center"><?= FormatHelper::badge($s['isErased'], $t['sectors_yes'], 'erased', '-') ?></td>
            <td class="center"><?= FormatHelper::badge($s['isWeak'],   $t['sectors_yes'], 'weak',   '-') ?></td>
            <td class="center"><?= FormatHelper::badge($s['isUsed'],   $t['sectors_yes'], 'yes',    '-') ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:var(--bg3);font-weight:700">
                <td colspan="3" style="color:var(--text-dim)"><?= $t['sectors_total'] ?></td>
                <td class="center"><?= number_format($d['totalRealBytes']) ?></td>
                <td class="mono"><?= number_format($d['totalSumData']) ?></td>
                <td colspan="6"></td>
            </tr>
        </tfoot>
    </table>
    </div>
</div>
