// assets/js/app.js

document.addEventListener('DOMContentLoaded', () => {
    // 1. Checkout Timer Logic
    const timerDisplay = document.getElementById('timer-display');
    if (timerDisplay) {
        let timeLeft = 600; // 10 minutes
        const timer = setInterval(() => {
            const minutes = Math.floor(timeLeft / 60);
            const seconds = timeLeft % 60;
            timerDisplay.innerText = `${minutes}:${seconds < 10 ? '0' : ''}${seconds}`;
            timeLeft--;

            if (timeLeft < 0) {
                clearInterval(timer);
                alert("Session Expired");
                window.location.reload();
            }
        }, 1000);
    }

    // 2. Chat Auto-Scroll
    const chatBox = document.getElementById('chatBox');
    if (chatBox) {
        chatBox.scrollTop = chatBox.scrollHeight;
    }
});