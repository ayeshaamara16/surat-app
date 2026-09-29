</main>
    
    <footer class="bg-white shadow-lg border-t border-gray-100/50 mt-auto">
        <div class="container mx-auto px-4 py-4 text-center text-gray-600 text-sm">
            &copy; <?php echo date('Y'); ?> Sekretariat Daerah Kabupaten Banyumas - Sistem Manajemen Surat
        </div>
    </footer>

</div>

<script>
    setTimeout(function() {
        document.querySelectorAll('.flash-message').forEach(function(el) {
            el.style.transition = 'opacity 0.5s';
            el.style.opacity = '0';
            setTimeout(function() { el.remove(); }, 500);
        });
    }, 5000);
</script>
</body>
</html>