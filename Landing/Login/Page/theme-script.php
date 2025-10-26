<script>
    // Apply theme before rendering
    (function() {
        const theme = localStorage.getItem('theme') || 
                     (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light');
        document.documentElement.className = theme;
    })();
</script>