/**
 * Edu-App - Main JavaScript
 */

// ===== SIDEBAR MOBILE =====
function toggleSidebar() {
    const sidebar = document.getElementById('mainSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    if (!sidebar) return;

    const isOpen = sidebar.classList.contains('show');
    if (isOpen) {
        closeSidebar();
    } else {
        sidebar.classList.add('show');
        overlay && overlay.classList.add('show');
        document.body.style.overflow = 'hidden'; // cegah scroll di belakang
    }
}

function closeSidebar() {
    const sidebar = document.getElementById('mainSidebar');
    const overlay = document.getElementById('sidebarOverlay');
    sidebar && sidebar.classList.remove('show');
    overlay && overlay.classList.remove('show');
    document.body.style.overflow = '';
}

// Tutup sidebar saat klik link menu
document.addEventListener('DOMContentLoaded', function () {
    const sidebarLinks = document.querySelectorAll('.sidebar-nav a');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function () {
            closeSidebar();
        });
    });

    // Tutup sidebar dengan tombol Escape (ESC)
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            closeSidebar();
        }
    });

    // Swipe gesture: geser ke kiri untuk tutup sidebar di mobile
    let touchStartX = 0;
    document.addEventListener('touchstart', e => { touchStartX = e.touches[0].clientX; }, { passive: true });
    document.addEventListener('touchend', e => {
        const delta = e.changedTouches[0].clientX - touchStartX;
        if (delta < -60 && window.innerWidth < 768) closeSidebar();
        if (delta > 60 && touchStartX < 30 && window.innerWidth < 768) toggleSidebar();
    }, { passive: true });

    // Auto-hide alert setelah 5 detik
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            alert.style.transform = 'translateY(-10px)';
            setTimeout(() => alert.remove(), 300);
        }, 5000);
    });
});

// ===== KONFIRMASI HAPUS =====
function confirmDelete(url, nama) {
    if (confirm('Apakah Anda yakin ingin menghapus "' + nama + '"?')) {
        window.location.href = url;
    }
}

// ===== FORMAT NOMINAL (Auto-detect & Event Delegation) =====
function formatNominal(input) {
    // Ambil value, hapus semua karakter non-digit
    let value = input.value.replace(/\D/g, '');
    
    // Jika kosong, set ke string kosong
    if (value === '') {
        input.value = '';
        return;
    }

    // Format dengan pemisah ribuan titik
    if (window.Intl && window.Intl.NumberFormat) {
        input.value = new Intl.NumberFormat('id-ID').format(value);
    } else {
        // Fallback regex jika Intl tidak tersedia
        input.value = value.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
    }

    // Jika ada target hidden field, isi dengan value mentah
    const hiddenId = input.dataset.target;
    if (hiddenId) {
        document.getElementById(hiddenId).value = value;
    }
}

// Inisialisasi otomatis untuk input nominal
function initAllNominalInputs() {
    const selector = '.nominal-input, input[name*="nominal"], input[name*="jumlah"], input[name*="biaya"], input[name*="nominal_spp"], input[name*="total"], input[name*="tagihan"], input[name*="target_uang_pangkal"], input[name*="uang_pangkal"]';
    
    const filterElement = (el) => {
        if (el.tagName !== 'INPUT') return false;
        if (['date', 'checkbox', 'radio', 'file', 'hidden'].includes(el.type)) return false;
        if (el.name && (el.name.includes('tanggal') || el.name.includes('metode'))) return false;
        return true;
    };

    // 1. Format semua yang sudah ada saat ini
    document.querySelectorAll(selector).forEach(el => {
        if (!filterElement(el)) return;
        if (el.type === 'number') el.type = 'text';
        el.classList.add('nominal-input');
        if (el.value) formatNominal(el);
    });

    // 2. Event Delegation agar selalu menangkap input baru/dinamis
    document.addEventListener('input', function(e) {
        if (e.target && e.target.matches(selector)) {
            if (!filterElement(e.target)) return;
            e.target.classList.add('nominal-input');
            formatNominal(e.target);
        }
    }, true);

    document.addEventListener('focusin', function(e) {
        if (e.target && e.target.matches(selector)) {
            if (!filterElement(e.target)) return;
            formatNominal(e.target);
        }
    }, true);

    document.addEventListener('change', function(e) {
        if (e.target && e.target.matches(selector)) {
            if (!filterElement(e.target)) return;
            formatNominal(e.target);
        }
    }, true);
}

// Jalankan segera dan saat DOM siap
initAllNominalInputs();
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAllNominalInputs);
}
window.addEventListener('load', initAllNominalInputs);

// Alias backward compatibility
window.formatRupiahInput = function(input) {
    formatNominal(input);
};

// Expose formatNominal ke global
window.applyNominalFormat = function(selectorOrElement) {
    let elements;
    if (typeof selectorOrElement === 'string') {
        elements = document.querySelectorAll(selectorOrElement);
    } else if (selectorOrElement instanceof HTMLElement) {
        elements = [selectorOrElement];
    } else {
        return;
    }
    elements.forEach(el => { formatNominal(el); });
};

// ===== CETAK KWITANSI =====
function cetakKwitansi() {
    window.print();
}

// ===== EXPORT CSV =====
function exportToCSV(tableId, filename) {
    const table = document.getElementById(tableId);
    const rows = table.querySelectorAll('tr');
    let csv = [];

    rows.forEach(row => {
        const cols = row.querySelectorAll('th, td');
        let rowData = [];
        cols.forEach(col => {
            let text = col.innerText.replace(/"/g, '""');
            rowData.push('"' + text + '"');
        });
        csv.push(rowData.join(','));
    });

    const blob = new Blob(['\ufeff' + csv.join('\n')], { type: 'text/csv;charset=utf-8;' });
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename + '.csv';
    link.click();
}

// ===== UNIVERSAL BULK SELECTION ENGINE (AKSI MASSAL) =====
function initBulkTable(options) {
    options = options || {};
    const tableId = options.tableId || 'dataTable';
    const floatingBarId = options.floatingBarId || 'bulkFloatingBar';
    const countBadgeId = options.countBadgeId || 'bulkCountBadge';
    const masterCheckId = options.masterCheckId || 'selectAll';
    const itemCheckClass = options.itemCheckClass || 'bulk-item-check';
    const formId = options.formId || 'bulkActionForm';

    const table = document.getElementById(tableId) || document.querySelector('table');
    const masterCheck = document.getElementById(masterCheckId) || document.querySelector('#' + masterCheckId);
    const floatingBar = document.getElementById(floatingBarId) || document.querySelector('.bulk-floating-bar');
    const countBadge = document.getElementById(countBadgeId) || document.querySelector('.bulk-count-badge');
    const form = document.getElementById(formId) || document.querySelector('#' + formId);

    if (!table) return;

    let lastChecked = null;

    function getCheckboxes() {
        return Array.from(document.querySelectorAll('.' + itemCheckClass));
    }

    function updateBulkUI() {
        const checkboxes = getCheckboxes();
        const checkedList = checkboxes.filter(cb => cb.checked);
        const count = checkedList.length;

        // Update row highlight
        checkboxes.forEach(cb => {
            const tr = cb.closest('tr');
            if (tr) {
                if (cb.checked) {
                    tr.classList.add('row-selected');
                } else {
                    tr.classList.remove('row-selected');
                }
            }
        });

        // Master check state
        const allMasterChecks = document.querySelectorAll('#selectAll, .select-all-checkbox, #' + masterCheckId);
        allMasterChecks.forEach(mc => {
            if (checkboxes.length > 0) {
                mc.checked = (count > 0 && count === checkboxes.length);
                mc.indeterminate = (count > 0 && count < checkboxes.length);
            }
        });

        // Floating bar state & count badge
        const allFloatingBars = document.querySelectorAll('.bulk-floating-bar, #' + floatingBarId);
        allFloatingBars.forEach(bar => {
            if (count > 0) {
                bar.classList.add('show');
            } else {
                bar.classList.remove('show');
            }
        });

        const allCountBadges = document.querySelectorAll('.bulk-count-badge, #' + countBadgeId);
        allCountBadges.forEach(badge => {
            badge.innerHTML = `<i class="bi bi-check2-circle"></i> <span><strong>${count}</strong> item dipilih</span>`;
        });
    }

    // Direct event listener on all master checks
    const allMasterChecks = document.querySelectorAll('#selectAll, .select-all-checkbox, #' + masterCheckId);
    allMasterChecks.forEach(mc => {
        mc.onclick = function () {
            const isChecked = this.checked;
            getCheckboxes().forEach(cb => {
                cb.checked = isChecked;
            });
            updateBulkUI();
        };
        mc.onchange = function () {
            const isChecked = this.checked;
            getCheckboxes().forEach(cb => {
                cb.checked = isChecked;
            });
            updateBulkUI();
        };
    });

    // Individual checkboxes
    getCheckboxes().forEach(cb => {
        cb.onchange = function () {
            updateBulkUI();
        };
    });

    // Helper to get selected values
    window.getSelectedBulkIds = function () {
        return getCheckboxes().filter(cb => cb.checked).map(cb => cb.value);
    };

    // Initial update
    updateBulkUI();
}

// GLOBAL EVENT DELEGATION (Automatic for all pages!)
document.addEventListener('click', function (e) {
    // 1. Master Checkbox Click
    if (e.target && (e.target.id === 'selectAll' || e.target.classList.contains('select-all-checkbox'))) {
        const isChecked = e.target.checked;
        const checkboxes = document.querySelectorAll('.bulk-item-check, input[name="ids[]"]');
        checkboxes.forEach(cb => {
            cb.checked = isChecked;
            const tr = cb.closest('tr');
            if (tr) {
                if (isChecked) tr.classList.add('row-selected');
                else tr.classList.remove('row-selected');
            }
        });

        const count = isChecked ? checkboxes.length : 0;
        const floatingBar = document.querySelector('.bulk-floating-bar');
        if (floatingBar) {
            if (count > 0) floatingBar.classList.add('show');
            else floatingBar.classList.remove('show');
        }
        const badge = document.querySelector('.bulk-count-badge');
        if (badge) {
            badge.innerHTML = `<i class="bi bi-check2-circle"></i> <span><strong>${count}</strong> item dipilih</span>`;
        }
    }

    // 2. Individual Item Checkbox Click
    if (e.target && (e.target.classList.contains('bulk-item-check') || e.target.name === 'ids[]')) {
        const checkboxes = Array.from(document.querySelectorAll('.bulk-item-check, input[name="ids[]"]'));
        const checkedList = checkboxes.filter(cb => cb.checked);
        const count = checkedList.length;

        // Row highlight
        checkboxes.forEach(cb => {
            const tr = cb.closest('tr');
            if (tr) {
                if (cb.checked) tr.classList.add('row-selected');
                else tr.classList.remove('row-selected');
            }
        });

        // Master checkbox state
        const master = document.querySelector('#selectAll, .select-all-checkbox');
        if (master) {
            master.checked = (count > 0 && count === checkboxes.length);
            master.indeterminate = (count > 0 && count < checkboxes.length);
        }

        // Floating bar state
        const floatingBar = document.querySelector('.bulk-floating-bar');
        if (floatingBar) {
            if (count > 0) floatingBar.classList.add('show');
            else floatingBar.classList.remove('show');
        }
        const badge = document.querySelector('.bulk-count-badge');
        if (badge) {
            badge.innerHTML = `<i class="bi bi-check2-circle"></i> <span><strong>${count}</strong> item dipilih</span>`;
        }
    }

    // 3. Cancel / Deselect Button Click
    if (e.target && (e.target.closest('.btn-bulk-cancel') || e.target.closest('[data-bulk-action="cancel"]'))) {
        e.preventDefault();
        const checkboxes = document.querySelectorAll('.bulk-item-check, input[name="ids[]"]');
        checkboxes.forEach(cb => {
            cb.checked = false;
            const tr = cb.closest('tr');
            if (tr) tr.classList.remove('row-selected');
        });
        const master = document.querySelector('#selectAll, .select-all-checkbox');
        if (master) {
            master.checked = false;
            master.indeterminate = false;
        }
        const floatingBar = document.querySelector('.bulk-floating-bar');
        if (floatingBar) floatingBar.classList.remove('show');
    }
});

// Helper globally available
window.getSelectedBulkIds = function () {
    return Array.from(document.querySelectorAll('.bulk-item-check:checked, input[name="ids[]"]:checked')).map(cb => cb.value);
};

// Self-initialize on DOM ready or immediate
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', function () {
        initBulkTable();
    });
} else {
    initBulkTable();
}


