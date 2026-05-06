<?php

namespace App\Notifications;

use App\Models\Relatorio;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class RelatorioConcluidoNotification extends Notification implements ShouldQueue
{
    use Queueable;

    protected $relatorio;

    /**
     * Create a new notification instance.
     */
    public function __construct(Relatorio $relatorio)
    {
        $this->relatorio = $relatorio;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'relatorio_id' => $this->relatorio->id,
            'feira_nome' => $this->relatorio->feira->nome ?? 'N/A',
            'tipo' => $this->relatorio->tipo,
            'status' => 'CONCLUIDO',
            'download_url' => $this->relatorio->urlDownloadSegura(),
            'tamanho_bytes' => $this->relatorio->tamanho_bytes,
            'message' => "O relatório {$this->relatorio->tipo} da feira {$this->relatorio->feira->nome} está pronto para download.",
        ];
    }
}
