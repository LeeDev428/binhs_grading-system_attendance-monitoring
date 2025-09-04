// Main Layout JavaScript
document.addEventListener('DOMContentLoaded', function() {
    
    // Sidebar toggle functionality
    const sidebarToggle = document.querySelector('.sidebar-toggle');
    const sidebar = document.querySelector('.sidebar');
    const mainContent = document.querySelector('.main-content');
    
    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            sidebar.classList.toggle('collapsed');
            mainContent.classList.toggle('expanded');
        });
    }
    
    // Submenu toggle functionality
    const menuToggles = document.querySelectorAll('.menu-toggle');
    
    menuToggles.forEach(toggle => {
        toggle.addEventListener('click', function(e) {
            e.preventDefault();
            const menuItem = this.closest('.menu-item');
            const isActive = menuItem.classList.contains('active');
            
            // Close all other submenus
            document.querySelectorAll('.menu-item.active').forEach(item => {
                if (item !== menuItem) {
                    item.classList.remove('active');
                }
            });
            
            // Toggle current submenu
            menuItem.classList.toggle('active', !isActive);
        });
    });
    
    // User dropdown toggle
    const userMenu = document.querySelector('.user-menu');
    const userBtn = document.querySelector('.user-btn');
    const userDropdown = document.querySelector('.user-dropdown');
    
    if (userBtn) {
        userBtn.addEventListener('click', function(e) {
            e.stopPropagation();
            userDropdown.classList.toggle('show');
        });
    }
    
    // Close dropdown when clicking outside
    document.addEventListener('click', function(e) {
        if (userDropdown && !userMenu.contains(e.target)) {
            userDropdown.classList.remove('show');
        }
    });
    
    // Mobile sidebar toggle
    if (window.innerWidth <= 768) {
        if (sidebarToggle) {
            sidebarToggle.addEventListener('click', function() {
                sidebar.classList.toggle('active');
            });
        }
        
        // Close sidebar when clicking outside
        document.addEventListener('click', function(e) {
            if (!sidebar.contains(e.target) && !sidebarToggle.contains(e.target)) {
                sidebar.classList.remove('active');
            }
        });
    }
    
    // Auto-close mobile sidebar on navigation
    const sidebarLinks = document.querySelectorAll('.sidebar a:not(.menu-toggle)');
    sidebarLinks.forEach(link => {
        link.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.remove('active');
            }
        });
    });
    
    // Notification handling
    const notificationBtn = document.querySelector('.notification-btn');
    if (notificationBtn) {
        notificationBtn.addEventListener('click', function() {
            // Add notification handling logic here
            console.log('Notifications clicked');
        });
    }
    
    // Form validation helpers
    const forms = document.querySelectorAll('form');
    forms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const requiredFields = form.querySelectorAll('[required]');
            let isValid = true;
            
            requiredFields.forEach(field => {
                if (!field.value.trim()) {
                    field.classList.add('is-invalid');
                    isValid = false;
                } else {
                    field.classList.remove('is-invalid');
                }
            });
            
            if (!isValid) {
                e.preventDefault();
                showAlert('Please fill in all required fields.', 'error');
            }
        });
    });
    
    // Auto-hide alerts after 5 seconds
    const alerts = document.querySelectorAll('.alert');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.opacity = '0';
            setTimeout(() => {
                alert.remove();
            }, 300);
        }, 5000);
    });
    
    // Add loading state to buttons on form submit
    const submitButtons = document.querySelectorAll('button[type="submit"]');
    submitButtons.forEach(button => {
        button.closest('form').addEventListener('submit', function() {
            button.disabled = true;
            button.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
        });
    });
    
    // Initialize tooltips (if using a tooltip library)
    const tooltips = document.querySelectorAll('[data-tooltip]');
    tooltips.forEach(element => {
        element.addEventListener('mouseenter', function() {
            // Add tooltip logic here
        });
    });
    
    // Responsive table handling
    const tables = document.querySelectorAll('.data-table');
    tables.forEach(table => {
        const wrapper = document.createElement('div');
        wrapper.className = 'table-responsive';
        table.parentNode.insertBefore(wrapper, table);
        wrapper.appendChild(table);
    });
    
    // Smooth scrolling for anchor links
    const anchorLinks = document.querySelectorAll('a[href^="#"]');
    anchorLinks.forEach(link => {
        link.addEventListener('click', function(e) {
            e.preventDefault();
            const targetId = this.getAttribute('href');
            const targetElement = document.querySelector(targetId);
            
            if (targetElement) {
                targetElement.scrollIntoView({
                    behavior: 'smooth'
                });
            }
        });
    });
});

// Utility functions
function showAlert(message, type = 'info') {
    const alertDiv = document.createElement('div');
    alertDiv.className = `alert alert-${type}`;
    alertDiv.innerHTML = `
        <i class="fas fa-${getAlertIcon(type)}"></i>
        ${message}
    `;
    
    const pageContent = document.querySelector('.page-content');
    if (pageContent) {
        pageContent.insertBefore(alertDiv, pageContent.firstChild);
    }
    
    // Auto-hide after 5 seconds
    setTimeout(() => {
        alertDiv.style.opacity = '0';
        setTimeout(() => {
            alertDiv.remove();
        }, 300);
    }, 5000);
}

function getAlertIcon(type) {
    const icons = {
        'success': 'check-circle',
        'error': 'exclamation-circle',
        'warning': 'exclamation-triangle',
        'info': 'info-circle'
    };
    return icons[type] || 'info-circle';
}

function confirmAction(message, callback) {
    if (confirm(message)) {
        callback();
    }
}

// Export functions for use in other scripts
window.layoutUtils = {
    showAlert,
    confirmAction
};
