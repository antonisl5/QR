# Database Architectural Analysis

This document outlines the architectural choices made for the Custom QR Coupon Platform database schema, ensuring scalability, performance, and data integrity. The exact implementation is found in `database/schema.sql`.

## 1. Engine and Encoding
- **Engine**: We enforce the `InnoDB` storage engine for all tables. This provides critical transactional safety (ACID compliance) necessary for financial or high-value operations like coupon redemptions. It also provides row-level locking to prevent race conditions during high concurrent traffic.
- **Encoding**: We use `utf8mb4` encoding and `utf8mb4_unicode_ci` collation across all tables to fully support internationalization (including full Greek character support and emojis) without character corruption.

## 2. The Core State Machine: `coupons` Table
The coupon table represents the state machine directly.
- **`uuid` column**: Instead of auto-incrementing IDs which are predictable (vulnerable to IDOR and enumeration attacks), we use a secure `CHAR(36)` UUID to generate the physical QR code URLs.
- **`status` column**: We enforce a strict state machine (`idle`, `activated`, `confirmed`) using an `ENUM`. This strongly types the allowed values at the database level.
- **State Timestamps (`activated_at`, `confirmed_at`)**: We denormalize the timestamps into the `coupons` table directly, alongside `created_at` and `updated_at`. While the `coupon_events` table contains an exhaustive audit log, querying for aggregate statistics (e.g., "how many coupons were activated today?") is exponentially faster when joining against indexed columns on the primary entity.
- **Constraints & Indexes**: The `status` and `uuid` columns are indexed, as almost all queries will either look up a specific coupon by UUID or filter campaigns by status.

## 3. Auditability and History: `coupon_events` Table
This table acts as an append-only event sourcing log. It enforces auditability.
- **Event tracking**: Each state transition (`activated`, `confirmed`, `reset`) records a new row. This ensures we never lose the context of *who* or *what* triggered a state change.
- **Metadata for Security**: We record `ip_address` (`VARCHAR(45)` to support IPv6 natively) and `user_agent`. If abuse (e.g., rapid automated activations) occurs, this metadata is vital for identifying and blocking malicious actors.
- **`store_id` & `user_id`**: For confirmation events, we explicitly capture which store and which staff member processed the redemption. This provides the foundation for the "Store Confirmation Flow" and "Admin Dashboard & Analytics".

## 4. Normalization and Data Integrity
- **Foreign Keys**: We strictly map foreign keys with `ON DELETE` and `ON UPDATE` triggers.
  - `ON DELETE CASCADE` is used sparingly. For instance, if a `campaign` is deleted, its `coupons` and subsequent `coupon_events` should cascade-delete to prevent orphaned records.
  - `ON DELETE SET NULL` is used for lookup tables like `users.store_id` or `coupon_events.store_id`. If a store closes and is removed from the database, we must *not* lose the historical audit log of redemptions that happened there. The historical record remains intact, simply unlinked from the active store table.
- **User Roles & Stores**: The `users` table uses an `ENUM` for roles (`admin`, `campaign_owner`, `store_staff`). Standard staff members are linked to a specific `stores.id` so they can only confirm coupons for their own location.

## 5. Summary of Key Indexes
- `idx_coupon_uuid` on `coupons.uuid`: Essential for fast O(1) lookups during QR scanning.
- `idx_coupon_status` on `coupons.status`: Essential for dashboards filtering by state.
- `idx_event_coupon_type` on `coupon_events (coupon_id, event_type)`: Speeds up queries like "Find the IP address that triggered the activation for this specific coupon".
- `idx_campaign_dates` on `campaigns (start_date, end_date)`: Ensures the system can quickly filter out expired campaigns during validation.
