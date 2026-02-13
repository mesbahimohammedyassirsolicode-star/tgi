import { useState } from 'react';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import * as z from 'zod';
import { Button } from '../components/ui/button';
import { Input } from '../components/ui/input';
import { Label } from '../components/ui/label';
import { useCreateUser, useUsers, useDeleteUser } from '../hooks/useUsers';
import { Loader2, Plus, Trash2, Search } from 'lucide-react';
import Modal from '../components/ui/modal';

// Define base user schema
const baseSchema = z.object({
    name: z.string().min(2, "Nom trop court"),
    email: z.string().email("Email invalide"),
    password: z.string().min(8, "Mot de passe trop court (min 8)"),
    role: z.enum(['admin', 'formateur', 'stagiaire', 'parent']),
});

// Define profile specific validation logic dynamically in submit handler or refine here
// For simplicity, we'll check conditionally in the form logic or create separate schemas.
// Let's create a unified schema with optional fields that become required based on role.

const userSchema = baseSchema.extend({
    // Formateur
    matricule: z.string().optional(),
    specialty: z.string().optional(),
    type: z.enum(['permanent', 'vacataire']).optional(),
    hourly_rate: z.coerce.number().optional(),

    // Stagiaire
    cef_number: z.string().optional(),
    date_naissance: z.string().optional(), // date string
    status: z.enum(['actif', 'abandon', 'exclu', 'diplome']).optional(),

    // Parent
    cin: z.string().optional(),
    phone: z.string().optional(),
    address: z.string().optional(),

    // Admin
    poste: z.string().optional(),
}).superRefine((data, ctx) => {
    if (data.role === 'formateur') {
        if (!data.matricule) ctx.addIssue({ code: z.ZodIssueCode.custom, message: "Matricule requis", path: ['matricule'] });
        if (!data.specialty) ctx.addIssue({ code: z.ZodIssueCode.custom, message: "Spécialité requise", path: ['specialty'] });
        if (!data.type) ctx.addIssue({ code: z.ZodIssueCode.custom, message: "Type requis", path: ['type'] });
    }
    if (data.role === 'stagiaire') {
        if (!data.cef_number) ctx.addIssue({ code: z.ZodIssueCode.custom, message: "CEF requis", path: ['cef_number'] });
        if (!data.date_naissance) ctx.addIssue({ code: z.ZodIssueCode.custom, message: "Date naissance requise", path: ['date_naissance'] });
    }
    if (data.role === 'parent') {
        if (!data.cin) ctx.addIssue({ code: z.ZodIssueCode.custom, message: "CIN requis", path: ['cin'] });
    }
});

type UserFormValues = z.infer<typeof userSchema>;

export default function UsersPage() {
    const [roleFilter, setRoleFilter] = useState<string>('');
    const [isModalOpen, setIsModalOpen] = useState(false);

    const { data: usersData, isLoading: isUsersLoading, error } = useUsers(roleFilter || undefined);
    const createUser = useCreateUser();
    const deleteUser = useDeleteUser();

    const { register, handleSubmit, watch, reset, formState: { errors } } = useForm<UserFormValues>({
        resolver: zodResolver(userSchema) as any,
        defaultValues: {
            role: 'stagiaire', // default
            type: 'permanent',
            status: 'actif',
        }
    });

    const selectedRole = watch('role');

    const onSubmit = (data: UserFormValues) => {
        createUser.mutate(data as any, {
            onSuccess: () => {
                setIsModalOpen(false);
                reset();
            }
        });
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
                <Button onClick={() => setIsModalOpen(true)}>
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
                                                <div className="font-semibold">CEF: {user.stagiaire.cef_number}</div>
                                                <div>Né(e): {user.stagiaire.date_naissance}</div>
                                            </>
                                        )}
                                    </td>
                                    <td className="p-4 text-right space-x-2">
                                        <Button variant="ghost" size="icon" onClick={() => handleDelete(user.id)}>
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
                onClose={() => setIsModalOpen(false)}
                title="Ajouter un utilisateur"
            >
                <form onSubmit={handleSubmit((data) => onSubmit(data as UserFormValues))} className="space-y-4">
                    <div className="grid gap-2">
                        <Label>Rôle</Label>
                        <select
                            {...register('role')}
                            className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2"
                        >
                            <option value="stagiaire">Stagiaire</option>
                            <option value="formateur">Formateur</option>
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
                        <Label>Mot de passe</Label>
                        <Input {...register('password')} type="password" placeholder="********" />
                        {errors.password && <p className="text-red-500 text-xs">{errors.password.message}</p>}
                    </div>

                    {/* Conditional Fields */}
                    {selectedRole === 'formateur' && (
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
                                <Label>Numéro CEF (Massar)</Label>
                                <Input {...register('cef_number')} placeholder="M13000..." />
                                {errors.cef_number && <p className="text-red-500 text-xs">{errors.cef_number.message}</p>}
                            </div>
                            <div className="grid gap-2">
                                <Label>Date de Naissance</Label>
                                <Input {...register('date_naissance')} type="date" />
                                {errors.date_naissance && <p className="text-red-500 text-xs">{errors.date_naissance.message}</p>}
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
                        <Button type="button" variant="outline" onClick={() => setIsModalOpen(false)}>Annuler</Button>
                        <Button type="submit" disabled={createUser.isPending}>
                            {createUser.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
                            Créer
                        </Button>
                    </div>
                </form>
            </Modal>
        </div>
    );
}
