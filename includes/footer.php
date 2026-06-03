<?php
$site_name = "FoodSave";
// includes/footer.php
?>

    <!-- ==================== FOOTER ==================== -->
    <footer class="relative py-12 px-4 bg-gray-900 text-gray-400 mt-auto">
        <div class="absolute inset-0 bg-gradient-to-t from-gray-950 to-gray-900"></div>
        <div class="absolute inset-0 bg-pattern-grid opacity-10"></div>
        
        <div class="relative max-w-7xl mx-auto">
            <div class="grid grid-cols-1 md:grid-cols-4 gap-10 mb-10">
                <!-- Brand -->
                <div class="md:col-span-2">
                    <a href="index.php" class="flex items-center gap-2.5 mb-5 group">
                        <span class="text-2xl group-hover:scale-110 transition-transform">🌿</span>
                        <span class="font-bold text-xl text-white"><?= htmlspecialchars($site_name) ?></span>
                    </a>
                    <p class="text-sm leading-relaxed max-w-sm text-gray-400">
                        Platform digital yang menghubungkan konsumen dengan produk makanan surplus berkualitas, 
                        mengurangi waste, dan menciptakan ekonomi sirkular yang berkelanjutan.
                    </p>
                </div>
                
                <!-- Links -->
                <div>
                    <h4 class="font-semibold text-white mb-5">Platform</h4>
                    <ul class="space-y-3 text-sm">
                        <li><a href="PromosiPage.php" class="hover:text-brand transition flex items-center gap-2"><i class="fa-solid fa-chevron-right text-xs"></i> Produk</a></li>
                        <li><a href="#" class="hover:text-brand transition flex items-center gap-2"><i class="fa-solid fa-chevron-right text-xs"></i> Cara Kerja</a></li>
                        <li><a href="#" class="hover:text-brand transition flex items-center gap-2"><i class="fa-solid fa-chevron-right text-xs"></i> Mitra Kami</a></li>
                        <li><a href="#" class="hover:text-brand transition flex items-center gap-2"><i class="fa-solid fa-chevron-right text-xs"></i> Blog</a></li>
                    </ul>
                </div>
                
                <!-- Support -->
                <div>
                    <h4 class="font-semibold text-white mb-5">Bantuan</h4>
                    <ul class="space-y-3 text-sm">
                        <li><a href="#" class="hover:text-brand transition flex items-center gap-2"><i class="fa-solid fa-chevron-right text-xs"></i> FAQ</a></li>
                        <li><a href="#" class="hover:text-brand transition flex items-center gap-2"><i class="fa-solid fa-chevron-right text-xs"></i> Kontak Kami</a></li>
                        <li><a href="#" class="hover:text-brand transition flex items-center gap-2"><i class="fa-solid fa-chevron-right text-xs"></i> Kebijakan Privasi</a></li>
                        <li><a href="#" class="hover:text-brand transition flex items-center gap-2"><i class="fa-solid fa-chevron-right text-xs"></i> Syarat & Ketentuan</a></li>
                    </ul>
                </div>
            </div>
            
            <div class="pt-8 border-t border-gray-800 flex flex-col sm:flex-row justify-between items-center gap-5">
                <p class="text-sm text-gray-500">© <?= date('Y') ?> <?= htmlspecialchars($site_name) ?>. All rights reserved.</p>
                <div class="flex items-center gap-3">
                    <a href="#" class="w-10 h-10 rounded-xl bg-gray-800 flex items-center justify-center hover:bg-brand hover:text-white transition shadow-lg hover:shadow-brand/25">
                        <i class="fa-brands fa-instagram"></i>
                    </a>
                    <a href="#" class="w-10 h-10 rounded-xl bg-gray-800 flex items-center justify-center hover:bg-brand hover:text-white transition shadow-lg hover:shadow-brand/25">
                        <i class="fa-brands fa-twitter"></i>
                    </a>
                    <a href="#" class="w-10 h-10 rounded-xl bg-gray-800 flex items-center justify-center hover:bg-brand hover:text-white transition shadow-lg hover:shadow-brand/25">
                        <i class="fa-brands fa-whatsapp"></i>
                    </a>
                    <a href="#" class="w-10 h-10 rounded-xl bg-gray-800 flex items-center justify-center hover:bg-brand hover:text-white transition shadow-lg hover:shadow-brand/25">
                        <i class="fa-brands fa-tiktok"></i>
                    </a>
                </div>
            </div>
        </div>
    </footer>

    <!-- Font Awesome -->
    <script src="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/js/all.min.js"></script>
    
    <!-- Simple Interactions -->
    <script>
        document.addEventListener('click', function(e) {
            document.querySelectorAll('.group').forEach(group => {
                if (!group.contains(e.target)) {
                    group.classList.remove('hover');
                }
            });
        });
        
        document.querySelectorAll('a[href^="#"]').forEach(anchor => {
            anchor.addEventListener('click', function(e) {
                e.preventDefault();
                const target = document.querySelector(this.getAttribute('href'));
                if (target) {
                    target.scrollIntoView({ behavior: 'smooth', block: 'start' });
                }
            });
        });
    </script>

</body>
</html>