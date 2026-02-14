export interface Administrator {
    id: number;
    poste: string;
    phone?: string;
}

export interface Formateur {
    id: number;
    matricule: string;
    specialty: string;
    type: 'permanent' | 'vacataire';
    hourly_rate?: number;
}

export interface Stagiaire {
    id: number;
    filiere_id: number;
    groupe_id?: number;
    cef_number: string;
    date_naissance: string;
    status: 'actif' | 'abandon' | 'exclu' | 'diplome';
    parent_id?: number;
    filiere?: { id: number; code: string; label: string };
    groupe?: { id: number; label: string };
    niveau_formation?: string;
    niveau_scolaire?: string;
}

export interface Parent {
    id: number;
    cin: string;
    phone: string;
    address?: string;
}

export interface User {
    id: number;
    name: string;
    email: string;
    role: 'admin' | 'formateur' | 'stagiaire' | 'parent';
    avatar_url?: string;
    is_active: boolean;

    // Profiles
    administrator?: Administrator;
    formateur?: Formateur;
    stagiaire?: Stagiaire;
    parent?: Parent; // Note: mapped to studentParent in backend but let's call it parent here

    created_at: string;
    updated_at: string;
}

export interface AuthResponse {
    access_token: string;
    token_type: string;
    user: User;
    roles?: { id: number; name: string; slug: string }[];
    permissions?: string[];
}

export interface LoginCredentials {
    email: string;
    password: string;
}
