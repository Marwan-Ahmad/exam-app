// Basic API helper for the exam app (vanilla JS, served from /public).
const defaultApiBase = `${window.location.origin}/api`;
const API_BASE = normalizeApiBase(localStorage.getItem('apiBase')) || defaultApiBase;

function normalizeApiBase(url) {
    if (!url) return '';
    try {
        const u = new URL(url);
        const parts = u.pathname.split('/').filter(Boolean);
        // keep only up to "api"
        const apiIndex = parts.indexOf('api');
        const basePath = apiIndex >= 0 ? '/' + parts.slice(0, apiIndex + 1).join('/') : '/api';
        return `${u.origin}${basePath}`;
    } catch {
        return '';
    }
}

function setApiBase(url) {
    const normalized = normalizeApiBase(url) || defaultApiBase;
    localStorage.setItem('apiBase', normalized);
}

function getToken() {
    return localStorage.getItem('token');
}

function saveToken(token) {
    localStorage.setItem('token', token);
}

function saveUser(user) {
    if (user) {
        localStorage.setItem('user', JSON.stringify(user));
    }
}

function getUser() {
    const raw = localStorage.getItem('user');
    if (!raw) return null;
    try {
        return JSON.parse(raw);
    } catch {
        return null;
    }
}

function getRole() {
    return localStorage.getItem('role');
}

function saveRole(role) {
    if (role) {
        localStorage.setItem('role', role);
    }
}

function clearSession() {
    localStorage.removeItem('token');
    localStorage.removeItem('examState');
    localStorage.removeItem('lastExamId');
    localStorage.removeItem('role');
    localStorage.removeItem('user');
}

async function api(path, { method = 'GET', body, headers = {} } = {}) {
    const token = getToken();
    const finalHeaders = {
        'Accept': 'application/json',
        ...headers,
    };

    if (body && !(body instanceof FormData)) {
        finalHeaders['Content-Type'] = 'application/json';
    }
    if (token) {
        finalHeaders['Authorization'] = `Bearer ${token}`;
    }

    const res = await fetch(`${API_BASE}${path}`, {
        method,
        headers: finalHeaders,
        body: body && !(body instanceof FormData) ? JSON.stringify(body) : body,
    });

    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
        const error = new Error(data.message || 'Request failed');
        error.status = res.status;
        error.data = data;
        throw error;
    }
    return data;
}

function requireAuth() {
    if (!getToken()) {
        window.location.href = './index.html';
    }
}

function saveExamState(state) {
    localStorage.setItem('examState', JSON.stringify(state));
    if (state?.exam_id) {
        localStorage.setItem('lastExamId', state.exam_id);
    }
}

function loadExamState() {
    const raw = localStorage.getItem('examState');
    if (!raw) return null;
    try {
        return JSON.parse(raw);
    } catch {
        return null;
    }
}

export {
    api,
    API_BASE,
    defaultApiBase,
    setApiBase,
    getToken,
    saveToken,
    saveUser,
    getUser,
    getRole,
    saveRole,
    clearSession,
    requireAuth,
    saveExamState,
    loadExamState,
};
