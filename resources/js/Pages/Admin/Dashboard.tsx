import AdminLayout from '@/Layouts/AdminLayout';
import { ReactElement } from 'react';

function Dashboard() {
    return (
        <div>
            <h1 className="text-2xl font-semibold tracking-tight">Dashboard</h1>
            <p className="text-stone-600 mt-1">
                Resumen de tu tienda online.
            </p>
        </div>
    );
}

Dashboard.layout = (page: ReactElement) => <AdminLayout>{page}</AdminLayout>;

export default Dashboard;
