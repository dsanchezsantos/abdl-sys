import AppLayout from '@/Layouts/AppLayout';
import AppSidebar from '@/Components/AppSidebar';
import { Head, Link, useForm, usePage } from '@inertiajs/react';
import { useState, useEffect } from 'react';

type User = {
    id: number;
    name: string;
    email: string;
    cpf: string | null;
    apelido: string | null;
};

type Convite = {
    id: number;
    email: string;
    token: string;
    expires_at: string;
    used_at: string | null;
    status: 'ativo' | 'usado' | 'expirado';
    link: string;
};

type Paginated<T> = {
    data: T[];
    current_page: number;
    last_page: number;
    per_page: number;
    total: number;
    from: number;
    to: number;
    links: Array<{ url: string | null; label: string; active: boolean }>;
};

type Props = {
    users: Paginated<User>;
    convites: Paginated<Convite>;
};

export default function Index({ users, convites }: Props) {
    const { auth, flash } = usePage().props as any;
    const currentUserId = auth.user.id;

    const [copiedToken, setCopiedToken] = useState<string | null>(null);
    const [successInvite, setSuccessInvite] = useState<{ email: string; link: string } | null>(null);

    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
    });

    useEffect(() => {
        if (flash.success && typeof flash.success === 'object' && flash.success.link) {
            setSuccessInvite(flash.success);
            reset('email');
        }
    }, [flash.success]);

    const handleInvite = (e: React.FormEvent) => {
        e.preventDefault();
        post(route('usuarios.convites.store'));
    };

    const handleCopy = (link: string, id: string) => {
        navigator.clipboard.writeText(link);
        setCopiedToken(id);
        setTimeout(() => setCopiedToken(null), 3000);
    };

    const formatCPF = (cpf: string | null) => {
        if (!cpf) return '---';
        const clean = cpf.replace(/\D/g, '');
        if (clean.length !== 11) return cpf;
        return clean.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    };

    const formatDate = (dateStr: string) => {
        return new Date(dateStr).toLocaleString('pt-BR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
        });
    };

    return (
        <AppLayout>
            <Head title="Gerenciamento de Usuários" />

            <div className="flex bg-surface min-h-screen">
                <AppSidebar activeItem="usuarios" />

                <main className="flex-1 min-w-0 transition-all duration-300">
                    <header className="flex justify-between items-center px-8 py-6 sticky top-0 z-30 bg-surface/80 backdrop-blur-xl border-b border-outline-variant/10">
                        <div>
                            <h1 className="text-on-surface font-extrabold text-xl font-headline uppercase tracking-tight">Gestão de Acessos</h1>
                            <p className="text-on-surface-variant text-xs font-semibold mt-0.5">Gerencie os usuários do sistema e crie novos convites de acesso.</p>
                        </div>
                    </header>

                    <div className="p-8 max-w-7xl mx-auto grid grid-cols-12 gap-8">
                        {/* Coluna Esquerda: Convite */}
                        <section className="col-span-12 lg:col-span-4 flex flex-col gap-6">
                            <div className="bg-surface-container-lowest p-6 rounded-2xl border border-outline-variant/10 shadow-[0_20px_40px_-10px_rgba(19,27,46,0.05)] flex flex-col gap-6">
                                <div>
                                    <h2 className="text-lg font-bold text-on-surface font-headline">Convidar Novo Usuário</h2>
                                    <p className="text-xs text-on-surface-variant font-body mt-1">
                                        Digite o e-mail da pessoa que você deseja convidar. Um link seguro válido por 6 horas será gerado.
                                    </p>
                                </div>

                                <form onSubmit={handleInvite} className="flex flex-col gap-4">
                                    <div className="flex flex-col gap-1.5">
                                        <label className="text-xs font-bold text-on-surface-variant tracking-wider uppercase px-1">E-mail do Convidado</label>
                                        <input
                                            type="email"
                                            value={data.email}
                                            onChange={e => setData('email', e.target.value)}
                                            placeholder="exemplo@abdl.com.br"
                                            className="w-full bg-surface-container-low border border-outline-variant/30 rounded-lg py-3 px-4 font-body text-on-surface focus:border-primary/50 focus:ring-0 transition-all text-sm"
                                            required
                                        />
                                        {errors.email && (
                                            <span className="text-error text-xs font-semibold px-1 mt-1">{errors.email}</span>
                                        )}
                                    </div>

                                    <button
                                        type="submit"
                                        disabled={processing}
                                        className="w-full bg-gradient-to-br from-primary to-primary-container hover:brightness-110 text-white font-headline font-bold py-3.5 rounded-lg shadow-md transition-all active:scale-[0.98] disabled:opacity-50"
                                    >
                                        {processing ? 'Gerando...' : 'Gerar Convite'}
                                    </button>
                                </form>

                                {/* Link Gerado com Sucesso */}
                                {successInvite && (
                                    <div className="mt-2 bg-emerald-50 border border-emerald-100 p-4 rounded-xl flex flex-col gap-3 animation-fade-in">
                                        <div className="flex items-center gap-2 text-emerald-800">
                                            <span className="material-symbols-outlined text-[20px]">check_circle</span>
                                            <span className="text-xs font-bold font-headline uppercase tracking-wider">Convite Gerado!</span>
                                        </div>
                                        <p className="text-[11px] text-slate-500 font-body">
                                            Copie o link abaixo e envie manualmente para o e-mail <strong className="text-slate-800">{successInvite.email}</strong>.
                                        </p>
                                        <div className="flex items-center gap-1.5 bg-white border border-emerald-100 rounded-lg p-1">
                                            <input
                                                type="text"
                                                readOnly
                                                value={successInvite.link}
                                                className="flex-1 bg-transparent border-none text-[11px] font-mono text-slate-700 py-1.5 px-2 focus:ring-0 select-all"
                                            />
                                            <button
                                                onClick={() => handleCopy(successInvite.link, 'success-box')}
                                                className={`px-3 py-1.5 rounded text-[10px] font-bold uppercase transition-all flex items-center gap-1 shrink-0 ${copiedToken === 'success-box' ? 'bg-emerald-600 text-white' : 'bg-surface-container-low hover:bg-surface-container-high text-on-surface'}`}
                                            >
                                                <span className="material-symbols-outlined text-[14px]">
                                                    {copiedToken === 'success-box' ? 'done' : 'content_copy'}
                                                </span>
                                                {copiedToken === 'success-box' ? 'Copiado' : 'Copiar'}
                                            </button>
                                        </div>
                                    </div>
                                )}
                            </div>
                        </section>

                        {/* Coluna Direita: Tabelas */}
                        <section className="col-span-12 lg:col-span-8 flex flex-col gap-8">
                            {/* Card 1: Usuários Ativos */}
                            <div className="bg-surface-container-lowest rounded-2xl border border-outline-variant/10 overflow-hidden flex flex-col shadow-[0_20px_40px_-10px_rgba(19,27,46,0.05)]">
                                <div className="p-6 border-b border-outline-variant/10 flex justify-between items-center">
                                    <h3 className="text-lg font-bold text-on-surface font-headline">Usuários Ativos</h3>
                                    <span className="bg-surface-container-highest text-primary text-xs font-bold px-2 py-0.5 rounded-full">{users.total} Cadastrados</span>
                                </div>

                                <div className="overflow-x-auto">
                                    <table className="w-full text-left border-collapse">
                                        <thead>
                                            <tr className="bg-surface-container-low/50 border-b border-outline-variant/10">
                                                <th className="px-6 py-4 text-xs font-bold text-primary tracking-widest uppercase">Nome / Apelido</th>
                                                <th className="px-6 py-4 text-xs font-bold text-primary tracking-widest uppercase">E-mail</th>
                                                <th className="px-6 py-4 text-xs font-bold text-primary tracking-widest uppercase text-center">CPF</th>
                                                <th className="px-6 py-4 text-xs font-bold text-primary tracking-widest uppercase text-right">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-outline-variant/10">
                                            {users.data.map(user => (
                                                <tr key={user.id} className="hover:bg-surface-container-low transition-colors">
                                                    <td className="px-6 py-5">
                                                        <div className="flex flex-col">
                                                            <span className="font-bold text-on-surface font-body">{user.name}</span>
                                                            <span className="text-[11px] text-primary/60 font-bold uppercase tracking-tight">{user.apelido || '---'}</span>
                                                        </div>
                                                    </td>
                                                    <td className="px-6 py-5 text-sm text-on-surface-variant font-body">
                                                        {user.email}
                                                    </td>
                                                    <td className="px-6 py-5 text-center text-sm text-on-surface-variant font-body">
                                                        {formatCPF(user.cpf)}
                                                    </td>
                                                    <td className="px-6 py-5 text-right">
                                                        {user.id !== currentUserId ? (
                                                            <Link
                                                                href={route('usuarios.destroy', { user: user.id })}
                                                                method="delete"
                                                                as="button"
                                                                className="text-error hover:bg-error-container/20 p-2 rounded-lg transition-colors inline-block"
                                                                title="Remover Usuário"
                                                                onClick={(e) => {
                                                                    if (!confirm(`Tem certeza que deseja remover o acesso do usuário ${user.name}?`)) {
                                                                        e.preventDefault();
                                                                    }
                                                                }}
                                                            >
                                                                <span className="material-symbols-outlined text-[18px]">delete</span>
                                                            </Link>
                                                        ) : (
                                                            <span className="text-outline-variant text-xs font-semibold px-2 py-1 select-none">Você</span>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                            {users.data.length === 0 && (
                                                <tr>
                                                    <td colSpan={4} className="text-center p-8 text-outline text-xs">Nenhum usuário cadastrado.</td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>

                                {/* Paginação Usuários Ativos */}
                                {users.links && users.links.length > 3 && (
                                    <div className="px-6 py-4 border-t border-outline-variant/10 bg-surface-container-low/30 flex items-center justify-between text-[10px] font-bold text-primary/60 uppercase tracking-wider">
                                        <span>Exibindo {users.from || 0}-{users.to || 0} de {users.total}</span>
                                        <div className="flex items-center gap-1">
                                            {users.links.map((link, idx) => (
                                                <Link
                                                    key={idx}
                                                    href={link.url || '#'}
                                                    className={`px-2.5 py-1 rounded border transition-all ${!link.url ? 'opacity-40 cursor-default pointer-events-none border-outline-variant/20 text-outline-variant' : link.active ? 'bg-primary text-on-primary border-primary shadow-sm' : 'bg-white hover:bg-surface-container-low text-primary border-outline-variant/30'}`}
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>

                            {/* Card 2: Histórico de Convites */}
                            <div className="bg-surface-container-lowest rounded-2xl border border-outline-variant/10 overflow-hidden flex flex-col shadow-[0_20px_40px_-10px_rgba(19,27,46,0.05)]">
                                <div className="p-6 border-b border-outline-variant/10 flex justify-between items-center">
                                    <h3 className="text-lg font-bold text-on-surface font-headline">Histórico de Convites</h3>
                                    <span className="bg-surface-container-highest text-primary text-xs font-bold px-2 py-0.5 rounded-full">{convites.total} Gerados</span>
                                </div>

                                <div className="overflow-x-auto">
                                    <table className="w-full text-left border-collapse">
                                        <thead>
                                            <tr className="bg-surface-container-low/50 border-b border-outline-variant/10">
                                                <th className="px-6 py-4 text-xs font-bold text-primary tracking-widest uppercase">E-mail Convidado</th>
                                                <th className="px-6 py-4 text-xs font-bold text-primary tracking-widest uppercase text-center">Status</th>
                                                <th className="px-6 py-4 text-xs font-bold text-primary tracking-widest uppercase text-center">Data Limite</th>
                                                <th className="px-6 py-4 text-xs font-bold text-primary tracking-widest uppercase text-right">Ações</th>
                                            </tr>
                                        </thead>
                                        <tbody className="divide-y divide-outline-variant/10">
                                            {convites.data.map(convite => (
                                                <tr key={convite.id} className="hover:bg-surface-container-low transition-colors">
                                                    <td className="px-6 py-5 text-sm text-on-surface font-body">
                                                        {convite.email}
                                                    </td>
                                                    <td className="px-6 py-5 text-center">
                                                        {convite.status === 'usado' ? (
                                                            <span className="bg-green-100 text-green-700 text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-full">Utilizado</span>
                                                        ) : convite.status === 'expirado' ? (
                                                            <span className="bg-surface-container-highest text-outline text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-full">Expirado</span>
                                                        ) : (
                                                            <span className="bg-amber-100 text-amber-700 text-[10px] font-extrabold uppercase px-2.5 py-0.5 rounded-full">Ativo</span>
                                                        )}
                                                    </td>
                                                    <td className="px-6 py-5 text-center text-xs text-on-surface-variant font-body">
                                                        {formatDate(convite.expires_at)}
                                                    </td>
                                                    <td className="px-6 py-5 text-right">
                                                        {convite.status === 'ativo' ? (
                                                            <button
                                                                onClick={() => handleCopy(convite.link, `row-${convite.id}`)}
                                                                className={`p-2 rounded-lg transition-all inline-flex items-center justify-center ${copiedToken === `row-${convite.id}` ? 'bg-emerald-500 text-white' : 'text-outline hover:bg-surface-container-low'}`}
                                                                title="Copiar link do convite"
                                                            >
                                                                <span className="material-symbols-outlined text-[18px]">
                                                                    {copiedToken === `row-${convite.id}` ? 'done' : 'link'}
                                                                </span>
                                                            </button>
                                                        ) : (
                                                            <span className="text-outline-variant text-xs p-2 select-none">---</span>
                                                        )}
                                                    </td>
                                                </tr>
                                            ))}
                                            {convites.data.length === 0 && (
                                                <tr>
                                                    <td colSpan={4} className="text-center p-8 text-outline text-xs">Nenhum convite gerado.</td>
                                                </tr>
                                            )}
                                        </tbody>
                                    </table>
                                </div>

                                {/* Paginação Histórico de Convites */}
                                {convites.links && convites.links.length > 3 && (
                                    <div className="px-6 py-4 border-t border-outline-variant/10 bg-surface-container-low/30 flex items-center justify-between text-[10px] font-bold text-primary/60 uppercase tracking-wider">
                                        <span>Exibindo {convites.from || 0}-{convites.to || 0} de {convites.total}</span>
                                        <div className="flex items-center gap-1">
                                            {convites.links.map((link, idx) => (
                                                <Link
                                                    key={idx}
                                                    href={link.url || '#'}
                                                    className={`px-2.5 py-1 rounded border transition-all ${!link.url ? 'opacity-40 cursor-default pointer-events-none border-outline-variant/20 text-outline-variant' : link.active ? 'bg-primary text-on-primary border-primary shadow-sm' : 'bg-white hover:bg-surface-container-low text-primary border-outline-variant/30'}`}
                                                    dangerouslySetInnerHTML={{ __html: link.label }}
                                                />
                                            ))}
                                        </div>
                                    </div>
                                )}
                            </div>
                        </section>
                    </div>
                </main>
            </div>
        </AppLayout>
    );
}
