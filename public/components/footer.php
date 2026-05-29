</main>
<script>
    // Micro-interactions for glass panels
    document.querySelectorAll('.glass-panel').forEach(panel => {
        panel.addEventListener('mousemove', (e) => {
            const rect = panel.getBoundingClientRect();
            const x = e.clientX - rect.left;
            const y = e.clientY - rect.top;
            panel.style.setProperty('--mouse-x', `${x}px`);
            panel.style.setProperty('--mouse-y', `${y}px`);
        });
    });

    // Simple Entrance Animation
    document.addEventListener('DOMContentLoaded', () => {
        const mainContent = document.querySelector('main');
        if(mainContent) {
            mainContent.style.opacity = '0';
            mainContent.style.transform = 'translateY(20px)';
            setTimeout(() => {
                mainContent.style.transition = 'all 0.8s cubic-bezier(0.16, 1, 0.3, 1)';
                mainContent.style.opacity = '1';
                mainContent.style.transform = 'translateY(0)';
            }, 100);
        }
    });
</script>
</body>
</html>