# Charity Platform

<p align="center">
  A complete digital platform for managing charitable activities and connecting donors, beneficiaries, volunteers, and charity administrators in one system.
</p>

<p align="center">
  <img src="https://img.shields.io/badge/Laravel-12.x-FF2D20?logo=laravel&logoColor=white" alt="Laravel 12">
  <img src="https://img.shields.io/badge/PHP-8.2%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.2+">
  <img src="https://img.shields.io/badge/Database-MySQL-4479A1?logo=mysql&logoColor=white" alt="MySQL">
  <img src="https://img.shields.io/badge/Frontend-Vite%20%2B%20Tailwind%20CSS-646CFF?logo=vite&logoColor=white" alt="Vite and Tailwind CSS">
  <img src="https://img.shields.io/badge/License-MIT-green" alt="MIT License">
</p>

## Table of Contents

- [Overview](#overview)
- [Demo and Screenshots](#demo-and-screenshots)
- [Core Features](#core-features)
- [User Roles](#user-roles)
- [Technology Stack and Integrations](#technology-stack-and-integrations)
- [Requirements](#requirements)
- [Local Installation](#local-installation)
- [External Services Configuration](#external-services-configuration)
- [Useful Commands](#useful-commands)
- [API Overview](#api-overview)
- [Project Structure](#project-structure)
- [Testing](#testing)
- [Security](#security)
- [Contributing](#contributing)
- [License](#license)

## Overview

Charity is an API-first charitable management platform built with Laravel. It supports the complete charity workflow, from account registration and profile completion to campaigns, donations, sponsorships, aid applications, notifications, reporting, and digital receipts.

The platform is designed to:

- Help donors discover campaigns and make one-time or recurring donations.
- Organize beneficiary aid applications and scheduled visits.
- Manage volunteer opportunities, tasks, evaluations, certificates, and points.
- Manage sponsorships, sponsorship payments, and communication between sponsors and beneficiaries.
- Give administrators centralized control over users, campaigns, donations, beneficiaries, and volunteer operations.
- Provide notifications, real-time conversations, reports, and PDF donation receipts.

## Demo and Screenshots

### Live Demo

> The public deployment URL will be added here after the production environment is published.

- **Live application:** `https://your-demo-url.com`
- **API base URL:** `https://your-demo-url.com/api`
- **Local application:** `http://127.0.0.1:8000`

### Main Screens

Replace the placeholder image paths below with screenshots from the deployed application. Keeping screenshots inside `docs/screenshots/` makes them easy to maintain and version with the project.

<p align="center">
  <img src="docs/screenshots/home.png" alt="Charity platform home page" width="48%">
  <img src="docs/screenshots/campaigns.png" alt="Campaigns listing" width="48%">
</p>

<p align="center">
  <img src="docs/screenshots/donor-dashboard.png" alt="Donor dashboard" width="48%">
  <img src="docs/screenshots/admin-dashboard.png" alt="Administration dashboard" width="48%">
</p>

### Suggested Demo Flow

The following flow demonstrates the main platform capabilities:

1. Register a new user and complete the required profile.
2. Browse featured campaigns and filter them by category.
3. Create a one-time, gift, or recurring donation.
4. View the donation status and download the generated PDF receipt.
5. Create or review a sponsorship and inspect its payment history.
6. Sign in as a beneficiary and submit an aid application.
7. Sign in as a volunteer, request a task, check in, and review earned points.
8. Open the administration reports to review donations, beneficiaries, and volunteers.

### Demo Credentials

For security reasons, real credentials should not be stored in this repository. Add temporary demo accounts to your deployment documentation or hosting platform instead:

| Account | Email | Password |
| --- | --- | --- |
| Donor | `demo-donor@example.com` | `Use-a-secure-demo-password` |
| Beneficiary | `demo-beneficiary@example.com` | `Use-a-secure-demo-password` |
| Volunteer | `demo-volunteer@example.com` | `Use-a-secure-demo-password` |
| Administrator | `demo-admin@example.com` | `Use-a-secure-demo-password` |

## Core Features

### Donations and Campaigns

- Create, update, view, and categorize campaigns.
- Feature urgent and highlighted campaigns.
- Support one-time, gift, and recurring donations.
- Manage a donation cart and a detailed donor donation history.
- Provide donation statistics and downloadable PDF receipts.
- Export donation data to Excel files.
- Support Stripe payments and additional payment integrations through environment configuration.

### Beneficiaries and Aid Applications

- Create and complete beneficiary profiles.
- Submit aid applications and track their statuses and statistics.
- Schedule and manage beneficiary visits.
- Organize needs, cities, categories, and other reference data.
- Manage beneficiary records and approval statuses through administrative endpoints.

### Volunteering

- Manage volunteer profiles, domains, skills, languages, and availability days.
- Create and assign volunteer tasks.
- Track task start and completion requests.
- Record check-ins and evaluate completed tasks.
- Award volunteer points, badges, certificates, and leaderboard positions.

### Sponsorships and Communication

- Display beneficiaries available for sponsorship.
- Create, update, and manage sponsorships.
- Track sponsorship payments.
- Exchange messages between sponsors and beneficiaries.
- Support individual and group conversations with read status and typing indicators.

### Notifications and Reporting

- Provide in-app notifications with unread counts and read-status management.
- Manage notification preferences and Firebase Cloud Messaging tokens.
- Generate reports for donations, payment sources, categories, top donors, beneficiaries, and volunteers.
- Maintain audit logs and login logs for important activities.

## User Roles

The application supports several user types and administrative roles:

| Role | Main Responsibilities |
| --- | --- |
| Donor | Browse campaigns, donate, view donation history and receipts, and manage sponsorships |
| Beneficiary | Complete a profile, submit aid applications, and manage visits |
| Volunteer | Browse tasks, request assignments, check in, and receive evaluations and certificates |
| Administrator | Manage users, campaigns, donations, beneficiaries, volunteers, and tasks |
| Manager / Accountant / Viewer | Specialized administrative access based on operational permissions |

## Technology Stack and Integrations

- **Backend:** PHP 8.2+ and Laravel 12.
- **Authentication:** Laravel Sanctum with OTP-based verification flows.
- **Database:** MySQL for local development and SQLite in-memory databases for tests.
- **Frontend assets:** Vite, Tailwind CSS, and Axios.
- **Payments:** Stripe, with PayerURL and cryptocurrency checkout packages available.
- **Notifications:** Firebase Cloud Messaging.
- **Real-time features:** Pusher and broadcast events for messaging, read status, and typing indicators.
- **Documents:** Dompdf and mPDF for PDF receipts.
- **Exports:** Laravel Excel for data exports.
- **Social login:** Laravel Socialite with Google and Facebook support when credentials are configured.

## Requirements

Install the following before running the project:

- PHP `8.2` or newer with the `gd`, `pdo_mysql`, and `zip` extensions.
- Composer 2.
- Node.js and npm.
- MySQL 8 or a compatible MariaDB version.
- Credentials for external services when payments, notifications, email, or social login are enabled.

## Local Installation

### 1. Clone the Repository

```bash
git clone https://github.com/ThaerGhaderi/Project-Charity.git
cd Project-Charity
```

### 2. Install Dependencies

```bash
composer install
npm install
```

### 3. Configure the Environment

```bash
cp .env.example .env
php artisan key:generate
```

On Windows PowerShell:

```powershell
Copy-Item .env.example .env
php artisan key:generate
```

### 4. Configure the Database

Create a database named `project_charity`, or choose another database name and update the `DB_*` values in `.env`:

```dotenv
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=project_charity
DB_USERNAME=root
DB_PASSWORD=
```

Run the migrations:

```bash
php artisan migrate
```

To load local development data:

```bash
php artisan db:seed
```

> Do not run `migrate:fresh --seed` against a database containing important data. It drops all tables before recreating them.

### 5. Link Storage and Build Frontend Assets

```bash
php artisan storage:link
npm run build
```

### 6. Run the Application

Start the HTTP server:

```bash
php artisan serve
```

Open `http://127.0.0.1:8000` in your browser.

During development, you can run the application server, queue listener, log viewer, and Vite together:

```bash
composer run dev
```

## External Services Configuration

Keep all credentials in `.env` and never commit them to GitHub.

### Stripe

```dotenv
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
STRIPE_CURRENCY=usd
```

Configure your Stripe webhook to point to:

```text
POST /api/stripe/webhook
```

### Google and Facebook OAuth

```dotenv
GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=
FACEBOOK_CLIENT_ID=
FACEBOOK_CLIENT_SECRET=
FACEBOOK_REDIRECT_URI=
```

### Firebase and Pusher

Firebase requires a service-account configuration referenced by the Firebase settings. Pusher requires application credentials and a cluster:

```dotenv
PUSHER_APP_ID=
PUSHER_APP_KEY=
PUSHER_APP_SECRET=
PUSHER_APP_CLUSTER=
```

Review the files in `config/` for additional variables related to email, PayerURL, Firebase, broadcasting, and cloud storage.

## Useful Commands

| Command | Description |
| --- | --- |
| `composer run setup` | Install dependencies, create the environment, run migrations, and build frontend assets |
| `composer run dev` | Run the complete local development environment |
| `php artisan migrate` | Apply database migrations |
| `php artisan db:seed` | Load development seed data |
| `php artisan route:list` | Display all registered application routes |
| `php artisan config:clear` | Clear cached configuration |
| `npm run dev` | Run Vite in development/watch mode |
| `npm run build` | Build frontend assets for production |
| `composer test` | Run the PHPUnit test suite through Laravel |

## API Overview

API routes are defined in [`routes/api.php`](routes/api.php). Laravel automatically applies the `/api` prefix.

| Group | Example Endpoints |
| --- | --- |
| Authentication | `/api/auth/register`, `/api/auth/login`, `/api/auth/verify-otp` |
| Beneficiaries | `/api/beneficiary/profile`, `/api/beneficiary/aid-applications` |
| Donors | `/api/donor/campaigns`, `/api/donor/donations` |
| Sponsorships | `/api/sponsorships`, `/api/sponsorships/{id}/payments` |
| Notifications | `/api/notifications` |
| Conversations | `/api/chat/conversations` |
| Reports | `/api/reports/general`, `/api/reports/donations` |
| Administration | Campaign, beneficiary, volunteer, task, and donation management endpoints |

Protected endpoints use Laravel Sanctum. Include the token returned after login in subsequent requests:

```http
Authorization: Bearer <token>
Accept: application/json
```

To view the complete list of endpoints, HTTP methods, and middleware:

```bash
php artisan route:list --path=api
```

## Project Structure

```text
app/
├── Http/Controllers/   # Web and API request handling
├── Http/Requests/      # Request validation
├── Models/             # Eloquent models and relationships
├── Services/           # Business services and integrations
├── Events/             # Broadcast and real-time events
└── Exports/            # Data export classes
database/
├── migrations/         # Database schema migrations
├── factories/          # Test and development factories
└── seeders/            # Development seed data
routes/
├── api.php             # REST API routes
├── web.php             # Web and payment result routes
└── console.php         # Artisan command routes
resources/
├── views/              # Blade templates, payment pages, and receipts
├── css/                # Tailwind styles
└── js/                 # Vite and Axios entry points
config/                 # Laravel and integration configuration
tests/                  # Unit and feature tests
```

## Testing

The project uses PHPUnit. The `phpunit.xml` configuration uses an in-memory SQLite database for tests:

```bash
php artisan test
```

Or:

```bash
composer test
```

Before opening a pull request, run the tests and frontend build, and verify that the migrations work on a clean database:

```bash
php artisan test
npm run build
```

## Security

- Never commit `.env`, Stripe, Firebase, OAuth, SMTP, or other service credentials.
- Rotate any credentials that have been exposed outside a secure secrets manager.
- Set `APP_DEBUG=false` in production.
- Use HTTPS in production.
- Configure `APP_URL`, `FRONTEND_URL`, and CORS settings for the deployment environment.
- Protect payment webhooks and verify Stripe and PayerURL signatures before processing payments.
- Report security vulnerabilities privately to the project maintainers instead of publishing exploitable details in a public issue.

## Contributing

Contributions are welcome:

1. Fork the repository.
2. Create a focused branch, such as `feature/recurring-donations`.
3. Implement the change with appropriate tests.
4. Run `php artisan test` and `npm run build`.
5. Open a pull request describing the problem, solution, and any configuration changes.

## License

This project is licensed under the **MIT License**, according to the current package configuration. Add an official `LICENSE` file to the repository before publishing the public release if one does not already exist.

---

Built to help create a greater charitable impact.
