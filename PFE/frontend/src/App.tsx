import { Routes, Route, Navigate } from 'react-router-dom';
import { useAuth } from './context/AuthContext';
import LoginPage from './pages/LoginPage';
import DashboardLayout from './layouts/DashboardLayout';
import DashboardPage from './pages/DashboardPage';
import AcademicYearsPage from './pages/academic/AcademicYearsPage';
import FilieresPage from './pages/academic/FilieresPage';
import GroupsPage from './pages/GroupsPage';
import GroupDetailPage from './pages/GroupDetailPage';
import ModulesPage from './pages/ModulesPage';
import AffectationsPage from './pages/AffectationsPage';
import AttendancePage from './pages/AttendancePage';
import SeanceRollCallPage from './pages/SeanceRollCallPage';
import EvaluationsPage from './pages/EvaluationsPage';
import StagesPage from './pages/StagesPage';
import FeedbackPage from './pages/FeedbackPage';
import NotificationsPage from './pages/NotificationsPage';
import ParentChildrenPage from './pages/parent/ParentChildrenPage';
import ParentChildDetailPage from './pages/parent/ParentChildDetailPage';
import UsersPage from './pages/UsersPage';
import GroupAttendanceRiskPage from './pages/GroupAttendanceRiskPage';
import TimetablePage from './pages/TimetablePage';
import AffectationGradesPage from './pages/AffectationGradesPage';
import ProgressPage from './pages/ProgressPage';
import { Loader2 } from 'lucide-react';

function ProtectedRoute({ children }: { children: React.ReactNode }) {
  const { isAuthenticated, isLoading } = useAuth();

  if (isLoading) {
    return (
      <div className="h-screen w-full flex items-center justify-center">
        <Loader2 className="animate-spin h-8 w-8 text-primary-600" />
      </div>
    );
  }

  if (!isAuthenticated) {
    return <Navigate to="/login" replace />;
  }

  return <>{children}</>;
}

export default function App() {
  return (
    <Routes>
      <Route path="/login" element={<LoginPage />} />

      {/* Protected Routes */}
      <Route element={<ProtectedRoute><DashboardLayout /></ProtectedRoute>}>
        <Route path="/dashboard" element={<DashboardPage />} />
        <Route path="/" element={<Navigate to="/dashboard" replace />} />

        {/* Admin only */}
        <Route path="/users" element={<UsersPage />} />
        <Route path="/academic/years" element={<AcademicYearsPage />} />
        <Route path="/academic/filieres" element={<FilieresPage />} />

        {/* General */}
        <Route path="/groups" element={<GroupsPage />} />
        <Route path="/groups/:id" element={<GroupDetailPage />} />
        <Route path="/groups/:id/attendance-summary" element={<GroupAttendanceRiskPage />} />
        <Route path="/timetable" element={<TimetablePage />} />
        <Route path="/progress" element={<ProgressPage />} />
        <Route path="/modules" element={<ModulesPage />} />
        <Route path="/affectations" element={<AffectationsPage />} />
        <Route path="/affectations/:id/grades" element={<AffectationGradesPage />} />
        <Route path="/attendance" element={<AttendancePage />} />
        <Route path="/attendance/seances/:id" element={<SeanceRollCallPage />} />
        <Route path="/evaluations" element={<EvaluationsPage />} />
        <Route path="/stages" element={<StagesPage />} />
        <Route path="/feedback" element={<FeedbackPage />} />
        <Route path="/notifications" element={<NotificationsPage />} />

        {/* Parent only */}
        <Route path="/parent/children" element={<ParentChildrenPage />} />
        <Route path="/parent/children/:id" element={<ParentChildDetailPage />} />

        {/* Fallback */}
        <Route path="*" element={<Navigate to="/dashboard" replace />} />
      </Route>
    </Routes>
  );
}
