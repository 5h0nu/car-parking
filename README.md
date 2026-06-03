# 🚗 ParkMaster - Car Parking Management System

![Banner](logo.jpg)

**ParkMaster** is a modern, responsive, and secure car parking management portal designed for terminal operators and administrators. It features a sleek glassmorphic dark-theme user interface, real-time metrics dashboards, live slots mapping, secure password-hashed user management, and timezone-aligned invoice printing.

---

## ✨ Features

- **📊 Unified Operator Dashboard**:
  - **KPI Metrics Widgets**: Real-time stats showing Total Slots, Occupied spaces, Empty left, Slot ratios, and Live revenue collected today.
  - **Interactive Pie Chart**: Click on any metric card to open a pure-CSS `conic-gradient` space allocation distribution chart.
  - **Side-by-Side Split Attendant Actions**: Check in vehicles and check out vehicles directly from the dashboard homepage.
  - **Live Registries**: Dual live logs tracking currently parked cars and the last 10 checkout transactions.
- **🗺️ Live Slots Map**: Responsive visual grid of all slots showing occupancy status, owner registers, license plates, and vehicle category tags.
- **🧾 Aligned Billing & Cool Print UI**:
  - Aligned time zone session settings (`Asia/Kolkata`) preventing billing discrepancies.
  - High-end printable invoice slips featuring the centered site logo in grayscale (optimized for monochrome receipt printers) and a custom CSS-rendered barcode.
  - Multi-payment support (UPI, CARD, CASH).
- **🗄️ Real-Time Database Section**: Monitor active MySQL tables dynamically. Automatically polls row counts and size metrics using AJAX, flashing numbers on change, with instant glowing status indicators when the connection status fluctuates.
- **👥 User Administration & Security**: Register and delete operators securely. Passwords are hashed using BCrypt. Features self-deletion blocks to safeguard the active login session.

---

## 🛠️ Tech Stack

- **Backend**: PHP 8.x
- **Database**: MySQL / MariaDB (PDO driver)
- **Frontend**: HTML5, Vanilla JavaScript, Custom CSS (Glassmorphism & animations)
- **Icons**: SVG & Feather icons

---

## 🚀 Setup & Installation

### 1. Prerequisites
- **Web Server**: Apache/XAMPP/WAMP
- **PHP**: Version 7.4 or higher
- **Database**: MySQL / MariaDB

### 2. Import Database
1. Open your database administration console (e.g. phpMyAdmin).
2. Create a new database named `car_parking_db`.
3. Import the database backup script **[db.sql](db.sql)** located in this repository.

### 3. Deploy Project Files
Place the repository files inside your web server root directory (e.g. `C:/xampp/htdocs/car-park` or equivalent).

### 4. Run Development Server
Alternatively, serve the application directly using PHP CLI:
```bash
php -S localhost:8000
```
Then navigate to: **[http://localhost:8000/login.php](http://localhost:8000/login.php)**

---

## 🔐 Default Credentials

| Username | Password | Role |
| :--- | :--- | :--- |
| `admin` | `admin123` | Administrator |

---

## 📄 License
This project is open-source and free to distribute.
