</main>

<footer class="modern-footer mt-auto">
    <div class="container text-center">
        <div class="d-flex flex-column flex-sm-row justify-content-between align-items-center gap-2">
            <div class="d-flex align-items-center gap-2">
                <i class="bi bi-shield-check text-success"></i>
                <span>Sistem Absensi Karyawan Terintegrasi WhatsApp Gateway Fonnte</span>
            </div>
            <div>
                <span>&copy; <?= date('Y') ?> PresensiWA. All rights reserved.</span>
            </div>
        </div>
    </div>
</footer>

<!-- Scripts -->
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="<?= BASE_URL ?>/assets/js/main.js"></script>

<?php if (isset($_SESSION['flash'])): ?>
<script>
    document.addEventListener('DOMContentLoaded', function() {
        Swal.fire({
            icon: '<?= $_SESSION['flash']['type'] ?? 'info' ?>',
            title: '<?= addslashes($_SESSION['flash']['title'] ?? 'Pemberitahuan') ?>',
            text: '<?= addslashes($_SESSION['flash']['message'] ?? '') ?>',
            timer: 3500,
            showConfirmButton: false,
            customClass: { popup: 'rounded-4' }
        });
    });
</script>
<?php unset($_SESSION['flash']); endif; ?>

</body>
</html>

