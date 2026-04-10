<div id="tab-tracks" class="tab-panel">
    <div class="table-scroll">
    <table class="data-table">
        <thead>
            <tr>
                <th><?= $t['tracks_col_track'] ?></th>
                <th><?= $t['tracks_col_sectors'] ?></th>
                <th><?= $t['tracks_col_sector_size'] ?></th>
                <th><?= $t['tracks_col_gap'] ?></th>
                <th><?= $t['tracks_col_filler'] ?></th>
                <th><?= $t['tracks_col_sum_data'] ?></th>
            </tr>
        </thead>
        <tbody>
        <?php foreach ($d['tracks'] as $tk): ?>
        <tr>
            <td class="center"><?= $tk['num'] ?></td>
            <td class="center"><?= $tk['spt'] ?></td>
            <td><?= number_format($tk['totalBytes']) ?><?= $t['tracks_unit_bytes'] ?> (<?= FormatHelper::bytes($tk['totalBytes']) ?>)</td>
            <td class="mono center"><?= FormatHelper::hex($tk['gap']) ?></td>
            <td class="mono center"><?= FormatHelper::hex($tk['filler']) ?></td>
            <td class="mono"><?= number_format($tk['sumData']) ?></td>
        </tr>
        <?php endforeach; ?>
        </tbody>
        <tfoot>
            <tr style="background:var(--bg3);font-weight:700">
                <td colspan="2" style="color:var(--text-dim)"><?= count($d['tracks']) ?><?= $t['tracks_footer_tracks'] ?></td>
                <td><?= number_format($d['totalSectors']) ?><?= $t['tracks_footer_sectors'] ?></td>
                <td colspan="2"></td>
                <td class="mono"><?= number_format($d['totalSumData']) ?></td>
            </tr>
        </tfoot>
    </table>
    </div>
</div>
