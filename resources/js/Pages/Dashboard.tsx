import AppSidebar from '@/Components/AppSidebar';
import SyncFairModal from '@/Components/SyncFairModal';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

type AuthUser = {
    name: string;
    email: string;
};

export default function Dashboard() {
    const user = usePage().props.auth.user as AuthUser;
    const [showSyncModal, setShowSyncModal] = useState(false);

    return (
        <>
            <Head title="Dashboard" />
            <Head>
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="" />
                <link
                    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap"
                    rel="stylesheet"
                />
                <link
                    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,500,0,0"
                    rel="stylesheet"
                />
            </Head>

            <div className="min-h-screen bg-base font-[Inter] text-primary">
                <AppSidebar
                    activeItem="feiras"
                    brandTitle="ABDL"
                    brandSubtitle="Auditoria e Gerenciamento"
                />

                <div className="ml-64">
                    <header className="sticky top-0 z-30 flex w-full items-center justify-between border-b border-primary/10 bg-base/90 px-8 py-4 backdrop-blur-xl">
                        <div className="relative w-full max-w-md">
                            <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-primary/45">
                                search
                            </span>
                            <input
                                type="text"
                                placeholder="Buscar feiras registradas..."
                                className="w-full rounded-full border border-primary/15 bg-white py-2 pl-10 pr-4 text-sm shadow-sm outline-none transition focus:border-secondary focus:ring-4 focus:ring-secondary/20"
                            />
                        </div>

                        <div className="ml-6 flex items-center gap-4">
                            <Link
                                href={route('profile.edit')}
                                className="rounded-lg px-4 py-2 text-sm font-semibold text-primary transition hover:bg-primary/5"
                            >
                                Perfil
                            </Link>
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="rounded-lg bg-primary px-5 py-2.5 text-sm font-bold text-white shadow-lg shadow-primary/20 transition hover:bg-primary/90"
                            >
                                Logout
                            </Link>
                        </div>
                    </header>

                    <main className="min-h-[calc(100vh-73px)] p-8">
                        <div className="mb-10 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                            <div>
                                <h2 className="font-[Manrope] text-3xl font-extrabold tracking-tight text-primary">
                                    Gestao de Feiras
                                </h2>
                                <p className="mt-1 text-sm text-primary/65">
                                    Selecione o ambiente de auditoria ou inicie uma nova sincronizacao.
                                </p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setShowSyncModal(true)}
                                className="flex items-center space-x-2 rounded-xl border-2 border-primary/15 bg-white px-6 py-3 font-bold text-primary shadow-sm transition hover:-translate-y-0.5 hover:border-secondary/45"
                            >
                                <span className="material-symbols-outlined text-xl">sync</span>
                                <span>Sincronizar Nova Feira</span>
                            </button>
                        </div>

                        <div className="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                            <div className="group rounded-2xl border border-primary/10 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/10">
                                <div className="mb-6 flex items-start justify-between">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-secondary/12 text-secondary">
                                        <span className="material-symbols-outlined text-[28px]">auto_stories</span>
                                    </div>
                                    <span className="flex items-center gap-1.5 rounded-full bg-secondary/15 px-3 py-1 text-[10px] font-extrabold uppercase tracking-widest text-secondary">
                                        <span className="h-1.5 w-1.5 rounded-full bg-secondary" /> Ativa
                                    </span>
                                </div>
                                <h3 className="font-[Manrope] text-xl font-bold text-primary">Saquarema 2025</h3>
                                <p className="mb-6 mt-1 flex items-center text-sm text-primary/65">
                                    <span className="material-symbols-outlined mr-1 text-base">calendar_month</span>
                                    15 Abr - 30 Abr, 2025
                                </p>
                                <div className="flex items-center justify-between border-t border-primary/10 pt-4">
                                    <div className="flex flex-col">
                                        <span className="text-[10px] font-bold uppercase text-primary/50">Auditados</span>
                                        <span className="text-sm font-bold text-primary">12.4k itens</span>
                                    </div>
                                    <button className="rounded-lg bg-primary px-6 py-2 text-sm font-bold text-white transition hover:bg-primary/90">
                                        Selecionar
                                    </button>
                                </div>
                            </div>

                            <div className="overflow-hidden rounded-2xl border border-secondary/20 bg-white p-6 shadow-sm">
                                <div className="mb-6 flex items-start justify-between">
                                    <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-primary/8 text-primary">
                                        <span className="material-symbols-outlined animate-spin text-[28px] [animation-duration:3s]">
                                            sync
                                        </span>
                                    </div>
                                    <span className="rounded-full bg-primary/10 px-3 py-1 text-[10px] font-extrabold uppercase tracking-widest text-primary">
                                        Processando...
                                    </span>
                                </div>
                                <h3 className="font-[Manrope] text-xl font-bold text-primary/70">Niteroi 2024</h3>
                                <p className="mb-6 mt-1 flex items-center text-sm text-primary/55">
                                    <span className="material-symbols-outlined mr-1 text-base">calendar_month</span>
                                    10 Out - 25 Out, 2024
                                </p>
                                <div className="pt-4">
                                    <div className="h-1.5 w-full overflow-hidden rounded-full bg-primary/10">
                                        <div className="h-full w-[65%] rounded-full bg-secondary" />
                                    </div>
                                    <p className="mt-2 text-[10px] font-bold uppercase tracking-wide text-secondary">
                                        Indexando Catalogo: 65%
                                    </p>
                                </div>
                            </div>

                            <div className="rounded-2xl border border-dashed border-primary/25 bg-white/70 p-6 text-center transition hover:border-secondary/50 hover:bg-secondary/5">
                                <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary/8 text-primary/70">
                                    <span className="material-symbols-outlined text-3xl">add</span>
                                </div>
                                <p className="font-[Manrope] font-bold text-primary/80">Registrar Nova Feira</p>
                                <p className="mt-1 px-8 text-xs text-primary/55">
                                    Inicie a sincronizacao de dados de um novo evento ID.
                                </p>
                            </div>
                        </div>
                    </main>
                </div>

                <SyncFairModal show={showSyncModal} onClose={() => setShowSyncModal(false)} />
            </div>
        </>
    );
}
