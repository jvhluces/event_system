# Event Approval, Notifications, and Reports

## Database update

Back up `ipt2_activity`, then select it in phpMyAdmin and run the SQL in
[`database/migrations/20261110_event_approval_notifications.sql`](../database/migrations/20261110_event_approval_notifications.sql).

The existing application already uses `events.status` for the event schedule
(`Upcoming`, `Ongoing`, `Ended`). The approval workflow therefore uses a
separate `events.approval_status` (`pending`, `approved`, `rejected`) so
moderation does not overwrite the schedule state. Existing events are marked
approved by the migration to keep them visible to the audience. New and edited
organizer events return to pending review.

The migration adds `end_date` and initializes old events to two hours after
their start time. New event forms require an end time after the start time.
The existing notifications table is upgraded from `notification_id` to `id`
and gains the `link` column and a foreign key to `users`.

## Installation steps

1. Back up the database.
2. Select database `ipt2_activity` in phpMyAdmin.
3. Run the migration SQL once. The migration is intended for the current
   project schema and its events and notifications tables.
4. Upload/deploy the changed PHP and CSS files with the project.
5. Open **Admin → Event Approval**. Approve an event or reject it with a
   reason. The organizer receives an in-app notification.
6. Confirm the event appears in the audience event listing after approval.
7. Admin CSV export works without additional packages. For PDF export, from
   the project root run `composer require tecnickcom/tcpdf`; `vendor/autoload.php`
   must be available to the PHP application.

## System flow

1. **Organizer creates an event.** The event is saved with
   `approval_status = 'pending'`, schedule dates, and organizer ownership.
2. **Admin reviews it.** Admin sees pending, approved, and rejected events.
   Approve/reject changes only the approval state. Rejection requires a reason
   stored in `admin_remarks`.
3. **Organizer receives a notification.** The notification is linked to the
   organizer's user ID and is visible in the bell menu. Opening it marks it
   read.
4. **Organizer checks status.** The dashboard shows Pending, Approved, or
   Rejected and displays admin remarks for rejected submissions. Editing an
   event sends it back to pending review.
5. **Audience discovers and joins.** The event feed and event details expose
   only approved events. Join actions also verify approval on the server.
6. **Audience reviews.** Reviews follow the existing participation and event
   completion rules. The schedule badge is calculated from start/end times,
   not the approval state.
7. **Admin exports a report.** CSV contains events, approval and schedule
   statuses, capacities, registered participant counts, and participant names.
   PDF uses the same report data through TCPDF.

## Data Flow Diagram description

### Level 0

The Event Management System receives organizer event submissions, admin
approval decisions, and audience registration/review actions. It stores event,
user, participation, review, and notification data in MySQL and returns
role-specific dashboards and admin reports.

### Level 1

- **Organizer → Event Submission:** validate event data, store in `events` as
  pending, and show approval status to its owner.
- **Admin → Moderation:** read event and organizer data, update approval and
  remarks, and write an in-app notification to the organizer.
- **Audience → Discovery/Join:** query only approved events, create a row in
  `event_participants` after server-side approval and capacity checks.
- **Audience → Review:** verify participant/event completion rules before
  writing a review.
- **Admin → Reporting:** aggregate events and participant rows, then stream a
  CSV or TCPDF PDF download.

## ER diagram updates

```text
users (user_id PK)
  1 ───────< events (organizer_id FK)
  1 ───────< event_participants (user_id FK)
  1 ───────< notifications (user_id FK, ON DELETE CASCADE)

events (event_id PK)
  1 ───────< event_participants (event_id FK)
  1 ───────< event_reviews (event_id FK, existing relationship)

events additions:
  approval_status ENUM('pending','approved','rejected')
  admin_remarks VARCHAR(255) NULL
  end_date DATETIME NULL

notifications:
  id PK, user_id FK, message, link, is_read, created_at
```

## Reusable schedule statuses

`includes/event_helpers.php` provides `event_lifecycle_status()` and
`event_lifecycle_badge()`. The rule is:

- Start time in the future: **Upcoming** (green)
- Start time has passed and end time has not: **Ongoing** (blue)
- End time has passed: **Ended** (gray)

`event_approval_badge()` separately displays moderation status.

## Feature list

- Admin event approval/rejection with required rejection remarks.
- Organizer-facing approval state and rejection feedback.
- Audience listings, details, and join actions restricted to approved events.
- Schedule-derived Upcoming/Ongoing/Ended badges.
- Per-user unread notification counts, notification list, and mark-read actions.
- Admin CSV/Excel-compatible and optional TCPDF PDF event reports.
- Prepared SQL statements for new writes, filters, and exports.
