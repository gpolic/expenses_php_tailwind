# Expenses PHP Tailwind

A mobile friendly web application for tracking personal expenses built with PHP and styled with Tailwind CSS. This application uses a MySQL database hosted on Aiven.io and includes comprehensive reporting features with interactive charts.

## Features

- **Expense Tracking**: Add, edit, and delete personal expenses with categories
- **Interactive Reports**: View expense trends and category analysis with charts
- **Mobile Friendly**: Responsive design optimized for mobile devices
- **User Authentication**: Secure login system
- **Category Management**: Organize expenses by customizable categories
- **Chart Analytics**:
  - Monthly expense trends (last 12 months)
  - Average spending lines with trend analysis
  - Top 10 expense categories bar chart

## Prerequisites

- PHP 7.4 or higher
- Web server with PHP support (Apache, Nginx, etc.)
- MySQL database (hosted on Aiven.io or elsewhere)
- Git (for cloning the repository)

## Installation

1. Clone the repository:

git clone https://github.com/gpolic/expenses_php_tailwind.git

2. Navigate to the project directory:

3. Configure your database connection (see Database Configuration section below).

4. Deploy to your web server or run locally

5. In case there are many categories setup in the DB, shorten your default category list by updating 'add_record.php' for $defaultCategories with your preferred category IDs

## Database Configuration

This application uses Aiven.io as MySQL cloud database. To configure your database connection:

1. Create a MySQL database service on [Aiven.io](https://aiven.io/)

2. From your Aiven console, obtain the following credentials:
   - Database host/endpoint
   - Database name
   - Username
   - Password
   - Port (usually 3306)
   - Download the SSL certificate (ca.pem) from the Overview page of your service

3. Create a `config.php` file in the root directory with the indicated structure

4. Execute the `database.sql` file in your MySQL database to create the required tables

5. Setup your server with all application files. Open your web site URL and login with admin/admin

6. Login using admin/admin to start tracking your expenses

## Database Schema

The application uses the following tables:

### `expenses`
- `expense_id` - Primary key
- `category_id` - Foreign key to expense_categories
- `expense_amount` - Expense amount (decimal)
- `created_at` - Expense date and time
- `expense_description` - Optional description
- `updated_at` - Auto-updated timestamp

### `expense_categories`
- `category_id` - Primary key
- `category_name` - Category name

### `users`
- `id` - Primary key
- `username` - User login name
- `password` - Hashed password

## Navigation

- **Dashboard**: View recent expenses and monthly totals
- **Add Record**: Add new expenses by category
- **Reports**: Interactive charts and analytics
- **Edit/Delete**: Modify existing expense records

## Reports Features

The Reports page provides:
- **Monthly Trend Chart**: Line chart showing expenses over the last 12 completed months
- **Average Line**: Shows average monthly spending with exact figure
- **Trend Analysis**: Linear regression trend line indicating spending direction
- **Category Analysis**: Bar chart of top 10 expense categories by total amount
- **Summary Cards**: Key metrics including averages, totals, and trend indicators