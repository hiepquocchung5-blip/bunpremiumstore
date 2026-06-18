</main> <!-- End Main Container -->

    <!-- Scroll to Top -->
    <button id="scrollToTop" class="fixed bottom-8 right-8 bg-blue-600 hover:bg-blue-500 text-white w-12 h-12 rounded-full shadow-2xl flex items-center justify-center transition-all duration-300 opacity-0 invisible translate-y-10 z-40 hover:scale-110">
        <i class="fas fa-arrow-up"></i>
    </button>

    <!-- Footer -->
    <footer id="global-footer" class="border-t border-white/5 dm-gradient-bg relative z-10 text-sm">
        
        <div class="max-w-7xl mx-auto px-6 py-16">
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-12 mb-16">
                
                <!-- Brand -->
                <div class="space-y-6">
                    <a href="index.php" class="flex items-center gap-3 group">
                        <div class="w-10 h-10 bg-blue-600 rounded-xl flex items-center justify-center text-white shadow-lg transition-transform group-hover:rotate-12">
                            <i class="fas fa-shopping-bag"></i>
                        </div>
                        <span class="font-bold text-xl text-white">Digital<span class="text-blue-500">MM</span></span>
                    </a>
                    <p class="text-slate-400 leading-relaxed">
                        Myanmar's premier digital marketplace. Instant access to global entertainment and software with local payments.
                    </p>
                    <div class="flex gap-4">
                        <a href="https://t.me/bunpremiumstore" target="_blank" class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center text-slate-400 hover:bg-blue-500 hover:text-white transition shadow-sm"><i class="fab fa-telegram-plane"></i></a>
                        <a href="#" target="_blank" class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center text-slate-400 hover:bg-blue-600 hover:text-white transition shadow-sm"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center text-slate-400 hover:bg-white hover:text-black transition shadow-sm"><i class="fab fa-tiktok"></i></a>
                    </div>
                </div>

                <!-- Store -->
                <div>
                    <h4 class="font-bold text-white mb-6 uppercase tracking-widest text-xs">Explore</h4>
                    <ul class="space-y-4 text-slate-400">
                        <li><a href="index.php?module=shop&page=search" class="hover:text-blue-400 transition">Browse All</a></li>
                        <li><a href="index.php?module=shop&page=category" class="hover:text-blue-400 transition">Categories</a></li>
                        <li><a href="index.php?module=user&page=agent" class="text-yellow-500 hover:text-yellow-400 transition font-bold">Reseller Program</a></li>
                    </ul>
                </div>

                <!-- Support -->
                <div>
                    <h4 class="font-bold text-white mb-6 uppercase tracking-widest text-xs">Customer Care</h4>
                    <ul class="space-y-4 text-slate-400">
                        <li><a href="index.php?module=info&page=support" class="hover:text-blue-400 transition">Help Center</a></li>
                        <li><a href="index.php?module=info&page=tutorial" class="hover:text-blue-400 transition">How to Buy</a></li>
                        <li><a href="index.php?module=info&page=terms" class="hover:text-blue-400 transition">Terms of Service</a></li>
                    </ul>
                </div>

                <!-- Trust -->
                <div>
                    <h4 class="font-bold text-white mb-6 uppercase tracking-widest text-xs">Safe Payments</h4>
                    <div class="grid grid-cols-2 gap-3 mb-6">
                        <div class="bg-slate-800/50 px-3 py-2.5 rounded-xl border border-white/5 text-center text-[10px] font-bold text-blue-400">KBZPay</div>
                        <div class="bg-slate-800/50 px-3 py-2.5 rounded-xl border border-white/5 text-center text-[10px] font-bold text-yellow-500">WavePay</div>
                    </div>
                    <p class="text-xs text-slate-500 leading-relaxed">
                        <i class="fas fa-lock mr-2 text-emerald-500"></i>
                        All transactions are processed through secure, verified channels.
                    </p>
                </div>
            </div>

            <!-- Bottom Bar -->
            <div class="border-t border-white/5 pt-10 flex flex-col md:row justify-between items-center gap-6">
                <p class="text-slate-500 text-xs">
                    &copy; <?php echo date('Y'); ?> DigitalMM. Handcrafted with care.
                </p>
                <div class="flex items-center gap-6 text-xs text-slate-600">
                    <span class="flex items-center gap-2"><span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse"></span> Systems Active</span>
                    <span>Ready for deployment</span>
                </div>
            </div>
        </div>
    </footer>
    
    <!-- Core JS -->
    <script src="<?php echo BASE_URL; ?>assets/js/app.js"></script>

    <!-- Scroll Top Script -->
    <script>
        const scrollBtn = document.getElementById('scrollToTop');
        
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                scrollBtn.classList.remove('opacity-0', 'invisible', 'translate-y-10');
            } else {
                scrollBtn.classList.add('opacity-0', 'invisible', 'translate-y-10');
            }
        });
        
        if(scrollBtn) {
            scrollBtn.addEventListener('click', () => {
                window.scrollTo({ top: 0, behavior: 'smooth' });
            });
        }

        // Trigger Push Permission Logic (if user clicks the button in header)
        const pushBtn = document.getElementById('enable-push');
        if(pushBtn) {
            pushBtn.addEventListener('click', (e) => {
                e.preventDefault();
                // This function is defined in assets/js/app.js
                if(typeof registerServiceWorker === 'function') {
                    registerServiceWorker().then(() => alert('Notifications Enabled!'));
                } else {
                    alert('Please check browser settings.');
                }
            });
        }
    </script>
</body>
</html>
