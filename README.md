# Digital Wallet 2

Una aplicación web de billetera digital que permite a los usuarios gestionar sus finanzas.

## Estructura del Proyecto

```
digital-wallet2/
├── frontend/           # Archivos del frontend
│   ├── css/           # Estilos CSS
│   ├── js/            # Scripts JavaScript
│   ├── login.html     # Página de inicio de sesión
│   ├── register.html  # Página de registro
│   └── dashboard.html # Panel principal
├── backend/           # API y lógica del servidor
│   ├── api/          # Endpoints de la API
│   ├── config/       # Configuración
│   └── Dockerfile    # Configuración de Docker
└── index.html        # Redirección al frontend
```

## URLs de Producción

- Frontend: https://giusepperazzetto.github.io/digital-wallet2/frontend/login.html
- Backend: https://digital-wallet2-backend.onrender.com/

## Tecnologías Utilizadas

- Frontend: HTML, CSS, JavaScript
- Backend: PHP 8.0
- Base de datos: MySQL
- Despliegue: GitHub Pages (frontend) y Render (backend)

## Desarrollo Local

1. Clonar el repositorio
2. Configurar XAMPP o servidor PHP local
3. Importar la base de datos
4. Configurar las variables de entorno

## Características

- Registro y autenticación de usuarios
- Gestión de billetera digital
- Transferencias entre usuarios
- Historial de transacciones
- Token personal de seguridad
