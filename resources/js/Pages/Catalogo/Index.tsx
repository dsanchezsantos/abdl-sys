import AppLayout from '@/Layouts/AppLayout';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

type Livro = {
    id: number;
    produto_id_api: number;
    produto: string;
    valor: string;
    categoria: string | null;
    editora: string;
    representante: string;
    isbn: string;
};

type Props = {
    livros: Livro[];
};

export default function Index({ livros }: Props) {
    const [searchTerm, setSearchTerm] = useState('');
    const [selectedItems, setSelectedItems] = useState<number[]>([]);

    const filteredLivros = livros.filter(livro => 
        livro.produto.toLowerCase().includes(searchTerm.toLowerCase()) ||
        livro.isbn.toLowerCase().includes(searchTerm.toLowerCase())
    );

    const toggleSelectAll = () => {
        if (selectedItems.length === filteredLivros.length) {
            setSelectedItems([]);
        } else {
            setSelectedItems(filteredLivros.map(l => l.id));
        }
    };

    const toggleSelectItem = (id: number) => {
        if (selectedItems.includes(id)) {
            setSelectedItems(selectedItems.filter(i => i !== id));
        } else {
            setSelectedItems([...selectedItems, id]);
        }
    };

    return (
        <AppLayout activeItem="catalogo">
            <Head title="Catálogo de Obras" />
            
            {/* TopNavBar */}
            <header className="flex justify-between items-center w-full px-8 py-4 sticky top-0 z-30 bg-surface/80 backdrop-blur-xl font-manrope font-semibold text-primary">
                <div className="flex items-center gap-6">
                    <h1 className="text-on-surface font-extrabold text-xl">Catálogo de Obras</h1>
                    <div className="relative group">
                        <div className="absolute inset-y-0 left-3 flex items-center pointer-events-none text-outline">
                            <span className="material-symbols-outlined text-[20px]">search</span>
                        </div>
                        <input 
                            className="bg-surface-container-low border-none rounded-full pl-10 pr-4 py-2 text-sm w-80 focus:ring-2 focus:ring-primary/20 transition-all" 
                            placeholder="Buscar no catálogo..." 
                            type="text"
                            value={searchTerm}
                            onChange={(e) => setSearchTerm(e.target.value)}
                        />
                    </div>
                </div>
                <div className="flex items-center gap-4">
                    <button className="flex items-center gap-2 px-4 py-2 bg-primary text-white rounded-lg hover:opacity-90 active:scale-[0.98] transition-all font-bold text-sm">
                        <span className="material-symbols-outlined text-[18px]">sync</span> Sincronizar Dados
                    </button>
                    <button className="p-2 text-on-surface-variant hover:bg-surface-container-high rounded-lg transition-all">
                        <span className="material-symbols-outlined">notifications</span>
                    </button>
                    <button className="p-2 text-on-surface-variant hover:bg-surface-container-high rounded-lg transition-all">
                        <span className="material-symbols-outlined">settings</span>
                    </button>
                </div>
            </header>

            <main className="p-8 flex-1">
                {/* Page Header & Quick Filter */}
                <div className="flex flex-col md:flex-row md:items-end justify-between mb-8 gap-4">
                    <div>
                        <h2 className="text-3xl font-extrabold text-on-background tracking-tight mb-2">Catálogo de Obras</h2>
                        <p className="text-on-surface-variant max-w-xl">Enriquecimento manual de metadados. Gerencie categorias, representantes e ISBNs para garantir a precisão da auditoria física.</p>
                    </div>
                    <div>
                        <button className="flex items-center gap-2 px-4 py-2.5 bg-surface-container-highest text-primary font-bold rounded-lg hover:bg-surface-container-high transition-colors shadow-sm">
                            <span className="material-symbols-outlined text-[20px]">person_off</span>
                            <span>Mostrar apenas livros sem Representante</span>
                        </button>
                    </div>
                </div>

                {/* Selection Overlay Action Bar */}
                {selectedItems.length > 0 && (
                    <div className="mb-6 animate-in fade-in slide-in-from-top-4 duration-300">
                        <div className="bg-primary-container/10 border border-primary-container/20 p-4 rounded-xl flex items-center justify-between">
                            <div className="flex items-center gap-4">
                                <div className="bg-primary-container text-white px-3 py-1 rounded-full text-xs font-bold">{selectedItems.length} itens selecionados</div>
                                <p className="text-primary font-semibold text-sm">Edição em Massa</p>
                            </div>
                            <div className="flex items-center gap-3">
                                <div className="flex items-center gap-2">
                                    <span className="text-xs text-on-surface-variant font-medium">Alterar Representante para:</span>
                                    <select className="bg-surface-container-lowest border-outline-variant/30 text-sm rounded-lg py-1 px-3 focus:ring-primary focus:border-primary">
                                        <option>Distribuidora Global</option>
                                        <option>Logística Nordeste</option>
                                        <option>Artes Gráficas SA</option>
                                    </select>
                                </div>
                                <button className="bg-primary text-on-primary px-4 py-1.5 rounded-lg text-sm font-bold shadow-md hover:brightness-110 transition-all">
                                    Aplicar Alterações
                                </button>
                                <button onClick={() => setSelectedItems([])} className="text-on-surface-variant hover:text-error transition-colors px-2">
                                    <span className="material-symbols-outlined">close</span>
                                </button>
                            </div>
                        </div>
                    </div>
                )}

                {/* Data Table Layout */}
                <div className="bg-surface-container-low rounded-2xl overflow-hidden shadow-sm border border-outline-variant/10">
                    <div className="overflow-x-auto">
                        <table className="w-full text-left border-collapse">
                            <thead>
                                <tr className="bg-surface-container text-on-surface-variant text-xs font-bold uppercase tracking-widest">
                                    <th className="py-5 px-6 w-12">
                                        <input 
                                            className="rounded border-outline-variant text-primary focus:ring-primary cursor-pointer" 
                                            type="checkbox"
                                            checked={selectedItems.length === filteredLivros.length && filteredLivros.length > 0}
                                            onChange={toggleSelectAll}
                                        />
                                    </th>
                                    <th className="py-5 px-4">ID</th>
                                    <th className="py-5 px-4">Nome do Produto</th>
                                    <th className="py-5 px-4 text-right">Valor Unitário</th>
                                    <th className="py-5 px-4">Categoria</th>
                                    <th className="py-5 px-4">Editora</th>
                                    <th className="py-5 px-4">Representante</th>
                                    <th className="py-5 px-4">ISBN</th>
                                    <th className="py-5 px-6 text-center">Ações</th>
                                </tr>
                            </thead>
                            <tbody className="divide-y divide-outline-variant/10 text-sm bg-surface-container-lowest">
                                {filteredLivros.map((livro) => (
                                    <tr key={livro.id} className="hover:bg-primary-fixed/20 transition-colors group">
                                        <td className="py-4 px-6">
                                            <input 
                                                className="rounded border-outline-variant text-primary focus:ring-primary cursor-pointer" 
                                                type="checkbox" 
                                                checked={selectedItems.includes(livro.id)}
                                                onChange={() => toggleSelectItem(livro.id)}
                                            />
                                        </td>
                                        <td className="py-4 px-4 font-mono text-xs text-outline">#{livro.produto_id_api}</td>
                                        <td className="py-4 px-4 font-semibold text-on-surface">{livro.produto}</td>
                                        <td className="py-4 px-4 text-right font-mono font-bold">R$ {parseFloat(livro.valor).toLocaleString('pt-BR', { minimumFractionDigits: 2 })}</td>
                                        <td className="py-4 px-4">
                                            <select className="bg-transparent border-none text-sm p-0 focus:ring-0 text-primary font-medium hover:underline cursor-pointer">
                                                <option>{livro.categoria || 'Não Categorizado'}</option>
                                            </select>
                                        </td>
                                        <td className="py-4 px-4">{livro.editora}</td>
                                        <td className="py-4 px-4">
                                            <div className={`flex items-center gap-1 ${livro.representante === 'NAO INFORMADO' ? 'bg-error-container/30 px-2 py-1 rounded border border-error/20' : 'border-b border-dashed border-outline-variant/50 hover:border-primary'} transition-colors`}>
                                                {livro.representante === 'NAO INFORMADO' && (
                                                    <span className="material-symbols-outlined text-[16px] text-error">warning</span>
                                                )}
                                                <select className={`bg-transparent border-none text-sm p-0 focus:ring-0 w-full cursor-pointer ${livro.representante === 'NAO INFORMADO' ? 'text-error font-bold' : ''}`}>
                                                    <option>{livro.representante}</option>
                                                    <option>Distribuidora Global</option>
                                                    <option>Logística Nordeste</option>
                                                </select>
                                                {livro.representante !== 'NAO INFORMADO' && (
                                                    <span className="material-symbols-outlined text-[16px] text-outline group-hover:text-primary">edit_note</span>
                                                )}
                                            </div>
                                        </td>
                                        <td className="py-4 px-4 font-mono text-xs tracking-tight">{livro.isbn}</td>
                                        <td className="py-4 px-6 text-center">
                                            <button className="text-outline hover:text-primary transition-colors">
                                                <span className="material-symbols-outlined">more_horiz</span>
                                            </button>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                    {/* Footer / Pagination */}
                    <div className="px-8 py-4 bg-surface-container flex items-center justify-between border-t border-outline-variant/10 text-xs font-bold text-on-surface-variant uppercase tracking-widest">
                        <div className="flex items-center gap-4">
                            <span>Exibindo 1-{filteredLivros.length} de {filteredLivros.length} itens</span>
                            <div className="flex items-center gap-2">
                                <span>Linhas por página:</span>
                                <select className="bg-transparent border-none focus:ring-0 p-0 text-xs font-bold uppercase">
                                    <option>50</option>
                                    <option>100</option>
                                    <option>200</option>
                                </select>
                            </div>
                        </div>
                        <div className="flex items-center gap-2">
                            <button className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/50 transition-colors">
                                <span className="material-symbols-outlined">chevron_left</span>
                            </button>
                            <span className="px-3 py-1 bg-primary text-white rounded-lg">1</span>
                            <button className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/50 transition-colors">2</button>
                            <button className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/50 transition-colors">3</button>
                            <span>...</span>
                            <button className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/50 transition-colors">25</button>
                            <button className="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-white/50 transition-colors">
                                <span className="material-symbols-outlined">chevron_right</span>
                            </button>
                        </div>
                    </div>
                </div>

                {/* Contextual Help / Metadata Status Bento Section */}
                <div className="mt-12 grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div className="p-6 bg-surface-container-highest/40 rounded-2xl border border-primary/5 flex flex-col justify-between">
                        <div>
                            <div className="w-10 h-10 rounded-full bg-primary/10 flex items-center justify-center text-primary mb-4">
                                <span className="material-symbols-outlined">verified</span>
                            </div>
                            <h3 className="text-lg font-bold mb-2">Integridade do Catálogo</h3>
                            <p className="text-sm text-on-surface-variant leading-relaxed">84% dos itens possuem metadados completos. Priorize livros marcados com aviso de pendência.</p>
                        </div>
                        <div className="mt-4 pt-4 border-t border-outline-variant/10">
                            <div className="w-full bg-surface-container rounded-full h-2">
                                <div className="bg-primary h-2 rounded-full w-[84%]"></div>
                            </div>
                        </div>
                    </div>
                    <div className="p-6 bg-surface-container-lowest rounded-2xl shadow-sm flex items-center gap-4 border border-outline-variant/5">
                        <div className="flex-1">
                            <p className="text-xs font-bold text-outline uppercase mb-1">Última Sincronização</p>
                            <p className="text-xl font-extrabold text-on-surface">Há 12 minutos</p>
                            <p className="text-xs text-tertiary-container font-semibold mt-1">Conexão estável com API ISBN</p>
                        </div>
                        <div className="w-12 h-12 bg-tertiary-container/10 flex items-center justify-center rounded-xl text-tertiary">
                            <span className="material-symbols-outlined text-[28px]">cloud_done</span>
                        </div>
                    </div>
                    <div className="p-6 bg-inverse-surface rounded-2xl flex flex-col justify-between text-white">
                        <p className="text-xs font-bold text-slate-400 uppercase tracking-widest">Dica de Auditoria</p>
                        <p className="text-sm font-medium leading-relaxed my-4">O campo "ISBN" é validado automaticamente. Se estiver incorreto, a auditoria de estoque será bloqueada para este item.</p>
                        <a className="text-blue-400 text-xs font-bold flex items-center gap-1 group" href="#">
                            Ver documentação 
                            <span className="material-symbols-outlined text-[14px] group-hover:translate-x-1 transition-transform">arrow_forward</span>
                        </a>
                    </div>
                </div>
            </main>
        </AppLayout>
    );
}
