type SyncFairModalProps = {
    show: boolean;
    onClose: () => void;
};

export default function SyncFairModal({ show, onClose }: SyncFairModalProps) {
    if (!show) {
        return null;
    }

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-inverse-surface/40 p-6 backdrop-blur-sm">
            <div className="w-full max-w-lg overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_40px_120px_-30px_rgba(15,23,42,0.55)] ring-1 ring-black/5">
                <div className="border-b border-slate-200 bg-white p-8">
                    <div className="flex items-start justify-between gap-6">
                        <div>
                            <h3 className="font-[Manrope] text-2xl font-extrabold tracking-tight text-primary">
                                Nova Sincronização
                            </h3>
                            <p className="mt-1 text-sm font-medium text-primary/65">
                                Conecte a base de dados central ao evento local.
                            </p>
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-full p-1 text-primary/45 transition-colors hover:bg-primary/5 hover:text-primary"
                        >
                            <span className="material-symbols-outlined">close</span>
                        </button>
                    </div>
                </div>

                <form className="space-y-6 bg-white p-8" onSubmit={(e) => e.preventDefault()}>
                    <div className="space-y-2">
                        <label className="text-xs font-extrabold uppercase tracking-wider text-primary/60">
                            Event ID (External)
                        </label>
                        <div className="relative">
                            <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg text-primary/45">
                                key
                            </span>
                            <input
                                className="w-full rounded-xl border-2 border-slate-200 bg-slate-50 py-3 pl-10 pr-4 text-sm font-medium text-primary transition-all placeholder:text-primary/35 focus:border-secondary focus:bg-white focus:ring-0"
                                placeholder="E.g. EVT-2025-SQ"
                                type="text"
                            />
                        </div>
                    </div>

                    <div className="space-y-2">
                        <label className="text-xs font-extrabold uppercase tracking-wider text-primary/60">
                            Nome da Feira
                        </label>
                        <div className="relative">
                            <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg text-primary/45">
                                badge
                            </span>
                            <input
                                className="w-full rounded-xl border-2 border-slate-200 bg-slate-50 py-3 pl-10 pr-4 text-sm font-medium text-primary transition-all placeholder:text-primary/35 focus:border-secondary focus:bg-white focus:ring-0"
                                placeholder="Nome comercial do evento"
                                type="text"
                            />
                        </div>
                    </div>

                    <div className="grid grid-cols-2 gap-4">
                        <div className="space-y-2">
                            <label className="text-xs font-extrabold uppercase tracking-wider text-primary/60">
                                Data Início
                            </label>
                            <input
                                className="w-full rounded-xl border-2 border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-primary transition-all focus:border-secondary focus:bg-white focus:ring-0"
                                type="date"
                            />
                        </div>
                        <div className="space-y-2">
                            <label className="text-xs font-extrabold uppercase tracking-wider text-primary/60">
                                Data Fim
                            </label>
                            <input
                                className="w-full rounded-xl border-2 border-slate-200 bg-slate-50 px-4 py-3 text-sm font-medium text-primary transition-all focus:border-secondary focus:bg-white focus:ring-0"
                                type="date"
                            />
                        </div>
                    </div>

                    <div className="flex items-center justify-end space-x-4 border-t border-slate-200 bg-[#f8fafc] p-8 -mx-8 -mb-8">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg px-6 py-2 text-sm font-bold text-primary/65 transition-all hover:bg-primary/5 hover:text-primary"
                        >
                            Cancelar
                        </button>
                        <button
                            type="submit"
                            className="flex items-center space-x-2 rounded-xl bg-primary px-8 py-3 text-sm font-extrabold text-white shadow-xl shadow-primary/20 transition-all hover:bg-primary/90"
                        >
                            <span className="material-symbols-outlined text-lg">rocket_launch</span>
                            <span>Iniciar Sincronização</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
