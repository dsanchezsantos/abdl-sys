<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class RelatorioFalhaNotification extends Notification
{
    use Queueable;

    protected $relatorio;

    /**
     * Create a new notification instance.
     */
    public function __construct(\App\Models\Relatorio $relatorio)
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
        return ['database', 'mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $tipoLabel = strtoupper($this->relatorio->tipo);

        return (new MailMessage)
            ->subject("ABDL-sys - Falha no Relatório: {$tipoLabel}")
            ->view('emails.relatorio', [
                'relatorio' => $this->relatorio,
                'status' => 'falha',
            ]);
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
            'status' => 'FALHA',
            'message' => "Ocorreu uma falha ao gerar o relatório {$this->relatorio->tipo} da feira " . ($this->relatorio->feira->nome ?? 'N/A') . ".",
        ];
    }
}
