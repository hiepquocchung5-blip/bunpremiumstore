// assets/js/app.js
// PRODUCTION v3.3 - Dynamic Alert Injection & Matrix Toast UI

/**
 * --------------------------------------------------------------------------
 * CONFIGURATION
 * --------------------------------------------------------------------------
 */
// Public VAPID Key for Web Push (Injected from server .env)
const PUBLIC_VAPID_KEY = window.AppConfig?.vapidPublicKey || '';
const BASE_URL = window.AppConfig?.baseUrl || '/';

/**
 * --------------------------------------------------------------------------
 * PUSH NOTIFICATION HELPERS
 * --------------------------------------------------------------------------
 */

function urlBase64ToUint8Array(base64String) {
    const padding = '='.repeat((4 - base64String.length % 4) % 4);
    const base64 = (base64String + padding)
        .replace(/\-/g, '+')
        .replace(/_/g, '/');

    const rawData = window.atob(base64);
    const outputArray = new Uint8Array(rawData.length);

    for (let i = 0; i < rawData.length; ++i) {
        outputArray[i] = rawData.charCodeAt(i);
    }
    return outputArray;
}

window.registerServiceWorker = async function(triggerWelcome = false) {
    if (!('serviceWorker' in navigator)) {
        console.error('Service Worker not supported');
        return;
    }

    if (!PUBLIC_VAPID_KEY) {
        console.error('VAPID Public Key is missing. Check .env configuration.');
        return;
    }

    try {
        // FORCE ABSOLUTE ROOT PATH: Prevents 404 errors by ensuring it never looks in /assets/
        const swUrl = '/sw.js';
        const register = await navigator.serviceWorker.register(swUrl, { scope: '/' });
        await navigator.serviceWorker.ready;

        const subscription = await register.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(PUBLIC_VAPID_KEY)
        });

        // 1. Instantly fire a local welcome notification (No DB required)
        if (triggerWelcome && Notification.permission === 'granted') {
            register.showNotification('System Uplink Active ⚡️', {
                body: 'Welcome to DigitalMarketplaceMM! Proceed to the authorization portal to access premium digital assets.',
                icon: '/assets/images/logo.png',
                badge: '/assets/images/logo.png',
                vibrate: [100, 50, 100, 50, 200],
                data: { url: '/index.php?module=auth&page=login' },
                actions: [
                    { action: 'open', title: 'Initialize Login' },
                    { action: 'close', title: 'Abort' }
                ]
            });
        }

        // 2. Sync endpoint with server (for logged in users)
        await fetch(BASE_URL + 'api/push_subscribe.php', {
            method: 'POST',
            body: JSON.stringify(subscription),
            headers: { 'Content-Type': 'application/json' }
        });

        console.log('[Matrix] Push Notification Uplink Secured.');
        return true;
    } catch (err) {
        console.error('Service Worker Error:', err);
        throw err;
    }
};

/**
 * --------------------------------------------------------------------------
 * DOM READY LOGIC
 * --------------------------------------------------------------------------
 */
document.addEventListener('DOMContentLoaded', () => {

    /**
     * 1. Notification Polling System & Dynamic Alert Toast
     */
    const bellIcon = document.querySelector('.fa-bell');
    let previousNotifCount = 0;
    
    // Dynamic Toast Injector
    function showDynamicToast(msg, link) {
        const toast = document.createElement('a');
        toast.href = link ? BASE_URL + link : '#';
        toast.className = 'fixed bottom-20 right-4 md:bottom-6 md:right-6 bg-slate-900/90 backdrop-blur-xl border border-[#00f0ff]/40 text-white p-4 rounded-2xl shadow-[0_10px_40px_rgba(0,240,255,0.25)] z-[100] flex items-center gap-4 transform transition-all duration-500 translate-y-20 opacity-0 group hover:border-[#00f0ff] hover:shadow-[0_10px_50px_rgba(0,240,255,0.4)] max-w-sm';
        toast.innerHTML = `
            <div class="w-12 h-12 bg-[#00f0ff]/10 rounded-xl flex items-center justify-center border border-[#00f0ff]/30 text-[#00f0ff] shrink-0 group-hover:scale-110 transition-transform">
                <i class="fas fa-satellite-dish animate-pulse text-lg"></i>
            </div>
            <div class="flex-1">
                <h4 class="text-[10px] font-black uppercase tracking-widest text-[#00f0ff] mb-0.5 flex items-center gap-2">Matrix Alert <span class="w-1.5 h-1.5 rounded-full bg-[#00f0ff] animate-ping"></span></h4>
                <p class="text-sm font-medium leading-snug text-slate-200">${msg}</p>
            </div>
        `;
        document.body.appendChild(toast);
        
        // Animate in
        requestAnimationFrame(() => {
            toast.classList.remove('translate-y-20', 'opacity-0');
        });
        
        // Auto remove after 6 seconds
        setTimeout(() => {
            toast.classList.add('translate-y-20', 'opacity-0');
            setTimeout(() => toast.remove(), 500);
        }, 6000);
    }

    if (bellIcon) {
        const parent = bellIcon.closest('.relative');
        const badge = parent ? parent.querySelector('span') : null;
        const dropdown = parent ? parent.querySelector('div.absolute') : null;

        function checkNotifications() {
            fetch(BASE_URL + 'api/notifications.php')
                .then(response => {
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    if (!data || typeof data !== 'object') return;

                    // Dynamic Alert Trigger (If count increased, show toast)
                    if (data.count > previousNotifCount && previousNotifCount !== 0) {
                        const latest = data.notifications && data.notifications.length > 0 ? data.notifications[0] : null;
                        const msg = latest ? latest.text : 'A new transmission has been decrypted.';
                        showDynamicToast(msg, latest?.link);
                    }
                    previousNotifCount = data.count;

                    if (data.count > 0) {
                        if (badge) badge.classList.remove('hidden');
                        if (bellIcon) bellIcon.classList.add('text-[#00f0ff]', 'animate-pulse');
                    } else {
                        if (badge) badge.classList.add('hidden');
                        if (bellIcon) bellIcon.classList.remove('text-[#00f0ff]', 'animate-pulse');
                    }

                    if (dropdown) {
                        let contentHtml = '';
                        if (data.notifications && Array.isArray(data.notifications) && data.notifications.length > 0) {
                             contentHtml = data.notifications.map(n => `
                                <a href="${BASE_URL}${n.link}" class="block px-4 py-3 text-xs text-gray-300 hover:bg-gray-700 hover:text-white transition border-b border-gray-700 last:border-0 flex items-start gap-2">
                                    <i class="fas fa-circle text-[6px] text-[#00f0ff] mt-1.5 shrink-0 shadow-[0_0_8px_#00f0ff]"></i>
                                    <span>${n.text}</span>
                                </a>
                            `).join('');
                        } else {
                            contentHtml = '<div class="text-xs text-center py-6 text-gray-500">No new notifications</div>';
                        }
                        
                        const listContainer = dropdown.querySelector('.custom-scrollbar');
                        if (listContainer) {
                             listContainer.innerHTML = contentHtml;
                        }
                    }
                })
                .catch(err => {});
        }

        setInterval(checkNotifications, 15000); // Polling faster at 15s to make it feel responsive
        checkNotifications(); 
    }

    /**
     * 2. Checkout Countdown Timer
     */
    const timerDisplay = document.getElementById('timer-display');
    if (timerDisplay) {
        let duration = 600; // 10 minutes
        const timer = setInterval(() => {
            const minutes = Math.floor(duration / 60);
            const seconds = duration % 60;
            const fmtMin = minutes < 10 ? '0' + minutes : minutes;
            const fmtSec = seconds < 10 ? '0' + seconds : seconds;
            
            timerDisplay.textContent = `${fmtMin}:${fmtSec}`;
            
            if (--duration < 0) {
                clearInterval(timer);
                alert("Session Expired. Please refresh the page.");
                window.location.reload();
            }
        }, 1000);
    }

    /**
     * 3. Chat Auto-Scroll & Resizing
     */
    const chatBox = document.getElementById('chatBox');
    if (chatBox) {
        const scrollToBottom = () => {
            chatBox.scrollTop = chatBox.scrollHeight;
        }
        scrollToBottom();
        window.addEventListener('resize', scrollToBottom);
        
        const observer = new MutationObserver(scrollToBottom);
        observer.observe(chatBox, { childList: true });
    }

    /**
     * 4. File Upload Preview
     */
    const fileInput = document.querySelector('input[type="file"]');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            const wrapper = fileInput.closest('div');
            let label = wrapper.querySelector('p') || wrapper.querySelector('span');
            if(!label && fileInput.nextElementSibling) label = fileInput.nextElementSibling;
            
            if (file) {
                if (label) {
                    label.innerHTML = `<span class="text-green-400 font-bold flex items-center justify-center gap-2"><i class="fas fa-check-circle"></i> ${file.name}</span>`;
                }
                if (wrapper) {
                    wrapper.classList.add('border-green-500/50', 'bg-green-500/10');
                    wrapper.classList.remove('border-gray-600');
                }
            }
        });
    }

    /**
     * 5. Flash Message Auto-Dismiss
     */
    const flashMessages = document.querySelectorAll('.bg-green-500\\/20, .bg-red-500\\/20, .bg-green-500\\/10, .bg-red-500\\/10');
    if (flashMessages.length > 0) {
        setTimeout(() => {
            flashMessages.forEach(msg => {
                msg.style.transition = "opacity 0.5s ease, transform 0.5s ease";
                msg.style.opacity = "0";
                msg.style.transform = "translateY(-10px)";
                setTimeout(() => msg.remove(), 500);
            });
        }, 4000);
    }
    
    /**
     * 6. Scroll To Top Logic
     */
    const scrollBtn = document.getElementById('scrollToTop');
    if (scrollBtn) {
        window.addEventListener('scroll', () => {
            if (window.scrollY > 300) {
                scrollBtn.classList.remove('opacity-0', 'invisible', 'translate-y-10');
            } else {
                scrollBtn.classList.add('opacity-0', 'invisible', 'translate-y-10');
            }
        });

        scrollBtn.addEventListener('click', () => {
            window.scrollTo({ top: 0, behavior: 'smooth' });
        });
    }

    /**
     * 7. Push Permission Hider
     * Note: Actual button click logic is handled inline in header.php 
     * to prevent z-index/loading conflicts.
     */
    if ('Notification' in window && (Notification.permission === 'granted' || Notification.permission === 'denied')) {
        document.querySelectorAll('.enable-push-wrapper').forEach(el => el.remove());
    }
});