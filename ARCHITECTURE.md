# Gym Management — Architecture

## 1. Architectural Style

The application is a pragmatic Modular Monolith.

The system is not initially designed as:

- microservices;
- distributed services;
- event-driven microservices;
- full Clean Architecture;
- full Hexagonal Architecture.

The architecture should remain simple while keeping business responsibilities separated.

---

# 2. Technology

Initial technology:

- Laravel
- PHP
- PostgreSQL
- Redis
- Docker
- Filament
- Blade
- Tailwind CSS
- Alpine.js
- Pest/PHPUnit

---

# 3. Application Structure

The application is organized around business modules.

Initial modules:

- Users
- Clients
- Trainers
- Plans
- Memberships
- Payments
- Scheduling
- Bookings
- Attendance
- Exercises
- Routines

The exact directory structure may evolve after the first architectural implementation.

---

# 4. Layers

When a feature requires explicit separation, use:

Presentation
↓
Application
↓
Domain
↓
Infrastructure

However, these layers are conceptual boundaries, not a requirement to create folders and interfaces for every operation.

Simple Laravel CRUD may use normal Laravel conventions.

Complex business operations should use explicit Actions / Use Cases.

---

# 5. Presentation

The application initially has two presentation contexts:

## Administration

Primarily implemented using Filament.

Used by:

- administrators;
- trainers.

## Client Portal

Implemented using Laravel web technologies.

Used by:

- clients.

## Public Website

Contains:

- landing page;
- plans;
- information;
- registration;
- login.

The public website and authenticated application share the same backend.

---

# 6. Controllers

Controllers should:

- receive requests;
- authorize;
- validate or delegate validation;
- invoke application behavior;
- return responses.

Controllers should not contain complex business rules.

---

# 7. Actions / Use Cases

Important business operations should be represented explicitly when doing so improves clarity.

Examples:

- CreateClient
- RegisterPayment
- CreateMembership
- BookSession
- CancelBooking
- AssignRoutine
- RecordWorkout

Actions should coordinate business behavior rather than becoming generic service classes.

---

# 8. Models

Eloquent models represent persisted business data.

Models may contain simple domain behavior.

Do not turn models into giant business objects containing unrelated operations.

---

# 9. Repositories

Repositories are NOT mandatory.

Use Eloquent directly unless a repository provides a concrete benefit.

Do not create:

ClientRepository
PaymentRepository
BookingRepository

merely because a pattern recommends them.

---

# 10. Events

Events may be used when an operation produces secondary effects.

Examples:

- MembershipPaid
- BookingCreated
- BookingCancelled
- PaymentRegistered

Events should not replace straightforward synchronous business logic without a reason.

---

# 11. Queues

Redis and Laravel queues may be used for:

- notifications;
- email;
- external API calls;
- report generation;
- other slow operations.

Do not queue operations that need immediate consistency unless there is a clear design for it.

---

# 12. Authentication

Authentication is handled through Laravel.

Initial roles:

- ADMIN
- TRAINER
- CLIENT

Authorization should use Laravel Policies and/or a suitable permission mechanism.

The client must never be able to access another client's private information.

---

# 13. Payments

Payment processing is separated conceptually from membership state.

A Payment represents a financial transaction.

Mercado Pago is an external payment provider.

The domain should not depend directly on Mercado Pago-specific concepts when avoidable.

---

# 14. Memberships

Plans define what the gym offers.

Memberships represent a client's subscription/contract to a plan.

Payments represent money received or processed.

These concepts must remain separate.

---

# 15. Scheduling

The current gym primarily operates as a free-weight gym.

The architecture should support future:

- individual sessions;
- group sessions;
- classes;
- capacity limits.

The MVP does not require full class management.

---

# 16. Routines

Separate:

### Prescription

What the trainer assigns.

### Execution

What the client actually performs.

The client's recorded weights/repetitions must not modify the prescribed routine.

---

# 17. Multi-location

The MVP has one location.

The architecture should not make future multi-location support impossible.

Full multi-location functionality is not implemented initially.

---

# 18. Multi-tenancy

The MVP represents one gym.

Multi-tenancy is not implemented initially.

Do not introduce tenant infrastructure until it is an explicit requirement.

---

# 19. API

The initial product is web-based.

Do not build a complete public API speculatively.

Introduce API endpoints when required by:

- client applications;
- mobile applications;
- external integrations;
- public functionality.

---

# 20. Guiding Principle

Use the simplest architecture that correctly represents the domain.

Architecture exists to reduce complexity, not to demonstrate patterns.
