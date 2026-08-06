import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link } from '@inertiajs/react';

export default function InviteInvalid() {
    return (
        <GuestLayout>
            <Head title="Convite Inválido ou Expirado" />

            <div className="flex flex-col items-center text-center p-4">
                <div className="w-16 h-16 bg-red-50 dark:bg-red-950/20 rounded-full flex items-center justify-center text-red-500 mb-6">
                    <span className="material-symbols-outlined text-4xl">block</span>
                </div>

                <h2 className="text-xl font-bold text-slate-800 dark:text-white font-headline mb-2">
                    Convite Inválido ou Expirado
                </h2>
                
                <p className="text-sm text-slate-500 dark:text-slate-400 font-body max-w-xs mb-8">
                    Este link de cadastro expirou (validade de 6 horas) ou já foi utilizado. Entre em contato com o administrador do sistema para solicitar um novo convite.
                </p>

                <Link
                    href={route('login')}
                    className="w-full text-center bg-slate-100 hover:bg-slate-200 dark:bg-slate-800 dark:hover:bg-slate-700/80 text-slate-700 dark:text-slate-200 py-3 rounded-lg font-headline font-bold text-sm shadow-sm transition-all active:scale-[0.98]"
                >
                    Voltar para o Login
                </Link>
            </div>
        </GuestLayout>
    );
}
