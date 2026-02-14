import api from '../lib/axios';
import type { LoginCredentials, AuthResponse, User } from '../types/auth';

/** API v1 returns { data: T }. Unwrap for callers. */
function unwrap<T>(res: any): T {
    // Standard Laravel response or our { data: T } wrapper
    const d = res?.data?.data !== undefined ? res.data.data : res?.data !== undefined ? res.data : undefined;
    if (d === undefined) {
        console.error('API Response missing data:', res);
        throw new Error('Réponse API invalide: données manquantes');
    }
    return d as T;
}

export const authService = {
    login: async (credentials: LoginCredentials): Promise<AuthResponse> => {
        const res = await api.post<{ data: AuthResponse }>('/login', credentials);
        return unwrap(res);
    },

    logout: async (): Promise<void> => {
        await api.post('/logout');
    },

    getMe: async (): Promise<{ user: User; roles: { id: number; name: string; slug: string }[]; permissions: string[] }> => {
        const res = await api.get<{ data: { user: User; roles: { id: number; name: string; slug: string }[]; permissions: string[] } }>('/me');
        return unwrap(res);
    },
};
