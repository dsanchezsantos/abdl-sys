import AppLayout from '@/Layouts/AppLayout';
import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import MultiSelect from '@/Components/MultiSelect';

type Cartao = {
    id: number;
    id_feira: number;
    tag_code: string;
    grupo: string | null;
    classificacao: 'ALUNO' | 'TESTE' | 'CORTESIA' | 'STAFF';
    identificacao_aluno: string | null;
    feira?: {
        id: number;
        nome: string;
    };
};

type Props = {
    cartoes: {
        data: Cartao[];
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
        classificacao?: string[];
        feira_id?: string[];
        grupo?: string[];
    };
    grupos: string[];
    classificacoes: Array<{ value: string; label: string }>;
    feiras: Array<{ id: number; nome: string }>;
};

export default function Index({ cartoes, filters, grupos, classificacoes, feiras }: Props) {
    const [search, setSearch] = useState(filters.search || '');
    const [classificacao, setClassificacao] = useState<string[]>(filters.classificacao || []);
    const [grupo, setGrupo] = useState<string[]>(filters.grupo || []);
    const [feiraId, setFeiraId] = useState<string[]>(filters.feira_id || []);

    const handleFilter = (e: React.FormEvent) => {
        e.preventDefault();
        router.get(route('cartoes.index'), {
            search,
            classificacao,
            grupo,
            feira_id: feiraId
        }, {
            preserveState: true,
            replace: true
        });
    };

    const handleClear = () => {
        setSearch('');
        setClassificacao([]);
        setGrupo([]);
        setFeiraId([]);

        router.get(route('cartoes.index'), {}, {
            preserveState: true,
            replace: true
        });
    };

    const getClassBadge = (cls: Cartao['classificacao']) => {
        const styles = {
            ALUNO: 'bg-blue-50 text-blue-700 border-blue-200',
            TESTE: 'bg-red-50 text-red-700 border-red-200',
            CORTESIA: 'bg-purple-50 text-purple-700 border-purple-200',
            STAFF: 'bg-slate-100 text-slate-700 border-slate-300'
        };
        const labels = {
            ALUNO: 'Aluno',
            TESTE: 'Teste',
            CORTESIA: 'Cortesia',
            STAFF: 'Staff'
        };
        return (
            <span className={`px-2.5 py-1 rounded-full text-[10px] font-extrabold uppercase border shadow-sm whitespace-nowrap ${styles[cls] || styles.ALUNO}`}>
                {labels[cls] || cls}
            </span>
        );
    };

    return (
        <AppLayout activeItem="cartoes">
            <Head title="Gerenciamento de Cartões" />

            {/* TopNavBar */}
            <header className="flex justify-between items-center w-full px-8 py-4 sticky top-0 z-30 bg-surface/80 backdrop-blur-xl font-manrope font-semibold text-primary border-b border-primary/5">
                <div className="flex items-center gap-6">
                    <h1 className="text-on-surface font-extrabold text-xl">Consulta de Cartões</h1>
                </div>
            </header>

            <main className="p-8 flex-1 font-manrope">

                {/* Filtros de Cartões */}
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
                        {/* Busca por Código ou Aluno */}
                        <div className="flex flex-col">
                            <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Buscar por Tag ou Aluno</label>
                            <div className="relative">
                                <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-primary/30 text-[18px]">search</span>
                                <input
                                    type="text"
                                    value={search}
                                    onChange={(e) => setSearch(e.target.value)}
                                    placeholder="Ex: tag_code ou Nome Aluno..."
                                    className="w-full pl-10 pr-4 py-2 bg-slate-50/50 border border-slate-200 focus:border-primary/50 focus:ring-1 focus:ring-primary/20 rounded-xl text-xs font-semibold text-primary transition-all placeholder:text-primary/20"
                                />
                            </div>
                        </div>

                        {/* Filtro por Classificação */}
                        <div className="flex flex-col">
                            <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Classificação</label>
                            <MultiSelect
                                options={classificacoes}
                                selected={classificacao}
                                onChange={setClassificacao}
                                placeholder="Todas as Classificações"
                            />
                        </div>

                        {/* Filtro por Grupo / Escola */}
                        <div className="flex flex-col">
                            <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Grupo / Escola</label>
                            <MultiSelect
                                options={grupos.map(g => ({ value: g, label: g }))}
                                selected={grupo}
                                onChange={setGrupo}
                                placeholder="Todos os Grupos / Escolas"
                            />
                        </div>

                        {/* Filtro por Feira */}
                        <div className="flex flex-col">
                            <label className="text-[10px] font-bold text-primary/50 uppercase tracking-widest block mb-1">Feira</label>
                            <MultiSelect
                                options={feiras.map(f => ({ value: f.id.toString(), label: f.nome }))}
                                selected={feiraId}
                                onChange={setFeiraId}
                                placeholder="Todas as Feiras"
                            />
                        </div>
                    </div>

                    <div className="flex justify-end pt-2">
                        <button
                            type="submit"
                            className="bg-primary text-white font-extrabold text-xs uppercase tracking-wider py-2.5 px-6 rounded-xl hover:bg-primary/95 transition-all shadow-md shadow-primary/10 active:scale-[0.98] flex items-center gap-1.5"
                        >
                            <span className="material-symbols-outlined text-[16px]">search</span>
                            Filtrar Cartões
                        </button>
                    </div>
                </form>

                {/* Tabela de Cartões */}
                <div className="bg-white rounded-2xl overflow-hidden shadow-sm border border-primary/5">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="bg-slate-50/50 border-b border-slate-100">
                                    <th className="py-5 px-8 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Código / Tag</th>
                                    <th className="py-5 px-8 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Aluno / Identificação</th>
                                    <th className="py-5 px-8 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Grupo / Escola</th>
                                    <th className="py-5 px-8 text-[11px] font-bold text-primary/40 uppercase tracking-widest text-center">Classificação</th>
                                    <th className="py-5 px-8 text-[11px] font-bold text-primary/40 uppercase tracking-widest">Feira Originária</th>
                                    <th className="py-5 px-8 text-[11px] font-bold text-primary/40 uppercase tracking-widest text-right">Ações</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-slate-50 text-sm font-manrope">
                                {cartoes.data && cartoes.data.length > 0 ? (
                                    cartoes.data.map((c) => (
                                        <tr key={c.id} className="hover:bg-slate-50/50 transition-colors group">
                                            <td className="py-5 px-8">
                                                <Link 
                                                    href={route('cartoes.show', c.id)}
                                                    className="font-mono font-bold text-primary hover:underline hover:text-primary/80"
                                                >
                                                    #{c.tag_code}
                                                </Link>
                                            </td>
                                            <td className="py-5 px-8 font-semibold text-primary/80">
                                                {c.identificacao_aluno || <span className="text-primary/20 italic">---</span>}
                                            </td>
                                            <td className="py-5 px-8 font-semibold text-primary/80">
                                                {c.grupo || <span className="text-primary/20 italic">---</span>}
                                            </td>
                                            <td className="py-5 px-8 text-center">
                                                {getClassBadge(c.classificacao)}
                                            </td>
                                            <td className="py-5 px-8">
                                                <span className="px-2.5 py-1 bg-slate-100 rounded-lg text-primary text-[10px] font-extrabold uppercase whitespace-nowrap">
                                                    {c.feira?.nome || 'F. Desconhecida'}
                                                </span>
                                            </td>
                                            <td className="py-5 px-8 text-right">
                                                <Link 
                                                    href={route('cartoes.show', c.id)}
                                                    className="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold bg-primary/5 text-primary hover:bg-primary/10 transition-all active:scale-95"
                                                >
                                                    <span className="material-symbols-outlined text-[16px]">visibility</span>
                                                    Estatísticas
                                                </Link>
                                            </td>
                                        </tr>
                                    ))
                                ) : (
                                    <tr>
                                        <td colSpan={6} className="py-16 text-center text-primary/40 italic text-sm">
                                            Nenhum cartão cadastrado com os filtros selecionados.
                                        </td>
                                    </tr>
                                )}
                            </tbody>
                        </table>
                    </div>

                    {/* Paginação */}
                    {cartoes.links && cartoes.links.length > 3 && (
                        <div className="px-8 py-4 border-t border-slate-100 bg-slate-50/50 flex items-center justify-between text-xs">
                            <span className="font-extrabold text-primary/40 uppercase tracking-widest">
                                Exibindo {cartoes.from || 0}-{cartoes.to || 0} de {cartoes.total} cartões
                            </span>
                            <div className="flex items-center gap-1.5">
                                {cartoes.links.map((link, idx) => {
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
