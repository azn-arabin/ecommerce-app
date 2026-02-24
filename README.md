# E-Commerce App - Multi-Login SSO System

This is the E-Commerce application part of the Multi-Login SSO system. It allows users to log in once and automatically be logged in to the Foodpanda application.

## Features

- User Registration & Authentication
- Single Sign-On (SSO) with Foodpanda App
- Secure token-based authentication
- Automatic cross-app login
- Modern, responsive UI

## Technology Stack

- **Framework:** Laravel 10
- **Authentication:** Laravel Sanctum
- **Database:** MySQL
- **Frontend:** Blade Templates with CSS

## Installation

### Prerequisites

- PHP 8.1 or higher
- Composer
- MySQL
- Web server (Apache/Nginx)

### Setup Instructions

1. **Clone the repository**

   ```bash
   git clone <repository-url>
   cd ecommerce-app
   ```

2. **Install dependencies**

   ```bash
   composer install
   ```

3. **Environment Configuration**

   ```bash
   cp .env.example .env
   ```

4. **Configure the `.env` file**

   ```env
   APP_NAME="E-Commerce App"
   APP_URL=http://localhost:8000

   DB_CONNECTION=mysql
   DB_HOST=127.0.0.1
   DB_PORT=3306
   DB_DATABASE=ecommerce_db
   DB_USERNAME=root
   DB_PASSWORD=

   # SSO Configuration
   SSO_SECRET_KEY=your-shared-secret-key-change-this
   FOODPANDA_APP_URL=http://localhost:8001
   ```

5. **Generate application key**

   ```bash
   php artisan key:generate
   ```

6. **Create database**

   ```sql
   CREATE DATABASE ecommerce_db;
   ```

7. **Run migrations**

   ```bash
   php artisan migrate
   ```

8. **Create storage directories**

   ```bash
   mkdir -p storage/framework/sessions
   mkdir -p storage/framework/views
   mkdir -p storage/framework/cache
   mkdir -p storage/logs
   ```

9. **Set permissions**

   ```bash
   chmod -R 775 storage bootstrap/cache
   ```

10. **Start the development server**

    ```bash
    php artisan serve --port=8000
    ```

11. **Access the application**
    - Open browser: http://localhost:8000

## How SSO Works

### Architecture

The Multi-Login SSO system uses a token-based authentication approach:

1. **User Registration/Login:**
   - User registers or logs in to the E-Commerce app
   - System generates a secure SSO token using HMAC-SHA256
   - Token contains user information and timestamp
   - Token is signed with a shared secret key

2. **Token Structure:**

   ```
   Token = base64(JSON_payload) + "." + HMAC-SHA256(payload, secret_key)

   Payload:
   {
       "user_id": 1,
       "email": "user@example.com",
       "name": "John Doe",
       "timestamp": 1234567890
   }
   ```

3. **Cross-App Authentication:**
   - E-Commerce app notifies Foodpanda app via API
   - SSO token is transmitted securely
   - Foodpanda app validates the token using the shared secret
   - User is automatically logged in to Foodpanda

4. **User Synchronization:**
   - When a user registers in E-Commerce, their account is synced to Foodpanda
   - Both apps maintain their own user databases
   - User credentials are synchronized during registration

5. **Logout:**
   - When user logs out from E-Commerce, Foodpanda is notified
   - User is logged out from both systems simultaneously

### Security Features

- **Token Expiry:** Tokens expire after 1 hour
- **HMAC Signing:** Tokens are cryptographically signed
- **Shared Secret:** Both apps use a shared secret key for validation
- **HTTPS Recommended:** Use HTTPS in production
- **CSRF Protection:** Laravel CSRF tokens protect forms

## API Endpoints

### User Synchronization

```
POST /api/sync-user
Content-Type: application/json

{
    "name": "John Doe",
    "email": "john@example.com",
    "password": "password123",
    "secret": "your-shared-secret-key"
}
```

### SSO Login

```
POST /api/sso-login
Content-Type: application/json

{
    "sso_token": "eyJhbGc..."
}
```

### SSO Logout

```
POST /api/sso-logout
Content-Type: application/json

{
    "sso_token": "eyJhbGc..."
}
```

## Database Schema

### Users Table

```sql
- id (bigint, primary key)
- name (varchar)
- email (varchar, unique)
- email_verified_at (timestamp, nullable)
- password (varchar)
- remember_token (varchar, nullable)
- created_at (timestamp)
- updated_at (timestamp)
```

## Configuration

### SSO Secret Key

**IMPORTANT:** Change the default SSO secret key in production!

Both E-Commerce and Foodpanda apps must use the **same** secret key:

```env
SSO_SECRET_KEY=your-very-secure-random-secret-key-here
```

Generate a strong secret:

```bash
php -r "echo bin2hex(random_bytes(32));"
```

### App URLs

Ensure both apps know each other's URLs:

**E-Commerce App (.env):**

```env
APP_URL=http://localhost:8000
FOODPANDA_APP_URL=http://localhost:8001
```

**Foodpanda App (.env):**

```env
APP_URL=http://localhost:8001
ECOMMERCE_APP_URL=http://localhost:8000
```

## Testing the SSO

1. Start both applications:

   ```bash
   # Terminal 1 - E-Commerce
   cd ecommerce-app
   php artisan serve --port=8000

   # Terminal 2 - Foodpanda
   cd foodpanda-app
   php artisan serve --port=8001
   ```

2. Register a new user in E-Commerce app
3. After registration, you'll be logged in to E-Commerce
4. Click "Access Foodpanda Dashboard" link
5. You should be automatically logged in to Foodpanda
6. Logout from either app logs you out from both

## Deployment

### Production Checklist

- [ ] Set `APP_ENV=production` in `.env`
- [ ] Set `APP_DEBUG=false` in `.env`
- [ ] Generate new `APP_KEY`
- [ ] Use a strong, unique `SSO_SECRET_KEY`
- [ ] Configure proper database credentials
- [ ] Enable HTTPS/SSL
- [ ] Set correct file permissions
- [ ] Configure web server (Apache/Nginx)
- [ ] Set up proper session management
- [ ] Enable caching (`php artisan config:cache`)

### Deployment Options

1. **Shared Hosting (cPanel)**
   - Upload files to public_html
   - Point domain to `public` directory
   - Import database
   - Configure `.env` file

2. **VPS/Cloud (DigitalOcean, AWS, etc.)**
   - Set up LEMP/LAMP stack
   - Configure Nginx/Apache virtual host
   - Set up SSL certificate (Let's Encrypt)
   - Configure firewall

3. **Platform as a Service (Render, Heroku)**
   - Connect GitHub repository
   - Configure environment variables
   - Set up database add-on
   - Deploy automatically

## Troubleshooting

### Common Issues

**Issue:** "Class not found" errors

- **Solution:** Run `composer dump-autoload`

**Issue:** 500 Internal Server Error

- **Solution:** Check `storage/logs/laravel.log` for details
- Ensure storage directory has write permissions

**Issue:** CSRF token mismatch

- **Solution:** Clear browser cookies and cache
- Regenerate APP_KEY

**Issue:** SSO not working between apps

- **Solution:** Verify both apps use the same `SSO_SECRET_KEY`
- Check that app URLs are correctly configured
- Ensure both apps are running

**Issue:** Database connection error

- **Solution:** Verify database credentials in `.env`
- Ensure MySQL service is running
- Check database exists

## License

MIT License

## Support

For issues and questions, please open an issue on GitHub.
