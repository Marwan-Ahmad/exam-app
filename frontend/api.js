// Basic API helper for the exam app (vanilla JS, no build step).
const API_BASE = localStorage.getItem('apiBase') || 'http://localhost:8000/api';

function setApiBase(url) {
    localStorage.setItem('apiBase', url);
}

function getToken() {
    return localStorage.getItem('token');
}

function saveToken(token) {
    localStorage.setItem('token', token);
}

function clearSession() {
    localStorage.removeItem('token');
    localStorage.removeItem('examState');
    localStorage.removeItem('lastExamId');
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
    setApiBase,
    getToken,
    saveToken,
    clearSession,
    requireAuth,
    saveExamState,
    loadExamState,
};
