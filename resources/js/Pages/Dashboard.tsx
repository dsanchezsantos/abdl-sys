import AppLayout from '@/Layouts/AppLayout';
import SyncFairModal from '@/Components/SyncFairModal';
import { Head, Link, usePage } from '@inertiajs/react';
import { useState } from 'react';

type AuthUser = {
    name: string;
    email: string;
};

export default function Dashboard({ feiras }: { feiras: any[] }) {
    const user = usePage().props.auth.user as AuthUser;
    const [showSyncModal, setShowSyncModal] = useState(false);

    return (
        <AppLayout activeItem="feiras">
            <Head title="Painel de Controle" />

            {/* TopNavBar */}
            <header className="sticky top-0 z-30 flex w-full items-center justify-between border-b border-primary/10 bg-surface/90 px-8 py-4 backdrop-blur-xl">
                <div className="flex items-center space-x-4">
                    <h2 className="font-[Manrope] text-xl font-bold text-primary">
                        Gestão de Feiras
                    </h2>
                </div>
                <div className="flex items-center space-x-4">
                    <div className="text-right mr-4">
                        <p className="text-sm font-bold text-primary">{user.name}</p>
                        <p className="text-xs text-primary/60">{user.email}</p>
                    </div>
                    <button className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/5 text-primary transition hover:bg-primary/10">
                        <span className="material-symbols-outlined">notifications</span>
                    </button>
                </div>
            </header>

            <main className="p-8">
                <div className="mb-10 flex flex-col gap-4 md:flex-row md:items-end md:justify-between">
                    <div>
                        <h2 className="font-[Manrope] text-3xl font-extrabold tracking-tight text-primary">
                            Bem-vindo de volta
                        </h2>
                        <p className="mt-1 text-sm text-primary/65">
                            Selecione o ambiente de auditoria ou inicie uma nova sincronização.
                        </p>
                    </div>
                </div>

                <div className="grid grid-cols-1 gap-6 md:grid-cols-2 xl:grid-cols-3">
                    {feiras.map((feira: any) => (
                        <div key={feira.id} className={`group rounded-2xl border border-primary/10 bg-white p-6 shadow-sm transition hover:-translate-y-0.5 hover:shadow-xl hover:shadow-primary/10 ${feira.is_sincronizando ? 'animate-pulse' : ''}`}>
                            <div className="mb-6 flex items-start justify-between">
                                <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-secondary/12 text-secondary">
                                    <span className="material-symbols-outlined text-[28px]">auto_stories</span>
                                </div>
                                <span className={`flex items-center gap-1.5 rounded-full px-3 py-1 text-[10px] font-extrabold uppercase tracking-widest ${feira.is_sincronizando ? 'bg-primary animate-pulse text-white' : feira.status === 'EM_ANDAMENTO' ? 'bg-secondary/15 text-secondary' : 'bg-primary/10 text-primary'}`}>
                                    <span className={`h-1.5 w-1.5 rounded-full ${feira.is_sincronizando ? 'bg-white animate-spin' : feira.status === 'EM_ANDAMENTO' ? 'bg-secondary' : 'bg-primary'}`} /> 
                                    {feira.is_sincronizando ? 'Sincronizando...' : feira.status}
                                </span>
                            </div>
                            <h3 className="font-[Manrope] text-xl font-bold text-primary">{feira.nome}</h3>
                            <p className="mb-6 mt-1 flex items-center text-sm text-primary/65">
                                <span className="material-symbols-outlined mr-1 text-base">calendar_month</span>
                                {new Date(feira.data_inicio).toLocaleDateString()} - {new Date(feira.data_fim).toLocaleDateString()}
                            </p>
                            <div className="flex items-center justify-between border-t border-primary/10 pt-4">
                                <div className="flex flex-col">
                                    <span className="text-[10px] font-bold uppercase text-primary/50">Auditados</span>
                                    <span className="text-sm font-bold text-primary">0 itens</span>
                                </div>
                                <Link
                                    href={route('feiras.auditoria', feira.id)}
                                    className="rounded-lg bg-primary px-6 py-2 text-sm font-bold text-white transition hover:bg-primary/90"
                                >
                                    Selecionar
                                </Link>
                            </div>
                        </div>
                    ))}

                    <div
                        onClick={() => setShowSyncModal(true)}
                        className="rounded-2xl border border-dashed border-primary/25 bg-white/70 p-6 text-center transition hover:border-secondary/50 hover:bg-secondary/5 cursor-pointer"
                    >
                        <div className="mx-auto mb-4 flex h-14 w-14 items-center justify-center rounded-full bg-primary/8 text-primary/70">
                            <span className="material-symbols-outlined text-3xl">add</span>
                        </div>
                        <p className="font-[Manrope] font-bold text-primary/80">Registrar Nova Feira</p>
                        <p className="mt-1 px-8 text-xs text-primary/55">
                            Inicie a sincronização de dados de um novo evento ID.
                        </p>
                    </div>
                </div>
            </main>

            <SyncFairModal show={showSyncModal} onClose={() => setShowSyncModal(false)} />
        </AppLayout>
    );
}
