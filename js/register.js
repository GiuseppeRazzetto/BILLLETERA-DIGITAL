document.addEventListener('DOMContentLoaded', function() {
    const registerForm = document.getElementById('register-form');
    const errorDiv = document.getElementById('error-message');
    const API_URL = 'https://digital-wallet2-backend.onrender.com/';

    function showError(message) {
        errorDiv.textContent = message;
        errorDiv.style.display = 'block';
        setTimeout(() => {
            errorDiv.style.display = 'none';
        }, 5000);
    }

    registerForm.addEventListener('submit', async function(e) {
        e.preventDefault();

        // Obtener valores del formulario
        const nombre = document.getElementById('nombre').value.trim();
        const apellido = document.getElementById('apellido').value.trim();
        const email = document.getElementById('email').value.trim();
        const telefono = document.getElementById('telefono').value.trim();
        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm-password').value;
        const token = document.getElementById('token').value.trim();

        // Validaciones
        if (password !== confirmPassword) {
            showError('Las contraseñas no coinciden');
            return;
        }

        if (password.length < 6) {
            showError('La contraseña debe tener al menos 6 caracteres');
            return;
        }

        if (!/^\d{4}$/.test(token)) {
            showError('El token personal debe ser de 4 dígitos');
            return;
        }

        try {
            const response = await fetch(API_URL + 'api/register.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json'
                },
                body: JSON.stringify({
                    nombre,
                    apellido,
                    email,
                    telefono,
                    password,
                    token_personal: token
                })
            });

            const data = await response.json();

            if (data.success) {
                // Registro exitoso
                alert('Registro exitoso. Por favor inicia sesión.');
                window.location.href = 'login.html';
            } else {
                showError(data.message);
            }
        } catch (error) {
            console.error('Error:', error);
            showError('Error al conectar con el servidor');
        }
    });

    // Validación en tiempo real del token
    const tokenInput = document.getElementById('token');
    tokenInput.addEventListener('input', function(e) {
        this.value = this.value.replace(/[^0-9]/g, '').slice(0, 4);
    });
});
