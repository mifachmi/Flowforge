import { BrowserRouter, Routes, Route, Navigate } from "react-router-dom";
import { QueryClient, QueryClientProvider } from "@tanstack/react-query";
import LoginPage from "./pages/LoginPage";
import DashboardPage from "./pages/DashboardPage";
import WorkflowDetailPage from "./pages/WorkflowDetailPage";
import { useAuthStore } from "./stores/authStore";

const queryClient = new QueryClient({
    defaultOptions: {
        queries: { retry: 1, staleTime: 30_000 },
    },
});

function PrivateRoute({ children }: { children: React.ReactNode }) {
    const isAuthenticated = useAuthStore((s) => s.isAuthenticated());
    return isAuthenticated ? <>{children}</> : <Navigate to="/login" />;
}

export default function App() {
    return (
        <QueryClientProvider client={queryClient}>
            <BrowserRouter>
                <Routes>
                    <Route path="/login" element={<LoginPage />} />
                    <Route
                        path="/"
                        element={
                            <PrivateRoute>
                                <DashboardPage />
                            </PrivateRoute>
                        }
                    />
                    <Route
                        path="/workflows/:id"
                        element={
                            <PrivateRoute>
                                <WorkflowDetailPage />
                            </PrivateRoute>
                        }
                    />
                </Routes>
            </BrowserRouter>
        </QueryClientProvider>
    );
}
