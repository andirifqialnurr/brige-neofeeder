# Audit and sensitive data

The normal row and dry-run APIs mask NIK (including parent NIK), NPWP, and phone
fields. Operators receive no original raw-row object. Payload construction,
fingerprints, stored staging, and outbound data retain the original values.
Numeric identifiers in error messages are masked where recognized. This is
field-based masking, not a guarantee that arbitrary free text contains no personal data.

Admins can open the original values from the row dialog with a selected purpose
(verification/correction). `POST .../rows/{id}/reveal` checks admin access and
batch membership, writes `import.row.sensitive_viewed` before returning data, and
uses `Cache-Control: no-store`. Password/token/secret fields remain hidden. Closing
the dialog discards revealed state; a later opening starts masked again.

`/audit` offers tenant-scoped pagination, event/subject search, date filters, and
CSV export. Operators see their own campus; admins can choose a campus or all.
The default period is 30 days. Export is limited to 10,000 matching entries,
escapes spreadsheet formula prefixes, and records `audit.exported`. Raw metadata,
IP addresses, user-agent strings, credentials and payloads are excluded.

Local verification 2026-09-15: privacy/audit tests passed (2 tests, 27 assertions);
inspection, dry-run and approval regression tests passed; frontend build/lint passed.
Browser validation uses synthetic demo data only.
