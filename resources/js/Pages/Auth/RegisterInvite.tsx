import GuestLayout from '@/Layouts/GuestLayout';
import { Head, useForm } from '@inertiajs/react';
import { useEffect, useState } from 'react';

type Props = {
    email: string;
    token: string;
};

export default function RegisterInvite({ email, token }: Props) {
    const [userEditedNickname, setUserEditedNickname] = useState(false);

    const { data, setData, post, processing, errors } = useForm({
        name: '',
        email: '',
        cpf: '',
        apelido: '',
        password: '',
        password_confirmation: '',
    });

    // Atualização dinâmica do apelido à medida que preenche o nome completo
    useEffect(() => {
        if (!userEditedNickname) {
            const parts = data.name.trim().split(/\s+/).filter(Boolean);
            if (parts.length > 0) {
                const firstTwo = parts.slice(0, 2);
                const autoNickname = firstTwo
                    .map(word => word.charAt(0).toUpperCase() + word.slice(1).toLowerCase())
                    .join(' ');
                setData('apelido', autoNickname);
            } else {
                setData('apelido', '');
            }
        }
    }, [data.name]);

    const handleCPFChange = (e: React.ChangeEvent<HTMLInputElement>) => {
        let val = e.target.value.replace(/\D/g, '');
        if (val.length > 11) val = val.slice(0, 11);
        
        let formatted = val;
        if (val.length > 3) formatted = val.slice(0, 3) + '.' + val.slice(3);
        if (val.length > 6) formatted = formatted.slice(0, 7) + '.' + formatted.slice(7);
        if (val.length > 9) formatted = formatted.slice(0, 11) + '-' + formatted.slice(11);
        
        setData('cpf', formatted);
    };

    const [clientErrors, setClientErrors] = useState<Record<string, string>>({});

    // Validação reativa de requisitos da senha
    const passCriteria = {
        length: data.password.length >= 8,
        uppercase: /[A-Z]/.test(data.password),
        lowercase: /[a-z]/.test(data.password),
        number: /[0-9]/.test(data.password),
        special: /[^A-Za-z0-9]/.test(data.password),
    };

    const allCriteriaMet = Object.values(passCriteria).every(Boolean);

    const submit = (e: React.FormEvent) => {
        e.preventDefault();
        
        const newErrors: Record<string, string> = {};

        // 1. Campos vazios
        if (!data.name.trim()) newErrors.name = 'O nome completo é obrigatório.';
        if (!data.email.trim()) newErrors.email = 'A confirmação do e-mail é obrigatória.';
        if (!data.cpf.trim()) newErrors.cpf = 'O CPF é obrigatório.';
        if (!data.apelido.trim()) newErrors.apelido = 'O apelido é obrigatório.';
        if (!data.password) newErrors.password = 'A senha é obrigatória.';
        if (!data.password_confirmation) newErrors.password_confirmation = 'A confirmação de senha é obrigatória.';

        // 2. Nome completo deve ter pelo menos duas palavras
        if (data.name.trim()) {
            const words = data.name.trim().split(/\s+/).filter(Boolean);
            if (words.length < 2) {
                newErrors.name = 'O nome completo deve conter pelo menos o nome e um sobrenome.';
            }
        }

        // 3. Confirmar e-mail idêntico ao convite
        if (data.email.trim() && data.email.trim().toLowerCase() !== email.trim().toLowerCase()) {
            newErrors.email = 'O e-mail digitado não corresponde ao e-mail convidado.';
        }

        // 4. CPF tamanho
        if (data.cpf.trim()) {
            const cleanCPF = data.cpf.replace(/\D/g, '');
            if (cleanCPF.length !== 11) {
                newErrors.cpf = 'O CPF deve conter exatamente 11 dígitos.';
            }
        }

        // 5. Senha no padrão de segurança
        if (data.password && !allCriteriaMet) {
            newErrors.password = 'A senha não atende a todos os requisitos de segurança exigidos.';
        }

        // 6. Confirmação de senha idêntica
        if (data.password && data.password_confirmation && data.password !== data.password_confirmation) {
            newErrors.password_confirmation = 'A confirmação de senha não confere com a senha digitada.';
        }

        if (Object.keys(newErrors).length > 0) {
            setClientErrors(newErrors);
            return;
        }

        setClientErrors({});
        post(route('convite.register', { token }), {
            preserveState: true,
            preserveScroll: true,
        });
    };

    const clearError = (field: string) => {
        if (clientErrors[field]) {
            setClientErrors(prev => {
                const c = { ...prev };
                delete c[field];
                return c;
            });
        }
    };

    const allErrors = { ...errors, ...clientErrors };

    return (
        <GuestLayout>
            <Head title="Cadastro por Convite" />

            <div className="mb-6 text-center">
                <h2 className="text-xl font-bold text-on-surface font-headline">Crie sua Conta</h2>
                <p className="text-xs text-on-surface-variant font-body mt-1">
                    Insira seus dados para concluir o convite de acesso à plataforma.
                </p>
            </div>

            {Object.keys(allErrors).length > 0 && (
                <div className="mb-4 bg-red-50 border border-red-200 text-red-800 p-3 rounded-lg text-xs font-bold font-body">
                    <div className="flex items-center gap-1.5 mb-1 text-red-700">
                        <span className="material-symbols-outlined text-[16px]">error_outline</span>
                        <span className="uppercase tracking-wider">Erros encontrados:</span>
                    </div>
                    <ul className="list-disc list-inside space-y-0.5">
                        {Object.values(allErrors).map((err, idx) => (
                            <li key={idx}>{err}</li>
                        ))}
                    </ul>
                </div>
            )}

            <form onSubmit={submit} className="space-y-4">
                {/* Nome Completo */}
                <div className="flex flex-col gap-1">
                    <label className="text-[10px] font-extrabold text-on-surface-variant uppercase tracking-wider px-1">Nome Completo</label>
                    <input
                        type="text"
                        value={data.name}
                        onChange={e => {
                            setData('name', e.target.value);
                            clearError('name');
                        }}
                        placeholder="Nome completo do usuário"
                        className="w-full bg-surface-container-low border border-outline-variant/30 rounded-lg py-2.5 px-3 font-body text-on-surface text-sm focus:border-primary/50 focus:ring-0 transition-all"
                        required
                    />
                    {allErrors.name && <span className="text-error text-[10px] font-bold mt-0.5 px-1">{allErrors.name}</span>}
                </div>

                {/* E-mail de Confirmação */}
                <div className="flex flex-col gap-1">
                    <label className="text-[10px] font-extrabold text-on-surface-variant uppercase tracking-wider px-1">Confirmar E-mail</label>
                    <input
                        type="email"
                        value={data.email}
                        onChange={e => {
                            setData('email', e.target.value);
                            clearError('email');
                        }}
                        placeholder="Confirme o e-mail do seu convite"
                        className="w-full bg-surface-container-low border border-outline-variant/30 rounded-lg py-2.5 px-3 font-body text-on-surface text-sm focus:border-primary/50 focus:ring-0 transition-all"
                        required
                    />
                    <span className="text-[10px] text-outline px-1 font-medium">Você deve digitar exatamente o e-mail que recebeu o convite.</span>
                    {allErrors.email && <span className="text-error text-[10px] font-bold mt-0.5 px-1">{allErrors.email}</span>}
                </div>

                {/* CPF e Apelido */}
                <div className="grid grid-cols-2 gap-4">
                    <div className="flex flex-col gap-1">
                        <label className="text-[10px] font-extrabold text-on-surface-variant uppercase tracking-wider px-1">CPF</label>
                        <input
                            type="text"
                            value={data.cpf}
                            onChange={e => {
                                handleCPFChange(e);
                                clearError('cpf');
                            }}
                            placeholder="000.000.000-00"
                            className="w-full bg-surface-container-low border border-outline-variant/30 rounded-lg py-2.5 px-3 font-body text-on-surface text-sm focus:border-primary/50 focus:ring-0 transition-all"
                            required
                        />
                        {allErrors.cpf && <span className="text-error text-[10px] font-bold mt-0.5 px-1">{allErrors.cpf}</span>}
                    </div>

                    <div className="flex flex-col gap-1">
                        <label className="text-[10px] font-extrabold text-on-surface-variant uppercase tracking-wider px-1">Apelido no Sistema</label>
                        <input
                            type="text"
                            value={data.apelido}
                            onChange={e => {
                                setUserEditedNickname(true);
                                setData('apelido', e.target.value);
                                clearError('apelido');
                            }}
                            placeholder="Como quer ser chamado"
                            className="w-full bg-surface-container-low border border-outline-variant/30 rounded-lg py-2.5 px-3 font-body text-on-surface text-sm focus:border-primary/50 focus:ring-0 transition-all"
                            required
                        />
                        {allErrors.apelido && <span className="text-error text-[10px] font-bold mt-0.5 px-1">{allErrors.apelido}</span>}
                    </div>
                </div>

                {/* Senha */}
                <div className="flex flex-col gap-1">
                    <label className="text-[10px] font-extrabold text-on-surface-variant uppercase tracking-wider px-1">Senha</label>
                    <input
                        type="password"
                        value={data.password}
                        onChange={e => {
                            setData('password', e.target.value);
                            clearError('password');
                        }}
                        placeholder="••••••••"
                        className="w-full bg-surface-container-low border border-outline-variant/30 rounded-lg py-2.5 px-3 font-body text-on-surface text-sm focus:border-primary/50 focus:ring-0 transition-all"
                        required
                    />
                    {allErrors.password && <span className="text-error text-[10px] font-bold mt-0.5 px-1">{allErrors.password}</span>}
                </div>

                {/* Força da Senha Checklist */}
                {data.password.length > 0 && (
                    <div className="bg-surface-container-low border border-outline-variant/20 p-3 rounded-lg text-[10px] grid grid-cols-2 gap-2 text-outline font-bold uppercase tracking-tight">
                        <div className="col-span-2 text-[9px] text-outline font-bold tracking-wider mb-0.5">Requisitos da Senha:</div>
                        <div className="flex items-center gap-1.5">
                            <span className={`material-symbols-outlined text-[14px] ${passCriteria.length ? 'text-green-600' : 'text-outline-variant'}`}>
                                {passCriteria.length ? 'check_circle' : 'circle'}
                            </span>
                            <span>Mínimo de 8 caracteres</span>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <span className={`material-symbols-outlined text-[14px] ${passCriteria.uppercase ? 'text-green-600' : 'text-outline-variant'}`}>
                                {passCriteria.uppercase ? 'check_circle' : 'circle'}
                            </span>
                            <span>Pelo menos 1 maiúscula</span>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <span className={`material-symbols-outlined text-[14px] ${passCriteria.lowercase ? 'text-green-600' : 'text-outline-variant'}`}>
                                {passCriteria.lowercase ? 'check_circle' : 'circle'}
                            </span>
                            <span>Pelo menos 1 minúscula</span>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <span className={`material-symbols-outlined text-[14px] ${passCriteria.number ? 'text-green-600' : 'text-outline-variant'}`}>
                                {passCriteria.number ? 'check_circle' : 'circle'}
                            </span>
                            <span>Pelo menos 1 número</span>
                        </div>
                        <div className="flex items-center gap-1.5">
                            <span className={`material-symbols-outlined text-[14px] ${passCriteria.special ? 'text-green-600' : 'text-outline-variant'}`}>
                                {passCriteria.special ? 'check_circle' : 'circle'}
                            </span>
                            <span>Caractere especial</span>
                        </div>
                    </div>
                )}

                {/* Confirmação de Senha */}
                <div className="flex flex-col gap-1">
                    <label className="text-[10px] font-extrabold text-on-surface-variant uppercase tracking-wider px-1">Confirmar Senha</label>
                    <input
                        type="password"
                        value={data.password_confirmation}
                        onChange={e => {
                            setData('password_confirmation', e.target.value);
                            clearError('password_confirmation');
                        }}
                        placeholder="••••••••"
                        className="w-full bg-surface-container-low border border-outline-variant/30 rounded-lg py-2.5 px-3 font-body text-on-surface text-sm focus:border-primary/50 focus:ring-0 transition-all"
                        required
                    />
                    {allErrors.password_confirmation && <span className="text-error text-[10px] font-bold mt-0.5 px-1">{allErrors.password_confirmation}</span>}
                </div>

                {/* Botão de Envio */}
                <div className="pt-2">
                    <button
                        type="submit"
                        disabled={processing || (!allCriteriaMet && data.password.length > 0)}
                        className="w-full bg-gradient-to-br from-primary to-primary-container hover:brightness-110 text-white font-headline font-bold py-3 rounded-lg text-sm shadow-md transition-all active:scale-[0.98] disabled:opacity-50"
                    >
                        {processing ? 'Criando Conta...' : 'Concluir Cadastro'}
                    </button>
                </div>
            </form>
        </GuestLayout>
    );
}
