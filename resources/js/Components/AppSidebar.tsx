import { Link, usePage } from "@inertiajs/react";

type SidebarKey = "dashboard" | "feiras" | "catalogo" | "auditoria" | "relatorios" | "perfil";

type AuthUser = {
    name: string;
    email: string;
};

type AppSidebarProps = {
    activeItem?: SidebarKey;
};

export default function AppSidebar({ activeItem = "dashboard" }: AppSidebarProps) {
    const user = usePage().props.auth.user as AuthUser;

    const itemClass = (key: SidebarKey) =>
        key === activeItem
            ? "flex items-center px-6 py-3 text-white bg-white/10 font-bold border-l-4 border-blue-400 scale-95 active:opacity-80 transition-transform"
            : "flex items-center px-6 py-3 text-slate-400 hover:text-white transition-colors hover:bg-white/5 transition-all duration-200";

    return (
        <nav className="fixed top-0 left-0 h-screen flex flex-col z-40 bg-[#283044] dark:bg-slate-950 w-64 shadow-[20px_0_40px_-10px_rgba(19,27,46,0.12)] font-manrope tracking-wide">
            <div className="px-6 py-8">
                <span className="text-lg font-bold text-white tracking-widest uppercase block">ABDL</span>
                <span className="text-slate-400 text-xs font-semibold mt-1 block">Auditoria e Análise Financeira</span>
            </div>

            <div className="flex flex-col flex-1 mt-4">
                <Link className={itemClass("dashboard")} href={route("dashboard")}>
                    <span className="material-symbols-outlined mr-3">dashboard</span>
                    <span>Dashboard</span>
                </Link>

                <Link className={itemClass("catalogo")} href={route("catalogo.index")}>
                    <span className="material-symbols-outlined mr-3">menu_book</span>
                    <span>Catálogo</span>
                </Link>

                <Link className={itemClass("relatorios")} href={route("relatorios.index")}>
                    <span className="material-symbols-outlined mr-3">analytics</span>
                    <span>Relatórios</span>
                </Link>
            </div>

            <div className="p-6 mt-auto">
                <Link href={route("profile.edit")} className="flex items-center gap-3 group">
                    <img
                        alt="User"
                        className="w-10 h-10 rounded-full object-cover border-2 border-white/10 group-hover:border-blue-400 transition-all"
                        src={`https://ui-avatars.com/api/?name=${encodeURIComponent(user.name)}&background=00246a&color=fff`}
                    />
                    <div className="overflow-hidden">
                        <p className="text-white text-sm font-bold truncate">{user.name}</p>
                        <p className="text-slate-400 text-xs truncate">Acesso Nível 5</p>
                    </div>
                </Link>
            </div>
        </nav>
    );
}
