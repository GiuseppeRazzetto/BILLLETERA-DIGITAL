# Digital Wallet 2

A modern digital wallet web application with PHP backend and MySQL database.

## Project Structure

```
digital-wallet2/
├── backend/
│   ├── api/
│   │   └── setup_db.php
│   ├── config/
│   │   ├── database.php
│   │   └── database.prod.php
│   ├── Dockerfile
│   ├── index.php
│   └── test_db.php
├── frontend/
│   ├── css/
│   ├── js/
│   └── login.html
└── README.md
```

## Technologies Used

- Frontend: HTML, CSS, JavaScript
- Backend: PHP 8.0
- Database: MySQL
- Development: XAMPP
- Deployment: 
  - Frontend: GitHub Pages
  - Backend: Render
  - Database: FreeSQLDatabase

## Development Setup

1. Clone the repository
2. Install XAMPP with PHP 8.0
3. Copy the project to `htdocs` directory
4. Configure database credentials in `backend/config/database.php`

## Deployment

### Frontend
- Hosted on GitHub Pages
- Access at: https://giusepperazzetto.github.io/digital-wallet2/frontend/login.html

### Backend
- Hosted on Render
- Base URL: https://digital-wallet2-backend.onrender.com
- Endpoints:
  - `/` - API status
  - `/test_db.php` - Database connection test
  - `/api/setup_db.php` - Database setup

### Database
- Host: sql10.freesqldatabase.com
- Configured tables:
  - users
  - currencies
  - wallets
  - transactions
  - sessions
  - login_attempts

## Security Features

- Secure password hashing
- Session management
- Login attempt tracking
- CORS configuration
- Environment-specific database settings
