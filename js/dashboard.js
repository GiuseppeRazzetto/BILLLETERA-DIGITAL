document.addEventListener('DOMContentLoaded', function() {
    // Verificar sesión primero
    const token = localStorage.getItem('session_token');
    if (!token) {
        window.location.href = 'login.html';
        return;
    }

    // Referencias a elementos DOM
    const userEmail = document.getElementById('user-email');
    const balanceElement = document.getElementById('balance');
    const transactionsList = document.getElementById('transactions');
    const logoutBtn = document.getElementById('logout-btn');
    
    // Referencias a modales
    const depositModalEl = document.getElementById('depositModal');
    const withdrawModalEl = document.getElementById('withdrawModal');
    const transferModalEl = document.getElementById('transferModal');
    
    // Formularios
    const depositForm = document.getElementById('deposit-form');
    const withdrawForm = document.getElementById('withdraw-form');
    const transferForm = document.getElementById('transfer-form');

    // Inicializar modales solo si existen
    let depositModal, withdrawModal, transferModal;
    
    if (depositModalEl) {
        depositModal = new bootstrap.Modal(depositModalEl);
    }
    if (withdrawModalEl) {
        withdrawModal = new bootstrap.Modal(withdrawModalEl);
    }
    if (transferModalEl) {
        transferModal = new bootstrap.Modal(transferModalEl);
    }

    // Función para mostrar notificaciones toast
    function showToast(title, message, type = 'success') {
        const toastContainer = document.querySelector('.toast-container');
        
        const toast = document.createElement('div');
        toast.className = `custom-toast ${type}`;
        
        let icon = '';
        switch(type) {
            case 'success':
                icon = '✓';
                break;
            case 'error':
                icon = '✕';
                break;
            case 'warning':
                icon = '⚠';
                break;
        }
        
        toast.innerHTML = `
            <div class="toast-icon ${type}">${icon}</div>
            <div class="toast-content">
                <div class="toast-title">${title}</div>
                <div class="toast-message">${message}</div>
            </div>
            <button class="toast-close">×</button>
        `;
        
        toastContainer.appendChild(toast);
        
        // Agregar evento para cerrar el toast
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => {
            toast.remove();
        });
        
        // Remover el toast después de 5 segundos
        setTimeout(() => {
            toast.style.opacity = '0';
            setTimeout(() => toast.remove(), 300);
        }, 5000);
    }

    function formatCurrency(amount) {
        return new Intl.NumberFormat('es-MX', {
            style: 'currency',
            currency: 'MXN'
        }).format(amount);
    }

    function formatDate(dateString) {
        const options = { 
            year: 'numeric', 
            month: 'long', 
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        };
        return new Date(dateString).toLocaleDateString('es-MX', options);
    }

    // Verificar sesión y cargar datos
    checkSession();
    loadWalletData();

    function checkSession() {
        fetch('/digital-wallet2/backend/api/check_session.php', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        })
        .then(response => response.json())
        .then(data => {
            if (!data.success) {
                throw new Error('Sesión inválida');
            }
            if (userEmail) {
                userEmail.textContent = data.user.email;
            }
        })
        .catch(error => {
            console.error('Error:', error);
            localStorage.removeItem('session_token');
            window.location.href = 'login.html';
        });
    }

    // Función para cargar los datos de la billetera
    function loadWalletData() {
        console.log('Cargando datos de la billetera...');
        
        // Obtener el email del usuario del localStorage
        const userEmail = localStorage.getItem('userEmail');
        if (userEmail) {
            document.getElementById('user-email').textContent = userEmail;
        }

        fetch('/digital-wallet2/backend/api/wallet.php', {
            headers: {
                'Authorization': `Bearer ${token}`
            }
        })
        .then(response => response.json())
        .then(data => {
            console.log('Datos recibidos:', data);
            if (data.success) {
                // Actualizar el balance
                updateBalance(data.balance);
                // Actualizar la lista de transacciones
                updateTransactions(data.transactions);
            } else {
                showToast('error', 'Error', data.message || 'Error al cargar los datos');
            }
        })
        .catch(error => {
            console.error('Error:', error);
            showToast('error', 'Error', 'Error al conectar con el servidor');
        });
    }

    function updateBalance(balance) {
        const balanceElement = document.getElementById('balance');
        if (balanceElement) {
            const amount = parseFloat(balance);
            balanceElement.textContent = `$${amount.toFixed(2)}`;
        }
    }

    function updateTransactions(transactions) {
        console.log('Actualizando transacciones:', transactions);
        const transactionsTable = document.getElementById('transactions');
        if (!transactionsTable) {
            console.error('No se encontró el elemento de la tabla de transacciones');
            return;
        }

        // Limpiar la tabla actual
        transactionsTable.innerHTML = '';

        // Agregar cada transacción a la tabla
        transactions.forEach(transaction => {
            const row = document.createElement('tr');
            
            // Formatear la fecha
            const date = new Date(transaction.fecha);
            const formattedDate = date.toLocaleDateString('es-ES', {
                year: 'numeric',
                month: 'short',
                day: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            });

            // Formatear el monto y determinar el tipo de transacción
            const amount = parseFloat(transaction.monto);
            let transactionType = '';
            let amountClass = '';
            let formattedAmount = '';
            let description = transaction.descripcion || '-';

            switch(transaction.tipo) {
                case 'deposito':
                    transactionType = 'Depósito';
                    amountClass = 'deposit';
                    formattedAmount = `+$${Math.abs(amount).toFixed(2)}`;
                    break;
                case 'retiro':
                    transactionType = 'Retiro';
                    amountClass = 'withdraw';
                    formattedAmount = `-$${Math.abs(amount).toFixed(2)}`;
                    break;
                case 'transferencia':
                    if (transaction.transaction_direction === 'recibida') {
                        transactionType = 'Transferencia Recibida';
                        amountClass = 'transfer-in';
                        formattedAmount = `+$${Math.abs(amount).toFixed(2)}`;
                        description = `De: <strong>${transaction.from_email}</strong>`;
                    } else {
                        transactionType = 'Transferencia Enviada';
                        amountClass = 'transfer-out';
                        formattedAmount = `-$${Math.abs(amount).toFixed(2)}`;
                        description = `Para: <strong>${transaction.to_email}</strong>`;
                    }
                    break;
                default:
                    transactionType = 'Desconocido';
                    amountClass = '';
                    formattedAmount = `$${amount.toFixed(2)}`;
            }

            // Crear el contenido de la fila
            row.innerHTML = `
                <td>${formattedDate}</td>
                <td>${transactionType}</td>
                <td class="transaction-description">${description}</td>
                <td class="transaction-amount ${amountClass}">${formattedAmount}</td>
            `;

            transactionsTable.appendChild(row);
        });
    }

    const API_URL = 'https://digital-wallet2-backend.onrender.com/';

    function handleTransaction(type, formData) {
        fetch(`${API_URL}transaction.php`, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': `Bearer ${token}`
            },
            body: JSON.stringify({
                tipo: type,
                ...formData
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Cerrar modal correspondiente
                switch (type) {
                    case 'deposito': 
                        if (depositModal) depositModal.hide();
                        if (depositForm) depositForm.reset();
                        break;
                    case 'retiro': 
                        if (withdrawModal) withdrawModal.hide();
                        if (withdrawForm) withdrawForm.reset();
                        break;
                    case 'transferencia': 
                        if (transferModal) transferModal.hide();
                        if (transferForm) transferForm.reset();
                        break;
                }
                
                // Recargar datos
                loadWalletData();
                
                showToast('Éxito', `Transacción ${type} realizada con éxito`, 'success');
            } else {
                throw new Error(data.message || 'Error al procesar la transacción');
            }
        })
        .catch(error => {
            showToast('Error', error.message || 'Error al procesar la transacción', 'error');
            console.error('Error:', error);
        });
    }

    // Event Listeners
    if (depositForm) {
        depositForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const amount = document.getElementById('deposit-amount').value;
            const description = document.getElementById('deposit-description').value;
            
            handleTransaction('deposito', {
                monto: parseFloat(amount),
                descripcion: description || 'Depósito'
            });
        });
    }

    if (withdrawForm) {
        withdrawForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const amount = document.getElementById('withdraw-amount').value;
            const description = document.getElementById('withdraw-description').value;
            
            handleTransaction('retiro', {
                monto: parseFloat(amount),
                descripcion: description || 'Retiro'
            });
        });
    }

    if (transferForm) {
        transferForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const amount = document.getElementById('transfer-amount').value;
            const description = document.getElementById('transfer-description').value;
            const email = document.getElementById('transfer-email').value;
            
            handleTransaction('transferencia', {
                monto: parseFloat(amount),
                descripcion: description || 'Transferencia',
                email_destino: email
            });
        });
    }

    if (logoutBtn) {
        logoutBtn.addEventListener('click', function(e) {
            e.preventDefault();
            
            fetch(`${API_URL}logout.php`, {
                method: 'POST',
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            })
            .finally(() => {
                localStorage.removeItem('session_token');
                window.location.href = 'login.html';
            });
        });
    }

    // Actualizar datos cada 30 segundos
    setInterval(loadWalletData, 30000);
});
