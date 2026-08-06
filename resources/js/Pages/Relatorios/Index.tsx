import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type Relatorio = {
    id: number;
    nome: string;
    id_label?: string;
    created_at: string;
    status: 'concluido' | 'processando' | 'erro' | 'falha' | 'fila';
    tipo: string;
    feira_nome: string;
    download_url?: string;
};

type Feira = {
    id: number;
    nome: string;
};

type Props = {
    relatorios: {
        data: Relatorio[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    feiras: Feira[];
    filters: {
        feira_id?: string;
    };
};

const TIPOS_RELATORIO = [
    { id: 'cartao', label: 'Transações por Cartão' },
    { id: 'vendas', label: 'Vendas Agrupadas' },
    { id: 'editoras', label: 'Consolidado por Editora / Representante' },
];

export default function Index({ relatorios, feiras, filters }: Props) {
    const [searchTerm, setSearchTerm] = useState('');
    const [isGenerating, setIsGenerating] = useState(false);
    const [filterFeiraId, setFilterFeiraId] = useState(filters?.feira_id || '');

    const { data, setData, post, processing } = useForm({
        id_feira: feiras.length > 0 ? feiras[0].id : '',
        tipo: TIPOS_RELATORIO[0].id,
    });

    // Verifica se existe algum relatório em processamento ou na fila
    const anyProcessing = relatorios.data.some(r => r.status === 'processando' || r.status === 'fila');

    // Monitoramento por Polling (Efeito Reativo)
    useEffect(() => {
        let interval: any;

        if (anyProcessing) {
            setIsGenerating(true);
            interval = setInterval(() => {
                router.reload({ 
                    only: ['relatorios'],
                    onSuccess: (page: any) => {
                        // Se não houver mais nada processando, paramos o polling
                        const stillProcessing = page.props.relatorios.data.some((r: any) => r.status === 'processando' || r.status === 'fila');
                        if (!stillProcessing) {
                            setIsGenerating(false);
                            clearInterval(interval);
                        }
                    }
                });
            }, 5000);
        } else {
            setIsGenerating(false);
        }

        return () => {
            if (interval) clearInterval(interval);
        };
    }, [anyProcessing]);

    const handleFilterFeiraChange = (e: React.ChangeEvent<HTMLSelectElement>) => {
        const val = e.target.value;
        setFilterFeiraId(val);
        router.get(route('relatorios.index'), { feira_id: val }, {
            preserveState: true,
            replace: true
        });
    };

    const handleGenerate = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('relatorios.store'), {
            preserveScroll: true,
            onStart: () => setIsGenerating(true),
        });
    };

    return (
        <AppLayout activeItem="relatorios">
            <Head title="Relatórios" />
            
            {/* Banner de Sincronização em Background */}
            {isGenerating && (
                <div className="bg-primary px-8 py-3 flex items-center justify-between animate-pulse sticky top-0 z-40 shadow-lg">
                    <div className="flex items-center gap-3 text-white">
                        <span className="material-symbols-outlined animate-spin text-[20px]">sync</span>
                        <p className="text-xs font-extrabold uppercase tracking-widest">
                            Processamento de Relatório em Andamento... A lista será atualizada automaticamente.
                        </p>
                    </div>
                    <div className="text-[10px] font-black text-white/60 bg-white/10 px-2 py-1 rounded border border-white/10">
                        POLLING ATIVO (5S)
                    </div>
                </div>
            )}

            {/* TopNavBar */}
            <header className={`flex justify-between items-center px-8 py-4 sticky top-0 z-30 bg-surface/80 backdrop-blur-xl transition-all ${isGenerating ? 'mt-0' : ''}`}>
                <div className="flex items-center gap-6">
                    <h1 className="text-on-surface font-extrabold text-xl font-headline uppercase tracking-tight">Centro de Relatórios</h1>
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
                    <section className="col-span-12 lg:col-span-4 flex flex-col gap-6">
                        <form onSubmit={handleGenerate} className="bg-surface-container-low p-8 rounded-xl shadow-sm border border-outline-variant/15 flex flex-col gap-8">
                            <div className="flex items-center gap-3">
                                <div className="p-2 bg-primary-container rounded-lg text-white">
                                    <span className="material-symbols-outlined">settings_suggest</span>
                                </div>
                                <h3 className="text-xl font-bold font-headline">Nova Solicitação</h3>
                            </div>
                            <div className="space-y-6">
                                <div className="flex flex-col gap-2">
                                    <label className="text-xs font-bold text-primary tracking-widest uppercase px-1">Selecione a Feira</label>
                                    <select 
                                        value={data.id_feira}
                                        onChange={e => setData('id_feira', e.target.value)}
                                        className="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-lg py-3 px-4 font-body text-on-surface focus:border-primary/50 focus:ring-0 transition-all cursor-pointer"
                                    >
                                        {feiras.map(f => (
                                            <option key={f.id} value={f.id}>{f.nome}</option>
                                        ))}
                                        {feiras.length === 0 && <option value="">Nenhuma feira cadastrada</option>}
                                    </select>
                                </div>
                                <div className="flex flex-col gap-2">
                                    <label className="text-xs font-bold text-primary tracking-widest uppercase px-1">Tipo de Documento</label>
                                    <select 
                                        value={data.tipo}
                                        onChange={e => setData('tipo', e.target.value)}
                                        className="w-full bg-surface-container-lowest border border-outline-variant/30 rounded-lg py-3 px-4 font-body text-on-surface focus:border-primary/50 focus:ring-0 transition-all cursor-pointer"
                                    >
                                        {TIPOS_RELATORIO.map(t => (
                                            <option key={t.id} value={t.id}>{t.label}</option>
                                        ))}
                                    </select>
                                </div>
                            </div>
                            <div className="mt-4">
                                <button 
                                    disabled={processing || isGenerating || feiras.length === 0}
                                    className="w-full bg-gradient-to-br from-primary to-primary-container text-white py-5 rounded-lg font-headline font-extrabold text-lg flex items-center justify-center gap-3 shadow-lg hover:brightness-110 active:scale-[0.98] transition-all disabled:opacity-50 disabled:grayscale"
                                >
                                    <span className="material-symbols-outlined text-[28px]">
                                        {isGenerating ? 'hourglass_top' : 'picture_as_pdf'}
                                    </span>
                                    {isGenerating ? 'Aguardando Fila...' : 'Gerar Relatório (PDF)'}
                                </button>
                                {isGenerating && (
                                    <p className="text-[10px] text-center mt-3 text-primary font-bold uppercase tracking-widest animate-pulse">
                                        Um relatório já está sendo processado
                                    </p>
                                )}
                            </div>
                        </form>
                    </section>

                    {/* Table Section */}
                    <section className="col-span-12 lg:col-span-8 flex flex-col gap-6">
                        <div className="flex items-center justify-between flex-wrap gap-4">
                            <div className="flex items-center gap-3">
                                <h3 className="text-2xl font-bold font-headline">Os Meus Relatórios</h3>
                                <span className="bg-surface-container-highest text-primary text-xs font-bold px-2 py-0.5 rounded-full">{relatorios.total} Totais</span>
                            </div>
                            <div className="flex items-center gap-2">
                                <label className="text-xs font-bold text-primary uppercase tracking-wider">Filtrar Feira:</label>
                                <select 
                                    value={filterFeiraId}
                                    onChange={handleFilterFeiraChange}
                                    className="bg-surface-container-lowest border border-outline-variant/30 rounded-lg py-1.5 text-xs text-on-surface focus:border-primary/50 focus:ring-0 transition-all cursor-pointer font-body"
                                >
                                    <option value="">Todas as Feiras</option>
                                    {feiras.map(f => (
                                        <option key={f.id} value={f.id}>{f.nome}</option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        {relatorios.data && relatorios.data.length > 0 ? (
                            <div className="bg-surface-container-lowest rounded-xl shadow-sm border border-outline-variant/10 overflow-hidden">
                                <table className="w-full text-left border-collapse">
                                    <thead>
                                        <tr className="bg-surface-container-low/50">
                                            <th className="px-6 py-4 text-xs font-bold text-primary tracking-widest uppercase">Nome / Feira</th>
                                            <th className="px-6 py-4 text-xs font-bold text-primary tracking-widest uppercase text-center">Data Solicitação</th>
                                            <th className="px-6 py-4 text-xs font-bold text-primary tracking-widest uppercase text-center">Estado</th>
                                            <th className="px-6 py-4 text-xs font-bold text-primary tracking-widest uppercase text-right">Ações</th>
                                        </tr>
                                    </thead>
                                    <tbody className="divide-y divide-outline-variant/10">
                                        {relatorios.data.map((rel) => (
                                            <tr key={rel.id} className="hover:bg-surface-container-low transition-colors group">
                                                <td className="px-6 py-5">
                                                    <div className={`flex flex-col ${rel.status === 'processando' || rel.status === 'fila' ? 'opacity-60' : ''}`}>
                                                        <span className="font-bold text-on-surface font-body">{rel.nome}</span>
                                                        <span className="text-xs text-primary font-bold uppercase tracking-tighter opacity-60">
                                                            {rel.feira_nome} • {rel.id_label}
                                                        </span>
                                                    </div>
                                                </td>
                                                <td className={`px-6 py-5 text-center text-sm text-on-surface-variant font-body ${rel.status === 'processando' || rel.status === 'fila' ? 'opacity-60' : ''}`}>
                                                    {new Date(rel.created_at).toLocaleDateString('pt-BR', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' })}
                                                </td>
                                                <td className="px-6 py-5 text-center">
                                                    {rel.status === 'concluido' ? (
                                                        <span className="bg-green-100 text-green-700 text-[10px] font-extrabold uppercase tracking-tighter px-3 py-1 rounded-full">Concluído</span>
                                                    ) : rel.status === 'processando' || rel.status === 'fila' ? (
                                                        <div className="flex items-center justify-center gap-2">
                                                            <div className="w-2 h-2 rounded-full bg-amber-500 animate-pulse"></div>
                                                            <span className="text-amber-700 text-[10px] font-extrabold uppercase tracking-tighter">
                                                                {rel.status === 'fila' ? 'Na Fila' : 'Processando'}
                                                            </span>
                                                        </div>
                                                    ) : (
                                                        <span className="bg-red-100 text-red-700 text-[10px] font-extrabold uppercase tracking-tighter px-3 py-1 rounded-full">Falha</span>
                                                    )}
                                                </td>
                                                <td className="px-6 py-5 text-right">
                                                    {rel.status === 'concluido' ? (
                                                        <a 
                                                            href={rel.download_url} 
                                                            target="_blank"
                                                            rel="noopener noreferrer"
                                                            className="text-primary hover:bg-primary/10 transition-all p-2 rounded-lg group-hover:scale-110 inline-block" 
                                                            title="Descarregar PDF"
                                                        >
                                                            <span className="material-symbols-outlined" style={{ fontVariationSettings: "'FILL' 1" }}>download</span>
                                                        </a>
                                                    ) : (
                                                        <button className="text-outline-variant cursor-not-allowed p-2">
                                                            <span className="material-symbols-outlined">block</span>
                                                        </button>
                                                    )}
                                                </td>
                                            </tr>
                                        ))}
                                    </tbody>
                                </table>
                                {relatorios.links && relatorios.links.length > 3 && (
                                     <div className="px-6 py-4 border-t border-outline-variant/10 bg-surface-container-low/30 flex items-center justify-between text-[10px] font-bold text-primary/60 uppercase tracking-widest">
                                         <span>
                                             Exibindo {relatorios.from || 0}-{relatorios.to || 0} de {relatorios.total} relatórios
                                         </span>
                                         <div className="flex items-center gap-1">
                                             {relatorios.links.map((link, idx) => {
                                                 if (link.label.includes('Previous')) {
                                                     return (
                                                         <Link
                                                             key={idx}
                                                             href={link.url || '#'}
                                                             className={`p-1 rounded border border-slate-200 text-primary transition-all active:scale-95 flex items-center justify-center ${!link.url ? 'opacity-40 cursor-default pointer-events-none' : 'hover:bg-white'}`}
                                                         >
                                                             <span className="material-symbols-outlined text-[14px]">chevron_left</span>
                                                         </Link>
                                                     );
                                                 }
                                                 if (link.label.includes('Next')) {
                                                     return (
                                                         <Link
                                                             key={idx}
                                                             href={link.url || '#'}
                                                             className={`p-1 rounded border border-slate-200 text-primary transition-all active:scale-95 flex items-center justify-center ${!link.url ? 'opacity-40 cursor-default pointer-events-none' : 'hover:bg-white'}`}
                                                         >
                                                             <span className="material-symbols-outlined text-[14px]">chevron_right</span>
                                                         </Link>
                                                     );
                                                 }
                                                 return (
                                                     <Link
                                                         key={idx}
                                                         href={link.url || '#'}
                                                         className={`px-2.5 py-1 rounded border transition-all active:scale-95 ${link.active ? 'bg-primary text-white border-primary shadow' : 'bg-white text-primary border-slate-200 hover:bg-slate-50'}`}
                                                         dangerouslySetInnerHTML={{ __html: link.label }}
                                                     />
                                                 );
                                             })}
                                         </div>
                                     </div>
                                 )}
                            </div>
                        ) : (
                            <div className="bg-surface-container-lowest border-2 border-dashed border-outline-variant/20 rounded-2xl p-20 flex flex-col items-center justify-center text-center">
                                <div className="w-16 h-16 bg-surface-container-low rounded-full flex items-center justify-center text-outline-variant mb-4">
                                    <span className="material-symbols-outlined text-4xl">inventory_2</span>
                                </div>
                                <h4 className="text-lg font-bold text-on-surface mb-1">Nenhum relatório encontrado</h4>
                                <p className="text-sm text-on-surface-variant max-w-xs">Você ainda não solicitou nenhum relatório para as feiras sincronizadas.</p>
                            </div>
                        )}
                    </section>
                </div>
            </main>
        </AppLayout>
    );
}
