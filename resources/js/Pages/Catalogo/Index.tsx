import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import MultiSelect from '@/Components/MultiSelect';

type Livro = {
    id: number;
    produto_id_api: number;
    produto: string;
    valor: string;
    categoria: string | null;
    editora: string;
    representante: string;
    id_feira: number;
};

type Props = {
    livros: {
        data: Livro[];
        current_page: number;
        last_page: number;
        per_page: number;
        total: number;
        from: number;
        to: number;
        links: Array<{ url: string | null; label: string; active: boolean }>;
    };
    filters: {
        search?: string;
        min_value?: string;
        max_value?: string;
        categoria?: string[];
        editora?: string[];
        representante?: string[];
        feira_id?: string[];
    };
    categorias: string[];
    editoras: string[];
    representantes: string[];
    feiras: Array<{ id: number; nome: string }>;
};

export default function Index({ livros, filters, categorias, editoras, representantes, feiras }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [minValue, setMinValue] = useState(filters.min_value || '');
    const [maxValue, setMaxValue] = useState(filters.max_value || '');
    const [categoria, setCategoria] = useState<string[]>(filters.categoria || []);
    const [editora, setEditora] = useState<string[]>(filters.editora || []);
    const [representante, setRepresentante] = useState<string[]>(filters.representante || []);
    const [feiraId, setFeiraId] = useState<string[]>(filters.feira_id || []);

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('catalogo.index'), {
            search,
            min_value: minValue,
            max_value: maxValue,
            categoria,
            editora,
            representante,
            feira_id: feiraId
        }, {
            preserveState: true,
            replace: true
        });
    };

    const handleClear = () => {
        setSearch('');
        setMinValue('');
        setMaxValue('');
        setCategoria([]);
        setEditora([]);
        setRepresentante([]);
        setFeiraId([]);

        router.get(route('catalogo.index'), {}, {
            preserveState: true,
            replace: true
        });
    };

    const formatCurrency = (value: string) => {
        return parseFloat(value).toLocaleString('pt-BR', {
            style: 'currency',
            currency: 'BRL',
        });
    };

    return (
        <AppLayout activeItem="catalogo">
            <Head title="Catálogo de Livros" />
            
            {/* TopNavBar */}
            <header className="flex justify-between items-center w-full px-8 py-4 sticky top-0 z-30 bg-surface/80 backdrop-blur-xl font-manrope font-semibold text-primary border-b border-primary/5">
                <div className="flex items-center gap-6">
                    <h1 className="text-on-surface font-extrabold text-xl">Catálogo de Livros</h1>
                </div>
            </header>

            <main className="p-8 flex-1 font-manrope">
                
                {/* Filtros de Livros */}
                <form onSubmit={handleFilter} className="bg-white rounded-2xl border border-primary/5 p-6 mb-8 shadow-sm space-y-4">
                    <div className="flex items-center justify-between border-b border-slate-100 pb-3">
                        <div className="flex items-center gap-2 text-primary font-bold">
                            <span className="material-symbols-outlined text-[20px]">filter_alt</span>
                            <span className="text-sm uppercase tracking-wider">Filtros de Busca</span>
                        </div>
                        <button 
                            type="button" 
                            onClick={handleClear} 
                            className="text-xs font-extrabold text-primary/50 hover:text-primary transition-colors flex items-center gap-1"
                        >
                            <span className="material-symbols-outlined text-[16px]">close</span>
                            Limpar Filtros
                        </button>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4">
                        {/* Busca por Nome/ID */}
                        <div className="flex flex-col">
                            <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Buscar Livro (Nome ou ID)</label>
                            <div className="relative">
                                <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-primary/30 text-[18px]">search</span>
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Ex: cute colors ou 35353..."
                                    className="w-full pl-10 pr-4 py-2 bg-slate-50/50 border border-slate-200 focus:border-primary/50 focus:ring-1 focus:ring-primary/20 rounded-xl text-xs font-semibold text-primary transition-all placeholder:text-primary/20"
                                />
                            </div>
                        </div>

                        {/* Filtro por Categoria */}
                        <div className="flex flex-col">
                            <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Categoria</label>
                            <MultiSelect
                                options={categorias.map(c => ({ value: c, label: c }))}
                                selected={categoria}
                                onChange={setCategoria}
                                placeholder="Todas as Categorias"
                            />
                        </div>

                        {/* Filtro por Editora */}
                        <div className="flex flex-col">
                            <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Editora</label>
                            <MultiSelect
                                options={editoras.map(ed => ({ value: ed, label: ed }))}
                                selected={editora}
                                onChange={setEditora}
                                placeholder="Todas as Editoras"
                            />
                        </div>

                        {/* Filtro por Representante */}
                        <div className="flex flex-col">
                            <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Representante</label>
                            <MultiSelect
                                options={representantes.map(rep => ({ value: rep, label: rep }))}
                                selected={representante}
                                onChange={setRepresentante}
                                placeholder="Todos os Representantes"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                        {/* Filtro por Feira */}
                        <div className="flex flex-col">
                            <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Feira Originária</label>
                            <MultiSelect
                                options={feiras.map(f => ({ value: f.id.toString(), label: f.nome }))}
                                selected={feiraId}
                                onChange={setFeiraId}
                                placeholder="Todas as Feiras"
                            />
                        </div>

                        {/* Valor Unitário Min */}
                        <div className="flex flex-col">
                            <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Valor Unitário Mínimo (R$)</label>
                            <input
                                type="number"
                                step="0.01"
                                value={minValue}
                                onChange={(e) => setMinValue(e.target.value)}
                                placeholder="Min valor"
                                className="w-full py-2 bg-slate-50/50 border border-slate-200 focus:border-primary/50 focus:ring-1 focus:ring-primary/20 rounded-xl text-xs font-semibold text-primary transition-all"
                            />
                        </div>

                        {/* Valor Unitário Max */}
                        <div className="flex flex-col">
                            <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Valor Unitário Máximo (R$)</label>
                            <input
                                type="number"
                                step="0.01"
                                value={maxValue}
                                onChange={(e) => setMaxValue(e.target.value)}
                                placeholder="Max valor"
                                className="w-full py-2 bg-slate-50/50 border border-slate-200 focus:border-primary/50 focus:ring-1 focus:ring-primary/20 rounded-xl text-xs font-semibold text-primary transition-all"
                            />
                        </div>

                        {/* Botão Filtrar */}
                        <div>
                            <button
                                type="submit"
                                className="w-full bg-primary text-white font-extrabold text-xs uppercase tracking-wider py-2.5 rounded-xl hover:bg-primary/95 transition-all shadow-md shadow-primary/10 active:scale-[0.98] flex items-center justify-center gap-1.5"
                            >
                                <span className="material-symbols-outlined text-[16px]">search</span>
                                Aplicar Filtros
                            </button>
                        </div>
                    </div>
                </form>

                {/* Tabela de Dados */}
                <div className="bg-white rounded-2xl overflow-hidden shadow-sm border border-primary/5">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="bg-slate-50/50 border-b border-slate-100">
                                    <th className="py-5 px-8 text-[11px] font-bold text-primary/40 uppercase tracking-widest">ID</th>
                                    <th className="py-5 px-8 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Nome do Livro</th>
                                    <th className="py-5 px-8 text-[11px] font-bold text-primary/40 uppercase tracking-widest text-right">Valor Unitário</th>
                                    <th className="py-5 px-8 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Categoria</th>
                                    <th className="py-5 px-8 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Editora</th>
                                    <th className="py-5 px-8 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Representante</th>
                                    <th className="py-5 px-8 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Feira</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-50 text-sm font-manrope">
                                {livros.data && livros.data.length > 0 ? (
                                    livros.data.map((livro) => (
                                        <tr key={livro.id} className="hover:bg-slate-50/50 transition-colors group">
                                            <td className="py-5 px-8 font-mono text-xs text-primary/50">#{livro.produto_id_api}</td>
                                            <td className="py-5 px-8">
                                                <Link 
                                                    href={route('catalogo.show', livro.id)}
                                                    className="font-bold text-primary hover:underline hover:text-primary/80 transition-colors"
                                                >
                                                    {livro.produto}
                                                </Link>
                                            </td>
                                            <td className="py-5 px-8 text-right font-mono font-bold text-primary">
                                                {formatCurrency(livro.valor)}
                                            </td>
                                            <td className="py-5 px-8 font-semibold text-primary/70">
                                                {livro.categoria || 'Não Categorizado'}
                                            </td>
                                            <td className="py-5 px-8 font-semibold text-primary/70">
                                                {livro.editora}
                                            </td>
                                            <td className="py-5 px-8 font-semibold text-primary/70">
                                                {livro.representante}
                                            </td>
                                            <td className="py-5 px-8">
                                                <span className="px-2.5 py-1 bg-slate-100 rounded-lg text-primary text-[10px] font-extrabold uppercase whitespace-nowrap">
                                                    {feiras.find(f => f.id === livro.id_feira)?.nome || 'F. Desconhecida'}
                                                </span>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={7} className="py-16 text-center text-primary/40 italic text-sm">
                                            Nenhum livro encontrado com os filtros aplicados.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Paginação do Backend */}
                    {livros.links && livros.links.length > 3 && (
                        <div className="px-8 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between text-xs">
                            <span className="font-extrabold text-primary/40 uppercase tracking-widest">
                                Exibindo {livros.from || 0}-{livros.to || 0} de {livros.total} livros
                            </span>
                            <div className="flex items-center gap-1.5">
                                {livros.links.map((link, idx) => {
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
            </main>
        </AppLayout>
    );
}
