document.addEventListener('DOMContentLoaded', function() {
    const loginForm = document.getElementById('login-form');
    const errorDiv = document.getElementById('error-message');
    const API_URL = 'https://digital-wallet2-backend.onrender.com';

    // Ocultar mensaje de error inicial
    errorDiv.style.display = 'none';

    function showError(message) {
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        setTimeout(() => {
            errorDiv.style.display = 'none';
        }, 5000);
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
                console.log('Enviando token:', { email, token_personal: token });
                
                const response = await fetch(`${API_URL}/api/auth/login.php`, {
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
                console.log('Respuesta del servidor:', data);
                
                if (data.success && data.data && data.data.session_token) {
                    localStorage.setItem('session_token', data.data.session_token);
                    localStorage.setItem('user_email', data.data.user.email);
                    window.location.href = 'dashboard.html';
                } else {
                    showError(data.message || 'Error al iniciar sesión');
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
            const response = await fetch(`${API_URL}/api/auth/login.php`, {
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
            
            if (data.success && data.data && data.data.session_token) {
                localStorage.setItem('session_token', data.data.session_token);
                localStorage.setItem('user_email', data.data.user.email);
                window.location.href = 'dashboard.html';
            } else if (data.require_token) {
                showTokenInput();
            } else {
                showError(data.message || 'Error al iniciar sesión');
            }
        } catch (error) {
            console.error('Error:', error);
            showError('Error al conectar con el servidor');
        }
    });

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
            tokenInput.focus();
        }
    }
});
