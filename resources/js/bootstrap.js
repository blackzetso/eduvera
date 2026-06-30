import axios from 'axios';

window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';
window.axios.defaults.withCredentials = true;
window.axios.defaults.xsrfHeaderName = 'X-XSRF-TOKEN';
window.axios.defaults.xsrfCookieName = 'XSRF-TOKEN';

function readCsrfFromMeta() {
    return document.head.querySelector('meta[name="csrf-token"]')?.content || null;
}

function readXsrfFromCookie() {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]*)/);
    return match ? decodeURIComponent(match[1]) : null;
}

function applyCsrfToConfig(config) {
    const headers = config.headers || {};
    const meta = readCsrfFromMeta();
    if (meta) {
        headers['X-CSRF-TOKEN'] = meta;
    }
    const xsrf = readXsrfFromCookie();
    if (xsrf) {
        headers['X-XSRF-TOKEN'] = xsrf;
    }
    config.headers = headers;
    return config;
}

window.axios.interceptors.request.use(applyCsrfToConfig);

const initialCsrf = readCsrfFromMeta();
if (initialCsrf) {
    window.axios.defaults.headers.common['X-CSRF-TOKEN'] = initialCsrf;
}
