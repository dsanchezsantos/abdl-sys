<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sincronização de Feira - {{ $feira->nome }}</title>
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
        .status-falha_parcial {
            background-color: #fffbeb;
            color: #d97706;
            border: 1px solid #fde68a;
        }
        .status-erro_critico {
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
                <h1>ABDL - Integração Nowigo</h1>
            </div>
            <div class="content">
                @if($status === 'sucesso')
                    <div class="status-badge status-sucesso">Sincronização Concluída</div>
                    <p style="font-size: 16px; line-height: 1.6; margin-top: 0;">
                        Olá! O processo de sincronização da feira <strong>{{ $feira->nome }}</strong> foi concluído com sucesso. Todos os dados estão atualizados no sistema.
                    </p>
                @elseif($status === 'falha_parcial')
                    <div class="status-badge status-falha_parcial">Concluída com Avisos</div>
                    <p style="font-size: 16px; line-height: 1.6; margin-top: 0;">
                        Olá! O processo de sincronização da feira <strong>{{ $feira->nome }}</strong> terminou com <strong>falhas parciais</strong>. Algumas páginas de vendas apresentaram instabilidade de conexão com a API e foram puladas.
                    </p>
                    <p style="font-size: 14px; color: #64748b; line-height: 1.5;">
                        Você pode tentar importar as páginas falhas clicando no botão "Repescar Dados Faltantes" na página de auditoria da feira.
                    </p>
                @else
                    <div class="status-badge status-erro_critico">Erro Crítico</div>
                    <p style="font-size: 16px; line-height: 1.6; margin-top: 0;">
                        Olá! Ocorreu um <strong>erro crítico</strong> durante a orquestração da feira <strong>{{ $feira->nome }}</strong>. O processo foi interrompido e nenhuma venda foi sincronizada neste lote.
                    </p>
                    @if(isset($erro))
                        <div style="background-color: #f8fafc; border-left: 4px solid #dc2626; padding: 12px; font-family: monospace; font-size: 12px; color: #334155; margin: 16px 0; border-radius: 0 4px 4px 0;">
                            {{ $erro }}
                        </div>
                    @endif
                @endif

                <table class="details-table">
                    <tr>
                        <th>Feira:</th>
                        <td>{{ $feira->nome }}</td>
                    </tr>
                    <tr>
                        <th>Período Sincronizado:</th>
                        <td>{{ $feira->data_inicio->format('d/m/Y') }} até {{ $feira->data_fim->format('d/m/Y') }}</td>
                    </tr>
                    @if($feira->ultima_sincronizacao_em)
                        <tr>
                            <th>Data Sincronização:</th>
                            <td>{{ $feira->ultima_sincronizacao_em->timezone('America/Sao_Paulo')->format('d/m/Y H:i:s') }}</td>
                        </tr>
                    @endif
                    <tr>
                        <th>Integridade:</th>
                        <td>{{ $feira->status_integridade }}</td>
                    </tr>
                </table>

                <div style="text-align: center;">
                    <a href="{{ route('feiras.auditoria', $feira->id) }}" class="button">Acessar Painel de Auditoria</a>
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
