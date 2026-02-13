import { useQuery } from '@tanstack/react-query';
import { useTranslation } from 'react-i18next';
import { useNavigate } from 'react-router-dom';
import { parentApi } from '../../services/api/parent';
import { Card, CardContent, CardHeader, CardTitle } from '../../components/ui/card';
import { Loader2 } from 'lucide-react';
import { toast } from 'sonner';

export default function ParentChildrenPage() {
  const { t } = useTranslation();
  const navigate = useNavigate();
  const { data, isLoading, error } = useQuery({
    queryKey: ['parent', 'children'],
    queryFn: parentApi.getChildren,
  });

  if (error) toast.error('Erreur chargement des enfants');

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">{t('nav.parentChildren')}</h1>
      {isLoading ? (
        <div className="flex justify-center py-12"><Loader2 className="h-8 w-8 animate-spin text-primary-600" /></div>
      ) : (
        <div className="grid gap-4 md:grid-cols-2">
          {data?.map((c) => (
            <Card key={c.id} className="cursor-pointer hover:shadow-md transition" onClick={() => navigate(`/parent/children/${c.id}`)}>
              <CardHeader className="pb-2">
                <CardTitle className="text-lg">{c.user?.name ?? `Stagiaire #${c.id}`}</CardTitle>
              </CardHeader>
              <CardContent className="text-sm text-gray-600">
                <p>CEF : {c.cef_number}</p>
                <p>Statut : {c.status}</p>
                {c.groupes?.length ? <p>Groupes : {c.groupes.map((g: { label: string }) => g.label).join(', ')}</p> : null}
              </CardContent>
            </Card>
          ))}
        </div>
      )}
    </div>
  );
}
