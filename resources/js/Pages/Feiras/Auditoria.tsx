import AppLayout from "@/Layouts/AppLayout";
import { Head } from "@inertiajs/react";

interface Props {
    feira: any;
}

export default function Auditoria({ feira }: Props) {
    return (
        <AppLayout activeItem="auditoria">
            <Head title={`Auditoria - ${feira.nome}`} />

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
                    <button className="flex items-center gap-2 px-5 py-2.5 bg-primary text-white rounded-lg font-bold text-sm hover:opacity-90 transition-all active:scale-95 shadow-lg shadow-primary/20">
                        <span className="material-symbols-outlined text-sm">sync</span>
                        Sync Data
                    </button>
                </div>
            </header>

            <main className="p-8 min-h-screen">
                {/* KPI Row: Bento Grid Style */}
                <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    {/* Faturamento Card */}
                    <div className="col-span-1 lg:col-span-2 bg-white p-6 rounded-xl shadow-sm border border-primary/5 relative overflow-hidden group">
                        <div className="flex justify-between items-start mb-4 relative z-10">
                            <div>
                                <p className="text-xs font-bold uppercase tracking-widest text-primary/60 mb-1">Faturamento Bruto vs. Líquido</p>
                                <h3 className="text-3xl font-extrabold text-primary">R$ 142.850,00</h3>
                            </div>
                            <span className="material-symbols-outlined text-primary bg-primary/10 p-2 rounded-lg">payments</span>
                        </div>
                        <div className="flex items-center gap-4 relative z-10">
                            <div className="flex-1">
                                <p className="text-[10px] font-bold text-primary/40 uppercase">Margem Líquida (Filtro de Ouro)</p>
                                <div className="flex items-baseline gap-2 mt-1">
                                    <span className="text-xl font-bold text-green-600">R$ 118.420,50</span>
                                    <span className="text-xs font-bold text-green-600">+82%</span>
                                </div>
                            </div>
                            <div className="w-24 h-12 flex items-end gap-1">
                                <div className="w-2 h-4 bg-primary/20 rounded-t-sm"></div>
                                <div className="w-2 h-6 bg-primary/20 rounded-t-sm"></div>
                                <div className="w-2 h-8 bg-primary/20 rounded-t-sm"></div>
                                <div className="w-2 h-10 bg-primary rounded-t-sm"></div>
                            </div>
                        </div>
                    </div>

                    {/* Ticket Médio */}
                    <div className="bg-white p-6 rounded-xl shadow-sm border border-primary/5 flex flex-col justify-between">
                        <div>
                            <p className="text-xs font-bold uppercase tracking-widest text-primary/60 mb-2">Ticket Médio Líquido</p>
                            <h3 className="text-3xl font-extrabold text-primary">R$ 85,30</h3>
                        </div>
                        <div className="mt-4 flex items-center text-xs text-green-700 bg-green-50 px-2 py-1 rounded-full self-start">
                            <span className="material-symbols-outlined text-sm mr-1">trending_up</span>
                            Acima da meta (R$ 75)
                        </div>
                    </div>

                    {/* Volume de Produtos */}
                    <div className="bg-white p-6 rounded-xl shadow-sm border border-primary/5 flex flex-col justify-between">
                        <div>
                            <p className="text-xs font-bold uppercase tracking-widest text-primary/60 mb-2">Volume de Produtos</p>
                            <h3 className="text-3xl font-extrabold text-primary">4.891 <span className="text-sm font-medium text-primary/40">livros</span></h3>
                        </div>
                        <div className="mt-4 w-full bg-slate-100 h-1.5 rounded-full overflow-hidden">
                            <div className="bg-primary h-full w-[65%] rounded-full"></div>
                        </div>
                    </div>

                    {/* Alert Card: Termômetro de Inconsistências */}
                    <div className="col-span-1 lg:col-span-4 bg-amber-50 border-l-4 border-amber-500 p-6 rounded-xl flex flex-col md:flex-row justify-between items-start md:items-center gap-4">
                        <div className="flex items-center gap-4">
                            <div className="bg-amber-100 p-3 rounded-full text-amber-700">
                                <span className="material-symbols-outlined font-bold">warning</span>
                            </div>
                            <div>
                                <h4 className="font-bold text-amber-900">Termômetro de Inconsistências</h4>
                                <p className="text-sm text-amber-800">12 livros vendidos sem Representante/Editora detectados.</p>
                            </div>
                        </div>
                        <a className="flex items-center gap-1 text-sm font-extrabold text-amber-900 border-b-2 border-amber-900 hover:opacity-70 transition-all" href="#">
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
                            <div className="relative flex justify-center mb-10">
                                <div className="w-56 h-56 rounded-full relative flex items-center justify-center shadow-lg" style={{ background: "conic-gradient(#00246a 0% 40%, #00389a 40% 75%, #515f74 75% 90%, #dbe1ff 90% 100%)" }}>
                                    <div className="w-40 h-40 bg-white rounded-full flex flex-col items-center justify-center shadow-inner">
                                        <p className="text-[10px] font-bold text-primary/40 uppercase tracking-[0.2em] mb-1">Total</p>
                                        <p className="text-lg font-extrabold text-primary leading-none">PIX</p>
                                        <p className="text-sm font-semibold text-primary/60">Dominante</p>
                                    </div>
                                </div>
                            </div>
                            <div className="w-full space-y-4 px-2">
                                {[
                                    { label: "PIX", color: "bg-[#00246a]", value: "40%" },
                                    { label: "Cartão de Crédito", color: "bg-[#00389a]", value: "35%" },
                                    { label: "Dinheiro", color: "bg-[#515f74]", value: "15%" },
                                    { label: "Saldo Pulseira", color: "bg-[#dbe1ff]", value: "10%" },
                                ].map((item) => (
                                    <div key={item.label} className="flex items-center justify-between">
                                        <div className="flex items-center gap-3">
                                            <div className={`w-3 h-3 rounded-full ${item.color}`}></div>
                                            <span className="text-sm font-medium text-primary/70">{item.label}</span>
                                        </div>
                                        <span className="text-sm font-bold text-primary">{item.value}</span>
                                    </div>
                                ))}
                            </div>
                        </div>
                    </div>

                    {/* Top Representantes (Bar Chart) */}
                    <div className="lg:col-span-8 bg-white p-8 rounded-2xl shadow-sm border border-primary/5">
                        <div className="flex justify-between items-center mb-8">
                            <h4 className="text-lg font-bold text-primary">Top 5 Representantes</h4>
                            <span className="text-xs font-bold text-primary px-3 py-1 bg-primary/5 rounded-full">Por Faturamento</span>
                        </div>
                        <div className="space-y-6">
                            {[
                                { name: "Editora Globo", value: "R$ 42.150,00", width: "100%", color: "bg-primary" },
                                { name: "Companhia das Letras", value: "R$ 38.400,00", width: "85%", color: "bg-primary/80" },
                                { name: "Editora Record", value: "R$ 29.800,00", width: "65%", color: "bg-primary/60" },
                                { name: "Sextante", value: "R$ 18.250,00", width: "40%", color: "bg-primary/40" },
                                { name: "Intrínseca", value: "R$ 14.250,00", width: "32%", color: "bg-primary/20" },
                            ].map((item) => (
                                <div key={item.name} className="space-y-2">
                                    <div className="flex justify-between text-sm font-semibold">
                                        <span className="text-primary/70">{item.name}</span>
                                        <span className="text-primary">{item.value}</span>
                                    </div>
                                    <div className="h-3 w-full bg-slate-100 rounded-full overflow-hidden">
                                        <div className={`h-full ${item.color} rounded-full`} style={{ width: item.width }}></div>
                                    </div>
                                </div>
                            ))}
                        </div>
                    </div>

                    {/* Picos de Venda (Visual) */}
                    <div className="lg:col-span-12 bg-white p-8 rounded-2xl shadow-sm border border-primary/5">
                        <div className="flex justify-between items-center mb-10">
                            <div>
                                <h4 className="text-lg font-bold text-primary">Picos de Venda</h4>
                                <p className="text-sm text-primary/40">Atividade por hora - {feira.nome}</p>
                            </div>
                            <div className="flex gap-2">
                                <button className="px-4 py-1.5 rounded-lg bg-primary text-white text-xs font-bold">Hoje</button>
                                <button className="px-4 py-1.5 rounded-lg bg-slate-100 text-primary/60 text-xs font-bold">Ontem</button>
                            </div>
                        </div>
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
                                <circle cx="600" cy="10" fill="#00246a" r="5"></circle>
                            </svg>
                            <div className="flex justify-between mt-4 text-[10px] font-bold text-primary/40 uppercase">
                                <span>08:00</span>
                                <span>10:00</span>
                                <span>12:00</span>
                                <span className="text-primary font-extrabold">14:00 (Pico)</span>
                                <span>16:00</span>
                                <span>18:00</span>
                                <span>20:00</span>
                                <span>22:00</span>
                            </div>
                        </div>
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
                                {[
                                    { id: "#SQ-2025-48902", time: "14:42:15", val: "R$ 142,90", method: "PIX", status: "Sincronizado" },
                                    { id: "#SQ-2025-48901", time: "14:41:58", val: "R$ 54,00", method: "Dinheiro", status: "Sincronizado" },
                                    { id: "#SQ-2025-48900", time: "14:40:22", val: "R$ 289,50", method: "Crédito", status: "Processado" },
                                    { id: "#SQ-2025-48899", time: "14:38:10", val: "R$ 15,00", method: "Pulseira", status: "Sincronizado" },
                                ].map((row) => (
                                    <tr key={row.id} className="hover:bg-slate-50/50 transition-colors group">
                                        <td className="px-8 py-5 font-mono text-sm text-primary font-bold">{row.id}</td>
                                        <td className="px-8 py-5 text-sm text-primary/60">{row.time}</td>
                                        <td className="px-8 py-5 text-sm font-bold text-primary">{row.val}</td>
                                        <td className="px-8 py-5">
                                            <span className="px-3 py-1 rounded-full text-[10px] font-extrabold bg-primary/5 text-primary">{row.method}</span>
                                        </td>
                                        <td className="px-8 py-5 text-right">
                                            <span className={`inline-flex items-center gap-1.5 px-3 py-1 rounded-full text-[10px] font-extrabold ${row.status === "Sincronizado" ? "bg-green-50 text-green-700" : "bg-primary/5 text-primary"}`}>
                                                <span className="material-symbols-outlined text-[12px]">{row.status === "Sincronizado" ? "check_circle" : "sync"}</span>
                                                {row.status}
                                            </span>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    <div className="p-4 bg-slate-50/30 text-center">
                        <button className="text-xs font-extrabold text-primary hover:underline transition-all">Ver todas as transações (8.412)</button>
                    </div>
                </div>
            </main>
        </AppLayout>
    );
}
