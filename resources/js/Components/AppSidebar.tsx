import { Link, usePage } from '@inertiajs/react';

type SidebarKey = 'dashboard' | 'feiras' | 'catalogo' | 'relatorios' | 'perfil';

type AuthUser = {
    name: string;
    email: string;
};

type AppSidebarProps = {
    activeItem?: SidebarKey;
    brandTitle?: string;
    brandSubtitle?: string;
};

export default function AppSidebar({
    activeItem = 'dashboard',
    brandTitle = 'ABDL',
    brandSubtitle = 'Auditoria e Gerenciamento',
}: AppSidebarProps) {
    const user = usePage().props.auth.user as AuthUser;

    const itemClass = (key: SidebarKey) =>
        key === activeItem
            ? 'group flex items-center rounded-r-lg border-l-4 border-secondary bg-white/15 px-4 py-3 font-bold text-white'
            : 'group flex items-center rounded-lg px-4 py-3 text-white/70 transition hover:bg-white/10 hover:text-white';

    return (
        <aside className="fixed left-0 top-0 z-40 flex h-screen w-64 flex-col bg-primary text-white shadow-[20px_0_40px_-10px_rgba(31,26,23,0.25)]">
            <div className="px-6 py-8">
                <h1 className="font-[Manrope] text-lg font-extrabold uppercase tracking-[0.18em]">
                    {brandTitle}
                </h1>
                <p className="mt-1 text-xs text-white/65">{brandSubtitle}</p>
            </div>

            <nav className="flex-1 space-y-1 px-3">
                <Link href={route('dashboard')} className={itemClass('dashboard')}>
                    <span className="material-symbols-outlined mr-3 text-[20px]">dashboard</span>
                    <span className="text-sm font-medium">Dashboard</span>
                </Link>

                <a className={itemClass('feiras')} href="#">
                    <span className="material-symbols-outlined mr-3 text-[20px]">event</span>
                    <span className="text-sm">Feiras</span>
                </a>

                <a className={itemClass('catalogo')} href="#">
                    <span className="material-symbols-outlined mr-3 text-[20px]">menu_book</span>
                    <span className="text-sm font-medium">Catalogo</span>
                </a>

                <a className={itemClass('relatorios')} href="#">
                    <span className="material-symbols-outlined mr-3 text-[20px]">analytics</span>
                    <span className="text-sm font-medium">Relatorios</span>
                </a>

                <Link href={route('profile.edit')} className={itemClass('perfil')}>
                    <span className="material-symbols-outlined mr-3 text-[20px]">person</span>
                    <span className="text-sm font-medium">Perfil</span>
                </Link>
            </nav>

            <div className="space-y-3 p-6">

                <div className="rounded-xl bg-white/10 p-3">
                    <p className="truncate text-sm font-bold">{user.name}</p>
                    <p className="truncate text-xs text-white/65">{user.email}</p>
                </div>
            </div>
        </aside>
    );
}
