# CampusMate
### Smart Campus Resource Exchange & Lost Item Management System

CampusMate is a web-based application designed to help students report lost and found items, claim their belongings, and share or borrow useful academic resources through a single platform.

## 🎯 Objectives

- Make reporting lost and found items easier.
- Help students identify and claim their belongings.
- Allow students to share and borrow academic resources.
- Manage claims and borrowing requests in one place.

## ✨ Features

### 1. User Registration and Login
Students can create accounts and log in to access the application.

### 2. Lost & Found Management
Users can report lost or found items with details such as the item name, category, location, and photograph.

### 3. Claim Management
Students can submit claims for found items. The person who reported an item can review and approve or reject a claim.

### 4. Resource Exchange
Students can list useful academic resources and request to borrow available resources.

### 5. Request Tracking
Users can track the status of claims and borrowing requests.

## 🛠️ Technologies Used

- **HTML** – Web page structure
- **CSS** – Styling and layout
- **PHP** – Backend logic and form processing
- **MySQL** – Database management
- **PDO** – PHP database connectivity
- **XAMPP** – Local development environment
- **phpMyAdmin** – Database management interface
- **InfinityFree** – Website hosting

## 📁 Project Structure

```text
CampusMate/
├── assets/
│   └── css/
│       └── style.css
├── config/
│   └── database.example.php
├── index.php
├── login.php
├── register.php
├── dashboard.php
├── logout.php
├── lost_found.php
├── report_item.php
├── claim_item.php
├── manage_claims.php
└── resources.php
```

## 🚀 Running the Project Locally

1. Install XAMPP.
2. Place the project folder inside the XAMPP `htdocs` directory.
3. Start Apache and MySQL from the XAMPP Control Panel.
4. Create and configure the required MySQL database using phpMyAdmin.
5. Configure the database connection using your local credentials.
6. Open the project in your browser using your localhost project path.

## 🔐 Security

The application includes security measures such as password hashing, prepared statements, session management, input validation, CSRF checks, and file-upload validation.

Never publish real database passwords or other secret credentials in this repository.

## 🔮 Future Enhancements

- Email notifications for claims and borrowing requests.
- Improved search and filtering.
- Better mobile responsiveness.
- Additional campus resource-sharing features.

## 👨‍💻 Project Information

**Project Name:** CampusMate  
**Project Type:** Web Application  
**Purpose:** Campus Lost & Found and Resource Exchange

---

*Your Campus. Your Community.*
