# SoundHeal — Wellness Operating System

A native PHP modular platform for sound healing studios, boutique wellness brands, and SME operators. Built to feel like *Calm* and an Aman resort — and to run happily on Hostinger shared hosting or a small VPS.

> Membership ecosystem · Event booking with QR ticketing · AI wellness concierge · Billplz payments · Audio sanctuary · Corporate wellness CRM.

---

## Stack

- **PHP 8.2+** (no framework, intentionally)
- **MySQL 8 / MariaDB 10.4+**
- **Tailwind (CDN)** + **Alpine.js** for soft interactions
- **Billplz** for payments
- **OpenAI** for the wellness concierge (graceful fallback if not configured)
- **Evolution API / WhatsApp Cloud API** ready
- **PHPMailer-friendly** mail config

## Folder structure

```
config/        db, app, payment, ai
includes/      bootstrap, auth, csrf, session, role, header/nav/footer
public/        landing, sessions, membership, contact, login, register
member/        dashboard, bookings, tickets, membership, journey, profile, checkout
admin/         dashboard, events, bookings, members, payments, checkin, content,
               testimonials, corporate_leads, reports
api/           billplz_create, billplz_webhook, ai_chat, qr_validate, whatsapp_webhook
assets/        css, js
database/      schema.sql + seed
uploads/ qr/ logs/
```

## Local setup (Hostinger or local LAMP)

1. Create a MySQL database, then import the schema:
   ```bash
   mysql -u root -p soundheal < database/schema.sql
   ```
2. Copy `.env.example` to `.env` (or set env vars in hPanel) and fill in DB + service credentials.
3. Point your web root at the project root (`index.php` redirects to `public/`).
4. Make `uploads/`, `qr/`, and `logs/` writable by PHP:
   ```bash
   chmod -R 775 uploads qr logs
   ```
5. Sign in to `/admin/dashboard.php`.

> **Default admin** seeded by `schema.sql`: `admin@soundheal.local` — set a real password by re-hashing through `php -r "echo password_hash('your-password', PASSWORD_DEFAULT);"` and updating the `users.password_hash` row before launch.

## Modules

| Module | Status |
| --- | --- |
| Public website | ✅ |
| Membership system (plans, subscribe) | ✅ scaffolded |
| Event booking + QR tickets | ✅ scaffolded |
| QR check-in (admin scanner) | ✅ |
| Billplz payments + webhook | ✅ stub-ready |
| AI wellness concierge (OpenAI + fallback) | ✅ |
| WhatsApp / Evolution API webhook | ✅ stub-ready |
| Audio content library | ✅ |
| Testimonials | ✅ |
| Corporate inquiries CRM | ✅ |
| Reports | ✅ |
| Audit logs | ✅ |

## Security

- Sessions: hardened cookies (`HttpOnly`, `SameSite=Lax`, optional `Secure`), strict mode, periodic ID rotation.
- CSRF: `csrf_field()` + `csrf_verify()` on every state-changing form / AJAX call.
- Auth: `password_hash()` + `password_verify()`, automatic rehash on algo upgrade.
- Roles: `admin`, `staff`, `member`, `guest` — enforced via `require_role(...)`.
- SQL: 100% prepared statements via PDO.
- Webhooks: Billplz `x_signature` HMAC verification; WhatsApp shared-secret header check.
- Apache: `.htaccess` denies direct access to `config/`, `includes/`, `logs/`, `database/`.
- Audit logs: every meaningful action recorded in `audit_logs`.

## Cron jobs (Hostinger → cPanel → Cron)

Suggested cadence:
```
*/30 * * * *  /usr/bin/php /home/USER/public_html/cron/membership_renewal.php
0    9 * * *  /usr/bin/php /home/USER/public_html/cron/session_reminders.php
```
*(Cron scripts not yet wired — slot them under a future `/cron` folder.)*

## Tone

Aria, the AI concierge, is intentionally soft. She:
- Acknowledges feeling before recommending.
- Suggests at most two experiences per reply.
- Always closes wellbeing answers with: *"This is not medical advice. Please consult qualified professionals for medical or mental health concerns."*

Update her behaviour in `config/ai.php` → `persona.system_prompt`.

## License

Proprietary — © SoundHeal. All rights reserved.
