document.addEventListener('DOMContentLoaded', async function() {
    console.log('Dashboard: Iniciando...');
    // Verificar sesión primero
    const token = localStorage.getItem('session_token');
    console.log('Dashboard: Token encontrado:', token ? 'Sí' : 'No');
    
    if (!token) {
        console.log('Dashboard: No hay token, redirigiendo a login');
        await new Promise(resolve => setTimeout(resolve, 3000)); // Esperar 3 segundos
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

    // Inicializar modales
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
        
        const closeBtn = toast.querySelector('.toast-close');
        closeBtn.addEventListener('click', () => {
            toast.remove();
        });
        
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

    async function checkSession() {
        console.log('Dashboard: Verificando sesión...');
        try {
            const response = await fetch('https://digital-wallet2-backend.onrender.com/api/auth/check_session.php', {
                headers: {
                    'Authorization': `Bearer ${token}`
                }
            });
            
            console.log('Dashboard: Respuesta de check_session:', response.status);
            const responseText = await response.text();
            console.log('Dashboard: Respuesta completa:', responseText);
            
            if (!response.ok) {
                throw new Error(`Sesión inválida: ${responseText}`);
            }

            const data = JSON.parse(responseText);
            console.log('Dashboard: Datos de sesión:', data);
            
            if (data.success) {
                userEmail.textContent = data.data.email;
                await loadWalletData();
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Dashboard: Error en checkSession:', error);
            await new Promise(resolve => setTimeout(resolve, 3000)); // Esperar 3 segundos
            localStorage.removeItem('session_token');
            window.location.href = 'login.html';
        }
    }

    async function loadWalletData() {
        console.log('Dashboard: Cargando datos de wallet...');
        try {
            const response = await fetch('https://digital-wallet2-backend.onrender.com/api/wallet/balance.php', {
                method: 'GET',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                }
            });
            
            console.log('Dashboard: Respuesta de balance:', response.status);
            
            if (!response.ok) {
                throw new Error('Error al cargar datos');
            }

            const data = await response.json();
            console.log('Dashboard: Datos de wallet:', data);
            
            if (data.success) {
                updateBalance(data.data.balance);
                updateTransactions(data.data.transactions);
            } else {
                throw new Error(data.message);
            }
        } catch (error) {
            console.error('Dashboard: Error en loadWalletData:', error);
            showToast('Error', 'Error al cargar los datos de la wallet', 'error');
        }
    }

    function updateBalance(balance) {
        balanceElement.textContent = formatCurrency(balance);
    }

    function updateTransactions(transactions) {
        transactionsList.innerHTML = '';
        
        if (!transactions || transactions.length === 0) {
            transactionsList.innerHTML = '<tr><td colspan="4" class="text-center">No hay transacciones</td></tr>';
            return;
        }

        transactions.forEach(transaction => {
            const row = document.createElement('tr');
            
            let typeText = '';
            let amountClass = '';
            let amountPrefix = '';
            
            switch(transaction.tipo) {
                case 'deposito':
                    typeText = 'Depósito';
                    amountClass = 'transaction-amount deposit';
                    amountPrefix = '+';
                    break;
                case 'retiro':
                    typeText = 'Retiro';
                    amountClass = 'transaction-amount withdraw';
                    amountPrefix = '-';
                    break;
                case 'transferencia':
                    if (transaction.wallet_from_id === transaction.wallet_id) {
                        typeText = 'Transferencia enviada';
                        amountClass = 'transaction-amount transfer-out';
                        amountPrefix = '-';
                    } else {
                        typeText = 'Transferencia recibida';
                        amountClass = 'transaction-amount transfer-in';
                        amountPrefix = '+';
                    }
                    break;
            }
            
            row.innerHTML = `
                <td>${formatDate(transaction.created_at)}</td>
                <td>${typeText}</td>
                <td>${transaction.description || '-'}</td>
                <td class="${amountClass}">${amountPrefix}${formatCurrency(transaction.amount)}</td>
            `;
            
            transactionsList.appendChild(row);
        });
    }

    async function handleTransaction(tipo, formData) {
        try {
            const token_personal = formData.get('token_personal');
            if (!token_personal) {
                throw new Error('Token personal requerido');
            }

            // Primero verificar el token personal
            const verifyResponse = await fetch('https://digital-wallet2-backend.onrender.com/api/wallet/verify_token.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify({ token_personal })
            });

            if (!verifyResponse.ok) {
                throw new Error('Token personal inválido');
            }

            // Si el token es válido, proceder con la transacción
            let endpoint;
            const data = { token_personal };

            switch(tipo) {
                case 'deposit':
                    endpoint = 'deposit.php';
                    data.monto = parseFloat(formData.get('monto'));
                    data.descripcion = formData.get('descripcion') || 'Depósito a la billetera';
                    break;
                case 'withdraw':
                    endpoint = 'withdraw.php';
                    data.monto = parseFloat(formData.get('monto'));
                    data.descripcion = formData.get('descripcion') || 'Retiro de la billetera';
                    break;
                case 'transfer':
                    endpoint = 'transfer.php';
                    data.monto = parseFloat(formData.get('monto'));
                    data.email_destino = formData.get('email_destino');
                    data.descripcion = formData.get('descripcion') || 'Transferencia';
                    break;
                default:
                    throw new Error('Tipo de transacción inválido');
            }

            const response = await fetch(`https://digital-wallet2-backend.onrender.com/api/wallet/${endpoint}`, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${token}`
                },
                body: JSON.stringify(data)
            });

            const responseData = await response.json();
            
            if (responseData.success) {
                showToast('Éxito', responseData.message);
                loadWalletData();
                return true;
            } else {
                throw new Error(responseData.message);
            }
        } catch (error) {
            showToast('Error', error.message, 'error');
            return false;
        }
    }

    // Event Listeners para los formularios de transacciones
    function showTokenInput(form) {
        // Remover el campo de token si ya existe
        const existingToken = form.querySelector('.token-field');
        if (existingToken) {
            existingToken.remove();
        }

        // Crear el nuevo campo de token
        const tokenDiv = document.createElement('div');
        tokenDiv.className = 'mb-3 token-field';
        tokenDiv.innerHTML = `
            <label for="token_personal" class="form-label">Token Personal (4 dígitos)</label>
            <input type="text" class="form-control text-center" name="token_personal" maxlength="4" required pattern="[0-9]{4}" inputmode="numeric" style="letter-spacing: 8px; font-size: 1.2em;">
            <div class="form-text text-center">Ingrese los 4 dígitos de su token personal</div>
        `;
        
        // Insertar el campo de token antes del botón submit
        const submitButton = form.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.parentNode.insertBefore(tokenDiv, submitButton);
        }

        // Agregar evento para solo permitir números
        const tokenInput = form.querySelector('input[name="token_personal"]');
        if (tokenInput) {
            tokenInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/\D/g, '').slice(0, 4);
            });
            tokenInput.focus();
        }
    }

    if (depositForm) {
        depositForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            if (!formData.get('token_personal')) {
                showTokenInput(this);
                return;
            }

            if (await handleTransaction('deposit', formData)) {
                this.reset();
            }
        });
    }

    if (withdrawForm) {
        withdrawForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            if (!formData.get('token_personal')) {
                showTokenInput(this);
                return;
            }

            if (await handleTransaction('withdraw', formData)) {
                this.reset();
            }
        });
    }

    if (transferForm) {
        transferForm.addEventListener('submit', async function(e) {
            e.preventDefault();
            const formData = new FormData(this);
            
            if (!formData.get('token_personal')) {
                showTokenInput(this);
                return;
            }

            if (await handleTransaction('transfer', formData)) {
                this.reset();
            }
        });
    }

    if (logoutBtn) {
        logoutBtn.addEventListener('click', function() {
            localStorage.removeItem('session_token');
            window.location.href = 'login.html';
        });
    }

    // Iniciar verificación de sesión y carga de datos
    try {
        await checkSession();
    } catch (error) {
        console.error('Dashboard: Error al verificar sesión:', error);
        await new Promise(resolve => setTimeout(resolve, 3000)); // Esperar 3 segundos
        localStorage.removeItem('session_token');
        window.location.href = 'login.html';
        return;
    }
});
