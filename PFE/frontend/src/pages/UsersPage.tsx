import { useState, useEffect } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { toast } from 'sonner';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { useCreateUser, useUsers, useDeleteUser, useUpdateUser } from '../hooks/useUsers';
import { academicStructureApi } from '../services/api/academicStructure';
import { groupsApi } from '../services/api/groups';
import { useQuery } from '@tanstack/react-query';
import { Loader2, Plus, Trash2, Search, Pencil } from 'lucide-react';
import Modal from '../components/ui/modal';
import { useAuth } from '../context/AuthContext';

// Define base user schema
const baseSchema = z.object({
    name: z.string().min(2, "Nom trop court"),
    email: z.string().email("Email invalide"),
    password: z.string().optional(), // required only on create, validated in submit
    role: z.enum(['admin', 'formateur', 'teacher', 'stagiaire', 'parent']),
});

// Define profile specific validation logic dynamically in submit handler or refine here
// For simplicity, we'll check conditionally in the form logic or create separate schemas.
// Let's create a unified schema with optional fields that become required based on role.

// Helper: preprocess empty strings to undefined so .optional() works with HTML selects
const emptyToUndefined = (val: unknown) => (val === '' || val === null ? undefined : val);
const emptyStringToUndefined = z.preprocess(emptyToUndefined, z.string().optional());
const optionalEnum = <T extends [string, ...string[]]>(values: T) =>
    z.preprocess(emptyToUndefined, z.enum(values).optional());
const optionalNumeric = z.preprocess(
    (val) => (val === '' || val === null || val === undefined ? undefined : Number(val)),
    z.number().optional()
);

const userSchema = baseSchema.extend({
    // Formateur
    matricule: z.string().optional(),
    specialty: z.string().optional(),
    type: optionalEnum(['permanent', 'vacataire']),
    hourly_rate: z.preprocess(
        (val) => (val === '' || val === null || val === undefined ? undefined : Number(val)),
        z.number().optional()
    ),

    // Stagiaire
    cin: z.string().optional(),
    cef_number: z.string().optional(),
    date_naissance: emptyStringToUndefined,
    niveau_scolaire: optionalEnum(['COLLEGE', 'BAC', 'BAC+2', 'BAC+3', 'MASTER']),
    niveau_formation: optionalEnum(['Q', 'T', 'TS', 'BACHELOR', 'MASTER']),
    filiere_id: optionalNumeric,
    groupe_id: optionalNumeric,
    status: optionalEnum(['actif', 'abandon', 'exclu', 'diplome']),

    // Parent
    phone: z.string().optional(),
    address: z.string().optional(),

    // Admin
    poste: z.string().optional(),
}).superRefine((data, ctx) => {
    if (data.role === 'formateur' || data.role === 'teacher') {
        if (!data.matricule) ctx.addIssue({ code: z.ZodIssueCode.custom, message: "Matricule requis", path: ['matricule'] });
        if (!data.specialty) ctx.addIssue({ code: z.ZodIssueCode.custom, message: "Spécialité requise", path: ['specialty'] });
        if (!data.type) ctx.addIssue({ code: z.ZodIssueCode.custom, message: "Type requis", path: ['type'] });
    }
    if (data.role === 'stagiaire') {
        if (!data.cin) ctx.addIssue({ code: z.ZodIssueCode.custom, message: "CIN requis (2 lettres + 6 chiffres)", path: ['cin'] });
        else if (!/^[A-Z]{2}\d{6}$/i.test(data.cin)) ctx.addIssue({ code: z.ZodIssueCode.custom, message: "CIN: 2 lettres + 6 chiffres (ex: AB123456)", path: ['cin'] });
        if (!data.cef_number) ctx.addIssue({ code: z.ZodIssueCode.custom, message: "CEF requis", path: ['cef_number'] });
        if (!data.date_naissance) ctx.addIssue({ code: z.ZodIssueCode.custom, message: "Date naissance requise", path: ['date_naissance'] });
        if (!data.niveau_scolaire) ctx.addIssue({ code: z.ZodIssueCode.custom, message: "Niveau scolaire requis", path: ['niveau_scolaire'] });
        if (!data.niveau_formation) ctx.addIssue({ code: z.ZodIssueCode.custom, message: "Type de formation requis", path: ['niveau_formation'] });
        if (!data.filiere_id) ctx.addIssue({ code: z.ZodIssueCode.custom, message: "Filière requise", path: ['filiere_id'] });
        if (!data.groupe_id) ctx.addIssue({ code: z.ZodIssueCode.custom, message: "Groupe requis", path: ['groupe_id'] });
        // Eligibility: Q min COLLEGE, T/TS min BAC, BACHELOR min BAC+2, MASTER min BAC+3
        if (data.niveau_scolaire && data.niveau_formation) {
            const order: Record<string, number> = { COLLEGE: 0, BAC: 1, 'BAC+2': 2, 'BAC+3': 3, MASTER: 4 };
            const minForFormation: Record<string, string> = { Q: 'COLLEGE', T: 'BAC', TS: 'BAC', BACHELOR: 'BAC+2', MASTER: 'BAC+3' };
            const min = minForFormation[data.niveau_formation];
            const scolaireRank = order[data.niveau_scolaire] ?? -1;
            const minRank = order[min] ?? 0;
            if (min && scolaireRank < minRank) {
                const formationLabels: Record<string, string> = { Q: 'Qualification', T: 'Technicien', TS: 'Technicien Spécialisé', BACHELOR: 'Bachelor', MASTER: 'Master' };
                const minLabels: Record<string, string> = { COLLEGE: 'Collège', BAC: 'Baccalauréat', 'BAC+2': 'Bac+2', 'BAC+3': 'Bac+3' };
                ctx.addIssue({ code: z.ZodIssueCode.custom, message: `Pour la formation ${formationLabels[data.niveau_formation]}, le niveau scolaire minimum requis est ${minLabels[min] || min}.`, path: ['niveau_scolaire'] });
            }
        }
    }
    if (data.role === 'parent') {
        if (!data.cin) ctx.addIssue({ code: z.ZodIssueCode.custom, message: "CIN requis", path: ['cin'] });
        if (!data.phone) ctx.addIssue({ code: z.ZodIssueCode.custom, message: "Téléphone requis", path: ['phone'] });
    }
});

type UserFormValues = z.infer<typeof userSchema>;

export default function UsersPage() {
    const { user } = useAuth();
    const [roleFilter, setRoleFilter] = useState<string>('');
    const [isModalOpen, setIsModalOpen] = useState(false);
    const [editingUser, setEditingUser] = useState<any | null>(null);

    const { register, handleSubmit, watch, reset, setValue, setError, formState: { errors } } = useForm<UserFormValues>({
        resolver: zodResolver(userSchema) as any,
        defaultValues: {
            role: 'stagiaire', // default
            type: 'permanent',
            status: 'actif',
        }
    });

    const selectedRole = watch('role');
    const filiereId = watch('filiere_id');
    const filiereIdForGroups = filiereId !== undefined && filiereId !== 0 ? Number(filiereId) : undefined;

    const { data: usersData, isLoading: isUsersLoading, error } = useUsers(roleFilter || undefined, user?.id, user?.role);
    const { data: filieres = [] } = useQuery({
        queryKey: ['academic', 'filieres', user?.id, user?.role],
        queryFn: () => academicStructureApi.getFilieres(),
    });
    const { data: groupesByFiliere, isLoading: isLoadingGroupes } = useQuery({
        queryKey: ['groups', 'by-filiere', user?.id, user?.role, filiereIdForGroups],
        queryFn: () => groupsApi.list({ filiere_id: filiereIdForGroups!, per_page: 100 }),
        enabled: selectedRole === 'stagiaire' && !!filiereIdForGroups,
    });
    const groupeOptions = Array.isArray(groupesByFiliere?.items) ? groupesByFiliere.items : [];
    const createUser = useCreateUser();
    const updateUser = useUpdateUser();
    const deleteUser = useDeleteUser();

    useEffect(() => {
        if (selectedRole === 'stagiaire' && (filiereId === 0 || filiereId === undefined)) {
            setValue('groupe_id', undefined);
        }
    }, [filiereId, selectedRole, setValue]);

    const buildPayload = (data: UserFormValues, forEdit = false) => {
        const payload = { ...data } as any;
        // Clean up fields not relevant to the selected role
        const stagiairOnlyFields = ['cin', 'cef_number', 'date_naissance', 'niveau_scolaire', 'niveau_formation', 'filiere_id', 'groupe_id', 'status'];
        const formateurOnlyFields = ['matricule', 'specialty', 'type', 'hourly_rate'];

        if (data.role === 'stagiaire') {
            if (data.filiere_id !== undefined && data.filiere_id !== 0) payload.filiere_id = Number(data.filiere_id);
            if (data.groupe_id !== undefined && data.groupe_id !== 0) payload.groupe_id = Number(data.groupe_id);
            // Remove fields not for stagiaire
            formateurOnlyFields.forEach(f => delete payload[f]);
        } else if (data.role === 'formateur' || data.role === 'teacher') {
            payload.type = payload.type || 'permanent';
            payload.specialty = payload.specialty || payload.specialite || '';
            payload.specialite = payload.specialty; // backend expects specialite
            const rate = payload.hourly_rate;
            if (rate === '' || rate === undefined || Number.isNaN(Number(rate))) {
                delete payload.hourly_rate;
            } else {
                payload.hourly_rate = Number(rate);
            }
            // Remove fields not for formateur
            stagiairOnlyFields.forEach((f: string) => delete payload[f]);
            delete payload.cin;
            delete payload.phone;
            delete payload.address;
            delete payload.poste;
        } else if (data.role === 'parent') {
            // Remove fields not for parent
            stagiairOnlyFields.filter(f => f !== 'cin').forEach(f => delete payload[f]);
            formateurOnlyFields.forEach(f => delete payload[f]);
            delete payload.poste;
        } else if (data.role === 'admin') {
            // Remove fields not for admin
            stagiairOnlyFields.forEach(f => delete payload[f]);
            formateurOnlyFields.forEach(f => delete payload[f]);
            delete payload.cin;
            delete payload.address;
        }
        if (forEdit && !payload.password) delete payload.password;
        return payload;
    };

    const handleApiError = (err: any, setErrorFn: (field: keyof UserFormValues, opts: { type: string; message: string }) => void) => {
        const status = err?.response?.status;
        const data = err?.response?.data;
        const apiErrors = data?.errors;
        const msg = data?.message;
        if (status === 403) {
            toast.error(msg || 'Accès refusé.');
            return;
        }
        if (apiErrors && typeof apiErrors === 'object') {
            Object.entries(apiErrors).forEach(([field, messages]) => {
                const m = Array.isArray(messages) ? messages[0] : String(messages);
                if (m) {
                    const formField = (field === 'specialite' ? 'specialty' : field) as keyof UserFormValues;
                    setErrorFn(formField, { type: 'server', message: m });
                }
            });
            toast.error('Veuillez corriger les erreurs dans le formulaire.');
        } else {
            toast.error(msg || 'Une erreur est survenue.');
        }
    };

    const onSubmit = (data: UserFormValues) => {
        if (!editingUser && (!data.password || String(data.password).length < 8)) {
            setError('password', { type: 'custom', message: 'Mot de passe requis (min 8 caractères)' });
            return;
        }
        const payload = buildPayload(data, !!editingUser);
        if (editingUser) {
            updateUser.mutate(
                { id: editingUser.id, data: payload },
                {
                    onSuccess: () => {
                        toast.success('Utilisateur modifié.');
                        setEditingUser(null);
                        setIsModalOpen(false);
                        reset();
                    },
                    onError: (err: any) => {
                        handleApiError(err, setError);
                    }
                }
            );
        } else {
            createUser.mutate(payload, {
                onSuccess: () => {
                    toast.success('Utilisateur créé.');
                    setIsModalOpen(false);
                    reset();
                },
                onError: (err: any) => {
                    handleApiError(err, setError);
                }
            });
        }
    };

    const handleEdit = (user: any) => {
        setEditingUser(user);
        reset({
            name: user.name,
            email: user.email,
            password: '',
            role: user.role,
            matricule: user.formateur?.matricule ?? '',
            specialty: user.formateur?.specialty ?? '',
            type: user.formateur?.type ?? 'permanent',
            hourly_rate: user.formateur?.hourly_rate,
            cin: user.stagiaire?.cin ?? user.parent?.cin ?? '',
            cef_number: user.stagiaire?.cef_number ?? '',
            date_naissance: user.stagiaire?.date_naissance?.slice?.(0, 10) ?? '',
            niveau_scolaire: user.stagiaire?.niveau_scolaire ?? undefined,
            niveau_formation: user.stagiaire?.niveau_formation ?? undefined,
            filiere_id: user.stagiaire?.filiere_id ?? user.stagiaire?.filiere?.id ?? '',
            groupe_id: user.stagiaire?.groupe_id ?? (user.stagiaire as any)?.groupe?.id ?? '',
            status: user.stagiaire?.status ?? 'actif',
            phone: user.parent?.phone ?? user.administrator?.phone ?? '',
            address: user.parent?.address ?? '',
            poste: user.administrator?.poste ?? '',
        });
        setIsModalOpen(true);
    };

    const handleCloseModal = () => {
        setIsModalOpen(false);
        setEditingUser(null);
        reset();
    };

    const handleDelete = (id: number) => {
        if (confirm('Êtes-vous sûr de vouloir supprimer cet utilisateur ?')) {
            deleteUser.mutate(id);
        }
    };

    if (error) return <div className="p-6 text-red-500">Erreur de chargement des utilisateurs.</div>;

    return (
        <div className="space-y-6">
            <div className="flex items-center justify-between">
                <h1 className="text-3xl font-bold tracking-tight">Utilisateurs</h1>
                <Button onClick={() => { setEditingUser(null); reset(); setIsModalOpen(true); }}>
                    <Plus className="w-4 h-4 mr-2" />
                    Nouveau
                </Button>
            </div>

            <div className="flex items-center space-x-2 bg-white p-4 rounded-lg border shadow-sm">
                <Search className="w-4 h-4 text-gray-400" />
                <select
                    className="border-none focus:ring-0 text-sm w-full"
                    value={roleFilter}
                    onChange={(e) => setRoleFilter(e.target.value)}
                >
                    <option value="">Tous les rôles</option>
                    <option value="stagiaire">Stagiaires</option>
                    <option value="formateur">Formateurs</option>
                    <option value="admin">Administrateurs</option>
                    <option value="parent">Parents</option>
                </select>
            </div>

            {isUsersLoading ? (
                <div className="flex justify-center p-8"><Loader2 className="animate-spin" /></div>
            ) : (
                <div className="bg-white rounded-lg border shadow-sm overflow-hidden">
                    <table className="w-full text-sm text-left">
                        <thead className="bg-gray-50 text-gray-500 font-medium border-b">
                            <tr>
                                <th className="p-4">Nom</th>
                                <th className="p-4">Email</th>
                                <th className="p-4">Rôle</th>
                                <th className="p-4">Détails</th>
                                <th className="p-4 text-right">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            {usersData?.data?.map((user: any) => (
                                <tr key={user.id} className="border-b last:border-0 hover:bg-gray-50 transition-colors">
                                    <td className="p-4 font-medium">{user.name}</td>
                                    <td className="p-4 text-gray-500">{user.email}</td>
                                    <td className="p-4 capitalize">
                                        <span className={`inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium ${user.role === 'admin' ? 'bg-purple-100 text-purple-800' :
                                            user.role === 'formateur' ? 'bg-blue-100 text-blue-800' :
                                                user.role === 'stagiaire' ? 'bg-green-100 text-green-800' :
                                                    'bg-gray-100 text-gray-800'
                                            }`}>
                                            {user.role}
                                        </span>
                                    </td>
                                    <td className="p-4 text-gray-500 text-xs">
                                        {user.role === 'formateur' && user.formateur && (
                                            <>
                                                <div className="font-semibold">{user.formateur.specialty}</div>
                                                <div>{user.formateur.matricule}</div>
                                            </>
                                        )}
                                        {user.role === 'stagiaire' && user.stagiaire && (
                                            <>
                                                <div className="font-semibold">CIN: {user.stagiaire.cin}</div>
                                                <div>CEF: {user.stagiaire.cef_number}</div>
                                                <div>Filière: {user.stagiaire.filiere?.label ?? user.stagiaire.filiere_id ?? '-'}</div>
                                                <div>Groupe: {user.stagiaire.groupe?.label ?? user.stagiaire.groupe_id ?? '-'}</div>
                                                <div>Né(e): {user.stagiaire.date_naissance}</div>
                                            </>
                                        )}
                                    </td>
                                    <td className="p-4 text-right space-x-2">
                                        <Button variant="ghost" size="icon" onClick={() => handleEdit(user)} title="Modifier">
                                            <Pencil className="w-4 h-4 text-gray-600" />
                                        </Button>
                                        <Button variant="ghost" size="icon" onClick={() => handleDelete(user.id)} title="Supprimer">
                                            <Trash2 className="w-4 h-4 text-red-500" />
                                        </Button>
                                    </td>
                                </tr>
                            ))}
                            {usersData?.data?.length === 0 && (
                                <tr>
                                    <td colSpan={5} className="p-8 text-center text-gray-500">Aucun utilisateur trouvé.</td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
            )}

            <Modal
                isOpen={isModalOpen}
                onClose={handleCloseModal}
                title={editingUser ? 'Modifier l\'utilisateur' : 'Ajouter un utilisateur'}
            >
                <form onSubmit={handleSubmit((data) => onSubmit(data as UserFormValues))} className="space-y-4">
                    <div className="grid gap-2">
                        <Label>Rôle</Label>
                        <select
                            {...register('role')}
                            name="role"
                            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="stagiaire">Stagiaire</option>
                            <option value="formateur">Formateur (enseignant)</option>
                            <option value="parent">Parent</option>
                            <option value="admin">Administrateur</option>
                        </select>
                    </div>

                    <div className="grid gap-2">
                        <Label>Nom complet</Label>
                        <Input {...register('name')} placeholder="Mohammed Alami" />
                        {errors.name && <p className="text-red-500 text-xs">{errors.name.message}</p>}
                    </div>

                    <div className="grid gap-2">
                        <Label>Email</Label>
                        <Input {...register('email')} type="email" placeholder="email@exemple.com" />
                        {errors.email && <p className="text-red-500 text-xs">{errors.email.message}</p>}
                    </div>

                    <div className="grid gap-2">
                        <Label>Mot de passe {editingUser ? '(laisser vide pour ne pas modifier)' : ''}</Label>
                        <Input {...register('password')} type="password" placeholder={editingUser ? '********' : '********'} />
                        {errors.password && <p className="text-red-500 text-xs">{errors.password.message}</p>}
                    </div>

                    {/* Conditional Fields */}
                    {(selectedRole === 'formateur' || selectedRole === 'teacher') && (
                        <>
                            <div className="grid gap-2">
                                <Label>Matricule</Label>
                                <Input {...register('matricule')} placeholder="F12345" />
                                {errors.matricule && <p className="text-red-500 text-xs">{errors.matricule.message}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label>Spécialité</Label>
                                <Input {...register('specialty')} placeholder="Développement Digital" />
                                {errors.specialty && <p className="text-red-500 text-xs">{errors.specialty.message}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label>Type</Label>
                                <select
                                    {...register('type')}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="permanent">Permanent</option>
                                    <option value="vacataire">Vacataire</option>
                                </select>
                                {errors.type && <p className="text-red-500 text-xs">{errors.type.message}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label>Taux Horaire (DH)</Label>
                                <Input {...register('hourly_rate')} type="number" step="0.01" placeholder="150.00" />
                            </div>
                        </>
                    )}

                    {selectedRole === 'stagiaire' && (
                        <>
                            <div className="grid gap-2">
                                <Label>Type de formation (niveau diplôme visé) <span className="text-red-500">*</span></Label>
                                <p className="text-xs text-muted-foreground">Qualification, TS, Bachelor, etc.</p>
                                <select
                                    {...register('niveau_formation')}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="">Sélectionner...</option>
                                    <option value="Q">Qualification</option>
                                    <option value="T">Technicien</option>
                                    <option value="TS">Technicien Spécialisé</option>
                                    <option value="BACHELOR">Bachelor</option>
                                    <option value="MASTER">Master</option>
                                </select>
                                {errors.niveau_formation && <p className="text-red-500 text-xs">{errors.niveau_formation.message}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label>Filière (spécialité) <span className="text-red-500">*</span></Label>
                                <p className="text-xs text-muted-foreground">Ex. Développement Digital, Gestion, Infographie</p>
                                <select
                                    {...register('filiere_id')}
                                    name="filiere_id"
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="">Sélectionner une filière...</option>
                                    {filieres.map((f) => (
                                        <option key={f.id} value={f.id}>{f.code} — {f.label}</option>
                                    ))}
                                </select>
                                {errors.filiere_id && <p className="text-red-500 text-xs">{errors.filiere_id.message}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label>Groupe <span className="text-red-500">*</span></Label>
                                <p className="text-xs text-muted-foreground">Choisir une filière d&apos;abord pour charger les groupes.</p>
                                <select
                                    {...register('groupe_id')}
                                    name="groupe_id"
                                    disabled={!filiereIdForGroups || isLoadingGroupes}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 disabled:opacity-50 disabled:cursor-not-allowed"
                                >
                                    <option value="">
                                        {!filiereIdForGroups ? 'Sélectionner une filière d\'abord' : isLoadingGroupes ? 'Chargement...' : groupeOptions.length === 0 ? 'Aucun groupe pour cette filière' : 'Sélectionner un groupe...'}
                                    </option>
                                    {groupeOptions.map((g) => (
                                        <option key={g.id} value={g.id}>{g.label}</option>
                                    ))}
                                </select>
                                {errors.groupe_id && <p className="text-red-500 text-xs">{errors.groupe_id.message}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label>CIN <span className="text-red-500">*</span></Label>
                                <Input {...register('cin')} placeholder="AB123456" maxLength={8} className="uppercase" />
                                {errors.cin && <p className="text-red-500 text-xs">{errors.cin.message}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label>Numéro CEF (Massar)</Label>
                                <Input {...register('cef_number')} placeholder="M13000..." />
                                {errors.cef_number && <p className="text-red-500 text-xs">{errors.cef_number.message}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label>Date de Naissance</Label>
                                <Input {...register('date_naissance')} type="date" />
                                {errors.date_naissance && <p className="text-red-500 text-xs">{errors.date_naissance.message}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label>Niveau scolaire (diplôme obtenu) <span className="text-red-500">*</span></Label>
                                <p className="text-xs text-muted-foreground">Diplôme ou niveau académique détenu avant l&apos;inscription</p>
                                <select
                                    {...register('niveau_scolaire')}
                                    className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                                >
                                    <option value="">Sélectionner...</option>
                                    <option value="COLLEGE">Collège</option>
                                    <option value="BAC">Baccalauréat</option>
                                    <option value="BAC+2">Bac+2 (DUT, BTS, etc.)</option>
                                    <option value="BAC+3">Bac+3</option>
                                    <option value="MASTER">Master</option>
                                </select>
                                {errors.niveau_scolaire && <p className="text-red-500 text-xs">{errors.niveau_scolaire.message}</p>}
                            </div>
                        </>
                    )}

                    {selectedRole === 'parent' && (
                        <>
                            <div className="grid gap-2">
                                <Label>CIN</Label>
                                <Input {...register('cin')} placeholder="KB123456" />
                                {errors.cin && <p className="text-red-500 text-xs">{errors.cin.message}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label>Téléphone</Label>
                                <Input {...register('phone')} placeholder="06..." />
                            </div>
                        </>
                    )}

                    <div className="pt-4 flex justify-end space-x-2">
                        <Button type="button" variant="outline" onClick={handleCloseModal}>Annuler</Button>
                        <Button type="submit" disabled={createUser.isPending || updateUser.isPending}>
                            {(createUser.isPending || updateUser.isPending) && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            {editingUser ? 'Enregistrer' : 'Créer'}
                        </Button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
