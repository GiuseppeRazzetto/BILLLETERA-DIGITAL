document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('login-form');
    const errorDiv = document.getElementById('error-message');
    let blockTimer = null;

    // Ocultar mensaje de error inicial
    errorDiv.style.display = 'none';

    function showError(message, isBlocked = false, timeLeft = 0) {
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';

        if (blockTimer) {
            clearInterval(blockTimer);
            blockTimer = null;
        }

        if (isBlocked && timeLeft > 0) {
            blockTimer = setInterval(() => {
                timeLeft--;
                if (timeLeft <= 0) {
                    clearInterval(blockTimer);
                    blockTimer = null;
                    errorDiv.style.display = 'none';
                    enableForm();
                    return;
                }
                errorDiv.textContent = `Cuenta bloqueada. Espere ${timeLeft} segundos para intentar nuevamente.`;
            }, 1000);
            disableForm();
        } else if (!isBlocked) {
            setTimeout(() => {
                errorDiv.style.display = 'none';
            }, 5000);
            enableForm();
        }
    }

    function disableForm() {
        const inputs = loginForm.querySelectorAll('input, button');
        inputs.forEach(input => input.disabled = true);
    }

    function enableForm() {
        const inputs = loginForm.querySelectorAll('input, button');
        inputs.forEach(input => input.disabled = false);
    }

    function showTokenInput() {
        // Ocultar campos de email y contraseña
        const emailField = document.querySelector('.email-field');
        const passwordField = document.querySelector('.password-field');
        if (emailField) emailField.style.display = 'none';
        if (passwordField) passwordField.style.display = 'none';

        // Remover el campo de token si ya existe
        const existingToken = document.querySelector('.token-field');
        if (existingToken) {
            existingToken.remove();
        }

        // Crear el nuevo campo de token
        const tokenDiv = document.createElement('div');
        tokenDiv.className = 'mb-3 token-field';
        tokenDiv.innerHTML = `
            <label for="token" class="form-label">Token Personal (4 dígitos)</label>
            <input type="text" class="form-control text-center" id="token" maxlength="4" required pattern="[0-9]{4}" inputmode="numeric" style="letter-spacing: 8px; font-size: 1.2em;">
            <div class="form-text text-center">Ingrese los 4 dígitos</div>
        `;
        
        // Encontrar el contenedor de botones
        const buttonContainer = document.querySelector('.d-grid');
        
        // Insertar el campo de token antes del contenedor de botones
        if (buttonContainer && buttonContainer.parentNode) {
            buttonContainer.parentNode.insertBefore(tokenDiv, buttonContainer);
        }

        // Cambiar texto del botón
        const submitButton = document.querySelector('button[type="submit"]');
        if (submitButton) {
            submitButton.textContent = 'Verificar Token';
        }

        // Agregar evento para solo permitir números
        const tokenInput = document.getElementById('token');
        if (tokenInput) {
            tokenInput.addEventListener('input', function(e) {
                this.value = this.value.replace(/\D/g, '').slice(0, 4);
            });
        }
    }

    loginForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        const tokenInput = document.getElementById('token');
        const emailInput = document.getElementById('email');
        
        if (tokenInput) {
            const token = tokenInput.value.replace(/\D/g, '');
            const email = emailInput ? emailInput.value.trim() : '';
            
            if (!/^\d{4}$/.test(token)) {
                showError('El token debe ser de 4 dígitos');
                return;
            }

            try {
                const response = await fetch('/digital-wallet2/backend/api/login.php', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({
                        email: email,
                        token_personal: token
                    })
                });

                const data = await response.json();
                
                if (data.success) {
                    localStorage.setItem('session_token', data.session_token);
                    localStorage.setItem('user_email', data.user.email);
                    window.location.href = 'dashboard.html';
                } else {
                    const errorData = data.message ? JSON.parse(data.message) : null;
                    if (errorData && errorData.blocked) {
                        showError(errorData.message, true, errorData.timeLeft);
                    } else {
                        showError(errorData ? errorData.message : 'Token incorrecto');
                    }
                }
            } catch (error) {
                console.error('Error:', error);
                showError('Error al conectar con el servidor');
            }
            return;
        }

        const email = emailInput.value.trim();
        const password = document.getElementById('password').value;
        
        if (!email || !password) {
            showError('Por favor complete todos los campos');
            return;
        }

        try {
            const response = await fetch('/digital-wallet2/backend/api/login.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    email: email,
                    password: password
                })
            });

            const data = await response.json();
            
            if (data.success && data.require_token) {
                showTokenInput();
            } else {
                const errorData = data.message ? JSON.parse(data.message) : null;
                if (errorData && errorData.blocked) {
                    showError(errorData.message, true, errorData.timeLeft);
                } else {
                    showError(errorData ? errorData.message : 'Credenciales inválidas');
                }
            }
        } catch (error) {
            console.error('Error:', error);
            showError('Error al conectar con el servidor');
        }
    });
});
