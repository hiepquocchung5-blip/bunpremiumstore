</main> <!-- End Main Content -->
        
    </div> <!-- End Flex Wrapper -->

    <!-- Global Admin Scripts -->
    <script>
        // Auto-dismiss Flash Messages
        document.addEventListener('DOMContentLoaded', () => {
            const alerts = document.querySelectorAll('.bg-green-500\\/20, .bg-red-500\\/20');
            if (alerts.length > 0) {
                setTimeout(() => {
                    alerts.forEach(el => {
                        el.style.transition = "opacity 0.5s ease";
                        el.style.opacity = "0";
                        setTimeout(() => el.remove(), 500);
                    });
                }, 4000);
            }
        });
    </script>
</body>
</html>