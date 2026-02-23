# ArtVault — Setup Guide

## Requirements
- PHP 7.4+ with GD extension enabled
- MySQL 5.7+ / MariaDB 10.3+
- Apache (mod_rewrite) or Nginx
- Composer (optional)

---

## Installation

### 1. Create the Database
```sql
mysql -u root -p < sql/database.sql
```

### 2. Configure DB Connection
Edit `includes/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_mysql_user');
define('DB_PASS', 'your_mysql_password');
define('DB_NAME', 'artvault');
```

### 3. Set Folder Permissions
```bash
chmod 750 uploads/
chmod 750 uploads/originals/
chmod 755 uploads/previews/
```

### 4. Add a TrueType Font (for watermarks)
- Place `arial.ttf` (or any TTF font) in `includes/fonts/`
- If no font is found, a built-in bitmap font is used as fallback

### 5. Apache .htaccess (if not using subdirectory)
Enable `AllowOverride All` in your Apache config. The `uploads/.htaccess`
blocks direct access to original files.

---

## Default Admin Account
- **Email:** admin@artvault.com
- **Password:** Admin@1234
- ⚠️ Change this immediately after first login via the database.

---

## User Roles

| Role   | Can Do                                              |
|--------|-----------------------------------------------------|
| Artist | Upload art, view sales, manage their own works      |
| Buyer  | Browse gallery, purchase art, download originals    |
| Admin  | Manage all users, artworks, transactions, features  |

---

## Image Protection System

1. **Upload** → Artist uploads image via Studio
2. **Watermark** → PHP GD Library tiles "ArtVault — Preview Only" across image diagonally + bottom banner
3. **Storage** → Original saved to `uploads/originals/` (blocked by `.htaccess`) · Preview saved to `uploads/previews/` (publicly accessible)
4. **Gallery** → Only watermarked preview shown to all visitors
5. **Purchase** → Buyer completes checkout → `purchases` table records completed transaction
6. **Download** → `artwork/download.php` verifies purchase in DB before serving original via `readfile()` — never exposes the actual file path

### Additional frontend protections (JS layer):
- Right-click disabled on all images
- `Ctrl+S` / `Cmd+S` intercepted
- Images hidden on `beforeprint`
- `user-select: none` + `-webkit-user-drag: none` on all images

> Note: These JS protections are a deterrent layer. Determined users can bypass them.
> The server-side purchase check in `download.php` is the true security gate.

---

## Plugging in a Real Payment Gateway

Replace the placeholder in `payment/checkout.php` with your preferred gateway:

**Stripe:**
```bash
composer require stripe/stripe-php
```
Then replace the `<form>` POST with Stripe's `PaymentIntent` flow.

**M-Pesa (Daraja API):**
Use Safaricom's STK Push API and verify the callback before marking `payment_status = 'completed'`.

---

## File Structure
```
art-gallery/
├── index.php               Homepage + gallery
├── login.php               Sign in
├── register.php            Register (buyer or artist)
├── logout.php
├── artwork/
│   ├── view.php            Artwork detail page
│   └── download.php        Secure original download
├── dashboard/
│   ├── artist.php          Artist studio
│   ├── buyer.php           Buyer collection
│   └── admin.php           Admin panel
├── payment/
│   └── checkout.php        Payment flow (placeholder)
├── includes/
│   ├── db.php              MySQL connection
│   ├── auth.php            Session / auth helpers
│   ├── watermark.php       GD Library watermarking
│   └── fonts/              Place arial.ttf here
├── uploads/
│   ├── originals/          Protected originals (.htaccess blocks access)
│   └── previews/           Public watermarked previews
├── assets/
│   ├── css/style.css
│   └── js/main.js
└── sql/
    └── database.sql        Full schema
```
