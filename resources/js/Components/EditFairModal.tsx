import { useForm } from "@inertiajs/react";
import { FormEvent, useEffect } from "react";

type EditFairModalProps = {
    show: boolean;
    feira: any | null;
    onClose: () => void;
};

const formatDateForInput = (dateString?: string | null) => {
    if (!dateString) return "";
    return dateString.split("T")[0];
};

const formatDateTime = (dateString?: string | null) => {
    if (!dateString) return "Nunca sincronizada";
    try {
        return new Date(dateString).toLocaleString("pt-BR", {
            day: "2-digit",
            month: "2-digit",
            year: "numeric",
            hour: "2-digit",
            minute: "2-digit",
        });
    } catch {
        return dateString;
    }
};

export default function EditFairModal({ show, feira, onClose }: EditFairModalProps) {
    const { data, setData, patch, processing, errors, clearErrors, reset } = useForm({
        nome: "",
        data_inicio: "",
        data_fim: "",
        endpoint_url: "",
        evento_id_api: "",
        user_id_api: "",
        status: "PLANEJADA",
    });

    useEffect(() => {
        if (feira) {
            clearErrors();
            setData({
                nome: feira.nome || "",
                data_inicio: formatDateForInput(feira.data_inicio),
                data_fim: formatDateForInput(feira.data_fim),
                endpoint_url: feira.endpoint_url || "",
                evento_id_api: feira.evento_id_api || "",
                user_id_api: feira.user_id_api || "",
                status: feira.status || "PLANEJADA",
            });
        }
    }, [feira, show]);

    const handleSubmit = (e: FormEvent) => {
        e.preventDefault();
        if (!feira || feira.is_sincronizando) return;

        patch(route("feiras.update", feira.id), {
            preserveScroll: true,
            onSuccess: () => {
                onClose();
            },
        });
    };

    if (!show || !feira) {
        return null;
    }

    const isSyncing = Boolean(feira.is_sincronizando);

    return (
        <div className="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4 sm:p-6 backdrop-blur-sm overflow-y-auto">
            <div className="w-full max-w-2xl overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-[0_40px_120px_-30px_rgba(15,23,42,0.55)] my-8">
                {/* Header */}
                <div className="border-b border-slate-200 bg-white p-6 sm:p-8">
                    <div className="flex items-start justify-between gap-4">
                        <div>
                            <div className="flex items-center gap-3">
                                <div className="flex h-10 w-10 items-center justify-center rounded-xl bg-primary/10 text-primary">
                                    <span className="material-symbols-outlined text-2xl">tune</span>
                                </div>
                                <div>
                                    <h3 className="font-[Manrope] text-xl sm:text-2xl font-extrabold tracking-tight text-primary">
                                        Detalhes e Edição da Feira
                                    </h3>
                                    <p className="text-xs sm:text-sm font-medium text-primary/65">
                                        Feira #{feira.id} — {feira.nome}
                                    </p>
                                </div>
                            </div>
                        </div>
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-full p-1.5 text-primary/45 transition-colors hover:bg-primary/5 hover:text-primary"
                        >
                            <span className="material-symbols-outlined text-xl">close</span>
                        </button>
                    </div>

                    {/* Metadados / Badges Informativos */}
                    <div className="mt-6 flex flex-wrap items-center gap-2.5 pt-4 border-t border-slate-100 text-xs font-semibold">
                        <div className="flex items-center gap-1.5 rounded-lg bg-slate-100 px-3 py-1.5 text-slate-700">
                            <span className="material-symbols-outlined text-base text-slate-500">history</span>
                            <span>Última Sincronização: <strong>{formatDateTime(feira.ultima_sincronizacao_em)}</strong></span>
                        </div>

                        {feira.status_integridade && (
                            <div className={`flex items-center gap-1.5 rounded-lg px-3 py-1.5 ${
                                feira.status_integridade === 'FALHA_PARCIAL'
                                    ? 'bg-amber-100 text-amber-800'
                                    : 'bg-emerald-100 text-emerald-800'
                            }`}>
                                <span className="material-symbols-outlined text-base">
                                    {feira.status_integridade === 'FALHA_PARCIAL' ? 'warning' : 'verified'}
                                </span>
                                <span>Integridade: <strong>{feira.status_integridade}</strong></span>
                            </div>
                        )}

                        {isSyncing && (
                            <div className="flex items-center gap-1.5 rounded-lg bg-blue-100 px-3 py-1.5 text-blue-800 animate-pulse">
                                <span className="material-symbols-outlined text-base animate-spin">sync</span>
                                <span>Sincronização em andamento</span>
                            </div>
                        )}
                    </div>
                </div>

                {isSyncing && (
                    <div className="bg-amber-50 border-b border-amber-200 px-6 sm:px-8 py-3 text-xs font-semibold text-amber-800 flex items-center gap-2">
                        <span className="material-symbols-outlined text-sm">lock</span>
                        <span>Esta feira está em sincronização. A edição está temporariamente bloqueada para preservar a integridade dos dados.</span>
                    </div>
                )}

                {/* Formulário */}
                <form className="space-y-5 bg-white p-6 sm:p-8" onSubmit={handleSubmit}>
                    {/* Linha 1: Nome e Status */}
                    <div className="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <div className="sm:col-span-2 space-y-1.5">
                            <label className="text-xs font-extrabold uppercase tracking-wider text-primary/60">
                                Nome da Feira
                            </label>
                            <div className="relative">
                                <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg text-primary/45">
                                    badge
                                </span>
                                <input
                                    className="w-full rounded-xl border-2 border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-sm font-medium text-primary transition-all placeholder:text-primary/35 focus:border-secondary focus:bg-white focus:ring-0 disabled:opacity-60"
                                    placeholder="Nome comercial do evento"
                                    type="text"
                                    value={data.nome}
                                    onChange={(e) => setData("nome", e.target.value)}
                                    disabled={isSyncing || processing}
                                    required
                                />
                            </div>
                            {errors.nome && <p className="text-xs font-bold text-red-500">{errors.nome}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <label className="text-xs font-extrabold uppercase tracking-wider text-primary/60">
                                Status
                            </label>
                            <div className="relative">
                                <select
                                    className="w-full rounded-xl border-2 border-slate-200 bg-slate-50 py-2.5 px-3 text-sm font-medium text-primary transition-all focus:border-secondary focus:bg-white focus:ring-0 disabled:opacity-60"
                                    value={data.status}
                                    onChange={(e) => setData("status", e.target.value)}
                                    disabled={isSyncing || processing}
                                >
                                    <option value="PLANEJADA">Planejada</option>
                                    <option value="EM_ANDAMENTO">Em Andamento</option>
                                    <option value="ENCERRADA">Encerrada</option>
                                </select>
                            </div>
                            {errors.status && <p className="text-xs font-bold text-red-500">{errors.status}</p>}
                        </div>
                    </div>

                    {/* Linha 2: Datas */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="space-y-1.5">
                            <label className="text-xs font-extrabold uppercase tracking-wider text-primary/60">
                                Data Início
                            </label>
                            <input
                                className="w-full rounded-xl border-2 border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-primary transition-all focus:border-secondary focus:bg-white focus:ring-0 disabled:opacity-60"
                                type="date"
                                value={data.data_inicio}
                                onChange={(e) => setData("data_inicio", e.target.value)}
                                disabled={isSyncing || processing}
                                required
                            />
                            {errors.data_inicio && <p className="text-xs font-bold text-red-500">{errors.data_inicio}</p>}
                        </div>
                        <div className="space-y-1.5">
                            <label className="text-xs font-extrabold uppercase tracking-wider text-primary/60">
                                Data Fim
                            </label>
                            <input
                                className="w-full rounded-xl border-2 border-slate-200 bg-slate-50 px-4 py-2.5 text-sm font-medium text-primary transition-all focus:border-secondary focus:bg-white focus:ring-0 disabled:opacity-60"
                                type="date"
                                value={data.data_fim}
                                onChange={(e) => setData("data_fim", e.target.value)}
                                disabled={isSyncing || processing}
                                required
                            />
                            {errors.data_fim && <p className="text-xs font-bold text-red-500">{errors.data_fim}</p>}
                        </div>
                    </div>

                    {/* Divisor Visual API */}
                    <div className="pt-2">
                        <div className="flex items-center gap-2 mb-3">
                            <span className="material-symbols-outlined text-base text-secondary">api</span>
                            <span className="text-xs font-extrabold uppercase tracking-wider text-secondary">
                                Parâmetros de Integração (API Nowigo)
                            </span>
                        </div>

                        {/* Linha 3: Endpoint */}
                        <div className="space-y-1.5">
                            <label className="text-xs font-extrabold uppercase tracking-wider text-primary/60">
                                URL do Endpoint (API)
                            </label>
                            <div className="relative">
                                <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg text-primary/45">
                                    link
                                </span>
                                <input
                                    className="w-full rounded-xl border-2 border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-sm font-medium text-primary transition-all placeholder:text-primary/35 focus:border-secondary focus:bg-white focus:ring-0 disabled:opacity-60"
                                    placeholder="https://feiradolivro-cidade.nowigo.com.br/app/sale.mdl"
                                    type="url"
                                    value={data.endpoint_url}
                                    onChange={(e) => setData("endpoint_url", e.target.value)}
                                    disabled={isSyncing || processing}
                                    required
                                />
                            </div>
                            {errors.endpoint_url && <p className="text-xs font-bold text-red-500">{errors.endpoint_url}</p>}
                        </div>
                    </div>

                    {/* Linha 4: Event ID e User ID */}
                    <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div className="space-y-1.5">
                            <label className="text-xs font-extrabold uppercase tracking-wider text-primary/60">
                                Event ID (External API)
                            </label>
                            <div className="relative">
                                <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg text-primary/45">
                                    key
                                </span>
                                <input
                                    className="w-full rounded-xl border-2 border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-sm font-medium text-primary transition-all placeholder:text-primary/35 focus:border-secondary focus:bg-white focus:ring-0 disabled:opacity-60"
                                    placeholder="Ex: EVT-2025-SQ"
                                    type="text"
                                    value={data.evento_id_api}
                                    onChange={(e) => setData("evento_id_api", e.target.value)}
                                    disabled={isSyncing || processing}
                                    required
                                />
                            </div>
                            {errors.evento_id_api && <p className="text-xs font-bold text-red-500">{errors.evento_id_api}</p>}
                        </div>

                        <div className="space-y-1.5">
                            <label className="text-xs font-extrabold uppercase tracking-wider text-primary/60">
                                User ID (External API)
                            </label>
                            <div className="relative">
                                <span className="material-symbols-outlined absolute left-3 top-1/2 -translate-y-1/2 text-lg text-primary/45">
                                    person
                                </span>
                                <input
                                    className="w-full rounded-xl border-2 border-slate-200 bg-slate-50 py-2.5 pl-10 pr-4 text-sm font-medium text-primary transition-all placeholder:text-primary/35 focus:border-secondary focus:bg-white focus:ring-0 disabled:opacity-60"
                                    placeholder="Ex: USER-123"
                                    type="text"
                                    value={data.user_id_api}
                                    onChange={(e) => setData("user_id_api", e.target.value)}
                                    disabled={isSyncing || processing}
                                    required
                                />
                            </div>
                            {errors.user_id_api && <p className="text-xs font-bold text-red-500">{errors.user_id_api}</p>}
                        </div>
                    </div>

                    {/* Mensagem de erro genérica da feira (ex: sincronização em andamento) */}
                    {(errors as any).feira && (
                        <div className="p-3 bg-red-50 border border-red-200 text-red-700 rounded-xl text-xs font-bold">
                            {(errors as any).feira}
                        </div>
                    )}

                    {/* Footer Actions */}
                    <div className="flex items-center justify-end space-x-3 border-t border-slate-200 bg-slate-50 p-6 -mx-6 -mb-6 sm:p-8 sm:-mx-8 sm:-mb-8 mt-6">
                        <button
                            type="button"
                            onClick={onClose}
                            className="rounded-lg px-5 py-2.5 text-sm font-bold text-primary/65 transition-all hover:bg-primary/5 hover:text-primary"
                            disabled={processing}
                        >
                            Fechar
                        </button>
                        <button
                            type="submit"
                            disabled={processing || isSyncing}
                            className="flex items-center space-x-2 rounded-xl bg-primary px-7 py-2.5 text-sm font-extrabold text-white shadow-xl shadow-primary/20 transition-all hover:bg-primary/90 disabled:opacity-50 disabled:cursor-not-allowed"
                        >
                            <span className="material-symbols-outlined text-lg">
                                {processing ? "progress_activity" : "save"}
                            </span>
                            <span>{processing ? "Salvando..." : "Salvar Alterações"}</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    );
}
