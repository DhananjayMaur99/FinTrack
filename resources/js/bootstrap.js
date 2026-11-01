import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// Send the Sanctum session cookie (and read the XSRF-TOKEN cookie it sets) on every
// request, so the web app authenticates via cookie instead of a bearer token.
window.axios.defaults.withCredentials = true;
window.axios.defaults.withXSRFToken = true;
