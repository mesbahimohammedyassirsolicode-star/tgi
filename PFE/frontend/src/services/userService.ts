import api from '../lib/axios';
import type { User } from '../types/auth';

export interface CreateUserPayload {
    name: string;
    email: string;
    password: string;
    role: string;
    // ... any profile fields
    [key: string]: any;
}

export const userService = {
    getAll: async (role?: string) => {
        const params = role ? { role } : {};
        const { data } = await api.get<{ data: User[] }>('/users', { params });
        return data; // standard laravel paginate response wrapper? or just array? Backend returns paginate object.
        // Backend: return $query->latest()->paginate(20);
        // So data structure is { data: User[], links: ..., meta: ... }
    },

    create: async (payload: CreateUserPayload) => {
        const { data } = await api.post<User>('/users', payload);
        return data;
    },

    update: async (id: number, payload: Partial<CreateUserPayload>) => {
        const { data } = await api.put<User>(`/users/${id}`, payload);
        return data;
    },

    delete: async (id: number) => {
        await api.delete(`/users/${id}`);
    }
};
