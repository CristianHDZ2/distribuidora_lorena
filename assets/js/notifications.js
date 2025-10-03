// Sistema de notificaciones flotante
class NotificationSystem {
    constructor() {
        this.notifications = [];
        this.init();
    }
    
    init() {
        // Crear el HTML del sistema de notificaciones
        const html = `
            <button class="notifications-toggle" id="notificationsToggle">
                <i class="fas fa-lightbulb"></i>
                <span class="badge" id="notificationCount" style="display: none;">0</span>
            </button>
            
            <div class="notifications-panel" id="notificationsPanel">
                <div class="notifications-header">
                    <span><i class="fas fa-bell"></i> Notificaciones</span>
                    <button class="btn btn-sm btn-light" onclick="notificationSystem.clearAll()">
                        <i class="fas fa-trash"></i>
                    </button>
                </div>
                <div class="notifications-content" id="notificationsContent">
                    <div class="no-notifications">
                        <i class="fas fa-inbox"></i>
                        <p>No hay notificaciones</p>
                    </div>
                </div>
            </div>
        `;
        
        document.body.insertAdjacentHTML('beforeend', html);
        
        // Event listeners
        document.getElementById('notificationsToggle').addEventListener('click', () => {
            this.toggle();
        });
        
        // Cerrar al hacer clic fuera
        document.addEventListener('click', (e) => {
            const panel = document.getElementById('notificationsPanel');
            const toggle = document.getElementById('notificationsToggle');
            
            if (!panel.contains(e.target) && !toggle.contains(e.target)) {
                this.hide();
            }
        });
        
        // Cargar notificaciones existentes
        this.loadExistingAlerts();
    }
    
    loadExistingAlerts() {
        // Buscar alertas existentes en la página
        const alerts = document.querySelectorAll('.alert-custom');
        
        alerts.forEach(alert => {
            let type = 'info';
            if (alert.classList.contains('alert-success')) type = 'success';
            if (alert.classList.contains('alert-danger')) type = 'danger';
            if (alert.classList.contains('alert-warning')) type = 'warning';
            
            const message = alert.textContent.trim();
            
            // Ocultar la alerta original
            alert.style.display = 'none';
            
            // Agregar a notificaciones
            this.add(message, type);
        });
    }
    
    add(message, type = 'info') {
        const notification = {
            id: Date.now(),
            message: message,
            type: type,
            timestamp: new Date()
        };
        
        this.notifications.push(notification);
        this.render();
        this.updateBadge();
    }
    
    remove(id) {
        this.notifications = this.notifications.filter(n => n.id !== id);
        this.render();
        this.updateBadge();
    }
    
    clearAll() {
        this.notifications = [];
        this.render();
        this.updateBadge();
    }
    
    render() {
        const content = document.getElementById('notificationsContent');
        
        if (this.notifications.length === 0) {
            content.innerHTML = `
                <div class="no-notifications">
                    <i class="fas fa-inbox"></i>
                    <p>No hay notificaciones</p>
                </div>
            `;
            return;
        }
        
        const html = this.notifications.map(notification => {
            const icon = this.getIcon(notification.type);
            return `
                <div class="notification-item ${notification.type}">
                    <i class="fas fa-${icon}"></i>
                    ${notification.message}
                </div>
            `;
        }).join('');
        
        content.innerHTML = html;
    }
    
    getIcon(type) {
        const icons = {
            'success': 'check-circle',
            'danger': 'exclamation-circle',
            'warning': 'exclamation-triangle',
            'info': 'info-circle'
        };
        return icons[type] || 'info-circle';
    }
    
    updateBadge() {
        const badge = document.getElementById('notificationCount');
        const count = this.notifications.length;
        
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
    
    toggle() {
        const panel = document.getElementById('notificationsPanel');
        panel.classList.toggle('show');
    }
    
    hide() {
        const panel = document.getElementById('notificationsPanel');
        panel.classList.remove('show');
    }
    
    show() {
        const panel = document.getElementById('notificationsPanel');
        panel.classList.add('show');
    }
}

// Inicializar el sistema cuando cargue la página
let notificationSystem;
document.addEventListener('DOMContentLoaded', function() {
    notificationSystem = new NotificationSystem();
});