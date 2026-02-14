import { useEffect } from 'react';
import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { groupsApi } from '../services/api/groups';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { useAuth } from '../context/AuthContext';

export default function GroupsPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { user } = useAuth();
  const { data, isLoading, error } = useQuery({
    queryKey: ['groups', user?.id, user?.role],
    queryFn: () => groupsApi.list({ per_page: 20 }),
  });

  useEffect(() => {
    if (error) toast.error('Erreur chargement des groupes');
  }, [error]);

  const items = Array.isArray(data?.items) ? data.items : [];
  const total = data?.meta?.total ?? items.length;
  const isEmpty = !isLoading && !error && items.length === 0;

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">{t('nav.groups')}</h1>
      {isLoading ? (
        <div className="flex justify-center py-12"><Loader2 className="h-8 w-8 animate-spin text-primary-600" /></div>
      ) : error ? (
        <div className="py-12 text-center text-gray-500">Impossible de charger les groupes. Vérifiez votre connexion.</div>
      ) : (
        <>
          <div className="grid gap-4 md:grid-cols-2 lg:grid-cols-3">
            {items.map((g) => (
              <Card key={g.id} className="cursor-pointer hover:shadow-md transition" onClick={() => navigate(`/groups/${g.id}`)}>
                <CardHeader className="pb-2">
                  <CardTitle className="text-lg">{g.label}</CardTitle>
                </CardHeader>
                <CardContent className="text-sm text-gray-600">
                  <p>Année : {g.annee_scolaire?.label ?? g.anneeScolaire?.label ?? g.annee_scolaire_id ?? '-'}</p>
                  <p>Filière : {g.filiere?.label ?? g.filiere_id}</p>
                  <p>Capacité : {g.capacity}</p>
                </CardContent>
              </Card>
            ))}
          </div>
          {isEmpty && (
            <div className="py-12 text-center text-gray-500 rounded-lg border border-dashed border-gray-300 bg-gray-50/50">
              <p className="font-medium">Aucun groupe trouvé.</p>
              <p className="text-sm mt-1">Les groupes sont créés par filière et année scolaire. Créez-en un ou vérifiez les filtres.</p>
            </div>
          )}
          {!isEmpty && data?.meta != null && (
            <p className="text-sm text-gray-500">
              Total : {total} groupe(s)
            </p>
          )}
        </>
      )}
    </div>
  );
}
