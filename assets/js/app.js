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

// ===== FORMAT NOMINAL =====
function formatNominal(input) {
    let value = input.value.replace(/\D/g, '');
    input.value = new Intl.NumberFormat('id-ID').format(value);
    const hiddenId = input.dataset.target;
    if (hiddenId) {
        document.getElementById(hiddenId).value = value;
    }
}

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
