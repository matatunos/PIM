// Hamburger Menu Toggle
document.addEventListener('DOMContentLoaded', function() {
    const hamburger = document.getElementById('hamburger-menu');
    const sidebar = document.querySelector('.sidebar');
    
    if (hamburger && sidebar) {
        hamburger.addEventListener('click', function(e) {
            e.preventDefault();
            e.stopPropagation();
            
            hamburger.classList.toggle('active');
            sidebar.classList.toggle('active');
        });
        
        // Cerrar sidebar al hacer click fuera
        document.addEventListener('click', function(e) {
            if (!sidebar.contains(e.target) && !hamburger.contains(e.target)) {
                if (sidebar.classList.contains('active')) {
                    hamburger.classList.remove('active');
                    sidebar.classList.remove('active');
                }
            }
        });
        
        // Cerrar sidebar al hacer click en un link
        sidebar.querySelectorAll('a').forEach(link => {
            link.addEventListener('click', function() {
                if (window.innerWidth <= 768) {
                    hamburger.classList.remove('active');
                    sidebar.classList.remove('active');
                }
            });
        });
    }
});
