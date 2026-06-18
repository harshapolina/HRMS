HR Punch Load Testing
=====================

Goal
----
Find the breaking point before 10,000 simultaneous punch-ins. Start with 500
virtual users, then increase once the server stays under 5% failure rate.

Option A: k6 (recommended)
--------------------------
1. Install k6: https://k6.io/docs/get-started/installation/
2. Log in as a test employee in the browser and copy the HRSESSID cookie.
3. Set environment variables:

   PowerShell:
     $env:BASE_URL="https://YOUR-DOMAIN/incentiveapp_integration/userlogin1/hrlogin"
     $env:SESSION_COOKIE="HRSESSID=paste_value_here"

4. Run from this folder:
     k6 run punch_load_test.js

5. Read stdout for p95 latency, punch_success, punch_errors.

Notes:
- One session cookie simulates one user repeatedly. For realistic multi-user
  tests, generate many employee sessions on staging or extend the script with
  a CSV of cookies.
- Run against STAGING only. punch_in is idempotent per day per user.

Option B: PowerShell smoke test (no k6)
---------------------------------------
From repo root:

  powershell -File loadtests/run_smoke_load.ps1 `
    -BaseUrl "https://YOUR-DOMAIN/.../hrlogin" `
    -SessionCookie "HRSESSID=..." `
    -Concurrent 50 `
    -Requests 200

Option C: Capacity diagnostics on server
----------------------------------------
1. Log in as HR admin.
2. Open capacity_diagnostics.php in the browser.
3. Record max_connections, threads_connected, and index status.

Interpreting results
--------------------
- punch_fail_rate > 5% or p95 > 5000 ms: server is overloaded at that VU count.
- HTTP 502/503/504: PHP worker or gateway limit hit.
- JSON error "Database connection failed": MySQL max_connections exceeded.
