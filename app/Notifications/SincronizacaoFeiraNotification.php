<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class SincronizacaoFeiraNotification extends Notification
{
    use Queueable;

    protected $feira;
    protected $status;
    protected $erro;

    /**
     * Create a new notification instance.
     */
    public function __construct(\App\Models\Feira $feira, string $status, ?string $erro = null)
    {
        $this->feira = $feira;
        $this->status = $status;
        $this->erro = $erro;
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
        $statusLabels = [
            'sucesso' => 'SUCESSO',
            'falha_parcial' => 'AVISO',
            'erro_critico' => 'FALHA CRÍTICA',
        ];

        $label = $statusLabels[$this->status] ?? strtoupper($this->status);

        return (new MailMessage)
            ->subject("ABDL-sys - Sincronização: {$this->feira->nome} [{$label}]")
            ->view('emails.sincronizacao', [
                'feira' => $this->feira,
                'status' => $this->status,
                'erro' => $this->erro,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $messages = [
            'sucesso' => "A sincronização da feira '{$this->feira->nome}' foi concluída com sucesso.",
            'falha_parcial' => "A sincronização da feira '{$this->feira->nome}' concluiu com alguns avisos (falha parcial).",
            'erro_critico' => "Erro crítico ao sincronizar a feira '{$this->feira->nome}'.",
        ];

        return [
            'feira_id' => $this->feira->id,
            'feira_nome' => $this->feira->nome,
            'status' => $this->status,
            'erro' => $this->erro,
            'message' => $messages[$this->status] ?? "Sincronização concluída com status: {$this->status}.",
        ];
    }
}
