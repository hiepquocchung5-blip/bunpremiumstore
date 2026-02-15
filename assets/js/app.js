// assets/js/app.js

/**
 * --------------------------------------------------------------------------
 * CONFIGURATION
 * --------------------------------------------------------------------------
 */
// Public VAPID Key for Web Push (Injected from server .env via window.AppConfig)
const PUBLIC_VAPID_KEY = window.AppConfig?.vapidPublicKey || '';

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

window.registerServiceWorker = async function() {
    if (!('serviceWorker' in navigator)) {
        console.error('Service Worker not supported');
        return;
    }

    if (!PUBLIC_VAPID_KEY) {
        console.error('VAPID Public Key is missing. Check .env configuration.');
        return;
    }

    try {
        const register = await navigator.serviceWorker.register('assets/sw.js');
        await navigator.serviceWorker.ready;

        const subscription = await register.pushManager.subscribe({
            userVisibleOnly: true,
            applicationServerKey: urlBase64ToUint8Array(PUBLIC_VAPID_KEY)
        });

        await fetch('api/push_subscribe.php', {
            method: 'POST',
            body: JSON.stringify(subscription),
            headers: { 'Content-Type': 'application/json' }
        });

        console.log('Push Notification Subscribed');
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
     * 1. Notification Polling System
     * Checks for internal notifications every 30s via AJAX
     */
    const bellIcon = document.querySelector('.fa-bell');
    
    if (bellIcon) {
        const parent = bellIcon.closest('.relative');
        const badge = parent ? parent.querySelector('span') : null;
        const dropdown = parent ? parent.querySelector('div.absolute') : null;

        function checkNotifications() {
            fetch('api/notifications.php')
                .then(response => {
                    // Check if response is valid JSON
                    if (!response.ok) throw new Error('Network response was not ok');
                    return response.json();
                })
                .then(data => {
                    // Safety check: ensure data is an object
                    if (!data || typeof data !== 'object') return;

                    // Update Badge Visibility
                    if (data.count > 0) {
                        if (badge) badge.classList.remove('hidden');
                    } else {
                        if (badge) badge.classList.add('hidden');
                    }

                    // Update Dropdown List
                    if (dropdown) {
                        const headerEl = dropdown.querySelector('div.border-b');
                        const headerHtml = headerEl ? headerEl.outerHTML : '';
                        
                        let contentHtml = '';
                        
                        // FIX: Strict check for array existence before length access
                        if (data.notifications && Array.isArray(data.notifications) && data.notifications.length > 0) {
                             contentHtml = data.notifications.map(n => `
                                <a href="${n.link}" class="block px-4 py-3 text-xs text-gray-300 hover:bg-gray-700 hover:text-white transition border-b border-gray-700 last:border-0 flex items-start gap-2">
                                    <i class="fas fa-circle text-[6px] text-blue-500 mt-1.5 shrink-0"></i>
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
                .catch(err => {
                    // console.error('Notify Poll Error:', err); // Suppress errors to keep console clean
                });
        }

        setInterval(checkNotifications, 30000);
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
            // Try to find label in siblings or children
            let label = wrapper.querySelector('p') || wrapper.querySelector('span');
            // Fallback for different HTML structures
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
     * 7. Push Permission Button Hook
     */
    const pushBtn = document.getElementById('enable-push');
    if(pushBtn) {
        pushBtn.addEventListener('click', (e) => {
            e.preventDefault();
            const icon = pushBtn.querySelector('i');
            
            if (icon) icon.className = "fas fa-spinner fa-spin w-5 text-center text-yellow-400";
            
            window.registerServiceWorker()
                .then(() => {
                    alert('✅ Notifications Enabled Successfully!');
                    if (icon) icon.className = "fas fa-check w-5 text-center text-green-400";
                    pushBtn.innerHTML = '<i class="fas fa-check-circle w-5 text-center text-green-400"></i> Alerts Active';
                    pushBtn.disabled = true;
                    pushBtn.classList.add('opacity-50', 'cursor-default');
                })
                .catch(err => {
                    console.error(err);
                    alert('❌ Could not enable notifications. Please check your browser settings.');
                    if (icon) icon.className = "fas fa-bell-slash w-5 text-center text-red-400";
                });
        });
    }
});