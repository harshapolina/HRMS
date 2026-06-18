/**
 * k6 load test for punch_action.php
 *
 * Prerequisites:
 *   1. Install k6: https://k6.io/docs/get-started/installation/
 *   2. Create test employee accounts or use a staging DB with known sessions.
 *
 * Usage (PowerShell):
 *   $env:BASE_URL="https://your-domain/incentiveapp_integration/userlogin1/hrlogin"
 *   $env:SESSION_COOKIE="HRSESSID=your_session_value"
 *   k6 run punch_load_test.js
 *
 * Staged ramp (default): 100 -> 500 virtual users over 5 minutes.
 */

import http from 'k6/http';
import { check, sleep } from 'k6';
import { Counter, Rate, Trend } from 'k6/metrics';

const punchErrors = new Counter('punch_errors');
const punchSuccess = new Counter('punch_success');
const punchFailRate = new Rate('punch_fail_rate');
const punchDuration = new Trend('punch_duration_ms');

export const options = {
    stages: [
        { duration: '1m', target: 100 },
        { duration: '2m', target: 300 },
        { duration: '2m', target: 500 },
        { duration: '1m', target: 0 },
    ],
    thresholds: {
        punch_fail_rate: ['rate<0.05'],
        http_req_duration: ['p(95)<5000'],
    },
};

const baseUrl = __ENV.BASE_URL || 'http://localhost/hrlogin';
const sessionCookie = __ENV.SESSION_COOKIE || '';
const latitude = __ENV.PUNCH_LAT || '12.9716';
const longitude = __ENV.PUNCH_LNG || '77.5946';

export default function () {
    if (!sessionCookie) {
        punchErrors.add(1);
        punchFailRate.add(1);
        sleep(1);
        return;
    }

    const payload = {
        action: 'punch_in',
        latitude: latitude,
        longitude: longitude,
        format: 'json',
    };

    const params = {
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest',
            Cookie: sessionCookie,
        },
        tags: { name: 'PunchIn' },
    };

    const start = Date.now();
    const res = http.post(`${baseUrl}/punch_action.php`, payload, params);
    punchDuration.add(Date.now() - start);

    const ok = check(res, {
        'status is 200': (r) => r.status === 200,
        'json success': (r) => {
            try {
                const body = r.json();
                return body.success === true;
            } catch (e) {
                return false;
            }
        },
    });

    if (ok) {
        punchSuccess.add(1);
        punchFailRate.add(0);
    } else {
        punchErrors.add(1);
        punchFailRate.add(1);
    }

    sleep(Math.random() * 2 + 0.5);
}

export function handleSummary(data) {
    return {
        stdout: textSummary(data),
    };
}

function textSummary(data) {
    const lines = [
        'Punch load test summary',
        '=======================',
        `http_req_duration p95: ${data.metrics.http_req_duration?.values?.['p(95)'] || 'n/a'} ms`,
        `punch_success: ${data.metrics.punch_success?.values?.count || 0}`,
        `punch_errors: ${data.metrics.punch_errors?.values?.count || 0}`,
        `punch_fail_rate: ${data.metrics.punch_fail_rate?.values?.rate || 0}`,
    ];
    return lines.join('\n') + '\n';
}
