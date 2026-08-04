import AppSidebar from "@/Components/AppSidebar";
import { PropsWithChildren, useEffect } from "react";
import { usePage } from "@inertiajs/react";
import toast, { Toaster } from "react-hot-toast";

type SidebarKey = "dashboard" | "feiras" | "catalogo" | "cartoes" | "auditoria" | "relatorios" | "perfil";

interface Props extends PropsWithChildren {
    activeItem?: SidebarKey;
}

export default function AppLayout({ children, activeItem }: Props) {
    const { flash } = usePage().props as any;

    useEffect(() => {
        if (flash?.success) {
            toast.success(flash.success);
        }
        if (flash?.error) {
            toast.error(flash.error);
        }
    }, [flash]);

    return (
        <div className="min-h-screen bg-surface font-body text-on-surface flex">
            <Toaster position="top-right" />
            <AppSidebar activeItem={activeItem as any} />
            <div className="flex-1 ml-64 flex flex-col min-h-screen">
                {children}
            </div>
        </div>
    );
}
