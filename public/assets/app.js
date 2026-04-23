// ===== ONGLETS =====
function showTab(name, btn) {
    document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
    document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
    document.getElementById('tab-' + name).classList.add('active');
    btn.classList.add('active');
}

// ===== DISK VISUAL — clic secteur → onglet Data =====
function diskSectorClick(index) {
    const dataBtn = document.querySelector('.tab-btn[onclick*="data"]');
    showTab('data', dataBtn);
    const sel = document.getElementById('sdata-select');
    if (sel) { sel.selectedIndex = index; sdataShow(index); }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ===== SECTOR DATA VIEWER =====
function sdataShow(index) {
    document.querySelectorAll('.sdata-panel').forEach(p => p.classList.remove('active'));
    const panel = document.getElementById('sdata-panel-' + index);
    if (panel) panel.classList.add('active');
}

function sdataNav(dir) {
    const sel = document.getElementById('sdata-select');
    if (!sel) return;
    const next = Math.max(0, Math.min(sel.options.length - 1, sel.selectedIndex + dir));
    sel.selectedIndex = next;
    sdataShow(next);
}

// ===== JUMP TO BLOCK (tape — depuis catalogue / checkdata) =====
function jumpToBlock(blockIndex) {
    const dataBtn = document.querySelector('.tab-btn[onclick*="data"]');
    if (!dataBtn) return;
    showTab('data', dataBtn);

    const sel = document.getElementById('sdata-select');
    if (!sel) return;
    const padded = String(blockIndex).padStart(4, '0');
    for (let i = 0; i < sel.options.length; i++) {
        if (sel.options[i].text.includes('[' + padded + ']')) {
            sel.selectedIndex = i;
            sdataShow(i);
            break;
        }
    }
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ===== HIGHLIGHT ROW =====
function highlightBlock(index) {
    document.querySelectorAll('.block-row-highlight').forEach(r => r.classList.remove('block-row-highlight'));
    const row = document.getElementById('block-row-' + index);
    if (row) { row.classList.add('block-row-highlight'); row.scrollIntoView({ behavior: 'smooth', block: 'nearest' }); }
}

// ===== DRAG & DROP — helper générique =====
function setupDropZone(dzId, fiId, fnId, allowedExts, icon) {
    const dz = document.getElementById(dzId);
    const fi = document.getElementById(fiId);
    const fn = document.getElementById(fnId);
    if (!dz || !fi) return;

    fi.addEventListener('change', () => {
        fn.textContent = fi.files[0] ? icon + ' ' + fi.files[0].name : '';
    });

    dz.addEventListener('dragover', e => { e.preventDefault(); dz.classList.add('drag-over'); });
    dz.addEventListener('dragleave', () => dz.classList.remove('drag-over'));
    dz.addEventListener('drop', e => {
        e.preventDefault();
        dz.classList.remove('drag-over');
        const dt = e.dataTransfer;
        if (!dt.files.length) return;
        const name = dt.files[0].name.toLowerCase();
        const ok = allowedExts.some(ext => name.endsWith('.' + ext));
        if (ok) {
            fi.files = dt.files;
            fn.textContent = icon + ' ' + dt.files[0].name;
        } else {
            fn.textContent = '⚠️ Format non accepté';
        }
    });
}

document.addEventListener('DOMContentLoaded', function () {
    // Drop zone DSK
    setupDropZone('drop-zone-dsk', 'dsk_file', 'dz-file-name-dsk', ['dsk'], '💽');
    // Drop zone CDT/TZX
    setupDropZone('drop-zone-cdt', 'cdt_file', 'dz-file-name-cdt', ['cdt', 'tzx'], '📼');
});
