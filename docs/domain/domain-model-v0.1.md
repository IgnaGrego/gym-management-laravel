# Domain Model v0.1

## Purpose

This document describes the initial business domain.

It is not a database schema.

It should be used to understand business concepts before designing persistence.

---

# Core Concepts

## User

An authenticated identity.

A User may have one or more roles.

Initial roles:

- ADMIN
- TRAINER
- CLIENT

---

## Client

A person who uses the gym's services.

A Client may have:

- memberships;
- payments;
- bookings;
- attendance records;
- routines;
- workout records.

---

## Trainer

A gym professional who may be assigned clients.

A Trainer may:

- create routines;
- assign routines;
- review workout progress.

---

## Plan

A product/service offered by the gym.

Examples:

- monthly gym membership;
- personal training;
- other future plans.

A Plan defines commercial characteristics.

---

## Membership

A client's enrollment in a Plan for a specific period.

A Client may have multiple Membership records over time.

A Client may also have more than one active Membership if the business permits it.

---

## Payment

A financial transaction related to a Membership.

A Payment records money received or processed.

---

## Schedule

An organized set of planned activities or trainer availability.

---

## Session

A scheduled activity that a Client can book.

A Session belongs to a Schedule.

---

## Booking

A reservation made by a Client for a Session.

---

## Attendance

A record that a Client accessed the gym or attended a Session.

---

## Exercise

A single exercise that can be included in routines.

---

## Routine

A plan of exercises assigned to a Client, typically created by a Trainer.

A Routine is organized in days.

---

## RoutineDay

A day within a Routine.

---

## RoutineExercise

An exercise assigned within a RoutineDay.

A RoutineExercise defines the prescription:

- sets;
- repetitions;
- weight;

For example:

60 kg × 10
60 kg × 10
62.5 kg × 8
62.5 kg × 8

---

## Workout Log

The record of what the Client actually performed.

A Workout Log references the performed RoutineExercise or Exercise.

The Workout Log must not alter the Routine prescription.

---

# Important Domain Relationships

Conceptually:

```
User
 ├── Client
 └── Trainer

Client
 ├── Membership
 ├── Payment
 ├── Booking
 ├── Attendance
 ├── Routine
 └── WorkoutLog

Trainer
 ├── Client assignments
 └── Routine

Plan
 └── Membership

Membership
 └── Payment

Session
 └── Booking

Routine
 └── RoutineDay
       └── RoutineExercise
             └── Exercise

WorkoutLog
 └── performed RoutineExercise / Exercise
```

These are conceptual relationships.

The final database design must be defined by the Architect.
