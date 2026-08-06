import AppLayout from "@/Layouts/AppLayout";
import { Head, Link, router } from "@inertiajs/react";
import { useState } from "react";

interface ItemVenda {
    id: number;
    produto_id_api: number;
    name: string;
    amount: number;
    unit_value: string;
    total_value: string;
}

interface Pagamento {
    id: number;
    pagamento_id_api: number;
    payment_way: string;
    tag_code: string | null;
    payment_group: string | null;
    value: string;
}

interface Venda {
    id: number;
    sell_number: string;
    sale_type: number | null;
    total_value: string;
    date_hour: string;
    box: string | null;
    processado: boolean;
    itens_venda?: ItemVenda[];
    pagamentos?: Pagamento[];
}

interface PaginationLink {
    url: string | null;
    label: string;
    active: boolean;
}

interface PaginatedVendas {
    current_page: number;
    data: Venda[];
    first_page_url: string;
    from: number | null;
    last_page: number;
    last_page_url: string;
    links: PaginationLink[];
    next_page_url: string | null;
    path: string;
    per_page: number;
    prev_page_url: string | null;
    to: number | null;
    total: number;
}

interface Props {
    feira: {
        id: number;
        nome: string;
        is_sincronizando: boolean;
        status: string;
        status_integridade: string;
    };
    vendas: PaginatedVendas;
    filters: {
        search?: string;
        sale_type?: string;
        box?: string;
        min_value?: string;
        max_value?: string;
        start_date?: string;
        end_date?: string;
    };
    boxes: string[];
}

export default function Vendas({ feira, vendas, filters, boxes }: Props) {
    const [search, setSearch] = useState(filters.search || "");
    const [saleType, setSaleType] = useState(filters.sale_type || "");
    const [box, setBox] = useState(filters.box || "");
    const [minValue, setMinValue] = useState(filters.min_value || "");
    const [maxValue, setMaxValue] = useState(filters.max_value || "");
    const [startDate, setStartDate] = useState(filters.start_date || "");
    const [endDate, setEndDate] = useState(filters.end_date || "");
    const [selectedVenda, setSelectedVenda] = useState<Venda | null>(null);

    // Formatar Moeda para BRL
    const formatCurrency = (value: any) => {
        if (!value) return "R$ 0,00";
        return new Intl.NumberFormat("pt-BR", { style: "currency", currency: "BRL" }).format(value);
    };

    const apply = (data: any) => {
        const cleaned = Object.keys(data).reduce((acc: any, key) => {
            if (data[key] !== undefined && data[key] !== null && data[key] !== "") {
                acc[key] = data[key];
            }
            return acc;
        }, {});

        router.get(route("feiras.vendas", feira.id), cleaned, {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        });
    };

    const handleSearch = (e: React.FormEvent) => {
        e.preventDefault();
        apply({
            search,
            sale_type: saleType,
            box,
            min_value: minValue,
            max_value: maxValue,
            start_date: startDate,
            end_date: endDate,
        });
    };

    const handleSaleTypeChange = (val: string) => {
        setSaleType(val);
        apply({
            search,
            sale_type: val,
            box,
            min_value: minValue,
            max_value: maxValue,
            start_date: startDate,
            end_date: endDate,
        });
    };

    const handleBoxChange = (val: string) => {
        setBox(val);
        apply({
            search,
            sale_type: saleType,
            box: val,
            min_value: minValue,
            max_value: maxValue,
            start_date: startDate,
            end_date: endDate,
        });
    };

    const handleClear = () => {
        setSearch("");
        setSaleType("");
        setBox("");
        setMinValue("");
        setMaxValue("");
        setStartDate("");
        setEndDate("");
        apply({});
    };

    return (
        <AppLayout activeItem="auditoria">
            <Head title={`Vendas - ${feira.nome}`} />

            {/* Header Interno */}
            <header className="flex justify-between items-center w-full px-8 py-4 sticky top-0 z-30 bg-[#faf8ff]/80 backdrop-blur-xl font-manrope font-semibold text-[#00246a] border-b border-primary/5">
                <div className="flex items-center gap-4">
                    <Link
                        href={route("feiras.auditoria", feira.id)}
                        className="p-2 hover:bg-slate-100 rounded-lg transition-all active:scale-95 flex items-center justify-center text-primary"
                    >
                        <span className="material-symbols-outlined text-[20px]">arrow_back</span>
                    </Link>
                    <div>
                        <span className="text-xs font-bold text-primary/60 uppercase tracking-widest block">Feira: {feira.nome}</span>
                        <h1 className="text-on-surface font-extrabold text-xl tracking-tight">
                            Auditoria de Vendas
                        </h1>
                    </div>
                </div>
                <div className="flex items-center gap-4">
                    <a
                        href={route("feiras.export.vendas", feira.id)}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="flex items-center gap-2 px-4 py-2 bg-white border border-primary/20 text-primary rounded-lg font-bold text-sm hover:bg-primary/5 transition-all active:scale-95 shadow-sm"
                    >
                        <span className="material-symbols-outlined text-[18px]">download</span>
                        Exportar Vendas (.xlsx)
                    </a>
                </div>
            </header>

            <main className="p-8 min-h-screen">
                {/* Painel de Filtros */}
                <div className="bg-white rounded-2xl p-6 shadow-sm border border-primary/5 mb-8">
                    <div className="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6 pb-6 border-b border-slate-100">
                        <div>
                            <h2 className="text-lg font-extrabold text-primary font-manrope">Filtros Avançados</h2>
                            <p className="text-xs text-primary/60 mt-1">Refine a sua busca por recibos, caixas e faixas de valores de vendas.</p>
                        </div>
                        <div className="flex items-center gap-3">
                            <span className="text-xs font-bold text-primary bg-primary/5 px-3 py-1.5 rounded-full">
                                {vendas.total} Vendas Encontradas
                            </span>
                        </div>
                    </div>

                    <form onSubmit={handleSearch} className="space-y-4 font-manrope">
                        <div className="grid grid-cols-1 md:grid-cols-3 gap-4">
                            {/* Buscar por ID */}
                            <div className="relative">
                                <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Buscar Venda (ID)</label>
                                <div className="relative">
                                    <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-primary/40 text-[18px]">search</span>
                                    <input
                                        type="text"
                                        value={search}
                                        onChange={(e) => setSearch(e.target.value)}
                                        placeholder="Ex: #12345"
                                        className="pl-9 pr-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 text-xs w-full transition-all text-primary font-semibold"
                                    />
                                </div>
                            </div>

                            {/* Método de Venda (sale_type) */}
                            <div>
                                <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Método de Venda</label>
                                <select
                                    value={saleType}
                                    onChange={(e) => handleSaleTypeChange(e.target.value)}
                                    className="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 text-xs w-full transition-all text-primary font-semibold"
                                >
                                    <option value="">Todos os métodos</option>
                                    <option value="-1">Pagamento Único</option>
                                    <option value="1">Múltiplos Pagamentos</option>
                                </select>
                            </div>

                            {/* Caixa / PDV */}
                            <div>
                                <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Caixa / PDV (Box)</label>
                                <select
                                    value={box}
                                    onChange={(e) => handleBoxChange(e.target.value)}
                                    className="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 text-xs w-full transition-all text-primary font-semibold"
                                >
                                    <option value="">Todos os caixas</option>
                                    {boxes.map((b) => (
                                        <option key={b} value={b}>
                                            {b}
                                        </option>
                                    ))}
                                </select>
                            </div>
                        </div>

                        <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                            {/* Data Inicial */}
                            <div>
                                <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Data Inicial</label>
                                <input
                                    type="date"
                                    value={startDate}
                                    onChange={(e) => setStartDate(e.target.value)}
                                    className="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 text-xs w-full transition-all text-primary font-semibold"
                                />
                            </div>

                            {/* Data Final */}
                            <div>
                                <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Data Final</label>
                                <input
                                    type="date"
                                    value={endDate}
                                    onChange={(e) => setEndDate(e.target.value)}
                                    className="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 text-xs w-full transition-all text-primary font-semibold"
                                />
                            </div>

                            {/* Faixa de Valor Mínimo */}
                            <div>
                                <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Valor Mínimo</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={minValue}
                                    onChange={(e) => setMinValue(e.target.value)}
                                    placeholder="R$ 0,00"
                                    className="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 text-xs w-full transition-all text-primary font-semibold"
                                />
                            </div>

                            {/* Faixa de Valor Máximo */}
                            <div>
                                <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Valor Máximo</label>
                                <input
                                    type="number"
                                    step="0.01"
                                    value={maxValue}
                                    onChange={(e) => setMaxValue(e.target.value)}
                                    placeholder="R$ 1.000,00"
                                    className="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 text-xs w-full transition-all text-primary font-semibold"
                                />
                            </div>
                        </div>

                        {/* Botões de Ação */}
                        <div className="flex justify-end items-center gap-3 pt-2">
                            {(search || saleType || box || minValue || maxValue || startDate || endDate) && (
                                <button
                                    type="button"
                                    onClick={handleClear}
                                    className="flex items-center gap-1.5 px-4 py-2 text-xs font-bold text-error bg-error/5 hover:bg-error/10 rounded-lg transition-all active:scale-95 shadow-sm"
                                >
                                    <span className="material-symbols-outlined text-[16px]">clear_all</span>
                                    Limpar Filtros
                                </button>
                            )}
                            <button
                                type="submit"
                                className="flex items-center gap-1.5 px-5 py-2 text-xs font-extrabold text-white bg-primary hover:bg-primary/90 rounded-lg transition-all active:scale-95 shadow-md shadow-primary/10"
                            >
                                <span className="material-symbols-outlined text-[16px]">filter_alt</span>
                                Aplicar Filtros
                            </button>
                        </div>
                    </form>
                </div>

                {/* Tabela de Vendas */}
                <div className="bg-white rounded-2xl shadow-sm overflow-hidden border border-primary/5">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="bg-slate-50/50 border-b border-slate-100">
                                    <th className="px-8 py-5 text-[11px] font-bold text-primary/40 uppercase tracking-widest">ID Venda</th>
                                    <th className="px-8 py-5 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Horário</th>
                                    <th className="px-8 py-5 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Caixa / PDV</th>
                                    <th className="px-8 py-5 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Qtd Livros</th>
                                    <th className="px-8 py-5 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Método</th>
                                    <th className="px-8 py-5 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Valor Total</th>
                                    <th className="px-8 py-5 text-[11px] font-bold text-primary/40 uppercase tracking-widest text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-50 font-manrope">
                                {vendas.data && vendas.data.length > 0 ? (
                                    vendas.data.map((venda) => (
                                        <tr 
                                            key={venda.id} 
                                            className="hover:bg-slate-50/50 transition-colors group cursor-pointer"
                                            onClick={() => setSelectedVenda(venda)}
                                        >
                                            <td className="px-8 py-5 font-mono text-sm text-primary font-bold">
                                                #{venda.sell_number}
                                            </td>
                                            <td className="px-8 py-5 text-sm text-primary/70">
                                                {venda.date_hour
                                                    ? new Date(venda.date_hour).toLocaleString("pt-BR", {
                                                          dateStyle: "short",
                                                          timeStyle: "medium",
                                                      })
                                                    : "---"}
                                            </td>
                                            <td className="px-8 py-5 text-sm text-primary/70 font-semibold">
                                                {venda.box || "---"}
                                            </td>
                                            <td className="px-8 py-5 text-sm font-bold text-primary/70">
                                                {venda.itens_venda?.reduce((sum: number, item: any) => sum + item.amount, 0) || 0} livros
                                            </td>
                                            <td className="px-8 py-5">
                                                {venda.sale_type === 1 ? (
                                                    <span className="px-3 py-1 rounded-full text-[10px] font-extrabold bg-purple-50 text-purple-700 border border-purple-200 shadow-sm whitespace-nowrap">
                                                        Múltiplos Pagamentos
                                                    </span>
                                                ) : venda.sale_type === -1 ? (
                                                    <span className="px-3 py-1 rounded-full text-[10px] font-extrabold bg-blue-50 text-blue-700 border border-blue-200 shadow-sm whitespace-nowrap">
                                                        Pagamento Único
                                                    </span>
                                                ) : (
                                                    <span className="px-3 py-1 rounded-full text-[10px] font-extrabold bg-slate-50 text-slate-600 border border-slate-200 shadow-sm whitespace-nowrap">
                                                        Não Informado
                                                    </span>
                                                )}
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
                                        <td colSpan={7} className="px-8 py-16 text-center text-primary/40 text-sm italic">
                                            Nenhuma venda encontrada com os filtros selecionados.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Rodapé / Paginação */}
                    {vendas.links && vendas.links.length > 3 && (
                        <div className="px-8 py-5 bg-slate-50/50 flex items-center justify-between border-t border-slate-100 text-xs font-bold text-primary/60 uppercase tracking-widest">
                            <div className="flex items-center gap-4">
                                <span>
                                    Exibindo {vendas.from || 0}-{vendas.to || 0} de {vendas.total} vendas
                                </span>
                            </div>
                            <div className="flex items-center gap-1.5">
                                {vendas.links.map((link, idx) => {
                                    let label = link.label;
                                    let isIcon = false;
                                    if (label.includes("Previous")) {
                                        label = "chevron_left";
                                        isIcon = true;
                                    } else if (label.includes("Next")) {
                                        label = "chevron_right";
                                        isIcon = true;
                                    }

                                    const isNumeric = !isNaN(Number(label));

                                    if (
                                        isNumeric &&
                                        vendas.last_page > 8 &&
                                        Math.abs(Number(label) - vendas.current_page) > 2 &&
                                        Number(label) !== 1 &&
                                        Number(label) !== vendas.last_page
                                    ) {
                                        if (
                                            (Number(label) < vendas.current_page && Number(label) === 2) ||
                                            (Number(label) > vendas.current_page && Number(label) === vendas.last_page - 1)
                                        ) {
                                            return <span key={idx} className="px-2 text-primary/30 font-bold">...</span>;
                                        }
                                        return null;
                                    }

                                    return link.url ? (
                                        <Link
                                            key={idx}
                                            href={link.url}
                                            className={`w-8 h-8 flex items-center justify-center rounded-lg border transition-all active:scale-95 hover:bg-white hover:text-primary ${
                                                link.active
                                                    ? "bg-primary text-white border-primary shadow-sm hover:bg-primary hover:text-white"
                                                    : "bg-transparent text-primary/60 border-slate-200"
                                            }`}
                                        >
                                            {isIcon ? (
                                                <span className="material-symbols-outlined text-[16px]">{label}</span>
                                            ) : (
                                                label
                                            )}
                                        </Link>
                                    ) : (
                                        <span
                                            key={idx}
                                            className="w-8 h-8 flex items-center justify-center rounded-lg border border-slate-100 bg-transparent text-primary/20 cursor-not-allowed"
                                        >
                                            {isIcon ? (
                                                <span className="material-symbols-outlined text-[16px]">{label}</span>
                                            ) : (
                                                label
                                            )}
                                        </span>
                                    );
                                })}
                            </div>
                        </div>
                    )}
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
