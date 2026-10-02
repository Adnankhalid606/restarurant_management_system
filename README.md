# Restaurant POS & Management System

A web-based Restaurant Point of Sale (POS) and Management System built with PHP and MySQL. The system provides a centralized platform for managing restaurant operations including orders, kitchen workflow, inventory, billing, customers, suppliers, expenses, salaries, and reports.

## Project Overview

The Restaurant POS & Management System is designed to digitize and simplify the day-to-day operations of a restaurant.

It provides role-based access for different staff members and connects major restaurant workflows together. Orders created by waiters can move through the kitchen workflow, recipes can be used to track ingredient consumption, bills can be generated for completed orders, and management can monitor financial and operational information through reports.

The project is designed to run locally using XAMPP and is suitable for small restaurant environments and learning/assessment purposes.

## Purpose & Use

The system is intended to:

- Manage restaurant orders and order items
- Provide a dedicated kitchen workflow
- Manage menu items and availability
- Manage restaurant tables and reservations
- Track raw-material inventory
- Track recipes and ingredient usage
- Manage suppliers and purchases
- Manage customers
- Handle bills and payments
- Track expenses and staff salaries
- Maintain customer and supplier ledgers
- Generate sales, purchase, expense, inventory, and profit/loss reports
- Provide role-based access for restaurant staff

## Tech Stack

- **Backend:** PHP
- **Database:** MySQL / MariaDB
- **Frontend:** HTML, CSS, JavaScript
- **UI Framework:** Bootstrap
- **Server Environment:** XAMPP / Apache
- **Database Management:** phpMyAdmin
- **Icons:** Bootstrap Icons
- **Fonts:** Poppins + Inter

## Modules

- Authentication
- User Accounts & Roles
- Dashboard
- Orders
- Kitchen Display
- Dining Tables
- Reservations
- Menu Items
- Customers
- Inventory
- Stock Adjustments
- Recipes
- Suppliers
- Purchases
- Bills & Payments
- Expenses
- Customer Ledger
- Supplier Ledger
- Staff Salaries
- Reports & Analytics

## Working / How It Works

### Order Workflow

The waiter creates an order by selecting menu items from the visual menu and specifying quantities.

The order is stored with its items, table, customer information, waiter, order type, and total amount.

### Kitchen Workflow

Active orders are displayed in the Kitchen Display.

The kitchen staff progresses orders through the kitchen workflow:

`Pending → Preparing → Ready → Completed`

Inventory consumption is processed when the kitchen completes an order that has a recipe configured.

### Inventory & Recipes

Raw materials are maintained in inventory.

Recipes define which raw materials are required for a menu item and in what quantity.

When an applicable order is completed by the kitchen, the system deducts the required ingredients from inventory and records the corresponding inventory transactions.

### Purchasing

Purchases can be recorded against suppliers and their associated raw materials.

Purchase quantities update inventory, while purchase payments can be tracked through their payment status.

### Billing & Payments

Bills are generated for eligible completed/ready orders.

The system tracks bill totals, payment status, and payment timestamps.

### Reports

Administrators can view operational and financial reports including:

- Sales
- Purchases
- Expenses
- Inventory
- Profit & Loss

Customer and supplier ledgers provide additional visibility into outstanding balances and payments.

## How to Run

### Requirements

- XAMPP
- Apache
- MySQL / MariaDB
- PHP 8.x
- Web browser

### 1. Clone the project

Open Command Prompt or PowerShell and navigate to the XAMPP `htdocs` directory:
```
cd C:\xampp\htdocs
```

Clone the repository directly into `htdocs`:
```
git clone https://github.com/Adnankhalid606/restarurant_management_system
```

This will create:

```
C:\xampp\htdocs\restaurant_management_system
```

### 2. Start XAMPP

Open XAMPP Control Panel and start:

- Apache
- MySQL


### 3. Create the database

Open phpMyAdmin and create a database named:
```
restaurant_pos
```

### 4. Import the database schema

Open the `restaurant_pos` database in phpMyAdmin and import:
```
database/schema.sql
```
This file contains the database tables, indexes, auto-increment definitions, and foreign-key constraints required by the application.


### 5. Configure the database connection

Open:
```
config/database.php
```
Configure the MySQL connection according to your local XAMPP environment.


### 6. Open the application
**WAIT, Before Opening the Project, Add Admin User Manually in Database then logged in to Application**

Insert Admin Users in users table using following query:
```sql
INSERT INTO `users` (`name`, `username`, `email`, `password`, `role`, `is_active`) 
VALUES ('Admin', 'admin', 'admin@gmail.com', 'admin123', 'admin', 1);
```
Now, Your Admin User is Created and Ready to Login.

Visit:
```
http://localhost/restaurant_management_system/
```

Login with:
- **Username or Email:** `admin` or `admin@gmail.com`
- **Password:** `admin123`

## Limitations

- Currently tested and developed using **XAMPP** as the local server environment.
- **Admin accounts are currently created manually through the database** rather than through the application.
- The current system is designed around **one administrator per restaurant**, with an administrator having access to that restaurant's complete data.
- Menu item images are currently **stored locally on the application server**.

## Future Plans

- Add **Admin registration through the website** and assign each administrator a unique **Restaurant/Panel ID**, allowing administrators to access only their own restaurant's data.
- Move image storage from the local server to **cloud storage such as Cloudinary**.
- Replace plaintext password storage with **secure password hashing**.
- Implement more advanced **role and permission management**, allowing access to be controlled at the module level.


## License
This project was developed as a restaurant management and POS application for learning, assessment, and portfolio purposes.