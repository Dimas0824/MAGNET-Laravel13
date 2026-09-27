import http from 'k6/http';
import { check, sleep } from 'k6';
import { Trend, Rate, Counter } from 'k6/metrics';

/**
 * MAGNET runtime comparison benchmark.
 *
 * Same script, same endpoints, same load shape for BOTH targets:
 *   - FrankenPHP  : http://127.0.0.1:8001  (Laravel 13, worker mode)
 *   - php-fpm/L10 : http://127.0.0.1:8002  (Laravel 10, php artisan serve)
 *
 * Usage:
 *   k6 run -e BASE_URL=http://127.0.0.1:8001 -e TAG=franken -e OUT=franken.json compare.js
 */

const BASE_URL = (__ENV.BASE_URL || 'http://127.0.0.1:8001').replace(/\/$/, '');
const TAG = __ENV.TAG || 'target';
const OUT = __ENV.OUT || `${TAG}-summary.json`;
const PEAK_RPS = Number(__ENV.PEAK_RPS || 60);      // reached at end of ramp
const PAGE_SHARE = Number(__ENV.PAGE_SHARE || 0.7); // 70% pages, 30% assets
const DURATION = __ENV.DURATION || '2m';
const STAGE = __ENV.STAGE || '20s';
const HOLD = __ENV.HOLD || '30s';

const PAGE_PATHS = ['/', '/pengembang', '/tata-tertib', '/cara-magang', '/tips-memilih-magang'];
const IO_PATHS = [
    '/robots.txt',
    '/sitemap.xml',
    '/img/wallpaper/wallpaper-chat.png',
    '/img/company/company-peruri.webp',
    '/img/company/company-petrokimia.jpg',
];

// Custom metrics so we can pull clean numbers out of handleSummary.
const pageDur = new Trend('page_duration', true);
const assetDur = new Trend('asset_duration', true);
const pageFail = new Rate('page_failed');
const assetFail = new Rate('asset_failed');
const pageBytes = new Counter('page_bytes');
const assetBytes = new Counter('asset_bytes');

export const options = {
    discardResponseBodies: false,
    scenarios: {
        mixed: {
            executor: 'ramping-arrival-rate',
            startRate: 5,
            timeUnit: '1s',
            preAllocatedVUs: 50,
            maxVUs: 500,
            stages: [
                { target: Math.round(PEAK_RPS * 0.25), duration: STAGE },
                { target: Math.round(PEAK_RPS * 0.5), duration: STAGE },
                { target: Math.round(PEAK_RPS * 0.75), duration: STAGE },
                { target: PEAK_RPS, duration: HOLD },
                { target: PEAK_RPS, duration: HOLD },
            ],
        },
    },
    thresholds: {
        http_req_failed: ['rate<0.05'],
        page_duration: ['p(95)<3000'],
    },
};

function pick(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
}

export default function () {
    const isPage = Math.random() < PAGE_SHARE;
    const path = isPage ? pick(PAGE_PATHS) : pick(IO_PATHS);
    const kind = isPage ? 'page' : 'asset';

    const res = http.get(`${BASE_URL}${path}`, {
        tags: { kind, endpoint: path, target: TAG },
        timeout: '30s',
    });

    const ok = check(res, {
        [`${kind} status 2xx/3xx`]: (r) => r.status >= 200 && r.status < 400,
    });

    if (isPage) {
        pageDur.add(res.timings.duration);
        pageFail.add(!ok);
        pageBytes.add(res.body ? res.body.length : 0);
    } else {
        assetDur.add(res.timings.duration);
        assetFail.add(!ok);
        assetBytes.add(res.body ? res.body.length : 0);
    }

    // Small think time keeps VU pressure realistic without dominating the profile.
    sleep(isPage ? 0.15 : 0.05);
}

function m(data, name, field) {
    const metric = data.metrics[name];
    if (!metric || !metric.values) return 0;
    return metric.values[field] || 0;
}

export function handleSummary(data) {
    const summary = {
        target: TAG,
        baseUrl: BASE_URL,
        peakRps: PEAK_RPS,
        duration: DURATION,
        metrics: {
            http_reqs: m(data, 'http_reqs', 'count'),
            actual_rps: m(data, 'http_reqs', 'rate'),
            http_req_failed: m(data, 'http_req_failed', 'rate'),
            http_req_duration_avg_ms: m(data, 'http_req_duration', 'avg'),
            http_req_duration_p50_ms: m(data, 'http_req_duration', 'p(50)'),
            http_req_duration_p95_ms: m(data, 'http_req_duration', 'p(95)'),
            http_req_duration_p99_ms: m(data, 'http_req_duration', 'p(99)'),
            page_avg_ms: m(data, 'page_duration', 'avg'),
            page_p95_ms: m(data, 'page_duration', 'p(95)'),
            page_failed: m(data, 'page_failed', 'rate'),
            asset_avg_ms: m(data, 'asset_duration', 'avg'),
            asset_p95_ms: m(data, 'asset_duration', 'p(95)'),
            asset_failed: m(data, 'asset_failed', 'rate'),
            data_received_kbs: m(data, 'data_received', 'rate') / 1024,
            data_sent_kbs: m(data, 'data_sent', 'rate') / 1024,
            vus_max: m(data, 'vus_max', 'value'),
            iterations: m(data, 'iterations', 'count'),
        },
    };

    const mm = summary.metrics;
    const text = [
        `=== MAGNET benchmark: ${TAG} ===`,
        `URL           : ${BASE_URL}`,
        `Peak target   : ${PEAK_RPS} RPS (ramp)`,
        `Total reqs    : ${mm.http_reqs}`,
        `Actual RPS    : ${mm.actual_rps.toFixed(2)} req/s`,
        `Failed        : ${(mm.http_req_failed * 100).toFixed(2)}%`,
        `Latency avg   : ${mm.http_req_duration_avg_ms.toFixed(1)} ms`,
        `Latency p50   : ${mm.http_req_duration_p50_ms.toFixed(1)} ms`,
        `Latency p95   : ${mm.http_req_duration_p95_ms.toFixed(1)} ms`,
        `Latency p99   : ${mm.http_req_duration_p99_ms.toFixed(1)} ms`,
        `Page p95      : ${mm.page_p95_ms.toFixed(1)} ms (fail ${(mm.page_failed * 100).toFixed(1)}%)`,
        `Asset p95     : ${mm.asset_p95_ms.toFixed(1)} ms (fail ${(mm.asset_failed * 100).toFixed(1)}%)`,
        `Recv / Sent   : ${mm.data_received_kbs.toFixed(1)} KB/s / ${mm.data_sent_kbs.toFixed(1)} KB/s`,
        `Max VUs       : ${mm.vus_max}`,
        '',
    ].join('\n');

    return {
        stdout: text,
        [OUT]: JSON.stringify(summary, null, 2),
    };
}
