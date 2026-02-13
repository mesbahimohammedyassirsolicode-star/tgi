import { useParams, useNavigate } from 'react-router-dom';
import { useQuery } from '@tanstack/react-query';
import { groupsApi } from '../services/api/groups';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Button } from '../components/ui/button';
import { Loader2, ArrowLeft } from 'lucide-react';
import { toast } from 'sonner';

export default function GroupDetailPage() {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { data, isLoading, error } = useQuery({
    queryKey: ['groups', id],
    queryFn: () => groupsApi.get(Number(id)),
    enabled: !!id,
  });

  if (error) toast.error('Erreur chargement du groupe');

  return (
    <div className="space-y-6">
      <Button variant="ghost" size="sm" onClick={() => navigate('/groups')}>
        <ArrowLeft className="w-4 h-4 mr-2" /> Retour
      </Button>
      {isLoading ? (
        <div className="flex justify-center py-12"><Loader2 className="h-8 w-8 animate-spin text-primary-600" /></div>
      ) : data ? (
        <Card>
          <CardHeader>
            <CardTitle>{data.label}</CardTitle>
          </CardHeader>
          <CardContent className="text-sm text-gray-600">
            <p>Filière : {data.filiere?.label}</p>
            <p>Année : {data.annee_scolaire?.label}</p>
            <p>Capacité : {data.capacity}</p>
            {data.stagiaires && <p>Inscrits : {data.stagiaires.length}</p>}
            <Button size="sm" className="mt-2" onClick={() => navigate(`/groups/${data.id}/attendance-summary`)}>
              Voir présences (à risque)
            </Button>
          </CardContent>
        </Card>
      ) : null}
    </div>
  );
}
