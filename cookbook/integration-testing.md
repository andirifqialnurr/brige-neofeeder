# Integration tests without Neo Feeder credentials

Run `bun run test:integration` against a disposable/local MySQL and Redis service.
The runner creates a random `bridge_integration_<uuid>` database, runs migrations,
uses a unique Redis prefix, and removes only its own database/keys afterwards.
It never uses the application's database name or sends requests to Neo Feeder.

Optional environment variables: `INTEGRATION_MYSQL_HOST`, `INTEGRATION_MYSQL_PORT`,
`INTEGRATION_MYSQL_USER`, `INTEGRATION_MYSQL_PASSWORD`, `INTEGRATION_REDIS_HOST`,
`INTEGRATION_REDIS_PORT`. Defaults: loopback, ports 3306/6379, MySQL root with no
password. Use a disposable service; the MySQL account needs CREATE/DROP DATABASE.
Redis in this harness is a local unauthenticated test service.

The loopback HTTP simulator logs action names only. Real Laravel HTTP controllers
run in separate PHP processes. A Redis barrier releases concurrent requests;
independent `queue:work` processes consume real Redis jobs and use MySQL row locks.

Covered assertions:

- Simultaneous start requests produce one attempt; two workers make one write.
- Simultaneous manual retry requests reuse one retry intent and make one write.
- HTTP timeout after POST becomes `unknown`; API retry and duplicate jobs cannot resend it.
- A worker killed after the simulator receives POST recovers to `unknown` and never resends.
  The recovery cutoff is advanced in the fixture instead of waiting ten minutes.

## Verification — 2026-09-15

Local Windows: PHP 8.4.0, MySQL 8.4.3, Redis 5.0.14.1: **4 tests, 62 assertions passed**.
The application deployment specifies Redis 7; this local result does not certify
that version or Linux process-timeout behavior. No real Neo Feeder or VPS was contacted.
