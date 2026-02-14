import axios from 'axios';

const baseURL = (import.meta.env.VITE_API_URL || 'http://localhost:8000/api').replace(/\/$/, '') + '/v1';

const api = axios.create({
    baseURL,
    headers: {
        'Content-Type': 'application/json',
        'Accept': 'application/json',
    },
    withCredentials: true,
});

// Request interceptor: set Authorization so every request sends the token
api.interceptors.request.use((config) => {
    const token = localStorage.getItem('token');
    if (!config.headers) {
        config.headers = {} as typeof config.headers;
    }
    if (token) {
        config.headers['Authorization'] = `Bearer ${token}`;
    } else {
        delete config.headers['Authorization'];
    }
    return config;
});

// Response interceptor for 401: clear token and redirect to login
api.interceptors.response.use(
    (response) => response,
    (error) => {
        if (error.response?.status === 401) {
            localStorage.removeItem('token');
            if (!window.location.pathname.startsWith('/login')) {
                window.location.href = '/login';
            }
        }
        return Promise.reject(error);
    }
);

export default api;

