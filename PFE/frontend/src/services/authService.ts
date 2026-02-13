import api from '../lib/axios';
import type { LoginCredentials, AuthResponse, User } from '../types/auth';

/** API v1 returns { data: T }. Unwrap for callers. */
function unwrap<T>(res: { data?: { data?: T } }): T {
    const d = res.data?.data ?? res.data;
    if (d === undefined) throw new Error('Réponse API invalide');
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
