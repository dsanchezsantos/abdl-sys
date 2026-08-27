import AppLayout from "@/Layouts/AppLayout";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { useEffect, useState } from "react";
import MultiSelect from "@/Components/MultiSelect";

interface EditoraRep {
    id: number;
    editora: string;
    representante: string;
}

interface Props {
    feira: any;
    estatisticas?: any;
    ultimas_vendas?: any[];
    editoras_representantes: EditoraRep[];
    representantes_unicos: string[];
}

export default function Auditoria({ feira, estatisticas, ultimas_vendas, editoras_representantes, representantes_unicos }: Props) {
    const { props } = usePage();
    const [isSyncing, setIsSyncing] = useState(feira.is_sincronizando);
    const [selectedVenda, setSelectedVenda] = useState<any | null>(null);
    const [exportOpen, setExportOpen] = useState(false);

    const [currentPage, setCurrentPage] = useState(1);
    const [editorasSearch, setEditorasSearch] = useState('');
    const [manualEditora, setManualEditora] = useState('');
    const [manualRepresentante, setManualRepresentante] = useState('');
    const [importFile, setImportFile] = useState<File | null>(null);
    const [isImporting, setIsImporting] = useState(false);
    const [isSavingManual, setIsSavingManual] = useState(false);
    const [importError, setImportError] = useState('');

    const handleManualSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSavingManual(true);
        router.post(route('feiras.editoras.store', feira.id), {
            editora: manualEditora,
            representante: manualRepresentante
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setManualEditora('');
                setManualRepresentante('');
                setIsSavingManual(false);
            },
            onError: () => {
                setIsSavingManual(false);
            }
        });
    };

    const handleDeleteEditoraRep = (id: number) => {
        if (confirm('Tem certeza que deseja remover esta associação?')) {
            router.delete(route('feiras.editoras.destroy', { feira: feira.id, id }), {
                preserveScroll: true
            });
        }
    };

    const downloadTemplate = () => {
        const headers = ['editora', 'representante'];
        const rows = [
            ['Exemplo Editora', 'Exemplo Representante'],
        ];
        
        const csvContent = [
            headers.join(','),
            ...rows.map(row => row.join(','))
        ].join('\n');

        // Adiciona o BOM UTF-8 (\uFEFF) para o Excel abrir com caracteres corretos (acentuação, etc.)
        const blob = new Blob([new Uint8Array([0xEF, 0xBB, 0xBF]), csvContent], { type: 'text/csv;charset=utf-8;' });
        const url = URL.createObjectURL(blob);
        const link = document.createElement("a");
        link.setAttribute("href", url);
        link.setAttribute("download", "modelo_importacao_editoras.csv");
        document.body.appendChild(link);
        link.click();
        document.body.removeChild(link);
    };

    const handleImport = (e: React.FormEvent) => {
        e.preventDefault();
        if (!importFile) return;

        setIsImporting(true);
        setImportError('');

        const proceedWithUpload = () => {
            const formData = new FormData();
            formData.append('file', importFile);

            router.post(route('feiras.editoras.import', feira.id), formData as any, {
                preserveScroll: true,
                onSuccess: () => {
                    setImportFile(null);
                    setIsImporting(false);
                },
                onError: (err) => {
                    setImportError(err.file || 'Erro ao importar arquivo.');
                    setIsImporting(false);
                }
            });
        };

        // Se for CSV, fazemos uma pré-validação rápida no client-side
        if (importFile.name.toLowerCase().endsWith('.csv')) {
            const reader = new FileReader();
            reader.onload = (event) => {
                const text = event.target?.result as string;
                if (!text || text.trim() === '') {
                    setImportError('O arquivo está vazio.');
                    setIsImporting(false);
                    return;
                }
                const firstLine = text.split('\n')[0] || '';
                const headers = firstLine.split(',').map(h => h.trim().toLowerCase().replace(/"/g, ''));
                const headersSemicolon = firstLine.split(';').map(h => h.trim().toLowerCase().replace(/"/g, ''));
                
                const hasEditora = headers.includes('editora') || headers.includes('editoras') || 
                                   headersSemicolon.includes('editora') || headersSemicolon.includes('editoras');
                const hasRepresentante = headers.includes('representante') || headers.includes('representantes') ||
                                         headersSemicolon.includes('representante') || headersSemicolon.includes('representantes');

                if (!hasEditora || !hasRepresentante) {
                    setImportError('Estrutura inválida. O arquivo deve conter obrigatoriamente as colunas "editora" e "representante".');
                    setIsImporting(false);
                    return;
                }

                proceedWithUpload();
            };
            reader.onerror = () => {
                setImportError('Erro ao ler o arquivo para validação.');
                setIsImporting(false);
            };
            reader.readAsText(importFile);
        } else {
            proceedWithUpload();
        }
    };

    const filteredEditorasReps = (editoras_representantes || []).filter(er => 
        er.editora.toLowerCase().includes(editorasSearch.toLowerCase()) ||
        er.representante.toLowerCase().includes(editorasSearch.toLowerCase())
    );

    const itemsPerPage = 10;
    const totalPages = Math.ceil(filteredEditorasReps.length / itemsPerPage) || 1;
    const startIndex = (currentPage - 1) * itemsPerPage;
    const paginatedEditorasReps = filteredEditorasReps.slice(startIndex, startIndex + itemsPerPage);

    // Carregamento inicial das estatísticas e vendas (Lazy load automático)
    useEffect(() => {
        if ((!estatisticas || !ultimas_vendas) && !feira.is_sincronizando) {
            router.reload({ only: ["estatisticas", "ultimas_vendas"] });
        }
    }, []);

    // Monitoramento do Polling (Dieta da Requisição)
    useEffect(() => {
        let interval: any;

        if (feira.is_sincronizando) {
            setIsSyncing(true);
            interval = setInterval(() => {
                router.reload({ 
                    only: ["feira"], 
                    onSuccess: (page: any) => {
                        // Se terminou agora
                        if (!page.props.feira.is_sincronizando) {
                            setIsSyncing(false);
                            clearInterval(interval);
                            // Carga Pesada Final
                            router.reload({ only: ["feira", "estatisticas", "ultimas_vendas"] });
                        }
                    }
                });
            }, 5000);
        }

        return () => {
            if (interval) clearInterval(interval);
        };
    }, [feira.is_sincronizando]);

    const handleSync = () => {
        router.post(route("feiras.sync", feira.id), {}, {
            onSuccess: () => {
                setIsSyncing(true);
            }
        });
    };

    // Helper para formatar moeda
    const formatCurrency = (value: any) => {
        if (!value) return "R$ 0,00";
        return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
    };

    return (
        <AppLayout activeItem="auditoria">
            <Head title={`Auditoria - ${feira.nome}`} />

            {/* Banner Global de Sincronização */}
            {isSyncing && (
                <div className="bg-primary px-8 py-3 flex items-center justify-between animate-pulse">
                    <div className="flex items-center gap-3 text-white">
                        <span className="material-symbols-outlined animate-spin">sync</span>
                        <p className="text-sm font-bold uppercase tracking-wider">
                            Sincronização em andamento. Os dados serão atualizados automaticamente ao final do processo.
                        </p>
                    </div>
                    <div className="text-[10px] font-bold text-white/60 bg-white/10 px-2 py-1 rounded">
                        POLLING ATIVO (5S)
                    </div>
                </div>
            )}

            {/* Banner de Falha Parcial (Repescagem) */}
            {!isSyncing && feira.status_integridade === 'FALHA_PARCIAL' && (
                <div className="bg-amber-500 px-8 py-3 flex items-center justify-between shadow-lg shadow-amber-500/20">
                    <div className="flex items-center gap-3 text-white">
                        <span className="material-symbols-outlined">warning</span>
                        <p className="text-sm font-bold uppercase tracking-wider">
                            Sincronização concluída com avisos. Algumas páginas apresentaram instabilidade.
                        </p>
                    </div>
                    <button 
                        onClick={() => router.post(route('feiras.retry-sync', feira.id), {}, { onSuccess: () => setIsSyncing(true) })}
                        className="bg-white/20 hover:bg-white/30 text-white px-4 py-1.5 rounded-lg text-xs font-black uppercase tracking-widest transition-all active:scale-95 border border-white/40 flex items-center gap-2"
                    >
                        <span className="material-symbols-outlined text-sm">refresh</span>
                        Repescar Dados Faltantes
                    </button>
                </div>
            )}

            {/* Header Interno da Página */}
            <header className="flex justify-between items-center w-full px-8 py-4 sticky top-0 z-30 bg-[#faf8ff]/80 backdrop-blur-xl font-manrope font-semibold text-[#00246a]">
                <div className="flex items-center gap-6">
                    <h1 className="text-on-surface font-extrabold text-xl tracking-tight">
                        Feira Selecionada: {feira.nome}
                    </h1>
                </div>
                <div className="flex items-center gap-4">
                    {/* Botão de Exportação Excel */}
                    <div className="relative">
                        <button
                            onClick={() => setExportOpen(!exportOpen)}
                            disabled={isSyncing}
                            className={`flex items-center gap-2 px-5 py-2.5 bg-white border border-primary/20 text-primary rounded-lg font-bold text-sm hover:bg-primary/5 transition-all active:scale-95 shadow-sm disabled:opacity-50 disabled:cursor-not-allowed`}
                        >
                            <span className="material-symbols-outlined text-sm">download</span>
                            Exportar (.xlsx)
                            <span className="material-symbols-outlined text-sm transition-transform duration-200" style={{ transform: exportOpen ? 'rotate(180deg)' : 'none' }}>keyboard_arrow_down</span>
                        </button>
 
                        {exportOpen && (
                            <>
                                {/* Click outside handler backdrop */}
                                <div className="fixed inset-0 z-40" onClick={() => setExportOpen(false)} />
                                <div className="absolute right-0 mt-2 w-56 bg-white rounded-xl shadow-xl border border-primary/5 py-2 z-50 animate-in fade-in slide-in-from-top-2 duration-150">
                                    <a
                                        href={route('feiras.export.livros', feira.id)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        onClick={() => setExportOpen(false)}
                                        className="flex items-center gap-3 px-4 py-2.5 text-xs font-bold text-primary/80 hover:bg-primary/5 hover:text-primary transition-colors"
                                    >
                                        <span className="material-symbols-outlined text-lg">menu_book</span>
                                        Catálogo de Livros
                                    </a>
                                    <a
                                        href={route('feiras.export.cartoes', feira.id)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        onClick={() => setExportOpen(false)}
                                        className="flex items-center gap-3 px-4 py-2.5 text-xs font-bold text-primary/80 hover:bg-primary/5 hover:text-primary transition-colors"
                                    >
                                        <span className="material-symbols-outlined text-lg">credit_card</span>
                                        Lista de Cartões
                                    </a>
                                    <a
                                        href={route('feiras.export.vendas', feira.id)}
                                        target="_blank"
                                        rel="noopener noreferrer"
                                        onClick={() => setExportOpen(false)}
                                        className="flex items-center gap-3 px-4 py-2.5 text-xs font-bold text-primary/80 hover:bg-primary/5 hover:text-primary transition-colors"
                                    >
                                        <span className="material-symbols-outlined text-lg">receipt_long</span>
                                        Vendas e Transações
                                    </a>
                                </div>
                            </>
                        )}
                    </div>
 
                    <button 
                        onClick={handleSync}
                        disabled={isSyncing}
                        className={`flex items-center gap-2 px-5 py-2.5 bg-primary text-white rounded-lg font-bold text-sm hover:opacity-90 transition-all active:scale-95 shadow-lg shadow-primary/20 disabled:opacity-50 disabled:cursor-not-allowed`}
                    >
                        <span className={`material-symbols-outlined text-sm ${isSyncing ? 'animate-spin' : ''}`}>sync</span>
                        {isSyncing ? 'Sincronizando...' : 'Sync Data'}
                    </button>
                </div>
            </header>

            <main className={`p-8 min-h-screen transition-all duration-500 ${isSyncing ? 'opacity-60 grayscale-[0.2]' : ''}`}>
                
                {/* Estado Inicial / Sem Dados */}
                {!estatisticas && !isSyncing && (
                    <div className="bg-primary/5 border-2 border-dashed border-primary/20 rounded-2xl p-12 flex flex-col items-center justify-center text-center mb-8">
                        <div className="bg-white p-4 rounded-full shadow-lg mb-4 text-primary">
                            <span className="material-symbols-outlined text-4xl">database_off</span>
                        </div>
                        <h2 className="text-xl font-extrabold text-primary mb-2">Sem dados analíticos para exibir</h2>
                        <p className="text-sm text-primary/60 max-w-md mx-auto">
                            Esta feira ainda não foi sincronizada com a Nowigo ou o cálculo de estatísticas ainda não foi concluído. Clique em <strong>Sync Data</strong> para começar.
                        </p>
                    </div>
                )}

                {/* KPI Row: Bento Grid Style */}
                <div className={`grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8 ${isSyncing ? 'blur-[1px]' : ''}`}>
                    {/* Faturamento Card */}
                    <div className="col-span-1 lg:col-span-2 bg-white p-6 rounded-xl shadow-sm border border-primary/5 relative overflow-hidden group">
                        <div className="flex justify-between items-start mb-4 relative z-10">
                            <div>
                                <p className="text-xs font-bold uppercase tracking-widest text-primary/60 mb-1">Faturamento Auditado</p>
                                <h3 className="text-3xl font-extrabold text-primary">
                                    {formatCurrency(estatisticas?.faturamento_bruto)}
                                </h3>
                            </div>
                            <span className="material-symbols-outlined text-primary bg-primary/10 p-2 rounded-lg">payments</span>
                        </div>
                    </div>

                    {/* Ticket Médio */}
                    <div className="bg-white p-6 rounded-xl shadow-sm border border-primary/5 flex flex-col justify-between">
                        <div>
                            <p className="text-xs font-bold uppercase tracking-widest text-primary/60 mb-2">Ticket Médio Líquido</p>
                            <h3 className="text-3xl font-extrabold text-primary">
                                {formatCurrency(estatisticas?.ticket_medio)}
                            </h3>
                        </div>
                    </div>

                    {/* Volume de Produtos */}
                    <div className="bg-white p-6 rounded-xl shadow-sm border border-primary/5 flex flex-col justify-between">
                        <div>
                            <p className="text-xs font-bold uppercase tracking-widest text-primary/60 mb-2">Volume de Produtos</p>
                            <h3 className="text-3xl font-extrabold text-primary">
                                {estatisticas?.total_livros_vendidos || 0} <span className="text-sm font-medium text-primary/40">livros</span>
                            </h3>
                        </div>
                    </div>

                    {/* Alert Card: Termômetro de Inconsistências */}
                    <div className={`col-span-1 lg:col-span-4 p-6 rounded-xl flex flex-col md:flex-row justify-between items-start md:items-center gap-4 ${estatisticas?.qtd_inconsistencias_catalogo > 0 ? 'bg-amber-50 border-l-4 border-amber-500' : 'bg-green-50 border-l-4 border-green-500'}`}>
                        <div className="flex items-center gap-4">
                            <div className={`p-3 rounded-full ${estatisticas?.qtd_inconsistencias_catalogo > 0 ? 'bg-amber-100 text-amber-700' : 'bg-green-100 text-green-700'}`}>
                                <span className="material-symbols-outlined font-bold">
                                    {estatisticas?.qtd_inconsistencias_catalogo > 0 ? 'warning' : 'check_circle'}
                                </span>
                            </div>
                            <div>
                                <h4 className={`font-bold ${estatisticas?.qtd_inconsistencias_catalogo > 0 ? 'text-amber-900' : 'text-green-900'}`}>
                                    Termômetro de Inconsistências
                                </h4>
                                <p className={`text-sm ${estatisticas?.qtd_inconsistencias_catalogo > 0 ? 'text-amber-800' : 'text-green-800'}`}>
                                    {estatisticas?.qtd_inconsistencias_catalogo || 0} livros vendidos sem Representante/Editora detectados.
                                </p>
                            </div>
                        </div>
                        <a className={`flex items-center gap-1 text-sm font-extrabold border-b-2 transition-all ${estatisticas?.qtd_inconsistencias_catalogo > 0 ? 'text-amber-900 border-amber-900' : 'text-green-900 border-green-900'}`} href={route('catalogo.index')}>
                            Ir para Catálogo
                            <span className="material-symbols-outlined text-sm">chevron_right</span>
                        </a>
                    </div>
                </div>

                {/* Charts Section */}
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 mb-8">
                    {/* Formas de Pagamento (Donut) */}
                    <div className="lg:col-span-4 bg-white p-8 rounded-2xl shadow-sm border border-primary/5">
                        <h4 className="text-lg font-bold text-primary mb-8">Formas de Pagamento</h4>
                        <div className="flex flex-col items-center">
                            {estatisticas?.dados_graficos?.formas_pagamento ? (
                                <>
                                    <div className="relative flex justify-center mb-10">
                                        {(() => {
                                            const payments = Object.entries(estatisticas.dados_graficos.formas_pagamento);
                                            const total = payments.reduce((acc, [_, val]: [any, any]) => acc + parseFloat(val), 0);
                                            let currentPercent = 0;
                                            
                                            const gradientParts = payments.map(([_, val]: [any, any], index) => {
                                                const percent = (parseFloat(val) / total) * 100;
                                                const start = currentPercent;
                                                currentPercent += percent;
                                                // Usando tons de azul da marca (primary) com opacidades variadas
                                                const opacity = 1 - (index * 0.2);
                                                return `rgba(0, 36, 106, ${opacity}) ${start}% ${currentPercent}%`;
                                            });

                                            const gradientString = `conic-gradient(${gradientParts.join(', ')})`;

                                            return (
                                                <div 
                                                    className="w-56 h-56 rounded-full relative flex items-center justify-center shadow-lg transition-all duration-1000" 
                                                    style={{ background: gradientString }}
                                                >
                                                    <div className="w-40 h-40 bg-white rounded-full flex flex-col items-center justify-center shadow-inner">
                                                        <p className="text-[10px] font-bold text-primary/40 uppercase tracking-[0.2em] mb-1">Status</p>
                                                        <p className="text-sm font-semibold text-primary/60">Sincronizado</p>
                                                    </div>
                                                </div>
                                            );
                                        })()}
                                    </div>
                                    <div className="w-full space-y-4 px-2">
                                        {Object.entries(estatisticas.dados_graficos.formas_pagamento).map(([label, value]: [string, any], index) => {
                                            const total = Object.values(estatisticas.dados_graficos.formas_pagamento).reduce((acc: number, val: any) => acc + parseFloat(val), 0);
                                            const percent = ((parseFloat(value) / total) * 100).toFixed(1);
                                            return (
                                                <div key={label} className="flex items-center justify-between">
                                                    <div className="flex items-center gap-3">
                                                        <div className={`w-3 h-3 rounded-full`} style={{ backgroundColor: `rgba(0, 36, 106, ${1 - (index * 0.2)})` }}></div>
                                                        <span className="text-sm font-medium text-primary/70">{label}</span>
                                                    </div>
                                                    <div className="flex flex-col items-end">
                                                        <span className="text-sm font-bold text-primary">{formatCurrency(value)}</span>
                                                        <span className="text-[10px] text-primary/40 font-bold">{percent}%</span>
                                                    </div>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </>
                            ) : (
                                <div className="py-20 text-center text-primary/30 text-sm italic">Aguardando dados...</div>
                            )}
                        </div>
                    </div>

                    {/* Top Representantes (Bar Chart) */}
                    <div className="lg:col-span-8 bg-white p-8 rounded-2xl shadow-sm border border-primary/5">
                        <div className="flex justify-between items-center mb-8">
                            <h4 className="text-lg font-bold text-primary">Top 5 Representantes</h4>
                            <span className="text-xs font-bold text-primary px-3 py-1 bg-primary/5 rounded-full">Por Faturamento</span>
                        </div>
                        <div className="space-y-6">
                            {estatisticas?.dados_graficos?.top_representantes ? (
                                Object.entries(estatisticas.dados_graficos.top_representantes).map(([name, value]: [string, any], index) => {
                                    const maxValue = Math.max(...Object.values(estatisticas.dados_graficos.top_representantes) as number[]);
                                    const width = maxValue > 0 ? (value / maxValue) * 100 : 0;
                                    return (
                                        <div key={name} className="space-y-2">
                                            <div className="flex justify-between text-sm font-semibold">
                                                <span className="text-primary/70">{name}</span>
                                                <span className="text-primary">{formatCurrency(value)}</span>
                                            </div>
                                            <div className="h-3 w-full bg-slate-100 rounded-full overflow-hidden">
                                                <div 
                                                    className="h-full bg-primary rounded-full transition-all duration-1000" 
                                                    style={{ width: `${width}%`, opacity: 1 - (index * 0.15) }}
                                                ></div>
                                            </div>
                                        </div>
                                    );
                                })
                            ) : (
                                <div className="py-20 text-center text-primary/30 text-sm italic">Nenhum representante identificado ainda.</div>
                            )}
                        </div>
                    </div>

                    {/* Picos de Venda (Visual) */}
                    <div className="lg:col-span-12 bg-white p-8 rounded-2xl shadow-sm border border-primary/5">
                        <div className="flex justify-between items-center mb-10">
                            <div>
                                <h4 className="text-lg font-bold text-primary">Picos de Venda</h4>
                                <p className="text-sm text-primary/40">Atividade por hora - {feira.nome}</p>
                            </div>
                        </div>
                        {estatisticas?.dados_graficos?.picos_venda && Object.keys(estatisticas.dados_graficos.picos_venda).length > 0 ? (
                            <div className="relative h-48 w-full">
                                <svg className="w-full h-full" preserveAspectRatio="none" viewBox="0 0 1000 100">
                                    <defs>
                                        <linearGradient id="lineGrad" x1="0%" x2="0%" y1="0%" y2="100%">
                                            <stop offset="0%" stopColor="#00246a" stopOpacity="0.2"></stop>
                                            <stop offset="100%" stopColor="#00246a" stopOpacity="0"></stop>
                                        </linearGradient>
                                    </defs>
                                    <path d="M0,80 Q100,75 200,60 T400,30 T600,10 T800,50 T1000,20 L1000,100 L0,100 Z" fill="url(#lineGrad)"></path>
                                    <path d="M0,80 Q100,75 200,60 T400,30 T600,10 T800,50 T1000,20" fill="none" stroke="#00246a" strokeWidth="3"></path>
                                </svg>
                                <div className="flex justify-between mt-4 text-[10px] font-bold text-primary/40 uppercase">
                                    {Object.keys(estatisticas.dados_graficos.picos_venda).map((hora) => (
                                        <span key={hora}>{hora}:00</span>
                                    ))}
                                </div>
                            </div>
                        ) : (
                            <div className="py-12 text-center text-primary/30 text-sm italic">Os dados de picos de venda serão calculados após a sincronização.</div>
                        )}
                    </div>
                </div>

                {/* Tables Section: Últimas Vendas */}
                <div className="bg-white rounded-2xl shadow-sm overflow-hidden border border-primary/5">
                    <div className="p-8 flex justify-between items-center border-b border-slate-50">
                        <h4 className="text-lg font-bold text-primary">Últimas Vendas</h4>
                        <div className="flex items-center gap-2 text-xs font-bold text-green-700">
                            <span className="relative flex h-2 w-2">
                                <span className="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                <span className="relative inline-flex rounded-full h-2 w-2 bg-green-500"></span>
                            </span>
                            Live Feed Ativo
                        </div>
                    </div>
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead className="bg-slate-50/50">
                                <tr>
                                    <th className="px-8 py-4 text-[11px] font-bold text-primary/40 uppercase tracking-widest">ID Venda</th>
                                    <th className="px-8 py-4 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Horário</th>
                                    <th className="px-8 py-4 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Qtd Livros</th>
                                    <th className="px-8 py-4 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Valor Total</th>
                                    <th className="px-8 py-4 text-[11px] font-bold text-primary/40 uppercase tracking-widest text-right">Ações</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-50">
                            {ultimas_vendas && ultimas_vendas.length > 0 ? (
                                ultimas_vendas.map((venda) => (
                                    <tr 
                                        key={venda.id} 
                                        className="hover:bg-slate-50/50 transition-colors group cursor-pointer"
                                        onClick={() => setSelectedVenda(venda)}
                                    >
                                        <td className="px-8 py-5 font-mono text-sm text-primary font-bold">
                                            #{venda.sell_number}
                                        </td>
                                        <td className="px-8 py-5 text-sm text-primary/60">
                                            {venda.date_hour ? new Date(venda.date_hour).toLocaleTimeString('pt-BR') : '---'}
                                        </td>
                                        <td className="px-8 py-5 text-sm font-bold text-primary/70">
                                            {venda.itens_venda?.reduce((sum: number, item: any) => sum + item.amount, 0) || 0} livros
                                        </td>
                                        <td className="px-8 py-5 text-sm font-extrabold text-primary">
                                            {formatCurrency(venda.total_value)}
                                        </td>
                                        <td className="px-8 py-5 text-right" onClick={(e) => e.stopPropagation()}>
                                            <button 
                                                onClick={() => setSelectedVenda(venda)}
                                                className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-primary/5 text-primary hover:bg-primary/10 transition-all active:scale-95"
                                            >
                                                <span className="material-symbols-outlined text-[16px]">visibility</span>
                                                Ver Detalhes
                                            </button>
                                        </td>
                                    </tr>
                                ))
                            ) : (
                                <tr>
                                    <td colSpan={5} className="px-8 py-12 text-center text-primary/40 text-sm italic">
                                        Nenhuma transação encontrada para esta feira.
                                    </td>
                                </tr>
                            )}
                        </tbody>
                    </table>
                </div>
                {ultimas_vendas && ultimas_vendas.length > 0 && (
                    <div className="p-4 bg-slate-50/30 text-center">
                        <Link 
                            href={route('feiras.vendas', feira.id)}
                            className="inline-block text-xs font-extrabold text-primary hover:underline transition-all"
                        >
                            Ver todas as vendas
                        </Link>
                    </div>
                )}
            </div>
                {/* Gestão de Editoras e Representantes */}
                <div className="grid grid-cols-1 lg:grid-cols-12 gap-8 mt-8 mb-24">
                    {/* Painel de Cadastro e Importação */}
                    <div className="lg:col-span-4 bg-white p-8 rounded-2xl shadow-sm border border-primary/5 flex flex-col gap-6 font-manrope">
                        <div>
                            <h4 className="text-lg font-bold text-primary">Importar / Cadastrar</h4>
                            <p className="text-xs text-primary/60 mt-1">Defina as editoras e representantes da feira para parametrizar o catálogo.</p>
                        </div>

                        {/* Importação CSV/XLSX */}
                        <div className="border-t border-slate-100 pt-6">
                            <div className="flex items-center justify-between mb-3">
                                <span className="text-xs font-bold text-primary uppercase tracking-wider">Importar Planilha</span>
                                <button
                                    type="button"
                                    onClick={downloadTemplate}
                                    className="text-[10px] font-bold text-primary/60 hover:text-primary flex items-center gap-0.5 hover:underline"
                                    title="Baixar modelo de CSV"
                                >
                                    <span className="material-symbols-outlined text-xs">download</span>
                                    Modelo CSV
                                </button>
                            </div>
                            <form onSubmit={handleImport} className="space-y-3">
                                <label className="flex flex-col items-center justify-center border-2 border-dashed border-primary/20 hover:border-primary/40 rounded-xl p-4 cursor-pointer hover:bg-primary/5 transition-all text-center">
                                    <span className="material-symbols-outlined text-primary text-2xl mb-1">upload_file</span>
                                    <span className="text-xs font-bold text-primary truncate max-w-full px-2">{importFile ? importFile.name : 'Selecionar Arquivo'}</span>
                                    <span className="text-[10px] text-primary/40 font-semibold mt-0.5">Formatos: .csv ou .xlsx</span>
                                    <input 
                                        type="file" 
                                        accept=".csv,.xlsx,.xls"
                                        onChange={(e) => setImportFile(e.target.files?.[0] || null)}
                                        className="hidden" 
                                    />
                                </label>
                                {importError && (
                                    <p className="text-[10px] text-error font-bold">{importError}</p>
                                )}
                                <button
                                    type="submit"
                                    disabled={!importFile || isImporting}
                                    className="w-full bg-primary hover:opacity-90 disabled:opacity-50 text-white font-headline font-bold py-2 rounded-lg text-xs shadow-sm transition-all active:scale-[0.98] flex items-center justify-center gap-1.5"
                                >
                                    <span className="material-symbols-outlined text-sm">publish</span>
                                    {isImporting ? 'Enviando...' : 'Carregar Planilha'}
                                </button>
                            </form>
                        </div>

                        {/* Cadastro Manual */}
                        <div className="border-t border-slate-100 pt-6">
                            <span className="text-xs font-bold text-primary uppercase tracking-wider block mb-3">Cadastro Manual</span>
                            <form onSubmit={handleManualSubmit} className="space-y-3">
                                <div className="flex flex-col gap-1">
                                    <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest px-1">Editora</label>
                                    <input 
                                        type="text"
                                        value={manualEditora}
                                        onChange={(e) => setManualEditora(e.target.value)}
                                        placeholder="Nome da Editora"
                                        className="w-full bg-slate-50 border border-slate-200 focus:border-primary/50 focus:ring-0 rounded-lg py-2 px-3 text-xs text-primary font-semibold"
                                        required
                                    />
                                </div>
                                <div className="flex flex-col gap-1">
                                    <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest px-1">Representante</label>
                                    <MultiSelect
                                        options={(representantes_unicos || []).map(rep => ({ value: rep, label: rep }))}
                                        selected={manualRepresentante ? [manualRepresentante] : []}
                                        onChange={(selected) => setManualRepresentante(selected[0] || '')}
                                        placeholder="Selecione ou digite o representante"
                                        single
                                        allowCreate
                                    />
                                </div>
                                <button
                                    type="submit"
                                    disabled={isSavingManual}
                                    className="w-full bg-gradient-to-br from-primary to-primary-container hover:brightness-110 text-white font-headline font-bold py-2 rounded-lg text-xs shadow-sm transition-all active:scale-[0.98] flex items-center justify-center gap-1.5"
                                >
                                    <span className="material-symbols-outlined text-sm">add</span>
                                    {isSavingManual ? 'Adicionando...' : 'Adicionar Associação'}
                                </button>
                            </form>
                        </div>
                    </div>

                    {/* Listagem de Editoras e Representantes */}
                    <div className="lg:col-span-8 bg-white p-8 rounded-2xl shadow-sm border border-primary/5 flex flex-col gap-4 font-manrope">
                        <div className="flex items-center justify-between flex-wrap gap-4">
                            <div>
                                <h4 className="text-lg font-bold text-primary">Editoras e Representantes</h4>
                                <p className="text-xs text-primary/60 mt-1">Lista completa de associações registradas nesta feira.</p>
                            </div>
                            <div className="relative w-48">
                                <span className="material-symbols-outlined absolute left-2.5 top-1/2 -translate-y-1/2 text-primary/30 text-sm">search</span>
                                <input 
                                    type="text"
                                    value={editorasSearch}
                                    onChange={(e) => setEditorasSearch(e.target.value)}
                                    placeholder="Buscar por editora..."
                                    className="w-full pl-8 pr-3 py-1.5 bg-slate-50 border border-slate-200 focus:border-primary/50 focus:ring-0 rounded-lg text-xs text-primary font-semibold placeholder:text-primary/20"
                                />
                            </div>
                        </div>

                        <div className="border border-slate-100 rounded-xl overflow-hidden mt-2">
                            <table className="w-full text-left border-collapse text-xs">
                                <thead>
                                    <tr className="bg-slate-50/50 border-b border-slate-100 text-primary/50 font-bold uppercase tracking-wider">
                                        <th className="px-5 py-3">Editora</th>
                                        <th className="px-5 py-3">Representante</th>
                                        <th className="px-5 py-3 text-right w-20">Ações</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50 font-semibold text-primary/80">
                                    {paginatedEditorasReps.length > 0 ? (
                                        paginatedEditorasReps.map((er) => (
                                            <tr key={er.id} className="hover:bg-slate-50/30">
                                                <td className="px-5 py-3 text-primary font-bold">{er.editora}</td>
                                                <td className="px-5 py-3">{er.representante}</td>
                                                <td className="px-5 py-3 text-right">
                                                    <button
                                                        onClick={() => handleDeleteEditoraRep(er.id)}
                                                        className="p-1 hover:bg-red-50 text-red-500 hover:text-red-700 rounded transition-all"
                                                        title="Excluir Associação"
                                                    >
                                                        <span className="material-symbols-outlined text-[18px]">delete</span>
                                                    </button>
                                                </td>
                                            </tr>
                                        ))
                                    ) : (
                                        <tr>
                                            <td colSpan={3} className="px-5 py-10 text-center text-primary/40 italic">
                                                Nenhuma associação cadastrada para esta feira.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Paginação Client-side */}
                        {filteredEditorasReps.length > itemsPerPage && (
                            <div className="flex items-center justify-between text-[11px] font-bold text-primary/40 uppercase mt-2">
                                <span>Exibindo {startIndex + 1}-{Math.min(startIndex + itemsPerPage, filteredEditorasReps.length)} de {filteredEditorasReps.length} registros</span>
                                <div className="flex items-center gap-1">
                                    <button 
                                        disabled={currentPage === 1}
                                        onClick={() => setCurrentPage(prev => prev - 1)}
                                        className="p-1 rounded border border-slate-200 text-primary hover:bg-slate-50 disabled:opacity-40 disabled:pointer-events-none"
                                    >
                                        <span className="material-symbols-outlined text-[14px]">chevron_left</span>
                                    </button>
                                    <button 
                                        disabled={currentPage === totalPages}
                                        onClick={() => setCurrentPage(prev => prev + 1)}
                                        className="p-1 rounded border border-slate-200 text-primary hover:bg-slate-50 disabled:opacity-40 disabled:pointer-events-none"
                                    >
                                        <span className="material-symbols-outlined text-[14px]">chevron_right</span>
                                    </button>
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </main>

            {/* Modal de Detalhes da Venda */}
            {selectedVenda && (
                <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
                    {/* Backdrop */}
                    <div 
                        className="absolute inset-0 bg-slate-900/60 backdrop-blur-sm transition-opacity" 
                        onClick={() => setSelectedVenda(null)}
                    />
                    
                    {/* Modal Content */}
                    <div className="bg-white rounded-2xl shadow-2xl border border-primary/5 w-full max-w-4xl overflow-hidden relative z-10 transform scale-100 transition-all max-h-[85vh] flex flex-col font-manrope">
                        {/* Header */}
                        <div className="px-8 py-5 border-b border-slate-100 flex items-center justify-between bg-slate-50/50">
                            <div className="flex items-center gap-3">
                                <span className="material-symbols-outlined text-primary p-2 bg-primary/10 rounded-lg">shopping_bag</span>
                                <div>
                                    <h3 className="text-lg font-extrabold text-primary">Detalhes da Venda #{selectedVenda.sell_number}</h3>
                                    <p className="text-xs text-primary/60 mt-0.5">Caixa: {selectedVenda.box || "N/A"} • {selectedVenda.date_hour ? new Date(selectedVenda.date_hour).toLocaleString("pt-BR") : ""}</p>
                                </div>
                            </div>
                            <button 
                                onClick={() => setSelectedVenda(null)}
                                className="p-1.5 hover:bg-slate-200 rounded-lg text-primary/60 hover:text-primary transition-all active:scale-95 flex items-center justify-center"
                            >
                                <span className="material-symbols-outlined text-[20px]">close</span>
                            </button>
                        </div>

                        {/* Scrollable Body */}
                        <div className="p-8 overflow-y-auto space-y-8">
                            {/* Resumo */}
                            <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                                <div className="bg-slate-50/50 p-4 rounded-xl border border-slate-100 flex flex-col justify-between">
                                    <span className="text-[10px] font-bold text-primary/40 uppercase tracking-widest block mb-1">Valor Total</span>
                                    <span className="text-xl font-extrabold text-primary">{formatCurrency(selectedVenda.total_value)}</span>
                                </div>
                                <div className="bg-slate-50/50 p-4 rounded-xl border border-slate-100 flex flex-col justify-between">
                                    <span className="text-[10px] font-bold text-primary/40 uppercase tracking-widest block mb-1">Método de Venda</span>
                                    <div>
                                        {selectedVenda.sale_type === 1 ? (
                                            <span className="inline-block px-3 py-1 rounded-full text-[10px] font-extrabold bg-purple-50 text-purple-700 border border-purple-200 whitespace-nowrap">
                                                Múltiplos Pagamentos
                                            </span>
                                        ) : selectedVenda.sale_type === -1 ? (
                                            <span className="inline-block px-3 py-1 rounded-full text-[10px] font-extrabold bg-blue-50 text-blue-700 border border-blue-200 whitespace-nowrap">
                                                Pagamento Único
                                            </span>
                                        ) : (
                                            <span className="inline-block px-3 py-1 rounded-full text-[10px] font-extrabold bg-slate-50 text-slate-600 border border-slate-200 whitespace-nowrap">
                                                Não Informado
                                            </span>
                                        )}
                                    </div>
                                </div>
                                <div className="bg-slate-50/50 p-4 rounded-xl border border-slate-100 flex flex-col justify-between">
                                    <span className="text-[10px] font-bold text-primary/40 uppercase tracking-widest block mb-1">Status Integração</span>
                                    <span className="inline-flex items-center gap-1 text-xs font-bold text-green-700">
                                        <span className="material-symbols-outlined text-[16px]">check_circle</span>
                                        Sincronizado
                                    </span>
                                </div>
                            </div>

                            {/* Section 1: Itens da Venda */}
                            <div>
                                <h4 className="text-sm font-bold text-primary uppercase tracking-wider mb-3 flex items-center gap-2">
                                    <span className="material-symbols-outlined text-primary text-[18px]">menu_book</span>
                                    Livros / Produtos ({selectedVenda.itens_venda?.length || 0})
                                </h4>
                                <div className="border border-slate-100 rounded-xl overflow-hidden">
                                    <table className="w-full text-left border-collapse text-xs">
                                        <thead>
                                            <tr className="bg-slate-50 border-b border-slate-100 text-primary/50 font-bold uppercase tracking-wider">
                                                <th className="px-5 py-3">Produto</th>
                                                <th className="px-5 py-3 text-center w-24">Qtd</th>
                                                <th className="px-5 py-3 text-right w-36">Valor Unitário</th>
                                                <th className="px-5 py-3 text-right w-36">Valor Total</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-50 font-semibold text-primary/80">
                                            {selectedVenda.itens_venda && selectedVenda.itens_venda.length > 0 ? (
                                                selectedVenda.itens_venda.map((item: any) => (
                                                    <tr key={item.id} className="hover:bg-slate-50/30">
                                                        <td className="px-5 py-3">{item.name}</td>
                                                        <td className="px-5 py-3 text-center font-bold text-primary">{item.amount}</td>
                                                        <td className="px-5 py-3 text-right font-mono">{formatCurrency(item.unit_value)}</td>
                                                        <td className="px-5 py-3 text-right font-mono font-bold text-primary">{formatCurrency(item.total_value)}</td>
                                                    </tr>
                                                ))
                                            ) : (
                                                <tr>
                                                    <td colSpan={4} className="px-5 py-6 text-center text-primary/40 italic">
                                                        Nenhum produto cadastrado para esta venda.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>

                            {/* Section 2: Transações / Pagamentos */}
                            <div>
                                <h4 className="text-sm font-bold text-primary uppercase tracking-wider mb-3 flex items-center gap-2">
                                    <span className="material-symbols-outlined text-primary text-[18px]">payments</span>
                                    Transações de Pagamento / Métodos ({selectedVenda.pagamentos?.length || 0})
                                </h4>
                                <div className="border border-slate-100 rounded-xl overflow-hidden">
                                    <table className="w-full text-left border-collapse text-xs">
                                        <thead>
                                            <tr className="bg-slate-50 border-b border-slate-100 text-primary/50 font-bold uppercase tracking-wider">
                                                <th className="px-5 py-3">ID Transação</th>
                                                <th className="px-5 py-3">Meio de Pagamento</th>
                                                <th className="px-5 py-3">Tag / Pulseira</th>
                                                <th className="px-5 py-3">Grupo / Escola</th>
                                                <th className="px-5 py-3 text-right w-36">Valor</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-slate-50 font-semibold text-primary/80">
                                            {selectedVenda.pagamentos && selectedVenda.pagamentos.length > 0 ? (
                                                selectedVenda.pagamentos.map((p: any) => (
                                                    <tr key={p.id} className="hover:bg-slate-50/30">
                                                        <td className="px-5 py-3 font-mono text-primary font-bold">
                                                            #{p.pagamento_id_api}
                                                        </td>
                                                        <td className="px-5 py-3">
                                                            <span className="px-2 py-0.5 bg-slate-100 rounded text-primary text-[10px] font-bold">
                                                                {p.payment_way}
                                                            </span>
                                                        </td>
                                                        <td className="px-5 py-3 font-mono text-primary">{p.tag_code || "---"}</td>
                                                        <td className="px-5 py-3 text-primary/60">{p.payment_group || "---"}</td>
                                                        <td className="px-5 py-3 text-right font-mono font-bold text-primary">{formatCurrency(p.value)}</td>
                                                    </tr>
                                                ))
                                            ) : (
                                                <tr>
                                                    <td colSpan={5} className="px-5 py-6 text-center text-primary/40 italic">
                                                        Nenhum pagamento cadastrado para esta venda.
                                                    </td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>

                        {/* Footer */}
                        <div className="px-8 py-4 border-t border-slate-100 bg-slate-50/50 flex justify-end">
                            <button
                                onClick={() => setSelectedVenda(null)}
                                className="px-5 py-2.5 bg-primary text-white text-xs font-bold rounded-lg hover:bg-primary/95 transition-all active:scale-95 shadow-md shadow-primary/10"
                            >
                                Fechar Detalhes
                            </button>
                        </div>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
