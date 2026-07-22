import axios from 'axios';
window.axios = axios;

window.axios.defaults.headers.common['X-Requested-With'] = 'XMLHttpRequest';

// No fijar X-CSRF-TOKEN desde el meta tag: ese valor queda congelado en la carga
// inicial y Laravel lo prioriza sobre X-XSRF-TOKEN, así que tras cualquier
// regeneración de sesión (login, logout) queda obsoleto y todo POST posterior
// -incluidas las visitas internas de Inertia, que usan este mismo axios- recibe
// 419. axios ya adjunta X-XSRF-TOKEN automáticamente leyendo la cookie
// XSRF-TOKEN (que Laravel reemite fresca en cada respuesta), así que no hace
// falta gestionar el token a mano.
