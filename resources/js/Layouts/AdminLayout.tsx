import { PropsWithChildren } from 'react';
import { Link, usePage, router } from '@inertiajs/react';
import { PageProps } from '@/types';

const navItems = [
    { label: 'Dashboard', href: '/admin' },
    { label: 'Productos', href: '/admin/products' },
    { label: 'Categorías', href: '/admin/categories' },
    { label: 'Ordenes', href: '/admin/orders' },
];

export default function AdminLayout({ children }: PropsWithChildren) {
    const { auth, url } = usePage<PageProps>().props;
    const currentUrl = usePage().url;

    const handleLogout = () => {
        router.post('/logout');
    };

    return (
        <div className="min-h-screen bg-stone-50 text-stone-900">
            <div className="flex">
                {/* Sidebar */}
                <aside className="w-64 shrink-0 min-h-screen border-r border-stone-200 bg-white flex flex-col">
                    <div className="px-6 py-5 border-b border-stone-200">
                        <span className="text-lg font-semibold tracking-tight">
                            Panel Ecommerce
                        </span>
                    </div>

                    <nav className="flex-1 px-3 py-4 space-y-1">
                        {navItems.map((item) => {
                            const isActive = currentUrl.startsWith(item.href) &&
                                (item.href !== '/admin' || currentUrl === '/admin');

                            return (
                                <Link
                                    key={item.href}
                                    href={item.href}
                                    className={`block rounded-md px-3 py-2 text-sm font-medium transition-colors ${
                                        isActive
                                            ? 'bg-amber-50 text-amber-900 border border-amber-200'
                                            : 'text-stone-600 hover:bg-stone-100'
                                    }`}
                                >
                                    {item.label}
                                </Link>
                            );
                        })}
                    </nav>

                    <div className="px-3 py-4 border-t border-stone-200">
                        <div className="px-3 mb-2">
                            <p className="text-sm font-medium truncate">{auth.user.name}</p>
                            <p className="text-xs text-stone-500 truncate">{auth.user.email}</p>
                        </div>
                        <button
                            onClick={handleLogout}
                            className="w-full text-left rounded-md px-3 py-2 text-sm font-medium text-stone-600 hover:bg-stone-100 transition-colors"
                        >
                            Cerrar sesión
                        </button>
                    </div>
                </aside>

                {/* Contenido */}
                <main className="flex-1 min-h-screen">
                    <div className="max-w-6xl mx-auto px-8 py-8">{children}</div>
                </main>
            </div>
        </div>
    );
}