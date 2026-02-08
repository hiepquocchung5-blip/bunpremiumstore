// assets/js/app.js

document.addEventListener('DOMContentLoaded', () => {
    
    /**
     * 1. Notification Polling System
     * Fetches unread count and latest updates from api/notifications.php
     */
    const bellIcon = document.querySelector('.fa-bell');
    
    if (bellIcon) {
        // Locate the badge (span) and dropdown container relative to the icon
        // Structure assumption: parent -> icon + span + div(dropdown)
        const parent = bellIcon.closest('.relative');
        const badge = parent.querySelector('span'); 
        const dropdown = parent.querySelector('div'); 
        
        function checkNotifications() {
            fetch('api/notifications.php')
                .then(response => response.json())
                .then(data => {
                    // Update Red Badge Visibility
                    if (data.count > 0) {
                        if (badge) badge.classList.remove('hidden');
                    } else {
                        if (badge) badge.classList.add('hidden');
                    }

                    // Update Dropdown Content
                    if (dropdown && data.notifications.length > 0) {
                        // Create HTML for new notifications
                        const listHtml = data.notifications.map(n => `
                            <a href="${n.link}" class="block px-4 py-2 text-xs text-gray-300 hover:bg-gray-700 hover:text-white transition border-b border-gray-700 last:border-0">
                                ${n.text}
                            </a>
                        `).join('');
                        
                        // Preserve the header ("Notifications") and update the list
                        const headerElement = dropdown.querySelector('h4');
                        const headerHtml = headerElement ? headerElement.outerHTML : '<h4 class="text-sm font-bold border-b border-gray-700 pb-2 mb-2">Notifications</h4>';
                        
                        dropdown.innerHTML = headerHtml + listHtml;
                    } else if (dropdown) {
                        // Empty state
                        const headerElement = dropdown.querySelector('h4');
                        const headerHtml = headerElement ? headerElement.outerHTML : '<h4 class="text-sm font-bold border-b border-gray-700 pb-2 mb-2">Notifications</h4>';
                        dropdown.innerHTML = headerHtml + '<div class="text-xs text-gray-400 text-center py-2">No new notifications</div>';
                    }
                })
                .catch(err => console.error('Notify Error:', err));
        }

        // Poll every 30 seconds
        setInterval(checkNotifications, 30000);
        
        // Initial check on load
        checkNotifications(); 
    }

    /**
     * 2. Checkout Countdown Timer
     * Used in modules/shop/checkout.php to show urgency
     */
    const timerDisplay = document.getElementById('timer-display');
    if (timerDisplay) {
        // Set duration in seconds (10 minutes)
        let duration = 600; 
        
        const timer = setInterval(() => {
            const minutes = Math.floor(duration / 60);
            const seconds = duration % 60;
            
            // Format time as MM:SS (e.g., 09:05)
            const fmtMin = minutes < 10 ? '0' + minutes : minutes;
            const fmtSec = seconds < 10 ? '0' + seconds : seconds;
            
            timerDisplay.textContent = `${fmtMin}:${fmtSec}`;
            
            if (--duration < 0) {
                clearInterval(timer);
                alert("Session Expired. Please refresh the page to continue.");
                window.location.reload();
            }
        }, 1000);
    }

    /**
     * 3. Chat Auto-Scroll
     * Used in modules/user/orders.php and admin chat
     * Keeps the scrollbar at the bottom when page loads or new message arrives
     */
    const chatBox = document.getElementById('chatBox');
    if (chatBox) {
        // Scroll to bottom immediately
        chatBox.scrollTop = chatBox.scrollHeight;
        
        // Optional: Re-scroll on window resize (mobile keyboard appearance)
        window.addEventListener('resize', () => {
            chatBox.scrollTop = chatBox.scrollHeight;
        });
    }

    /**
     * 4. File Upload Preview
     * Used in Checkout to show filename after selection
     */
    const fileInput = document.querySelector('input[type="file"]');
    if (fileInput) {
        fileInput.addEventListener('change', function(e) {
            const file = e.target.files[0];
            // Find the sibling element or parent text container to update
            // Assumption: Input is inside a wrapper, and text is a sibling or parent's child
            const wrapper = fileInput.closest('div');
            const textLabel = wrapper.querySelector('p') || wrapper.querySelector('span') || fileInput.nextElementSibling;
            
            if (file && textLabel) {
                // Update text with filename and success icon
                textLabel.innerHTML = `<span class="text-green-400 font-bold"><i class="fas fa-check-circle"></i> ${file.name}</span>`;
                // Add a border highlight to the container
                wrapper.classList.add('border-green-500', 'bg-green-500/10');
                wrapper.classList.remove('border-gray-600');
            }
        });
    }

    /**
     * 5. Flash Message Auto-Dismiss
     * Automatically hides success/error alerts after 4 seconds
     */
    const flashMessages = document.querySelectorAll('.bg-green-500\\/20, .bg-red-500\\/20, .bg-green-500\\/10, .bg-red-500\\/10');
    if (flashMessages.length > 0) {
        setTimeout(() => {
            flashMessages.forEach(msg => {
                // Add transition for smooth fade out
                msg.style.transition = "opacity 0.5s ease, transform 0.5s ease";
                msg.style.opacity = "0";
                msg.style.transform = "translateY(-10px)";
                
                // Remove from DOM after transition
                setTimeout(() => msg.remove(), 500);
            });
        }, 4000);
    }

});