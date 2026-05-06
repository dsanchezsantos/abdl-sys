<!DOCTYPE html>
<html lang="pt-BR">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('title', 'Relatório de Auditoria')</title>
    <style>
        /* CSS Base para Relatórios A4 Landscape no Gotenberg */
        @page {
            size: A4 landscape;
            margin: 15mm 10mm 15mm 10mm; /* Reduzido para ganhar espaço */
        }

        body {
            font-family: 'Helvetica', 'Arial', sans-serif;
            font-size: 10pt;
            line-height: 1.4;
            color: #334155;
            margin: 0;
            padding: 0;
            background-color: #ffffff;
        }

        .container {
            width: 100%;
            padding: 5mm;
            box-sizing: border-box;
        }

        /* Quebra de página inteligente */
        .page-break {
            page-break-after: always;
        }

        /* Evita quebras no meio de elementos críticos */
        table tr {
            page-break-inside: avoid;
            break-inside: avoid;
        }

        /* Cabeçalho Fixo (Simulado via HTML se necessário, ou repetido em cada página) */
        .header {
            width: 100%;
            height: 25mm;
            border-bottom: 0.5pt solid #000000;
            margin-bottom: 5mm;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .header img {
            height: 19mm;
        }

        .header .titles {
            text-align: center;
            flex-grow: 1;
        }

        .header h1 {
            font-size: 16pt;
            margin: 0;
            font-weight: bold;
        }

        .header p {
            font-size: 10pt;
            margin: 2pt 0 0 0;
        }

        /* Rodapé Fixo */
        .footer {
            position: fixed;
            bottom: 5mm;
            left: 0;
            right: 0;
            text-align: center;
            font-size: 8pt;
            color: #64748b;
            font-style: italic;
        }

        /* Tabelas Estilizadas */
        table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 5mm;
        }

        th {
            background-color: #f1f5f9;
            font-weight: bold;
            font-size: 8pt;
            padding: 5pt;
            border: 0.5pt solid #cbd5e1;
            text-align: left;
        }

        td {
            padding: 4pt 5pt;
            border: 0.5pt solid #e2e8f0;
            font-size: 8pt;
        }

        .bg-slate-100 { background-color: #f1f5f9; }
        .bg-slate-200 { background-color: #e2e8f0; }
        .font-bold { font-weight: bold; }
        .text-center { text-align: center; }
        .text-right { text-align: right; }

        /* KPIs */
        .kpi-container {
            display: flex;
            justify-content: space-between;
            margin-bottom: 10mm;
        }

        .kpi-card {
            width: 48%;
            background-color: #f8fafc;
            border: 1pt solid #e2e8f0;
            padding: 8pt;
            box-sizing: border-box;
        }

        .kpi-card .label {
            font-size: 9pt;
            font-weight: bold;
            display: block;
        }

        .kpi-card .value {
            font-size: 12pt;
            font-weight: bold;
            color: #0f172a;
        }

        /* Gráficos */
        .chart-container {
            text-align: center;
            margin: 2mm 0;
        }

        .chart-container img {
            max-width: 100%;
            height: auto;
        }
    </style>
</head>
<body>
    <div class="container">
        @yield('content')
    </div>
</body>
</html>
