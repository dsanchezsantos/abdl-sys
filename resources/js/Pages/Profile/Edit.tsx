import AppSidebar from '@/Components/AppSidebar';
import { PageProps } from '@/types';
import { Head, Link, usePage } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({
    mustVerifyEmail,
    status,
}: PageProps<{ mustVerifyEmail: boolean; status?: string }>) {
    const user = usePage().props.auth.user;

    return (
        <>
            <Head title="Profile" />
            <Head>
                <link rel="preconnect" href="https://fonts.googleapis.com" />
                <link rel="preconnect" href="https://fonts.gstatic.com" crossOrigin="" />
                <link
                    href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700&family=Manrope:wght@500;600;700;800&display=swap"
                    rel="stylesheet"
                />
                <link
                    href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@24,500,0,0"
                    rel="stylesheet"
                />
            </Head>

            <div className="min-h-screen bg-base font-[Inter] text-primary">
                <AppSidebar
                    activeItem="perfil"
                />

                <div className="ml-64">
                    <header className="sticky top-0 z-30 flex items-center justify-between bg-base/90 px-8 py-4 backdrop-blur-xl">
                        <h1 className="font-[Manrope] text-xl font-extrabold text-primary">
                            Perfil do Utilizador
                        </h1>
                        <div className="flex items-center gap-4">
                            <Link
                                href={route('logout')}
                                method="post"
                                as="button"
                                className="rounded-lg bg-primary px-5 py-2 text-sm font-bold text-white transition hover:bg-primary/90"
                            >
                                Sair
                            </Link>
                        </div>
                    </header>

                    <main className="min-h-screen p-8">
                        <div className="mx-auto max-w-6xl">
                            <div className="mb-10">
                                <h2 className="font-[Manrope] text-3xl font-extrabold tracking-tight text-primary">
                                    Perfil do Utilizador
                                </h2>
                                <p className="mt-2 text-lg text-primary/65">
                                    Gerencie suas informacoes de acesso e seguranca.
                                </p>
                            </div>

                            <div className="grid grid-cols-1 items-start gap-8 lg:grid-cols-12">
                                <section className="space-y-6 lg:col-span-7">
                                    <UpdateProfileInformationForm
                                        mustVerifyEmail={mustVerifyEmail}
                                        status={status}
                                    />
                                </section>

                                <section className="space-y-6 lg:col-span-5">
                                    <UpdatePasswordForm />

                                    <div className="relative overflow-hidden rounded-xl bg-primary p-6 text-white">
                                        <div className="relative z-10">
                                            <h4 className="mb-4 text-[10px] font-bold uppercase tracking-[0.2em] text-secondary/80">
                                                Acesso a Conta
                                            </h4>
                                            <div className="space-y-4">
                                                <div className="flex items-center justify-between">
                                                    <span className="text-xs text-white/60">Conta</span>
                                                    <span className="text-xs font-medium">Ativa</span>
                                                </div>
                                                <div className="flex items-center justify-between">
                                                    <span className="text-xs text-white/60">Usuario</span>
                                                    <span className="max-w-[180px] truncate text-xs font-medium">
                                                        {user.email}
                                                    </span>
                                                </div>
                                                <div className="flex items-center justify-between">
                                                    <span className="text-xs text-white/60">Perfil</span>
                                                    <span className="text-xs font-medium">Administrador</span>
                                                </div>
                                            </div>
                                        </div>
                                        <div className="pointer-events-none absolute -bottom-10 -right-10 opacity-10">
                                            <span className="material-symbols-outlined text-[140px]">
                                                admin_panel_settings
                                            </span>
                                        </div>
                                    </div>

                                    <div className="rounded-xl border border-red-200 bg-white p-6">
                                        <DeleteUserForm />
                                    </div>
                                </section>
                            </div>
                        </div>
                    </main>
                </div>
            </div>
        </>
    );
}
