# Advanced Classroom System

A complete, modular PHP/MySQL Learning Management System.

---

## 🚀 How to Run

**Requirements:** XAMPP (D:\xampp), PHP 7.4+, MySQL

1. Make sure your project is in `D:\xampp\htdocs\advance_classroom\`
2. Start Apache and MySQL from XAMPP Control Panel
3. Visit: **http://localhost/advance_classroom/**

---

## 🔑 Default Login

| Role  | Email                     | Password   |
|-------|---------------------------|------------|
| Admin | admin@classroom.com       | `password` |

> ⚠️ Change the admin password after first login via Profile Settings.

---

## 📁 Project Structure

```
advance_classroom/
├── config/
│   └── db.php              ← Database settings (BASE_URL, DB config)
├── includes/
│   ├── header.php          ← Session + DB + HTML head
│   ├── navbar.php          ← Sidebar + top nav
│   ├── footer.php          ← AI widget + JS
│   ├── auth.php            ← Login guards
│   └── functions.php       ← Helper functions
├── admin/                  ← Admin-only pages
├── teacher/                ← Teacher pages
├── student/                ← Student pages
├── guardian/               ← Guardian pages
├── assets/
│   ├── css/style.css       ← Main stylesheet
│   ├── js/app.js           ← Main JavaScript
│   └── uploads/            ← File uploads (auto-created)
├── index.php               ← Landing page
├── login.php               ← Sign in
├── register.php            ← Create account
├── dashboard.php           ← Teacher/Student dashboard
├── class_details.php       ← Class hub (stream/classwork/materials)
├── notifications.php       ← Notifications
├── profile.php             ← User settings
├── logout.php              ← Sign out
├── export.php              ← CSV export
└── database.sql            ← DB schema (reference only)
```

---

## ⚙️ Configuration

Edit `config/db.php` to change settings:

```php
define('DB_HOST', 'localhost');
define('DB_NAME', 'advanced_classroom');
define('DB_USER', 'root');
define('DB_PASS', '');       // XAMPP default: empty
define('BASE_URL', '/advance_classroom');  // Must match folder name
```

---

## 🔄 Keeping E:\ Drive in Sync with XAMPP

If you edit files in `e:\My Projects\HTML\Advance Classroom\`, run this command to sync them to XAMPP:

```powershell
robocopy "e:\My Projects\HTML\Advance Classroom" "D:\xampp\htdocs\advance_classroom" /E /MIR /XD ".git" /NFL /NDL /NJH /NJS
```

---

## 👥 Roles

| Role     | Capabilities                                                  |
|----------|---------------------------------------------------------------|
| Admin    | Manage all users, view reports, block/unblock accounts        |
| Teacher  | Create classes, post assignments, upload materials, grade work |
| Student  | Join classes, submit assignments, take quizzes, view grades   |
| Guardian | View linked student's progress, attendance, grades (read-only)|

---

## 🗃️ Database Setup (if needed again)

If MySQL is reset, import `database.sql` via phpMyAdmin at:
http://localhost/phpmyadmin

Or create a new `setup.php` based on the one used during initial setup.

---

## ✅ Issues Fixed

- Database `advanced_classroom` created and seeded
- `BASE_URL` corrected to `/advance_classroom` (matches XAMPP folder)
- `assets/uploads/` directories created for file uploads
- Guardian registration now properly links student accounts
- Logout redirect fixed to work from all subdirectories
