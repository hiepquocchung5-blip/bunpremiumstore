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
                        <a href="https://t.me/digitalmm_support" target="_blank" rel="noopener noreferrer" aria-label="Telegram support" class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center text-slate-400 hover:bg-blue-500 hover:text-white transition shadow-sm"><i class="fab fa-telegram-plane"></i></a>
                        <a href="https://www.facebook.com/share/1EVfYN66Kp/?mibextid=wwXIfr" target="_blank" rel="noopener noreferrer" aria-label="Facebook page" class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center text-slate-400 hover:bg-blue-600 hover:text-white transition shadow-sm"><i class="fab fa-facebook-f"></i></a>
                        <a href="https://www.tiktok.com/@digitalmarketplacemm?_r=1&_t=ZS-97TirtbK2rP" target="_blank" rel="noopener noreferrer" aria-label="TikTok profile" class="w-10 h-10 rounded-xl bg-slate-800 flex items-center justify-center text-slate-400 hover:bg-white hover:text-black transition shadow-sm"><i class="fab fa-tiktok"></i></a>
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

    <!-- PREMIUM COOKIE CONSENT UI -->
    <style>
        .cookie-banner-glass {
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.4);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.15), 0 0 30px rgba(0, 0, 0, 0.05);
        }
        html[data-theme="dark"] .cookie-banner-glass {
            background: rgba(15, 23, 42, 0.85);
            border-color: rgba(255, 255, 255, 0.1);
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.5), 0 0 30px rgba(0, 240, 255, 0.05);
        }
        .toggle-checkbox:checked {
            right: 0;
            border-color: #3b82f6;
        }
        .toggle-checkbox:checked + .toggle-label {
            background-color: #3b82f6;
        }
    </style>

    <div id="cookieConsentBanner" class="fixed bottom-6 left-6 right-6 md:left-auto md:right-8 md:w-[450px] z-[9999] cookie-banner-glass rounded-3xl p-6 transition-all duration-500 translate-y-full opacity-0">
        <div class="flex items-start justify-between mb-4">
            <div class="flex items-center gap-3">
                <div class="w-10 h-10 rounded-full bg-blue-500/10 flex items-center justify-center text-blue-500">
                    <i class="fas fa-cookie-bite text-xl"></i>
                </div>
                <h3 class="font-bold text-slate-800 dark:text-white text-lg">Your Privacy Matters</h3>
            </div>
            <button onclick="closeCookieBanner()" class="text-slate-400 hover:text-slate-600 dark:hover:text-white transition"><i class="fas fa-times text-lg"></i></button>
        </div>
        <p class="text-sm text-slate-600 dark:text-slate-400 leading-relaxed mb-6">
            We use cookies to enhance your experience, ensure secure sessions, and analyze marketplace traffic. Choose your preferences below.
        </p>
        <div class="flex flex-col sm:flex-row gap-3">
            <button onclick="acceptAllCookies()" class="flex-1 bg-blue-600 hover:bg-blue-500 text-white font-bold py-3 rounded-xl shadow-lg shadow-blue-500/20 transition active:scale-95">Accept All</button>
            <button onclick="openCookiePrefs()" class="flex-1 bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-white font-bold py-3 rounded-xl transition active:scale-95">Preferences</button>
        </div>
    </div>

    <!-- COOKIE PREFERENCES MODAL -->
    <div id="cookiePrefsModal" class="fixed inset-0 z-[10000] hidden flex items-center justify-center px-4">
        <div class="absolute inset-0 bg-slate-900/60 backdrop-blur-sm" onclick="closeCookiePrefs()"></div>
        <div class="relative w-full max-w-lg cookie-banner-glass rounded-[2rem] p-8 animate-fade-in-up">
            <div class="flex justify-between items-center mb-6 border-b border-slate-200 dark:border-white/10 pb-4">
                <h2 class="text-xl font-bold text-slate-800 dark:text-white">Cookie Preferences</h2>
                <button onclick="closeCookiePrefs()" class="text-slate-400 hover:text-slate-600 dark:hover:text-white transition"><i class="fas fa-times text-xl"></i></button>
            </div>

            <div class="space-y-6 max-h-[60vh] overflow-y-auto pr-2 no-scrollbar">
                
                <!-- Essential -->
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h4 class="font-bold text-slate-800 dark:text-white text-sm">Essential Cookies</h4>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">Required for secure sessions, login state, and CSRF protection. Cannot be disabled.</p>
                    </div>
                    <div class="text-xs font-bold text-blue-500 bg-blue-500/10 px-3 py-1 rounded-full uppercase tracking-wider shrink-0">Always On</div>
                </div>

                <!-- Analytics -->
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h4 class="font-bold text-slate-800 dark:text-white text-sm">Analytics</h4>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">Helps us understand how you use the marketplace to improve UI and performance.</p>
                    </div>
                    <div class="relative inline-block w-10 mr-2 align-middle select-none transition duration-200 ease-in shrink-0">
                        <input type="checkbox" name="toggle" id="cookieAnalytics" class="toggle-checkbox absolute block w-5 h-5 rounded-full bg-white border-4 appearance-none cursor-pointer border-slate-300 dark:border-slate-500 z-10 top-0 left-0 transition-all duration-300"/>
                        <label for="cookieAnalytics" class="toggle-label block overflow-hidden h-5 rounded-full bg-slate-300 dark:bg-slate-600 cursor-pointer"></label>
                    </div>
                </div>

                <!-- Marketing -->
                <div class="flex items-start justify-between gap-4">
                    <div>
                        <h4 class="font-bold text-slate-800 dark:text-white text-sm">Marketing & Personalization</h4>
                        <p class="text-xs text-slate-500 mt-1 leading-relaxed">Used to deliver personalized discounts and track affiliate performance.</p>
                    </div>
                    <div class="relative inline-block w-10 mr-2 align-middle select-none transition duration-200 ease-in shrink-0">
                        <input type="checkbox" name="toggle" id="cookieMarketing" class="toggle-checkbox absolute block w-5 h-5 rounded-full bg-white border-4 appearance-none cursor-pointer border-slate-300 dark:border-slate-500 z-10 top-0 left-0 transition-all duration-300"/>
                        <label for="cookieMarketing" class="toggle-label block overflow-hidden h-5 rounded-full bg-slate-300 dark:bg-slate-600 cursor-pointer"></label>
                    </div>
                </div>

            </div>

            <div class="mt-8 pt-4 border-t border-slate-200 dark:border-white/10 flex justify-end gap-3">
                <button onclick="saveCookiePrefs()" class="bg-blue-600 hover:bg-blue-500 text-white font-bold px-6 py-3 rounded-xl shadow-lg transition active:scale-95">Save Preferences</button>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener("DOMContentLoaded", () => {
            if (!localStorage.getItem('cookie_consent')) {
                setTimeout(() => {
                    const banner = document.getElementById('cookieConsentBanner');
                    if(banner) {
                        banner.classList.remove('translate-y-full', 'opacity-0');
                    }
                }, 1000);
            }
        });

        function acceptAllCookies() {
            localStorage.setItem('cookie_consent', 'all');
            document.getElementById('cookieAnalytics').checked = true;
            document.getElementById('cookieMarketing').checked = true;
            closeCookieBanner();
        }

        function closeCookieBanner() {
            const banner = document.getElementById('cookieConsentBanner');
            if(banner) {
                banner.classList.add('translate-y-full', 'opacity-0');
                setTimeout(() => banner.style.display = 'none', 500);
            }
        }

        function openCookiePrefs() {
            document.getElementById('cookiePrefsModal').classList.remove('hidden');
        }

        function closeCookiePrefs() {
            document.getElementById('cookiePrefsModal').classList.add('hidden');
        }

        function saveCookiePrefs() {
            const analytics = document.getElementById('cookieAnalytics').checked;
            const marketing = document.getElementById('cookieMarketing').checked;
            localStorage.setItem('cookie_consent', JSON.stringify({ analytics, marketing }));
            closeCookiePrefs();
            closeCookieBanner();
        }
    </script>
</body>
</html>
