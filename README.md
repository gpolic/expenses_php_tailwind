# Expenses PHP Tailwind

A web application for tracking personal expenses built with PHP and styled with Tailwind CSS. This application uses a MySQL database hosted on Aiven.io.

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

## Database Configuration

This application uses Aiven.io for MySQL database hosting. To configure your database connection:

1. Create a MySQL database service on [Aiven.io](https://aiven.io/)

2. From your Aiven console, obtain the following credentials:
   - Database host/endpoint
   - Database name
   - Username
   - Password
   - Port (usually 3306)
   - Download the SSL certificate (ca.pem) from the Overview page of your service

3. Create a `config.php` file in the root directory with the indicated structure