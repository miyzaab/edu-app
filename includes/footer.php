</div><!-- /.content-wrapper -->
</div><!-- /.main-content -->

<!-- jQuery (required for Select2) -->
<script src="https://cdn.jsdelivr.net/npm/jquery@3.7.1/dist/jquery.min.js"></script>
<!-- Bootstrap 5 JS Bundle -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<!-- Select2 JS -->
<script src="https://cdn.jsdelivr.net/npm/select2@4.1.0-rc.0/dist/js/select2.min.js"></script>
<!-- SweetAlert2 -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script>
    $(document).ready(function () {
        // Auto-init Select2 only when dropdowns exist
        function initSelect2() {
            var $targets = $('select.select2, select[name="siswa_id"], select[name="santri_id"]');
            if (!$targets.length) return;

            $targets.each(function () {
                var $el = $(this);
                if ($el.hasClass('select2-hidden-accessible')) {
                    return; // already initialized
                }
                var ph = $el.data('placeholder') || ($el.find('option[value=""]').first().text()) || '🔍 Ketik NIS atau Nama Siswa...';

                $el.select2({
                    theme: 'bootstrap-5',
                    width: '100%',
                    placeholder: ph,
                    allowClear: true,
                    language: {
                        noResults: function () {
                            return "Siswa tidak ditemukan";
                        },
                        searching: function () {
                            return "Mencari...";
                        }
                    }
                });
            });
        }

        initSelect2();

        // Auto focus search field on select2 open
        $(document).on('select2:open', function () {
            setTimeout(function () {
                var searchInput = document.querySelector('.select2-container--open .select2-search__field');
                if (searchInput) {
                    searchInput.focus();
                }
            }, 30);
        });
    });
</script>
</body>

</html>