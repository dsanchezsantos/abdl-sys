<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Relatório de Auditoria - {{ $relatorio->tipo }}</title>
    <style>
        body {
            font-family: 'Inter', Helvetica, Arial, sans-serif;
            background-color: #faf8ff;
            color: #1e293b;
            margin: 0;
            padding: 0;
            -webkit-font-smoothing: antialiased;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
            padding: 40px 20px;
        }
        .card {
            background-color: #ffffff;
            border-radius: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.05), 0 2px 4px -1px rgba(0, 0, 0, 0.03);
            border: 1px solid #f1f5f9;
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #00246a 0%, #1e40af 100%);
            padding: 32px;
            text-align: center;
            color: #ffffff;
        }
        .header h1 {
            margin: 0;
            font-size: 22px;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .content {
            padding: 32px;
        }
        .status-badge {
            display: inline-block;
            padding: 8px 16px;
            border-radius: 9999px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 24px;
        }
        .status-sucesso {
            background-color: #ecfdf5;
            color: #059669;
            border: 1px solid #a7f3d0;
        }
        .status-erro {
            background-color: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }
        .details-table {
            width: 100%;
            border-collapse: collapse;
            margin: 24px 0;
        }
        .details-table th, .details-table td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }
        .details-table th {
            color: #64748b;
            font-weight: 600;
            width: 35%;
        }
        .details-table td {
            color: #0f172a;
            font-weight: 500;
        }
        .button {
            display: inline-block;
            background-color: #00246a;
            color: #ffffff;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 700;
            text-align: center;
            margin-top: 16px;
            box-shadow: 0 4px 6px -1px rgba(0, 36, 106, 0.2);
        }
        .footer {
            text-align: center;
            padding: 24px;
            font-size: 12px;
            color: #94a3b8;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="card">
            <div class="header">
                <h1>ABDL - Relatórios de Auditoria</h1>
            </div>
            <div class="content">
                @if($status === 'concluido')
                    <div class="status-badge status-sucesso">Relatório Concluído</div>
                    <p style="font-size: 16px; line-height: 1.6; margin-top: 0;">
                        Olá! O relatório do tipo <strong>{{ strtoupper($relatorio->tipo) }}</strong> para a feira <strong>{{ $relatorio->feira->nome ?? 'N/A' }}</strong> foi gerado e está pronto para download.
                    </p>
                    <p style="font-size: 14px; color: #64748b; line-height: 1.5;">
                        Por motivos de segurança, o botão de download abaixo contém uma chave temporária assinada que expirará em 30 minutos. Após esse período, você poderá obter um novo link na página de relatórios do painel.
                    </p>
                @else
                    <div class="status-badge status-erro">Falha na Geração</div>
                    <p style="font-size: 16px; line-height: 1.6; margin-top: 0;">
                        Olá! Infelizmente ocorreu um problema ao gerar o relatório do tipo <strong>{{ strtoupper($relatorio->tipo) }}</strong> da feira <strong>{{ $relatorio->feira->nome ?? 'N/A' }}</strong>.
                    </p>
                    @if($relatorio->mensagem_erro)
                        <div style="background-color: #fef2f2; border-left: 4px solid #dc2626; padding: 12px; font-family: monospace; font-size: 12px; color: #991b1b; margin: 16px 0; border-radius: 0 4px 4px 0;">
                            <strong>Motivo da Falha:</strong><br>
                            {{ $relatorio->mensagem_erro }}
                        </div>
                    @endif
                @endif

                <table class="details-table">
                    <tr>
                        <th>Relatório:</th>
                        <td>{{ strtoupper($relatorio->tipo) }}</td>
                    </tr>
                    <tr>
                        <th>Feira:</th>
                        <td>{{ $relatorio->feira->nome ?? 'N/A' }}</td>
                    </tr>
                    <tr>
                        <th>Solicitado Em:</th>
                        <td>{{ $relatorio->created_at->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }}</td>
                    </tr>
                    @if($status === 'concluido' && $relatorio->tamanho_bytes)
                        <tr>
                            <th>Tamanho:</th>
                            <td>{{ number_format($relatorio->tamanho_bytes / 1024 / 1024, 2) }} MB</td>
                        </tr>
                    @endif
                </table>

                <div style="text-align: center;">
                    @if($status === 'concluido')
                        <a href="{{ $relatorio->urlDownloadSegura() }}" class="button">Baixar PDF Seguro</a>
                    @else
                        <a href="{{ route('relatorios.index') }}" class="button">Ver Meus Relatórios</a>
                    @endif
                </div>
            </div>
        </div>
        <div class="footer">
            Este é um e-mail automático enviado pela plataforma ABDL-sys.<br>
            © {{ date('Y') }} Associação Brasileira de Difusão do Livro. Todos os direitos reservados.
        </div>
    </div>
</body>
</html>
