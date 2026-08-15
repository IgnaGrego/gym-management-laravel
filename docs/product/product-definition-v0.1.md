# Product Definition — Gym Management MVP

## Document Status

Version: 0.1

Status: Draft

Owner: Product

This document represents the current product definition.

It is the source of truth for the initial MVP scope.

Changes to business behavior must be reflected here or in an approved specification.

---

## Overview

A gym management system that supports the daily operations of a gym and its interaction with clients and trainers.

## Goals

- Centralize the management of clients, trainers, memberships and payments.
- Support scheduling, bookings and attendance tracking.
- Provide personalized routines and exercises.
- Offer a public website and client access.
- Provide an administration panel for staff.

## Users and Roles

Initial roles:

- ADMIN
- TRAINER
- CLIENT

- System users (staff) who administer the gym.
- Clients who access the gym and their own information.
- Trainers who create and manage routines and schedules.

## Business Areas

| Area | Description |
| --- | --- |
| Users | Authentication and administration of system users. |
| Clients | People who attend the gym. |
| Trainers | Staff who train clients and design routines. |
| Plans | The offers the gym provides (what the gym sells). |
| Memberships | A client's subscription/contract to a plan. |
| Payments | Financial transactions related to memberships. |
| Scheduling | Organization of classes and trainer availability. |
| Bookings | Reservation of spots in scheduled activities. |
| Attendance | Control of gym access and class presence. |
| Exercises | Catalogue of individual exercises. |
| Routines | Personalized exercise plans assigned to clients. |

## Out of Scope

- Mobile applications (an API may be introduced later if required).
- Full multi-tenancy (multiple gyms) is not implemented yet.
- Full multi-location support (the MVP has one location).
- Complete public website content.

## Open Questions

The following product details are not yet defined and must be clarified through requirements analysis:

- Are memberships billed automatically or manually?
- What happens when a booking is missed or cancelled?
- Is attendance linked to class bookings, general gym access, or both?
- Are routines versioned over time?
- Does the public website require online booking or membership purchases?

These questions must be answered before their related features are specified.
