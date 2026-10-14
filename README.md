<p align="center"><a href="https://laravel.com" target="_blank"><img src="https://raw.githubusercontent.com/laravel/art/master/logo-lockup/5%20SVG/2%20CMYK/1%20Full%20Color/laravel-logolockup-cmyk-red.svg" width="400" alt="Laravel Logo"></a></p>

<p align="center">
<a href="https://github.com/laravel/framework/actions"><img src="https://github.com/laravel/framework/workflows/tests/badge.svg" alt="Build Status"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/dt/laravel/framework" alt="Total Downloads"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/v/laravel/framework" alt="Latest Stable Version"></a>
<a href="https://packagist.org/packages/laravel/framework"><img src="https://img.shields.io/packagist/l/laravel/framework" alt="License"></a>
</p>

## About Laravel

Laravel is a web application framework with expressive, elegant syntax. We believe development must be an enjoyable and creative experience to be truly fulfilling. Laravel takes the pain out of development by easing common tasks used in many web projects, such as:

- [Simple, fast routing engine](https://laravel.com/docs/routing).
- [Powerful dependency injection container](https://laravel.com/docs/container).
- Multiple back-ends for [session](https://laravel.com/docs/session) and [cache](https://laravel.com/docs/cache) storage.
- Expressive, intuitive [database ORM](https://laravel.com/docs/eloquent).
- Database agnostic [schema migrations](https://laravel.com/docs/migrations).
- [Robust background job processing](https://laravel.com/docs/queues).
- [Real-time event broadcasting](https://laravel.com/docs/broadcasting).

Laravel is accessible, powerful, and provides tools required for large, robust applications.

## Telemedicine application

This application provides a patient, doctor, and administrator telemedicine workflow:

- User accounts use Laravel session authentication and the `patient`, `doctor`, and `admin` roles.
- Patients and doctors can update their profiles. Doctors can publish recurring weekly opening hours; patients request 30-minute appointments from available slots in the application timezone (UTC by default).
- Patients and doctors can cancel pending or upcoming appointments before their start time. In-app notifications report appointment requests, status changes, cancellations, and new messages.
- Appointment consultations embed the Jitsi Meet IFrame API. The default `meet.jit.si` community service needs no API key; set `JITSI_DOMAIN` in `.env` to use another Jitsi deployment.
- Appointments connect patient and doctor profiles, and prescriptions and medical records can be linked to completed consultations.
- Clinical notes, prescription details, appointment reasons, patient contact details, and meeting URLs use Laravel encrypted Eloquent casts. Keep `APP_KEY` and database backups secure; encrypted values depend on that key.
- Bootstrap is bundled through Vite. Messaging is text-only and tied to an appointment.

**Video privacy notice:** the default public Jitsi service is for demos only. This application does not configure a HIPAA-compliant provider, business associate agreement, service-level agreement, or private Jitsi infrastructure. Do not use the public service for real patient care or discuss sensitive health information there. The app restricts access to the room page and creates a random room name, but the shared public room itself is not protected by this application's login. Production telehealth requires a provider and deployment reviewed for applicable privacy and regulatory obligations.

Notifications are in-app only; no email or SMS provider is configured. Payments, insurance processing, lab testing, pharmacy inventory, and automated diagnosis are not implemented.

Set the MySQL connection in `.env`, then apply the migrations and build frontend assets:

```sh
php artisan migrate
npm run build
```

Sign in at `/login`. The first administrator must be provisioned by an operator; there is no public registration route. Administrators can then create patient, doctor, and administrator accounts through `POST /admin/users`. Patient and doctor resource endpoints are session-authenticated, role-restricted routes in `routes/web.php` and support browser pages and JSON responses.

### Local browser journey

Apply outstanding migrations and seed the three repeatable demo accounts:

```sh
php artisan migrate
php artisan db:seed
npm run build
```

Open `http://localhost:8000/login` (or the URL configured in `APP_URL`) and use `password123` with one of:

| Role | Email |
| --- | --- |
| Administrator | `admin@telemed.test` |
| Doctor | `doctor@telemed.test` |
| Patient | `patient@telemed.test` |

The demo doctor is intentionally awaiting approval and has weekday hours from 09:00 to 17:00 UTC. Sign in as the administrator and approve the profile first. The patient can then choose an available 30-minute slot; the doctor can approve it, message the patient, join a Jitsi demo call, complete the consultation, and add clinical notes and a prescription. Patients and doctors can edit their profiles, manage notifications, and cancel future appointments.

`DatabaseSeeder` is safe to rerun for these three demo email addresses and does not truncate the database. Change or remove the demo passwords before exposing a non-local environment.

## Learning Laravel

Laravel has the most extensive and thorough [documentation](https://laravel.com/docs) and video tutorial library of all modern web application frameworks, making it a breeze to get started with the framework.

In addition, [Laracasts](https://laracasts.com) contains thousands of video tutorials on a range of topics including Laravel, modern PHP, unit testing, and JavaScript. Boost your skills by digging into our comprehensive video library.

You can also watch bite-sized lessons with real-world projects on [Laravel Learn](https://laravel.com/learn), where you will be guided through building a Laravel application from scratch while learning PHP fundamentals.

## Agentic Development

Laravel's predictable structure and conventions make it ideal for AI coding agents like Claude Code, Cursor, and GitHub Copilot. Install [Laravel Boost](https://laravel.com/docs/ai) to supercharge your AI workflow:

```bash
composer require laravel/boost --dev

php artisan boost:install
```

Boost provides your agent 15+ tools and skills that help agents build Laravel applications while following best practices.

## Contributing

Thank you for considering contributing to the Laravel framework! The contribution guide can be found in the [Laravel documentation](https://laravel.com/docs/contributions).

## Code of Conduct

In order to ensure that the Laravel community is welcoming to all, please review and abide by the [Code of Conduct](https://laravel.com/docs/contributions#code-of-conduct).

## Security Vulnerabilities

If you discover a security vulnerability within Laravel, please send an e-mail to Taylor Otwell via [taylor@laravel.com](mailto:taylor@laravel.com). All security vulnerabilities will be promptly addressed.

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).
