import InputError from '@/Components/InputError';
import { Head, Link, useForm } from '@inertiajs/react';
import { FormEventHandler } from 'react';

export default function Login({
    status,
    canResetPassword,
}: {
    status?: string;
    canResetPassword: boolean;
}) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false as boolean,
    });

    const submit: FormEventHandler = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <>
            <Head title="Entrar">
                <link
                    rel="preconnect"
                    href="https://fonts.googleapis.com"
                />
                <link rel="preconnect" href="https://fonts.gstatic.com" />
                <link
                    href="https://fonts.googleapis.com/css2?family=Manrope:wght@500;600;700;800&family=Inter:wght@400;500;600;700&display=swap"
                    rel="stylesheet"
                />
            </Head>

            <div className="min-h-screen bg-[radial-gradient(circle_at_top_left,_rgba(8,122,178,0.12),_transparent_30%),radial-gradient(circle_at_bottom_right,_rgba(31,26,23,0.08),_transparent_28%),linear-gradient(180deg,#f7f7f5_0%,#f2f1ee_100%)] px-6 py-8 text-primary sm:px-8">
                <main className="mx-auto flex min-h-[calc(100vh-4rem)] w-full max-w-[460px] items-center justify-center">
                    <section className="relative w-full animate-[pop-in_420ms_ease-out_both] rounded-3xl border border-primary/10 bg-white/95 p-8 shadow-[0_24px_80px_-28px_rgba(31,26,23,0.24)] backdrop-blur-xl sm:p-10">
                        <style>{`
                            @keyframes pop-in {
                                from {
                                    opacity: 0;
                                    transform: translateY(14px) scale(0.985);
                                }
                                to {
                                    opacity: 1;
                                    transform: translateY(0) scale(1);
                                }
                            }
                        `}</style>

                        <div className="absolute inset-x-8 top-0 -z-10 h-20 rounded-full bg-secondary/15 blur-3xl" />

                        <div className="flex flex-col items-center text-center">
                            <a
                                href="/"
                                className="mb-6 inline-flex items-center justify-center rounded-2xl border border-primary/10 bg-base px-4 py-4 shadow-lg shadow-primary/10"
                            >
                                <img
                                    src="/abdl_logo.png"
                                    alt="ABDL"
                                    className="h-10 w-auto"
                                />
                            </a>

                            <p className="text-xs font-semibold uppercase tracking-[0.35em] text-primary/60">
                                Acesso restrito
                            </p>
                            <h1 className="mt-3 font-[Manrope] text-3xl font-extrabold tracking-tight text-primary sm:text-4xl">
                                ABDL
                            </h1>
                        </div>

                        {status && (
                            <div className="mt-6 rounded-2xl border border-green-200 bg-green-50 px-4 py-3 text-sm text-green-700">
                                {status}
                            </div>
                        )}

                        <form onSubmit={submit} className="mt-6 space-y-5">
                            <div className="space-y-2">
                                <label
                                    htmlFor="email"
                                        className="ml-1 block text-xs font-semibold uppercase tracking-[0.25em] text-primary/60"
                                >
                                    E-mail
                                </label>
                                <div className="relative">
                                    <span className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-primary/50 transition-colors focus-within:text-secondary">
                                        <svg
                                            viewBox="0 0 24 24"
                                            className="h-5 w-5 fill-none stroke-current stroke-2"
                                            aria-hidden="true"
                                        >
                                            <path d="M4 6h16v12H4z" />
                                            <path d="m4 7 8 6 8-6" />
                                        </svg>
                                    </span>
                                    <input
                                        id="email"
                                        type="email"
                                        name="email"
                                        value={data.email}
                                        autoComplete="username"
                                        autoFocus
                                        placeholder="admin@exemplo.com"
                                        className={`w-full rounded-2xl border bg-white px-4 py-3.5 pl-12 text-sm text-primary shadow-sm outline-none transition focus:ring-4 focus:ring-secondary/20 ${errors.email ? 'border-red-300 focus:border-red-500' : 'border-primary/15 focus:border-secondary'}`}
                                        onChange={(e) => setData('email', e.target.value)}
                                    />
                                </div>
                                <InputError message={errors.email} className="px-1" />
                            </div>

                            <div className="space-y-2">
                                <div className="flex items-center justify-between px-1">
                                    <label
                                        htmlFor="password"
                                        className="block text-xs font-semibold uppercase tracking-[0.25em] text-primary/60"
                                    >
                                        Senha
                                    </label>

                                    {canResetPassword && (
                                        <Link
                                            href={route('password.request')}
                                            className="text-xs font-semibold text-secondary transition hover:text-primary"
                                        >
                                            Esqueceu a senha?
                                        </Link>
                                    )}
                                </div>
                                <div className="relative">
                                    <span className="pointer-events-none absolute left-4 top-1/2 -translate-y-1/2 text-primary/50 transition-colors focus-within:text-secondary">
                                        <svg
                                            viewBox="0 0 24 24"
                                            className="h-5 w-5 fill-none stroke-current stroke-2"
                                            aria-hidden="true"
                                        >
                                            <rect x="5" y="11" width="14" height="9" rx="2" />
                                            <path d="M8 11V8a4 4 0 0 1 8 0v3" />
                                        </svg>
                                    </span>
                                    <input
                                        id="password"
                                        type="password"
                                        name="password"
                                        value={data.password}
                                        autoComplete="current-password"
                                        placeholder="••••••••"
                                        className={`w-full rounded-2xl border bg-white px-4 py-3.5 pl-12 text-sm text-primary shadow-sm outline-none transition focus:ring-4 focus:ring-secondary/20 ${errors.password ? 'border-red-300 focus:border-red-500' : 'border-primary/15 focus:border-secondary'}`}
                                        onChange={(e) => setData('password', e.target.value)}
                                    />
                                </div>
                                <InputError message={errors.password} className="px-1" />
                            </div>

                            <div className="flex items-center justify-between gap-4 pt-1">
                                <label className="inline-flex items-center gap-3 text-sm text-primary/70">
                                    <input
                                        name="remember"
                                        type="checkbox"
                                        checked={data.remember}
                                        onChange={(e) =>
                                            setData('remember', e.target.checked)
                                        }
                                        className="h-4 w-4 rounded border-primary/25 text-secondary focus:ring-secondary/20"
                                    />
                                    <span>Lembrar-me</span>
                                </label>
                            </div>

                            <button
                                type="submit"
                                disabled={processing}
                                className="group flex w-full items-center justify-center gap-3 rounded-2xl bg-primary px-4 py-4 text-sm font-bold text-white shadow-lg shadow-primary/15 transition duration-200 hover:-translate-y-0.5 hover:bg-primary/90 focus:outline-none focus:ring-4 focus:ring-secondary/20 disabled:cursor-not-allowed disabled:opacity-70"
                            >
                                <span>Entrar</span>
                                <svg
                                    viewBox="0 0 24 24"
                                    className="h-5 w-5 fill-none stroke-current stroke-2 transition-transform group-hover:translate-x-0.5"
                                    aria-hidden="true"
                                >
                                    <path d="m9 18 6-6-6-6" />
                                </svg>
                            </button>
                        </form>

                        <div className="mt-8 border-t border-primary/15 pt-6 text-center">
                            <p className="text-sm text-primary/70">
                                Acesso restrito a administradores.
                                <span className="mt-1 block font-semibold text-primary">
                                    Sistema de Auditoria
                                </span>
                            </p>
                        </div>
                    </section>
                </main>
            </div>
        </>
    );
}
