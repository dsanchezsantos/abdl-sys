import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';

type Livro = {
    id: number;
    produto_id_api: number;
    produto: string;
    valor: string;
    editora: string;
    representante: string;
};

type EstFeira = {
    feira_id: number;
    feira_nome: string;
    total_vendido: number;
    faturamento: string;
};

type ItemVenda = {
    id: number;
    name: string;
    amount: number;
    unit_value: string;
    total_value: string;
    produto_id_api: number;
};

type Pagamento = {
    id: number;
    pagamento_id_api: string;
    payment_way: string;
    tag_code: string | null;
    payment_group: string | null;
    value: string;
};

type Venda = {
    id: number;
    sell_number: string;
    date_hour: string | null;
    box: string | null;
    sale_type: number;
    total_value: string;
    itens_venda?: ItemVenda[];
    pagamentos?: Pagamento[];
};

type EditoraRep = {
    id: number;
    editora: string;
    representante: string;
};

type Props = {
    livro: Livro;
    estatisticas_feiras: EstFeira[];
    vendas: {
        data: Venda[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    feiras: Array<{ id: number; nome: string }>;
    filters: {
        feira_id?: string;
        min_value?: string;
        max_value?: string;
        start_date?: string;
        end_date?: string;
        sale_type?: string;
        min_items?: string;
        max_items?: string;
        box?: string;
    };
    editoras_representantes: EditoraRep[];
    boxes: string[];
};

export default function Show({ livro, estatisticas_feiras, vendas, feiras, filters, editoras_representantes, boxes }: Props) {
    const [selectedFeiraId, setSelectedFeiraId] = useState(filters.feira_id || '');
    const [selectedVenda, setSelectedVenda] = useState<Venda | null>(null);

    // Estados para filtros
    const [minValue, setMinValue] = useState(filters.min_value || '');
    const [maxValue, setMaxValue] = useState(filters.max_value || '');
    const [startDate, setStartDate] = useState(filters.start_date || '');
    const [endDate, setEndDate] = useState(filters.end_date || '');
    const [saleType, setSaleType] = useState(filters.sale_type || '');
    const [minItems, setMinItems] = useState(filters.min_items || '');
    const [maxItems, setMaxItems] = useState(filters.max_items || '');
    const [box, setBox] = useState(filters.box || '');

    // Estados para edição
    const [isEditModalOpen, setIsEditModalOpen] = useState(false);
    const [editEditora, setEditEditora] = useState(livro.editora || '');
    const [isSaving, setIsSaving] = useState(false);

    const handleEditSubmit = (e: React.FormEvent) => {
        e.preventDefault();
        setIsSaving(true);
        router.patch(route('catalogo.livros.update', livro.id), {
            editora: editEditora
        }, {
            preserveScroll: true,
            onSuccess: () => {
                setIsEditModalOpen(false);
                setIsSaving(false);
            },
            onError: () => {
                setIsSaving(false);
            }
        });
    };

    const applyFilters = (newFilters: any) => {
        const cleaned = Object.keys(newFilters).reduce((acc: any, key) => {
            if (newFilters[key] !== undefined && newFilters[key] !== null && newFilters[key] !== "") {
                acc[key] = newFilters[key];
            }
            return acc;
        }, {});

        // Preservar feira_id se selecionado
        if (selectedFeiraId && !cleaned.hasOwnProperty('feira_id')) {
            cleaned.feira_id = selectedFeiraId;
        }

        router.get(route('catalogo.show', livro.id), cleaned, {
            preserveState: true,
            preserveScroll: true,
            replace: true
        });
    };

    const handleSearchFilters = (e: React.FormEvent) => {
        e.preventDefault();
        applyFilters({
            feira_id: selectedFeiraId,
            min_value: minValue,
            max_value: maxValue,
            start_date: startDate,
            end_date: endDate,
            sale_type: saleType,
            min_items: minItems,
            max_items: maxItems,
            box: box
        });
    };

    const handleFeiraChange = (newFeiraId: string) => {
        setSelectedFeiraId(newFeiraId);
        applyFilters({
            feira_id: newFeiraId,
            min_value: minValue,
            max_value: maxValue,
            start_date: startDate,
            end_date: endDate,
            sale_type: saleType,
            min_items: minItems,
            max_items: maxItems,
            box: box
        });
    };

    const handleClearFilters = () => {
        setMinValue('');
        setMaxValue('');
        setStartDate('');
        setEndDate('');
        setSaleType('');
        setMinItems('');
        setMaxItems('');
        setBox('');
        
        const cleaned: any = {};
        if (selectedFeiraId) {
            cleaned.feira_id = selectedFeiraId;
        }
        router.get(route('catalogo.show', livro.id), cleaned, {
            preserveState: true,
            preserveScroll: true,
            replace: true
        });
    };

    const formatCurrency = (value: string | number) => {
        const val = typeof value === 'string' ? parseFloat(value) : value;
        return val.toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        });
    };

    // Calcular KPIs dependendo se há filtro por feira ou não
    const getKPIs = () => {
        if (selectedFeiraId) {
            const match = estatisticas_feiras.find(f => f.feira_id.toString() === selectedFeiraId);
            return {
                qtdVendida: match ? match.total_vendido : 0,
                faturamento: match ? parseFloat(match.faturamento) : 0
            };
        } else {
            return estatisticas_feiras.reduce((acc, curr) => ({
                qtdVendida: acc.qtdVendida + curr.total_vendido,
                faturamento: acc.faturamento + parseFloat(curr.faturamento)
            }), { qtdVendida: 0, faturamento: 0 });
        }
    };

    const kpis = getKPIs();

    return (
        <AppLayout activeItem="catalogo">
            <Head title={`Detalhes - ${livro.produto}`} />

            {/* TopNavBar */}
            <header className="flex items-center gap-2 w-full px-8 py-4 sticky top-0 z-30 bg-surface/80 backdrop-blur-xl font-manrope font-semibold text-primary border-b border-primary/5">
                <Link href={route('catalogo.index')} className="hover:text-primary/70 transition-colors">
                    Catálogo
                </Link>
                <span className="material-symbols-outlined text-[16px] text-primary/40">chevron_right</span>
                <span className="text-on-surface font-extrabold text-xl">Detalhes do Livro</span>
            </header>

            <main className="p-8 flex-1 font-manrope space-y-8">
                
                {/* Cabeçalho do Livro */}
                <div className="bg-white rounded-2xl border border-primary/5 p-8 shadow-sm flex flex-col md:flex-row md:items-center justify-between gap-6">
                    <div className="space-y-2">
                        <div className="flex items-center gap-3">
                            <span className="px-2.5 py-1 bg-primary/10 rounded-lg text-primary text-[10px] font-extrabold uppercase">
                                ID #{livro.produto_id_api}
                            </span>
                        </div>
                        <h2 className="text-2xl font-extrabold text-primary">{livro.produto}</h2>
                        <div className="flex flex-wrap items-center gap-x-6 gap-y-2 text-xs font-semibold text-primary/60">
                            <span className="flex items-center gap-1.5">
                                <span className="material-symbols-outlined text-[16px]">apartment</span>
                                Editora: <strong className="text-primary">{livro.editora || 'Não Informada'}</strong>
                            </span>
                            <span className="flex items-center gap-1.5">
                                <span className="material-symbols-outlined text-[16px]">person</span>
                                Representante: <strong className="text-primary">{livro.representante || 'Não Informado'}</strong>
                            </span>
                        </div>
                    </div>
                    
                    <div className="flex items-center gap-4">
                        <div className="bg-slate-50 border border-slate-100 p-4 rounded-xl flex items-center gap-3 self-start md:self-center">
                            <div className="w-10 h-10 rounded-lg bg-primary/5 flex items-center justify-center text-primary">
                                <span className="material-symbols-outlined">payments</span>
                            </div>
                            <div>
                                <span className="text-[9px] font-bold text-primary/40 uppercase tracking-widest block">Preço Unitário</span>
                                <span className="text-lg font-extrabold text-primary">{formatCurrency(livro.valor)}</span>
                            </div>
                        </div>

                        <button 
                            onClick={() => setIsEditModalOpen(true)}
                            className="bg-primary/5 hover:bg-primary/10 text-primary p-3 rounded-xl transition-all active:scale-95 flex items-center justify-center self-start md:self-center border border-primary/10"
                            title="Editar Editora/Representante"
                        >
                            <span className="material-symbols-outlined text-[20px]">edit</span>
                        </button>
                    </div>
                </div>

                {/* Filtro por Feira & Estatísticas */}
                <div className="space-y-6">
                    <div className="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div>
                            <h3 className="text-lg font-extrabold text-primary">Estatísticas de Vendas</h3>
                            <p className="text-xs text-primary/60">Monitore o faturamento e unidades vendidas deste livro.</p>
                        </div>
                        <div className="flex items-center gap-2">
                            <span className="text-xs font-bold text-primary/50 uppercase tracking-wider whitespace-nowrap">Filtrar por Feira:</span>
                            <select
                                value={selectedFeiraId}
                                onChange={(e) => handleFeiraChange(e.target.value)}
                                className="py-1.5 pl-3 pr-8 bg-white border border-slate-200 focus:border-primary/50 focus:ring-1 focus:ring-primary/20 rounded-xl text-xs font-semibold text-primary transition-all cursor-pointer"
                            >
                                <option value="">Todas as Feiras com Vendas</option>
                                {feiras.map((f) => (
                                    <option key={f.id} value={f.id}>{f.nome}</option>
                                ))}
                            </select>
                        </div>
                    </div>

                    {/* Bento Grid - KPIs */}
                    <div className="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div className="bg-white p-6 rounded-2xl border border-primary/5 shadow-sm flex items-center gap-4 justify-between">
                            <div className="space-y-1">
                                <span className="text-[10px] font-bold text-primary/40 uppercase tracking-widest block">Faturamento Oficial</span>
                                <span className="text-2xl font-extrabold text-primary">{formatCurrency(kpis.faturamento)}</span>
                            </div>
                            <div className="w-12 h-12 bg-green-50 rounded-xl flex items-center justify-center text-green-600 border border-green-100">
                                <span className="material-symbols-outlined text-[28px]">trending_up</span>
                            </div>
                        </div>

                        <div className="bg-white p-6 rounded-2xl border border-primary/5 shadow-sm flex items-center gap-4 justify-between">
                            <div className="space-y-1">
                                <span className="text-[10px] font-bold text-primary/40 uppercase tracking-widest block">Unidades Vendidas</span>
                                <span className="text-2xl font-extrabold text-primary">{kpis.qtdVendida} {kpis.qtdVendida === 1 ? 'livro' : 'livros'}</span>
                            </div>
                            <div className="w-12 h-12 bg-blue-50 rounded-xl flex items-center justify-center text-blue-600 border border-blue-100">
                                <span className="material-symbols-outlined text-[28px]">menu_book</span>
                            </div>
                        </div>
                    </div>
                </div>

                {/* Histórico de Vendas */}
                <div className="space-y-4 font-manrope">
                    <div>
                        <h3 className="text-lg font-extrabold text-primary">Histórico de Vendas</h3>
                        <p className="text-xs text-primary/60">Lista de todas as vendas que possuem este livro no carrinho.</p>
                    </div>

                    {/* Painel de Filtros */}
                    <div className="bg-white rounded-2xl p-6 shadow-sm border border-primary/5">
                        <form onSubmit={handleSearchFilters} className="space-y-4">
                            <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                                {/* Método de Pagamento */}
                                <div>
                                    <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Método de Venda</label>
                                    <select
                                        value={saleType}
                                        onChange={(e) => setSaleType(e.target.value)}
                                        className="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 text-xs w-full transition-all text-primary font-semibold"
                                    >
                                        <option value="">Todos os métodos</option>
                                        <option value="-1">Pagamento Único</option>
                                        <option value="1">Múltiplos Pagamentos</option>
                                    </select>
                                </div>

                                {/* Caixa / PDV */}
                                <div>
                                    <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Caixa / PDV</label>
                                    <select
                                        value={box}
                                        onChange={(e) => setBox(e.target.value)}
                                        className="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 text-xs w-full transition-all text-primary font-semibold"
                                    >
                                        <option value="">Todos os caixas</option>
                                        {(boxes || []).map((b) => (
                                            <option key={b} value={b}>{b}</option>
                                        ))}
                                    </select>
                                </div>

                                {/* Qtd Mínima de Itens */}
                                <div>
                                    <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Qtd Mínima Livros</label>
                                    <input
                                        type="number"
                                        value={minItems}
                                        onChange={(e) => setMinItems(e.target.value)}
                                        placeholder="Mínimo"
                                        className="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 text-xs w-full transition-all text-primary font-semibold"
                                    />
                                </div>

                                {/* Qtd Máxima de Itens */}
                                <div>
                                    <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Qtd Máxima Livros</label>
                                    <input
                                        type="number"
                                        value={maxItems}
                                        onChange={(e) => setMaxItems(e.target.value)}
                                        placeholder="Máximo"
                                        className="px-4 py-2 bg-slate-50 border border-slate-200 rounded-lg focus:outline-none focus:ring-2 focus:ring-primary/20 text-xs w-full transition-all text-primary font-semibold"
                                    />
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

                                {/* Valor Mínimo */}
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

                                {/* Valor Máximo */}
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
                                {(minValue || maxValue || startDate || endDate || saleType || minItems || maxItems || box) && (
                                    <button
                                        type="button"
                                        onClick={handleClearFilters}
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

                    <div className="bg-white rounded-2xl overflow-hidden shadow-sm border border-primary/5">
                        <div className="overflow-x-auto">
                            <table className="w-full text-left border-collapse">
                                <thead>
                                    <tr className="bg-slate-50/50 border-b border-slate-100">
                                        <th className="py-5 px-8 text-[11px] font-bold text-primary/40 uppercase tracking-widest">ID Venda</th>
                                        <th className="py-5 px-8 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Horário</th>
                                        <th className="py-5 px-8 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Caixa / PDV</th>
                                        <th className="py-5 px-8 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Qtd Total Itens</th>
                                        <th className="py-5 px-8 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Método</th>
                                        <th className="py-5 px-8 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Valor Total Venda</th>
                                        <th className="py-5 px-8 text-[11px] font-bold text-primary/40 uppercase tracking-widest text-right">Ações</th>
                                    </tr>
                                </thead>
                                <tbody className="divide-y divide-slate-50 text-sm font-manrope">
                                    {vendas.data && vendas.data.length > 0 ? (
                                        vendas.data.map((venda) => (
                                            <tr 
                                                key={venda.id} 
                                                className="hover:bg-slate-50/50 transition-colors group cursor-pointer"
                                                onClick={() => setSelectedVenda(venda)}
                                            >
                                                <td className="py-5 px-8 font-mono text-sm text-primary font-bold">#{venda.sell_number}</td>
                                                <td className="py-5 px-8 text-sm text-primary/70">
                                                    {venda.date_hour
                                                        ? new Date(venda.date_hour).toLocaleString("pt-BR", {
                                                              dateStyle: "short",
                                                              timeStyle: "medium",
                                                          })
                                                        : "---"}
                                                </td>
                                                <td className="py-5 px-8 text-sm text-primary/70 font-semibold">{venda.box || '---'}</td>
                                                <td className="py-5 px-8 text-sm font-bold text-primary/70">
                                                    {venda.itens_venda?.reduce((sum, item) => sum + item.amount, 0) || 0} livros
                                                </td>
                                                <td className="py-5 px-8">
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
                                                <td className="py-5 px-8 text-sm font-extrabold text-primary">{formatCurrency(venda.total_value)}</td>
                                                <td className="py-5 px-8 text-right" onClick={(e) => e.stopPropagation()}>
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
                                            <td colSpan={7} className="py-16 text-center text-primary/40 italic text-sm">
                                                Nenhuma venda registrada contendo este livro.
                                            </td>
                                        </tr>
                                    )}
                                </tbody>
                            </table>
                        </div>

                        {/* Paginação de Vendas */}
                        {vendas.links && vendas.links.length > 3 && (
                            <div className="px-8 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between text-xs">
                                <span className="font-extrabold text-primary/40 uppercase tracking-widest">
                                    Exibindo {vendas.from || 0}-{vendas.to || 0} de {vendas.total} vendas
                                </span>
                                <div className="flex items-center gap-1.5">
                                    {vendas.links.map((link, idx) => {
                                        if (link.label.includes('Previous')) {
                                            return (
                                                <Link
                                                    key={idx}
                                                    href={link.url || '#'}
                                                    className={`p-1.5 rounded-lg border border-slate-200 text-primary transition-all active:scale-95 flex items-center justify-center ${!link.url ? 'opacity-40 cursor-default pointer-events-none' : 'hover:bg-white'}`}
                                                >
                                                    <span className="material-symbols-outlined text-[16px]">chevron_left</span>
                                                </Link>
                                            );
                                        }
                                        if (link.label.includes('Next')) {
                                            return (
                                                <Link
                                                    key={idx}
                                                    href={link.url || '#'}
                                                    className={`p-1.5 rounded-lg border border-slate-200 text-primary transition-all active:scale-95 flex items-center justify-center ${!link.url ? 'opacity-40 cursor-default pointer-events-none' : 'hover:bg-white'}`}
                                                >
                                                    <span className="material-symbols-outlined text-[16px]">chevron_right</span>
                                                </Link>
                                            );
                                        }
                                        return (
                                            <Link
                                                key={idx}
                                                href={link.url || '#'}
                                                className={`px-3 py-1.5 rounded-lg border font-extrabold transition-all active:scale-95 ${link.active ? 'bg-primary text-white border-primary shadow-md shadow-primary/10' : 'bg-white text-primary border-slate-200 hover:bg-slate-50'}`}
                                                dangerouslySetInnerHTML={{ __html: link.label }}
                                            />
                                        );
                                    })}
                                </div>
                            </div>
                        )}
                    </div>
                </div>
            </main>

            {/* Modal de Detalhes da Venda */}
            {selectedVenda && (
                <div className="fixed inset-0 bg-primary/40 backdrop-blur-sm z-50 flex items-center justify-center p-4 animate-in fade-in duration-200">
                    <div className="bg-white rounded-2xl w-full max-w-4xl max-h-[90vh] flex flex-col shadow-2xl border border-primary/5 animate-in zoom-in-95 duration-200">
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
                                                        <td className="px-5 py-3 text-primary font-bold">{item.name}</td>
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
                        <div className="px-8 py-4 border-t border-slate-100 flex items-center justify-end bg-slate-50/50">
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
            {/* Modal de Edição */}
            {isEditModalOpen && (
                <div className="fixed inset-0 bg-primary/40 backdrop-blur-sm z-50 flex items-center justify-center p-4">
                    <div className="bg-white rounded-2xl w-full max-w-md shadow-2xl border border-primary/5 p-6 animate-in zoom-in-95 duration-200 font-manrope">
                        <div className="flex items-center justify-between border-b border-slate-100 pb-3 mb-4">
                            <h3 className="text-lg font-extrabold text-primary flex items-center gap-1.5">
                                <span className="material-symbols-outlined">edit_note</span>
                                Editar Livro
                            </h3>
                            <button 
                                onClick={() => setIsEditModalOpen(false)}
                                className="p-1 hover:bg-slate-100 rounded text-primary/60 flex items-center justify-center"
                            >
                                <span className="material-symbols-outlined text-[20px]">close</span>
                            </button>
                        </div>

                        <form onSubmit={handleEditSubmit} className="space-y-4">
                            <div className="flex flex-col gap-1">
                                <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest px-1">Editora</label>
                                <input 
                                    type="text"
                                    list="editoras-list"
                                    value={editEditora}
                                    onChange={(e) => setEditEditora(e.target.value)}
                                    placeholder="Escolha ou digite a editora"
                                    className="w-full bg-slate-50 border border-slate-200 focus:border-primary/50 focus:ring-0 rounded-lg py-2 px-3 text-xs text-primary font-semibold"
                                    required
                                />
                                <datalist id="editoras-list">
                                    {Array.from(new Set(editoras_representantes.map(er => er.editora))).map(ed => (
                                        <option key={ed} value={ed} />
                                    ))}
                                </datalist>
                            </div>



                            <div className="flex justify-end gap-2 pt-2 border-t border-slate-100 mt-4">
                                <button
                                    type="button"
                                    onClick={() => setIsEditModalOpen(false)}
                                    className="px-4 py-2 bg-slate-100 hover:bg-slate-200 text-slate-700 text-xs font-bold rounded-lg transition-all"
                                >
                                    Cancelar
                                </button>
                                <button
                                    type="submit"
                                    disabled={isSaving}
                                    className="px-5 py-2 bg-primary hover:opacity-95 text-white text-xs font-bold rounded-lg shadow-md transition-all"
                                >
                                    {isSaving ? 'Salvando...' : 'Salvar Alterações'}
                                </button>
                            </div>
                        </form>
                    </div>
                </div>
            )}
        </AppLayout>
    );
}
