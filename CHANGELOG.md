# Changelog

All notable changes to SoundHeal will be documented in this file.
The format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/).

## [Unreleased]

### Added
- Initial scaffold of the SoundHeal Wellness Operating System
  - Native PHP 8.2+ modular architecture (no framework dependency)
  - MySQL schema with 14 tables + audit log + seed data
  - Public site (landing, experiences, sessions, membership, contact, auth)
  - Member sanctuary (dashboard, bookings, QR tickets, membership, profile, Aria AI chat)
  - Admin BOS (events CRUD, bookings, members, payments, QR check-in, content,
    testimonials, corporate CRM, reports)
  - API endpoints (Billplz create + webhook, OpenAI chat, QR validate,
    WhatsApp/Evolution webhook)
  - Tailwind + Alpine.js calm/cinematic UI
  - Hardened security (PDO, password_hash, CSRF, session, role guards,
    webhook signatures, .htaccess deny rules)
