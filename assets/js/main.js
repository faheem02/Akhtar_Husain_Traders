document.addEventListener('DOMContentLoaded', function() {

    // Sidebar toggle
    window.toggleSidebar = function() {
        var sidebar = document.getElementById('sidebar');
        var mainContent = document.getElementById('mainContent');

        if (window.innerWidth <= 992) {
            sidebar.classList.toggle('show');
            var overlay = document.querySelector('.sidebar-overlay');
            if (!overlay) {
                overlay = document.createElement('div');
                overlay.className = 'sidebar-overlay';
                document.body.appendChild(overlay);
                overlay.addEventListener('click', function() {
                    sidebar.classList.remove('show');
                    overlay.classList.remove('show');
                });
            }
            overlay.classList.toggle('show');
        }
    };

    // Close sidebar on window resize to desktop
    window.addEventListener('resize', function() {
        if (window.innerWidth > 992) {
            var sidebar = document.getElementById('sidebar');
            sidebar.classList.remove('show');
            var overlay = document.querySelector('.sidebar-overlay');
            if (overlay) overlay.classList.remove('show');
        }
    });

    // Auto-hide flash messages
    var flashMessages = document.querySelectorAll('.flash-alert');
    flashMessages.forEach(function(msg) {
        setTimeout(function() {
            var bsAlert = bootstrap.Alert.getOrCreateInstance(msg);
            bsAlert.close();
        }, 4000);
    });

    // Format amount inputs
    document.querySelectorAll('input[type="number"]').forEach(function(input) {
        input.addEventListener('blur', function() {
            if (this.value && !isNaN(this.value)) {
                this.value = parseFloat(this.value).toFixed(2);
            }
        });
    });
});
