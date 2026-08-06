import InputError from '@/Components/InputError';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { FormEventHandler, useState } from 'react';

export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}: {
    mustVerifyEmail: boolean;
    status?: string;
    className?: string;
}) {
    const user = usePage().props.auth.user as any;
    const [confirmEmail, setConfirmEmail] = useState(user.email);
    const [confirmEmailError, setConfirmEmailError] = useState('');
 
    const formatCPF = (val: string) => {
        let clean = val.replace(/\D/g, '');
        if (clean.length > 11) clean = clean.slice(0, 11);
        let formatted = clean;
        if (clean.length > 3) formatted = clean.slice(0, 3) + '.' + clean.slice(3);
        if (clean.length > 6) formatted = formatted.slice(0, 7) + '.' + formatted.slice(7);
        if (clean.length > 9) formatted = formatted.slice(0, 11) + '-' + formatted.slice(11);
        return formatted;
    };
 
    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
            cpf: formatCPF(user.cpf || ''),
            apelido: user.apelido || '',
        });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        const emailChanged = data.email !== user.email;
        if (emailChanged && data.email !== confirmEmail) {
            setConfirmEmailError('A confirmacao do e-mail nao corresponde.');
            return;
        }

        setConfirmEmailError('');

        patch(route('profile.update'));
    };

    return (
        <section className={className}>
            <div className="rounded-xl border border-primary/10 bg-white p-8 shadow-[0_20px_40px_-10px_rgba(19,27,46,0.05)]">
                <div className="mb-8 flex items-center gap-4">
                    <span className="material-symbols-outlined rounded-lg bg-secondary/12 p-2 text-secondary">
                        person
                    </span>
                    <h3 className="font-[Manrope] text-xl font-bold text-primary">
                        Informacoes Pessoais
                    </h3>
                </div>

                <div className="mb-8 flex flex-col gap-6">
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label
                                htmlFor="name"
                                className="mb-1.5 ml-1 block text-[11px] font-bold uppercase tracking-wider text-primary/60"
                            >
                                Nome Completo
                            </label>
                            <input
                                id="name"
                                className="w-full rounded-lg border border-primary/15 bg-base px-4 py-3 text-sm font-medium text-primary outline-none transition focus:border-secondary focus:ring-2 focus:ring-secondary/20"
                                value={data.name}
                                onChange={(e) => setData('name', e.target.value)}
                                autoComplete="name"
                                required
                            />
                            <InputError className="mt-2" message={errors.name} />
                        </div>
 
                        <div>
                            <label
                                htmlFor="apelido"
                                className="mb-1.5 ml-1 block text-[11px] font-bold uppercase tracking-wider text-primary/60"
                            >
                                Apelido no Sistema
                            </label>
                            <input
                                id="apelido"
                                className="w-full rounded-lg border border-primary/15 bg-base px-4 py-3 text-sm font-medium text-primary outline-none transition focus:border-secondary focus:ring-2 focus:ring-secondary/20"
                                value={data.apelido}
                                onChange={(e) => setData('apelido', e.target.value)}
                                required
                            />
                            <InputError className="mt-2" message={errors.apelido} />
                        </div>
                    </div>
 
                    <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                        <div>
                            <label
                                htmlFor="cpf"
                                className="mb-1.5 ml-1 block text-[11px] font-bold uppercase tracking-wider text-primary/60"
                            >
                                CPF
                            </label>
                            <input
                                id="cpf"
                                className="w-full rounded-lg border border-primary/15 bg-base px-4 py-3 text-sm font-medium text-primary outline-none transition focus:border-secondary focus:ring-2 focus:ring-secondary/20"
                                value={data.cpf}
                                onChange={(e) => {
                                    let val = e.target.value.replace(/\D/g, '');
                                    if (val.length > 11) val = val.slice(0, 11);
                                    let formatted = val;
                                    if (val.length > 3) formatted = val.slice(0, 3) + '.' + val.slice(3);
                                    if (val.length > 6) formatted = formatted.slice(0, 7) + '.' + formatted.slice(7);
                                    if (val.length > 9) formatted = formatted.slice(0, 11) + '-' + formatted.slice(11);
                                    setData('cpf', formatted);
                                }}
                                placeholder="000.000.000-00"
                                required
                            />
                            <InputError className="mt-2" message={errors.cpf} />
                        </div>
 
                        <div className="flex items-center gap-2 rounded-lg border border-secondary/25 bg-secondary/10 p-3 h-[46px] mt-[22px]">
                            <span className="material-symbols-outlined text-sm text-secondary">
                                verified_user
                            </span>
                            <span className="text-xs font-semibold uppercase tracking-tight text-secondary">
                                Conta autenticada
                            </span>
                        </div>
                    </div>
                </div>

                <div className="border-t border-primary/10 pt-6">
                    <h4 className="mb-6 text-sm font-bold uppercase tracking-widest text-primary">
                        Alterar E-mail
                    </h4>

                    <form onSubmit={submit} className="space-y-5">
                        <div>
                            <label className="mb-1.5 ml-1 block text-[11px] font-bold uppercase tracking-wider text-primary/60">
                                E-mail Atual
                            </label>
                            <input
                                className="w-full cursor-not-allowed rounded-lg border border-primary/10 bg-base px-4 py-3 text-sm font-medium text-primary/50"
                                type="email"
                                value={user.email}
                                readOnly
                            />
                        </div>

                        <div className="grid grid-cols-1 gap-4 md:grid-cols-2">
                            <div>
                                <label
                                    htmlFor="email"
                                    className="mb-1.5 ml-1 block text-[11px] font-bold uppercase tracking-wider text-primary/60"
                                >
                                    Novo E-mail
                                </label>
                                <input
                                    id="email"
                                    className="w-full rounded-lg border border-primary/20 bg-white px-4 py-3 text-sm text-primary outline-none transition focus:border-secondary focus:ring-2 focus:ring-secondary/20"
                                    type="email"
                                    value={data.email}
                                    onChange={(e) => setData('email', e.target.value)}
                                    autoComplete="username"
                                    required
                                />
                                <InputError className="mt-2" message={errors.email} />
                            </div>

                            <div>
                                <label
                                    htmlFor="confirm_email"
                                    className="mb-1.5 ml-1 block text-[11px] font-bold uppercase tracking-wider text-primary/60"
                                >
                                    Confirmar Novo E-mail
                                </label>
                                <input
                                    id="confirm_email"
                                    className="w-full rounded-lg border border-primary/20 bg-white px-4 py-3 text-sm text-primary outline-none transition focus:border-secondary focus:ring-2 focus:ring-secondary/20"
                                    type="email"
                                    value={confirmEmail}
                                    onChange={(e) => setConfirmEmail(e.target.value)}
                                    required
                                />
                                {confirmEmailError && (
                                    <p className="mt-2 text-sm text-red-600">{confirmEmailError}</p>
                                )}
                            </div>
                        </div>

                        {mustVerifyEmail && user.email_verified_at === null && (
                            <div className="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm text-amber-800">
                                Seu e-mail ainda nao foi verificado.
                                <Link
                                    href={route('verification.send')}
                                    method="post"
                                    as="button"
                                    className="ml-2 font-semibold underline"
                                >
                                    Reenviar verificacao
                                </Link>
                            </div>
                        )}

                        {status === 'verification-link-sent' && (
                            <div className="rounded-lg border border-green-200 bg-green-50 p-3 text-sm font-medium text-green-700">
                                Novo link de verificacao enviado para seu e-mail.
                            </div>
                        )}

                        <div className="flex items-center gap-4 pt-1">
                            <button
                                type="submit"
                                disabled={processing}
                                className="w-full rounded-lg bg-primary px-8 py-3 text-sm font-bold text-white shadow-md transition hover:bg-primary/90 disabled:opacity-70 md:w-auto"
                            >
                                Atualizar Informacoes
                            </button>

                            <Transition
                                show={recentlySuccessful}
                                enter="transition ease-in-out"
                                enterFrom="opacity-0"
                                leave="transition ease-in-out"
                                leaveTo="opacity-0"
                            >
                                <p className="text-sm text-primary/70">Salvo.</p>
                            </Transition>
                        </div>
                    </form>
                </div>
            </div>
        </section>
    );
}
