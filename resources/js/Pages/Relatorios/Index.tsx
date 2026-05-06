import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

type Relatorio = {
    id: number;
    nome: string;
    id_label?: string;
    created_at: string;
    status: 'concluido' | 'processando' | 'erro';
};

type Props = {
    relatorios: Relatorio[];
};

export default function Index({ relatorios }: Props) {
    const [searchTerm, setSearchTerm] = useState('');

    return (
        <AppLayout activeItem="relatorios">
            <Head title="Relatórios" />
            
            {/* TopNavBar */}
            <header className="flex justify-between items-center px-8 py-4 sticky top-0 z-30 bg-surface/80 backdrop-blur-xl">
                <div className="flex items-center gap-6">
                    <h1 className="text-on-surface font-extrabold text-xl font-headline">Feira Selecionada: Saquarema 2025</h1>
                    <div className="relative">
                        <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">search</span>
                        <input
                            className="bg-surface-container-low border-none rounded-full py-2 pl-10 pr-4 text-sm w-64 focus:ring-2 focus:ring-primary/20 placeholder:text-outline-variant"
                            placeholder="Pesquisar relatórios..." 
                            type="text"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                    </div>
                </div>
                <div className="flex items-center gap-4">
                    <button className="p-2 text-slate-500 hover:bg-slate-100 rounded-lg transition-all duration-200 active:scale-95">
                        <span className="material-symbols-outlined">notifications</span>
                    </button>
                    <button className="p-2 text-slate-500 hover:bg-slate-100 rounded-lg transition-all duration-200 active:scale-95">
                        <span className="material-symbols-outlined">settings</span>
                    </button>
                    <button className="bg-primary text-on-primary px-5 py-2 rounded-lg font-headline font-semibold flex items-center gap-2 hover:bg-primary-container transition-colors shadow-sm active:scale-95">
                        <span className="material-symbols-outlined text-[20px]">sync</span>Sincronizar Dados
                    </button>
                </div>
            </header>

            {/* Main Content Area */}
            <main className="p-8 flex-grow">
                {/* Page Header */}
                <div className="mb-10 flex flex-col gap-2">
                    <h2 className="text-4xl font-extrabold text-on-surface font-headline tracking-tight">Relatórios</h2>
                    <p className="text-on-surface-variant font-body max-w-2xl">Gerencie a prestação de contas institucional e gere relatórios auditados de alta precisão para entidades públicas e privadas.</p>
                </div>

                <div className="grid grid-cols-12 gap-8">
                    {/* Configuration Panel (Bento Style) */}
                    <section className="col-span-12 lg:col-span-5 flex flex-col gap-6">
                        <div className="bg-surface-container-low p-8 rounded-xl shadow-sm border border-outline-variant/15 flex flex-col gap-8">
                            <div className="flex items-center gap-3">
                                <div className="p-2 bg-primary-container rounded-lg">
                                    <span className="material-symbols-outlined text-white">settings_suggest</span>
                                </div>
                                <h3 className="text-xl font-bold font-headline">Configuração do Relatório</h3>
                            </div>
                            <div className="space-y-6">
                                <div className="flex flex-col gap-2">
                                    <label className="text-xs font-bold text-primary tracking-widest uppercase px-1">Tipo de Documento</label>
                                    <select className="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-lg py-3 px-4 font-body text-on-surface focus:border-primary/50 focus:ring-0 transition-all appearance-none cursor-pointer">
                                        <option>Relatório Geral de Vendas</option>
                                        <option>Auditoria de Inventário Físico</option>
                                        <option>Consolidado por Editora</option>
                                        <option>Desempenho de Representante</option>
                                    </select>
                                </div>
                                <div className="flex flex-col gap-2">
                                    <label className="text-xs font-bold text-primary tracking-widest uppercase px-1">Editora / Entidade</label>
                                    <select className="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-lg py-3 px-4 font-body text-on-surface focus:border-primary/50 focus:ring-0 transition-all appearance-none cursor-pointer">
                                        <option>Todas as Editoras</option>
                                        <option>Grupo Editorial Record</option>
                                        <option>Companhia das Letras</option>
                                        <option>Editora Sextante</option>
                                        <option>Representantes Regionais (Sul)</option>
                                    </select>
                                </div>
                                <div className="flex flex-col gap-2 pt-2">
                                    <label className="text-xs font-bold text-primary tracking-widest uppercase px-1">Intervalo de Datas</label>
                                    <div className="grid grid-cols-2 gap-4">
                                        <input className="bg-surface-container-lowest border border-outline-variant/30 rounded-lg py-3 px-4 font-body text-sm" type="date" defaultValue="2025-01-01" />
                                        <input className="bg-surface-container-lowest border border-outline-variant/30 rounded-lg py-3 px-4 font-body text-sm" type="date" defaultValue="2025-01-31" />
                                    </div>
                                </div>
                            </div>
                            <div className="mt-4">
                                <button className="w-full bg-gradient-to-br from-primary to-primary-container text-white py-5 rounded-lg font-headline font-extrabold text-lg flex items-center justify-center gap-3 shadow-lg hover:brightness-110 active:scale-[0.98] transition-all">
                                    <span className="material-symbols-outlined text-[28px]">picture_as_pdf</span>
                                    Gerar Relatório Oficial (PDF)
                                </button>
                            </div>
                        </div>
                        <div className="bg-primary text-on-primary-container p-6 rounded-xl relative overflow-hidden">
                            <div className="relative z-10 flex flex-col gap-2">
                                <h4 className="font-headline font-bold text-white text-lg">Exportação em Lote</h4>
                                <p className="text-sm opacity-80 font-body">Precisa de todos os relatórios da feira? Utilize a exportação unificada para arquivos .ZIP.</p>
                                <button className="mt-4 flex items-center gap-2 text-white font-bold text-sm underline underline-offset-4 hover:opacity-100 opacity-80">
                                    Iniciar Exportação Bulk
                                    <span className="material-symbols-outlined text-xs">arrow_forward</span>
                                </button>
                            </div>
                            <span className="material-symbols-outlined absolute -bottom-6 -right-6 text-white opacity-5 text-[120px]">folder_zip</span>
                        </div>
                    </section>

                    {/* Table Section */}
                    <section className="col-span-12 lg:col-span-7 flex flex-col gap-6">
                        <div className="flex items-center justify-between">
                            <div className="flex items-center gap-3">
                                <h3 className="text-2xl font-bold font-headline">Os Meus Relatórios</h3>
                                <span className="bg-surface-container-highest text-primary text-xs font-bold px-2 py-0.5 rounded-full">{relatorios.length} Totais</span>
                            </div>
                            <button className="text-primary font-bold text-sm flex items-center gap-1 hover:underline">
                                Ver Todos
                                <span className="material-symbols-outlined text-sm">open_in_new</span>
                            </button>
                        </div>
                        <div className="bg-surface-container-lowest rounded-xl shadow-sm border border-outline-variant/10 overflow-hidden">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="bg-surface-container-low/50">
                                        <th className="px-6 py-4 text-xs font-bold text-primary tracking-widest uppercase">Nome do Relatório</th>
                                        <th className="px-6 py-4 text-xs font-bold text-primary tracking-widest uppercase">Data</th>
                                        <th className="px-6 py-4 text-xs font-bold text-primary tracking-widest uppercase text-center">Estado</th>
                                        <th className="px-6 py-4 text-xs font-bold text-primary tracking-widest uppercase text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-outline-variant/10">
                                    {relatorios.map((rel) => (
                                        <tr key={rel.id} className="hover:bg-surface-container-low transition-colors group">
                                            <td className="px-6 py-5">
                                                <div className={`flex flex-col ${rel.status === 'processando' ? 'opacity-60' : ''}`}>
                                                    <span className="font-bold text-on-surface font-body">{rel.nome}</span>
                                                    <span className="text-xs text-on-surface-variant italic">ID: {rel.id_label || `#REP-2025-${rel.id.toString().padStart(3, '0')}`}</span>
                                                </div>
                                            </td>
                                            <td className={`px-6 py-5 text-sm text-on-surface-variant font-body ${rel.status === 'processando' ? 'opacity-60' : ''}`}>
                                                {new Date(rel.created_at).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric' })}
                                            </td>
                                            <td className="px-6 py-5 text-center">
                                                {rel.status === 'concluido' ? (
                                                    <span className="bg-tertiary-container text-on-tertiary-container text-[10px] font-extrabold uppercase tracking-tighter px-3 py-1 rounded-full">Concluído</span>
                                                ) : rel.status === 'processando' ? (
                                                    <div className="flex items-center justify-center gap-2">
                                                        <div className="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></div>
                                                        <span className="text-amber-700 text-[10px] font-extrabold uppercase tracking-tighter">Processando</span>
                                                    </div>
                                                ) : (
                                                    <span className="bg-error-container text-on-error-container text-[10px] font-extrabold uppercase tracking-tighter px-3 py-1 rounded-full">Erro</span>
                                                )}
                                            </td>
                                            <td className="px-6 py-5 text-right">
                                                {rel.status === 'concluido' ? (
                                                    <button className="text-on-tertiary-container hover:bg-tertiary-fixed transition-all p-2 rounded-lg group-hover:scale-110" title="Descarregar PDF">
                                                        <span className="material-symbols-outlined" style={{ fontVariationSettings: "'FILL' 1" }}>download</span>
                                                    </button>
                                                ) : rel.status === 'processando' ? (
                                                    <div className="p-2 text-outline-variant animate-spin inline-block">
                                                        <span className="material-symbols-outlined">progress_activity</span>
                                                    </div>
                                                ) : null}
                                            </td>
                                        </tr>
                                    ))}
                                </tbody>
                            </table>
                            <div className="bg-surface-container-low/30 p-4 text-center border-t border-outline-variant/10">
                                <span className="text-xs text-outline font-medium">Exibindo os últimos {relatorios.length} relatórios gerados hoje.</span>
                            </div>
                        </div>
                    </section>
                </div>
            </main>

            {/* Toast Notification (Floating Glassmorphism) */}
            <div className="fixed bottom-8 right-8 z-50 animate-in slide-in-from-right fade-in">
                <div className="glass-panel border border-primary/20 shadow-2xl rounded-xl p-4 flex items-center gap-4 max-w-xs ring-1 ring-white/50">
                    <div className="relative">
                        <div className="w-10 h-10 rounded-full bg-primary-container/20 flex items-center justify-center text-primary-container animate-spin duration-[2000ms]">
                            <span className="material-symbols-outlined text-xl">refresh</span>
                        </div>
                    </div>
                    <div className="flex flex-col">
                        <span className="text-sm font-bold font-headline text-on-surface">Relatório em processamento...</span>
                        <span className="text-[11px] text-on-surface-variant font-body">Isso pode levar alguns segundos dependendo do volume de dados.</span>
                    </div>
                    <button className="text-outline-variant hover:text-on-surface-variant transition-colors ml-2">
                        <span className="material-symbols-outlined text-lg">close</span>
                    </button>
                </div>
            </div>
        </AppLayout>
    );
}
