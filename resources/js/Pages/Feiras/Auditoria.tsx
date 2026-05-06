import AppLayout from "@/Layouts/AppLayout";
import { Head, router, usePage } from "@inertiajs/react";
import { useEffect, useState } from "react";

interface Props {
    feira: any;
    estatisticas?: any;
    ultimas_vendas?: any[];
}

export default function Auditoria({ feira, estatisticas, ultimas_vendas }: Props) {
    const { props } = usePage();
    const [isSyncing, setIsSyncing] = useState(feira.is_sincronizando);

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
                    <div className="relative hidden lg:block">
                        <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-outline">search</span>
                        <input
                            className="pl-10 pr-4 py-2 bg-surface-container-low border-none rounded-lg focus:ring-2 focus:ring-primary/20 text-sm w-64 transition-all"
                            placeholder="Pesquisar registros..."
                            type="text"
                        />
                    </div>
                </div>
                <div className="flex items-center gap-4">
                    <button className="p-2 hover:bg-slate-100 rounded-lg transition-all active:scale-95">
                        <span className="material-symbols-outlined text-outline">notifications</span>
                    </button>
                    <button className="p-2 hover:bg-slate-100 rounded-lg transition-all active:scale-95">
                        <span className="material-symbols-outlined text-outline">settings</span>
                    </button>
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

                {/* Tables Section: Últimas Transações */}
                <div className="bg-white rounded-2xl shadow-sm overflow-hidden border border-primary/5">
                    <div className="p-8 flex justify-between items-center border-b border-slate-50">
                        <h4 className="text-lg font-bold text-primary">Últimas Transações</h4>
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
                                    <th className="px-8 py-4 text-[11px] font-bold text-primary/40 uppercase tracking-widest">ID Transação</th>
                                    <th className="px-8 py-4 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Horário</th>
                                    <th className="px-8 py-4 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Valor</th>
                                    <th className="px-8 py-4 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Método</th>
                                <th className="px-8 py-4 text-[11px] font-bold text-primary/40 uppercase tracking-widest text-right">Status</th>
                            </tr>
                        </thead>
                        <tbody className="divide-y divide-slate-50">
                            {ultimas_vendas && ultimas_vendas.length > 0 ? (
                                ultimas_vendas.map((venda) => (
                                    <tr key={venda.id} className="hover:bg-slate-50/50 transition-colors group">
                                        <td className="px-8 py-5 font-mono text-sm text-primary font-bold">
                                            #{venda.sell_number}
                                        </td>
                                        <td className="px-8 py-5 text-sm text-primary/60">
                                            {venda.date_hour ? new Date(venda.date_hour).toLocaleTimeString('pt-BR') : '---'}
                                        </td>
                                        <td className="px-8 py-5 text-sm font-bold text-primary">
                                            {formatCurrency(venda.total_value)}
                                        </td>
                                        <td className="px-8 py-5">
                                            <span className="px-3 py-1 rounded-full text-[10px] font-extrabold bg-primary/5 text-primary">
                                                {venda.sale_type || 'NORMAL'}
                                            </span>
                                        </td>
                                        <td className="px-8 py-5 text-right">
                                            <span className="inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-extrabold bg-green-50 text-green-700">
                                                <span className="material-symbols-outlined text-[12px]">check_circle</span>
                                                Sincronizado
                                            </span>
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
                        <button className="text-xs font-extrabold text-primary hover:underline transition-all">
                            Ver todas as transações
                        </button>
                    </div>
                )}
            </div>
            </main>
        </AppLayout>
    );
}
