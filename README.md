# 🖤 sclavii.online

A dark-themed, minimal content management platform built with vanilla PHP. No frameworks, no bloat — just clean, functional code.

![PHP](https://img.shields.io/badge/PHP-8.0+-777BB4?logo=php&logoColor=white)
![MySQL](https://img.shields.io/badge/MySQL-5.7+-4479A1?logo=mysql&logoColor=white)
![License](https://img.shields.io/badge/License-MIT-green)

---

## ✨ Features

- **📝 Posts / Pastes** — Create, read, edit, and delete content with rich text support
- **🖼️ Image Uploads** — Drag & drop or click to upload, with automatic placeholder insertion
- **📰 News System** — Collapsible news cards with CRUD operations (admin only)
- **👥 User Management** — Role-based access control (admin, viewer, user)
- **📊 View Counter** — Track post views automatically
- **📌 Pin Posts** — Highlight important content
- **🔍 Search** — Full-text search across posts
- **🎵 Song of the Day** — Spotify embed with lyrics display
- **📱 Responsive** — Works on desktop and mobile
- **🌙 Dark Theme** — Consistent `#1b1b1b` aesthetic throughout

---

## 🚀 Quick Start

### Requirements
- PHP 8.0+
- MySQL 5.7+ or MariaDB 10.3+
- Apache/Nginx with mod_rewrite enabled

### Installation

```bash
# Clone the repository
git clone https://github.com/yourusername/sclavii.online.git
cd sclavii.online

# Create the database
mysql -u root -p < database/schema.sql

# Configure your environment
cp config.example.php config.php
# Edit config.php with your database credentials

# Create uploads folder and set permissions
mkdir uploads
chmod 755 uploads

# Ensure proper URL rewriting (Apache .htaccess included)
```

### Database Schema

```sql
CREATE TABLE users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(100) UNIQUE NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    role ENUM('admin','viewer','user') DEFAULT 'user',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE posts (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    description TEXT,
    images TEXT,
    links TEXT,
    created_by VARCHAR(100),
    views INT DEFAULT 0,
    is_pinned BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
);

CREATE TABLE news (
    id INT AUTO_INCREMENT PRIMARY KEY,
    title VARCHAR(255) NOT NULL,
    url VARCHAR(500),
    description TEXT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);
```

---

## 🗂️ Project Structure

```
sclavii.online/
├── 📄 index.php              # Homepage with posts list, news, last users
├── 📄 post.php               # Single post view (read-only, clean design)
├── 📄 add.php                # Create new post (editor + sidebar)
├── 📄 edit.php               # Edit existing post (same design as add)
├── 📄 login.php              # Authentication
├── 📄 logout.php             # Session destroy
├── 📄 users.php              # User list (admin only)
├── 📄 profile.php            # User profile page
├── 📄 news_ajax.php          # News CRUD API
├── 📄 post_ajax.php          # Post actions API (pin, delete)
├── 📄 config.php             # Database + session configuration
├── 📄 navbar.php             # Shared navigation component
├── 📁 uploads/               # Uploaded images storage
├── 📁 database/
│   └── schema.sql            # Full database schema
└── 📄 .htaccess              # URL rewriting rules
```

---

## 🎨 Design Philosophy

Every page follows the same visual language:

| Element | Value |
|---------|-------|
| Background | `#1b1b1b` |
| Card BG | `#121212` |
| Card Border | `#2a2a2a` |
| Border Radius | `16px` |
| Accent Blue | `#4da6ff` |
| Accent Green | `#6ec56b` |
| Accent Red | `#dc2626` |
| Text Primary | `#ffffff` |
| Text Secondary | `#8f9bb3` |

**No navbar on content pages** — clean, distraction-free reading experience with just a back link.

---

## 🔐 Role System

| Role | Permissions |
|------|-------------|
| `admin` | Full access: add, edit, delete, pin, manage users, manage news |
| `viewer` | Read-only: view posts, no admin actions |
| `user` | Standard user (can be extended) |

---

## 🛠️ API Endpoints

### News (news_ajax.php)
```
POST news_ajax
  action=add    &title=...&url=...&description=...
  action=edit   &id=...&title=...&url=...&description=...
  action=delete &id=...
```

### Posts (post_ajax.php)
```
POST post_ajax.php
  action=toggle_pin &id=...&is_pinned=0|1
  action=delete     &id=...
```

---

## 📝 Image Upload Flow

1. User drags or clicks to select images
2. JavaScript generates placeholders: `##UPLOAD_IMAGE_0##`
3. On submit, PHP processes uploads and replaces placeholders with real paths
4. Images stored in `/uploads/` with sanitized filenames

---

## 🎵 Song of the Day

Configure in `index.php`:

```php
$songOfTheDay = [
    'spotify_id' => '11dFghVXANMlKmJXsDCbje',
    'title' => 'Starboy',
    'artist' => 'The Weeknd ft. Daft Punk',
    'lyrics' => "..."
];
```

---

## ⚙️ Configuration

Edit `config.php`:

```php
$host = 'localhost';
$db   = 'sclavii';
$user = 'root';
$pass = 'password';
$charset = 'utf8mb4';
```

---

## 🐛 Troubleshooting

| Issue | Solution |
|-------|----------|
| Images not showing | Check `uploads/` folder exists and has correct permissions (755) |
| 404 on routes | Ensure `.htaccess` is present and mod_rewrite is enabled |
| Database connection failed | Verify credentials in `config.php` |
| Can't upload images | Increase `upload_max_filesize` and `post_max_size` in php.ini |

---

## 🤝 Contributing

1. Fork the repository
2. Create a feature branch (`git checkout -b feature/amazing-feature`)
3. Commit your changes (`git commit -m 'Add amazing feature'`)
4. Push to the branch (`git push origin feature/amazing-feature`)
5. Open a Pull Request

---

## 📜 License

Distributed under the MIT License. See `LICENSE` for more information.

---

## 🙏 Acknowledgments

- Built with ❤️ and vanilla PHP
- No frameworks were harmed in the making of this project
- Dark theme inspired by the void

---

<p align="center">
  <sub>Built for the community. Used by the community.</sub>
</p>
