import InputError from '@/Components/InputError';
import { Transition } from '@headlessui/react';
import { useForm } from '@inertiajs/react';
import { FormEventHandler, useRef } from 'react';

export default function UpdatePasswordForm({
    className = '',
}: {
    className?: string;
}) {
    const passwordInput = useRef<HTMLInputElement>(null);
    const currentPasswordInput = useRef<HTMLInputElement>(null);

    const {
        data,
        setData,
        errors,
        put,
        reset,
        processing,
        recentlySuccessful,
    } = useForm({
        current_password: '',
        password: '',
        password_confirmation: '',
    });

    const updatePassword: FormEventHandler = (e) => {
        e.preventDefault();

        put(route('password.update'), {
            preserveScroll: true,
            onSuccess: () => reset(),
            onError: (errors) => {
                if (errors.password) {
                    reset('password', 'password_confirmation');
                    passwordInput.current?.focus();
                }

                if (errors.current_password) {
                    reset('current_password');
                    currentPasswordInput.current?.focus();
                }
            },
        });
    };

    return (
        <section className={className}>
            <div className="rounded-xl border border-primary/10 bg-white p-8 shadow-[0_20px_40px_-10px_rgba(19,27,46,0.05)]">
                <div className="mb-8 flex items-center gap-4">
                    <span className="material-symbols-outlined rounded-lg bg-red-100 p-2 text-red-700">
                        lock
                    </span>
                    <h3 className="font-[Manrope] text-xl font-bold text-primary">
                        Seguranca
                    </h3>
                </div>

                <form onSubmit={updatePassword} className="space-y-5">
                    <div>
                        <label
                            htmlFor="current_password"
                            className="mb-1.5 ml-1 block text-[11px] font-bold uppercase tracking-wider text-primary/60"
                        >
                            Senha Atual
                        </label>

                        <input
                            id="current_password"
                            ref={currentPasswordInput}
                            value={data.current_password}
                            onChange={(e) => setData('current_password', e.target.value)}
                            type="password"
                            className="w-full rounded-lg border border-primary/20 bg-white px-4 py-3 text-sm text-primary outline-none transition focus:border-secondary focus:ring-2 focus:ring-secondary/20"
                            autoComplete="current-password"
                        />

                        <InputError message={errors.current_password} className="mt-2" />
                    </div>

                    <div className="border-t border-primary/10 pt-4">
                        <label
                            htmlFor="password"
                            className="mb-1.5 ml-1 block text-[11px] font-bold uppercase tracking-wider text-primary/60"
                        >
                            Nova Senha
                        </label>

                        <input
                            id="password"
                            ref={passwordInput}
                            value={data.password}
                            onChange={(e) => setData('password', e.target.value)}
                            type="password"
                            className="mb-4 w-full rounded-lg border border-primary/20 bg-white px-4 py-3 text-sm text-primary outline-none transition focus:border-secondary focus:ring-2 focus:ring-secondary/20"
                            autoComplete="new-password"
                        />
                        <InputError message={errors.password} className="mt-2" />

                        <label
                            htmlFor="password_confirmation"
                            className="mb-1.5 ml-1 block text-[11px] font-bold uppercase tracking-wider text-primary/60"
                        >
                            Confirmar Nova Senha
                        </label>

                        <input
                            id="password_confirmation"
                            value={data.password_confirmation}
                            onChange={(e) => setData('password_confirmation', e.target.value)}
                            type="password"
                            className="w-full rounded-lg border border-primary/20 bg-white px-4 py-3 text-sm text-primary outline-none transition focus:border-secondary focus:ring-2 focus:ring-secondary/20"
                            autoComplete="new-password"
                        />
                        <InputError
                            message={errors.password_confirmation}
                            className="mt-2"
                        />
                    </div>

                    <div className="flex items-start gap-3 rounded-lg border border-primary/10 bg-base p-4">
                        <span className="material-symbols-outlined mt-0.5 text-lg text-primary/60">
                            info
                        </span>
                        <p className="text-xs leading-relaxed text-primary/65">
                            A senha deve conter pelo menos 8 caracteres, com letras e numeros.
                        </p>
                    </div>

                    <div className="flex items-center gap-4">
                        <button
                            type="submit"
                            disabled={processing}
                            className="w-full rounded-lg bg-primary px-8 py-3 text-sm font-bold text-white shadow-md transition hover:bg-primary/90 disabled:opacity-70"
                        >
                            Atualizar Senha
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
        </section>
    );
}
