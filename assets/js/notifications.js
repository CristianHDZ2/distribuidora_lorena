// Sistema de notificaciones e instrucciones flotantes
class NotificationSystem {
    constructor() {
        this.notifications = [];
        this.instructions = [];
        this.stockAlerts = [];
        this.stockAlertsInterval = null;
        this.stockAlertsTimeout = null;
        
        console.log('Constructor NotificationSystem ejecutado');
        console.log('notifications:', this.notifications);
        console.log('instructions:', this.instructions);
        
        this.init();
    }
    
    init() {
        console.log('Método init() ejecutado');
        
        // Limpiar botones existentes si los hay
        const existingBell = document.getElementById('notificationsToggle');
        const existingBulb = document.getElementById('instructionsToggle');
        const existingNotifPanel = document.getElementById('notificationsPanel');
        const existingInstrPanel = document.getElementById('instructionsPanel');
        
        if (existingBell) existingBell.remove();
        if (existingBulb) existingBulb.remove();
        if (existingNotifPanel) existingNotifPanel.remove();
        if (existingInstrPanel) existingInstrPanel.remove();
        
        // Crear el HTML del sistema de notificaciones (campanita)
        const notificationsHTML = `
            <button class="notifications-toggle notifications-bell" id="notificationsToggle" title="Notificaciones">
                <i class="fas fa-bell"></i>
                <span class="badge" id="notificationCount" style="display: none;">0</span>
            </button>
            
            <div class="notifications-panel" id="notificationsPanel">
                <div class="notifications-header">
                    <span><i class="fas fa-bell"></i> Notificaciones</span>
                    <button class="btn btn-sm btn-light" onclick="notificationSystem.clearAllNotifications()">
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
        
        // Crear el HTML del sistema de instrucciones (foquito)
        const instructionsHTML = `
            <button class="notifications-toggle instructions-bulb" id="instructionsToggle" title="Instrucciones">
                <i class="fas fa-lightbulb"></i>
            </button>
            
            <div class="notifications-panel instructions-panel" id="instructionsPanel">
                <div class="notifications-header" style="background: linear-gradient(135deg, #f39c12, #e67e22);">
                    <span><i class="fas fa-lightbulb"></i> Instrucciones</span>
                    <button class="btn btn-sm btn-light" onclick="notificationSystem.hideInstructions()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
                <div class="notifications-content" id="instructionsContent">
                    <div class="no-notifications">
                        <i class="fas fa-info-circle"></i>
                        <p>No hay instrucciones disponibles</p>
                    </div>
                </div>
            </div>
        `;
        
        // Insertar los botones en el body
        document.body.insertAdjacentHTML('beforeend', notificationsHTML);
        console.log('Botón de notificaciones (campanita) creado');
        
        document.body.insertAdjacentHTML('beforeend', instructionsHTML);
        console.log('Botón de instrucciones (foquito) creado');
        
        // Verificar que se crearon
        const bellBtn = document.getElementById('notificationsToggle');
        const bulbBtn = document.getElementById('instructionsToggle');
        console.log('Campanita existe:', bellBtn !== null);
        console.log('Foquito existe:', bulbBtn !== null);
        
        // Event listeners para notificaciones
        if (bellBtn) {
            bellBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleNotifications();
            });
            console.log('Event listener agregado a campanita');
        }
        
        // Event listeners para instrucciones
        if (bulbBtn) {
            bulbBtn.addEventListener('click', (e) => {
                e.stopPropagation();
                this.toggleInstructions();
            });
            console.log('Event listener agregado a foquito');
        }
        
        // Cerrar al hacer clic fuera
        document.addEventListener('click', (e) => {
            const notifPanel = document.getElementById('notificationsPanel');
            const notifToggle = document.getElementById('notificationsToggle');
            const instrPanel = document.getElementById('instructionsPanel');
            const instrToggle = document.getElementById('instructionsToggle');
            
            if (notifPanel && notifToggle && !notifPanel.contains(e.target) && !notifToggle.contains(e.target)) {
                this.hideNotifications();
            }
            
            if (instrPanel && instrToggle && !instrPanel.contains(e.target) && !instrToggle.contains(e.target)) {
                this.hideInstructions();
            }
        });
        
        // Cargar notificaciones e instrucciones existentes
        setTimeout(() => {
            console.log('Cargando alertas e instrucciones...');
            this.loadExistingAlerts();
            this.loadPageInstructions();
            console.log('Total instrucciones cargadas:', this.instructions.length);
            console.log('Total notificaciones cargadas:', this.notifications.length);
        }, 100);
    }
    
    loadExistingAlerts() {
        // Buscar alertas de notificaciones en la página (EXCLUIR alert-info)
        const alerts = document.querySelectorAll('.alert:not(.alert-info)');
        console.log('Alertas encontradas (no info):', alerts.length);
        
        alerts.forEach(alert => {
            let type = 'info';
            if (alert.classList.contains('alert-success')) type = 'success';
            if (alert.classList.contains('alert-danger')) type = 'danger';
            if (alert.classList.contains('alert-warning')) type = 'warning';
            
            // Extraer solo el texto, sin los elementos hijos
            const alertClone = alert.cloneNode(true);
            const closeBtn = alertClone.querySelector('.btn-close');
            const icons = alertClone.querySelectorAll('i');
            const buttons = alertClone.querySelectorAll('button, a');
            
            if (closeBtn) closeBtn.remove();
            icons.forEach(icon => icon.remove());
            buttons.forEach(btn => btn.remove());
            
            let message = alertClone.textContent.trim();
            
            // Limpiar mensajes comunes
            message = message.replace(/Instrucciones:/gi, '').trim();
            message = message.replace(/¡Alerta de Stock Bajo!/gi, '').trim();
            message = message.replace(/¡Atención! Productos con Stock Bajo/gi, '').trim();
            
            if (message && message.length > 10) {
                // Verificar si es una alerta de stock bajo
                const originalText = alert.textContent.toLowerCase();
                if (originalText.includes('stock bajo') || originalText.includes('stock crítico') || originalText.includes('stock igual o menor')) {
                    this.stockAlerts.push({ message, type });
                    this.startStockAlertsCycle();
                } else {
                    this.addNotification(message, type);
                }
                
                // Ocultar la alerta original
                alert.style.display = 'none';
            }
        });
        
        // Agregar notificación por defecto si no hay productos dañados
        const hasDamagedProducts = document.querySelector('[data-has-damaged]');
        if (hasDamagedProducts && hasDamagedProducts.dataset.hasDamaged === 'false') {
            this.addNotification('No hay productos dañados registrados todavía.', 'info');
        }
    }
    
    loadPageInstructions() {
        // Buscar instrucciones en la página (alerts con clase alert-info)
        const instructionAlerts = document.querySelectorAll('.alert.alert-info');
        console.log('Alertas de instrucciones encontradas:', instructionAlerts.length);
        
        instructionAlerts.forEach((alert, index) => {
            const alertClone = alert.cloneNode(true);
            const closeBtn = alertClone.querySelector('.btn-close');
            const icons = alertClone.querySelectorAll('i.fas');
            const buttons = alertClone.querySelectorAll('button, a');
            
            if (closeBtn) closeBtn.remove();
            icons.forEach(icon => icon.remove());
            buttons.forEach(btn => btn.remove());
            
            let message = alertClone.textContent.trim();
            
            // Limpiar el mensaje
            message = message.replace(/Instrucciones:/gi, '').trim();
            message = message.replace(/INSTRUCCIONES:/gi, '').trim();
            
            console.log(`Instrucción ${index + 1}:`, message.substring(0, 50) + '...');
            
            if (message && message.length > 10) {
                this.addInstruction(message);
                // Ocultar la alerta original
                alert.style.display = 'none';
                console.log(`Instrucción ${index + 1} agregada y ocultada`);
            }
        });
    }
    
    startStockAlertsCycle() {
        // Evitar múltiples intervalos
        if (this.stockAlertsInterval) {
            return;
        }
        
        // Mostrar las alertas de stock inmediatamente
        this.showStockAlertsTemporarily();
        
        // Configurar el intervalo para mostrar cada 10 minutos
        this.stockAlertsInterval = setInterval(() => {
            this.showStockAlertsTemporarily();
        }, 10 * 60 * 1000); // 10 minutos
    }
    
    showStockAlertsTemporarily() {
        if (this.stockAlerts.length === 0) return;
        
        // Agregar las alertas de stock a las notificaciones
        this.stockAlerts.forEach(alert => {
            this.addNotification(alert.message, alert.type, true);
        });
        
        // Mostrar el panel automáticamente
        this.showNotifications();
        
        // Ocultar después de 20 segundos
        if (this.stockAlertsTimeout) {
            clearTimeout(this.stockAlertsTimeout);
        }
        
        this.stockAlertsTimeout = setTimeout(() => {
            // Remover las alertas de stock temporales
            this.notifications = this.notifications.filter(n => !n.isStockAlert);
            this.renderNotifications();
            this.updateBadge();
            this.hideNotifications();
        }, 20000); // 20 segundos
    }
    
    addNotification(message, type = 'info', isStockAlert = false) {
        const notification = {
            id: Date.now() + Math.random(),
            message: message,
            type: type,
            timestamp: new Date(),
            isStockAlert: isStockAlert
        };
        
        this.notifications.push(notification);
        this.renderNotifications();
        this.updateBadge();
    }
    
    addInstruction(message) {
        const instruction = {
            id: Date.now() + Math.random(),
            message: message,
            timestamp: new Date()
        };
        
        this.instructions.push(instruction);
        this.renderInstructions();
        console.log('Instrucción agregada al array. Total:', this.instructions.length);
    }
    
    removeNotification(id) {
        this.notifications = this.notifications.filter(n => n.id !== id);
        this.renderNotifications();
        this.updateBadge();
    }
    
    clearAllNotifications() {
        // Solo limpiar notificaciones que no sean alertas de stock
        this.notifications = this.notifications.filter(n => n.isStockAlert);
        this.renderNotifications();
        this.updateBadge();
    }
    
    renderNotifications() {
        const content = document.getElementById('notificationsContent');
        if (!content) return;
        
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
                    <div class="notification-message">${notification.message}</div>
                </div>
            `;
        }).join('');
        
        content.innerHTML = html;
    }
    
    renderInstructions() {
        const content = document.getElementById('instructionsContent');
        if (!content) {
            console.error('No se encontró el contenedor de instrucciones');
            return;
        }
        
        console.log('Renderizando instrucciones. Total:', this.instructions.length);
        
        if (this.instructions.length === 0) {
            content.innerHTML = `
                <div class="no-notifications">
                    <i class="fas fa-info-circle"></i>
                    <p>No hay instrucciones disponibles</p>
                </div>
            `;
            return;
        }
        
        const html = this.instructions.map(instruction => {
            return `
                <div class="notification-item info">
                    <i class="fas fa-lightbulb"></i>
                    <div class="notification-message">${instruction.message}</div>
                </div>
            `;
        }).join('');
        
        content.innerHTML = html;
        console.log('Instrucciones renderizadas en el panel');
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
        if (!badge) return;
        
        const count = this.notifications.filter(n => !n.isStockAlert).length;
        
        if (count > 0) {
            badge.textContent = count;
            badge.style.display = 'flex';
        } else {
            badge.style.display = 'none';
        }
    }
    
    toggleNotifications() {
        const panel = document.getElementById('notificationsPanel');
        const instrPanel = document.getElementById('instructionsPanel');
        
        if (!panel) return;
        
        // Cerrar instrucciones si está abierto
        if (instrPanel) instrPanel.classList.remove('show');
        
        panel.classList.toggle('show');
    }
    
    toggleInstructions() {
        const panel = document.getElementById('instructionsPanel');
        const notifPanel = document.getElementById('notificationsPanel');
        
        if (!panel) return;
        
        // Cerrar notificaciones si está abierto
        if (notifPanel) notifPanel.classList.remove('show');
        
        panel.classList.toggle('show');
    }
    
    hideNotifications() {
        const panel = document.getElementById('notificationsPanel');
        if (panel) panel.classList.remove('show');
    }
    
    hideInstructions() {
        const panel = document.getElementById('instructionsPanel');
        if (panel) panel.classList.remove('show');
    }
    
    showNotifications() {
        const panel = document.getElementById('notificationsPanel');
        const instrPanel = document.getElementById('instructionsPanel');
        
        if (!panel) return;
        
        if (instrPanel) instrPanel.classList.remove('show');
        panel.classList.add('show');
    }
    
    showInstructions() {
        const panel = document.getElementById('instructionsPanel');
        const notifPanel = document.getElementById('notificationsPanel');
        
        if (!panel) return;
        
        if (notifPanel) notifPanel.classList.remove('show');
        panel.classList.add('show');
    }
}

// Inicializar el sistema cuando cargue la página
let notificationSystem;

// Función de inicialización
function initNotificationSystem() {
    if (typeof notificationSystem === 'undefined' || notificationSystem === null) {
        console.log('=== INICIANDO NOTIFICATION SYSTEM ===');
        notificationSystem = new NotificationSystem();
        console.log('=== NOTIFICATION SYSTEM INICIALIZADO ===');
        console.log('Instrucciones:', notificationSystem.instructions);
        console.log('Notificaciones:', notificationSystem.notifications);
    }
}

// Intentar inicializar inmediatamente si el DOM ya está listo
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initNotificationSystem);
} else {
    // DOM ya está listo, inicializar inmediatamente
    initNotificationSystem();
}