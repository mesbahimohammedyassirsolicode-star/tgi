import { useEffect, useMemo, useState } from 'react';
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query';
import { Card, CardContent, CardHeader, CardTitle } from '../components/ui/card';
import { Button } from '../components/ui/button';
import { Loader2 } from 'lucide-react';
import { toast } from 'sonner';
import { useAuth } from '../context/AuthContext';
import { userService } from '../services/userService';
import { modulesApi } from '../services/api/modules';
import { academicStructureApi } from '../services/api/academicStructure';
import { formateurAssignmentsApi } from '../services/api/formateurAssignments';

type TeacherOption = { id: number; label: string };

export default function FormateurAssignmentsPage() {
  const { user } = useAuth();
  const qc = useQueryClient();
  const [teacherId, setTeacherId] = useState<number | ''>('');
  const [academicYear, setAcademicYear] = useState<number | ''>('');
  const [selectedModuleIds, setSelectedModuleIds] = useState<number[]>([]);

  const { data: years = [], isLoading: isYearsLoading } = useQuery({
    queryKey: ['academic', 'years', user?.id, user?.role],
    queryFn: academicStructureApi.getYears,
  });

  const { data: usersRaw, isLoading: isTeachersLoading } = useQuery({
    queryKey: ['users', user?.id, user?.role, 'formateur'],
    queryFn: () => userService.getAll('formateur'),
  });

  const teacherOptions: TeacherOption[] = useMemo(() => {
    const rows = Array.isArray((usersRaw as any)?.data) ? (usersRaw as any).data : [];
    return rows
      .filter((u: any) => u?.formateur?.id)
      .map((u: any) => ({ id: Number(u.formateur.id), label: `${u.name} (${u.email})` }));
  }, [usersRaw]);

  const { data: modules = [], isLoading: isModulesLoading } = useQuery({
    queryKey: ['modules', user?.id, user?.role, 'assignments'],
    queryFn: () => modulesApi.list(),
  });

  const { data: assigned, isLoading: isAssignedLoading } = useQuery({
    queryKey: ['formateur-assignments', user?.id, user?.role, teacherId, academicYear],
    queryFn: () => formateurAssignmentsApi.byTeacher(Number(teacherId), Number(academicYear)),
    enabled: !!teacherId && !!academicYear,
  });

  const saveMutation = useMutation({
    mutationFn: (payload: { teacher_id: number; academic_year: number; module_ids: number[] }) =>
      formateurAssignmentsApi.save(payload),
    onSuccess: () => {
      toast.success('Affectations formateur enregistrées.');
      qc.invalidateQueries({ queryKey: ['formateur-assignments', user?.id, user?.role, teacherId, academicYear] });
      qc.invalidateQueries({ queryKey: ['dashboard', user?.id] });
    },
    onError: () => toast.error("Erreur d'enregistrement des affectations."),
  });

  const loading = isYearsLoading || isTeachersLoading || isModulesLoading;

  const currentYearId = useMemo(() => {
    const y = years.find((row) => row.is_current);
    return y?.id;
  }, [years]);

  const onChooseYear = (value: string) => {
    const parsed = value ? Number(value) : '';
    setAcademicYear(parsed);
    setSelectedModuleIds([]);
  };

  const onChooseTeacher = (value: string) => {
    const parsed = value ? Number(value) : '';
    setTeacherId(parsed);
    setSelectedModuleIds([]);
  };

  const assignedModuleIds = useMemo(
    () => (assigned?.modules ?? []).map((m) => Number(m.id)),
    [assigned]
  );

  useEffect(() => {
    if (assignedModuleIds.length > 0) {
      setSelectedModuleIds(assignedModuleIds);
    } else if (teacherId && academicYear) {
      setSelectedModuleIds([]);
    }
  }, [assignedModuleIds, teacherId, academicYear]);

  const toggleModule = (moduleId: number) => {
    setSelectedModuleIds((prev) =>
      prev.includes(moduleId) ? prev.filter((id) => id !== moduleId) : [...prev, moduleId]
    );
  };

  const handleSave = () => {
    if (!teacherId || !academicYear) {
      toast.error('Choisissez un formateur et une année scolaire.');
      return;
    }
    saveMutation.mutate({
      teacher_id: Number(teacherId),
      academic_year: Number(academicYear),
      module_ids: selectedModuleIds,
    });
  };

  return (
    <div className="space-y-6">
      <h1 className="text-2xl font-bold text-gray-900">Affectation formateurs</h1>

      <Card>
        <CardHeader>
          <CardTitle>Paramètres d'affectation</CardTitle>
        </CardHeader>
        <CardContent className="grid gap-4 md:grid-cols-2">
          <div>
            <label className="block text-sm font-medium mb-1">Année scolaire</label>
            <select
              value={academicYear}
              onChange={(e) => onChooseYear(e.target.value)}
              className="w-full rounded border border-gray-300 px-3 py-2"
              disabled={loading}
            >
              <option value="">Sélectionner...</option>
              {years.map((y) => (
                <option key={y.id} value={y.id}>
                  {y.label}{y.id === currentYearId ? ' (En cours)' : ''}
                </option>
              ))}
            </select>
          </div>

          <div>
            <label className="block text-sm font-medium mb-1">Formateur</label>
            <select
              value={teacherId}
              onChange={(e) => onChooseTeacher(e.target.value)}
              className="w-full rounded border border-gray-300 px-3 py-2"
              disabled={loading}
            >
              <option value="">Sélectionner...</option>
              {teacherOptions.map((t) => (
                <option key={t.id} value={t.id}>{t.label}</option>
              ))}
            </select>
          </div>
        </CardContent>
      </Card>

      <Card>
        <CardHeader>
          <CardTitle>Modules à assigner</CardTitle>
        </CardHeader>
        <CardContent>
          {loading || isAssignedLoading ? (
            <div className="flex items-center justify-center py-8">
              <Loader2 className="h-6 w-6 animate-spin text-primary-600" />
            </div>
          ) : (
            <div className="grid gap-2 md:grid-cols-2 lg:grid-cols-3">
              {modules.map((m) => (
                <label key={m.id} className="flex items-center gap-2 rounded border p-2">
                  <input
                    type="checkbox"
                    checked={selectedModuleIds.includes(m.id)}
                    onChange={() => toggleModule(m.id)}
                    disabled={!teacherId || !academicYear}
                  />
                  <span className="text-sm">{m.code} - {m.label}</span>
                </label>
              ))}
            </div>
          )}
        </CardContent>
      </Card>

      <div className="flex justify-end">
        <Button onClick={handleSave} disabled={saveMutation.isPending || !teacherId || !academicYear}>
          {saveMutation.isPending && <Loader2 className="mr-2 h-4 w-4 animate-spin" />}
          Enregistrer les affectations
        </Button>
      </div>
    </div>
  );
}
