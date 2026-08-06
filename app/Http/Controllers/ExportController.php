<?php
 
namespace App\Http\Controllers;
 
use App\Models\Feira;
use App\Models\Livro;
use App\Models\Cartao;
use App\Models\VendaHeader;
use App\Models\Pagamento;
use App\Enums\CartaoClassificacao;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
 
class ExportController extends Controller
{
    /**
     * Exporta todos os livros do catálogo de uma feira específica.
     */
    public function exportLivros(Feira $feira)
    {
        $livros = Livro::where('id_feira', $feira->id)
            ->orderBy('produto')
            ->get();
 
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Livros');
 
        // Cabeçalhos
        $headers = ['ID API', 'Livro', 'Preço de Capa', 'Categoria', 'Editora', 'Representante'];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:F1')->getFont()->setBold(true);
 
        $row = 2;
        foreach ($livros as $livro) {
            $sheet->setCellValue('A' . $row, $livro->produto_id_api);
            $sheet->setCellValue('B' . $row, $livro->produto);
            $sheet->setCellValue('C' . $row, (float) $livro->valor);
            $sheet->setCellValue('D' . $row, $livro->categoria ?: 'Não Categorizado');
            $sheet->setCellValue('E' . $row, $livro->editora);
            $sheet->setCellValue('F' . $row, $livro->representante);
 
            // Formata a coluna de preço como moeda BRL
            $sheet->getStyle('C' . $row)->getNumberFormat()->setFormatCode('R$ #,##0.00');
            $row++;
        }
 
        // Dimensionamento automático das colunas
        foreach (range('A', 'F') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
 
        $filename = 'livros_feira_' . $feira->id . '_' . \Str::slug($feira->nome) . '.xlsx';
 
        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
 
    /**
     * Exporta todos os cartões cadastrados e válidos de uma feira específica.
     */
    public function exportCartoes(Feira $feira)
    {
        // Query com left join para obter o gasto total de pagamentos por cartão
        $cartoes = Cartao::where('id_feira', $feira->id)
            ->where('grupo', '!=', 'PAGAMENTO SEM GRUPO')
            ->where('classificacao', '!=', CartaoClassificacao::TESTE->value)
            ->leftJoin(DB::raw('(
                SELECT tag_code, SUM(value) as total_gasto
                FROM pagamentos
                WHERE id_feira = ' . (int)$feira->id . '
                GROUP BY tag_code
            ) as p'), 'cartoes.tag_code', '=', 'p.tag_code')
            ->select('cartoes.*', DB::raw('COALESCE(p.total_gasto, 0) as total_gasto'))
            ->orderBy('cartoes.tag_code')
            ->get();
 
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Cartões');
 
        // Cabeçalhos
        $headers = [
            'Código (Tag)',
            'Identificação Aluno',
            'Grupo / Escola',
            'Classificação',
            'Valor Inicial',
            'Valor Gasto',
            'Saldo Restante'
        ];
        $sheet->fromArray($headers, null, 'A1');
        $sheet->getStyle('A1:G1')->getFont()->setBold(true);
 
        $row = 2;
        $saldoInicial = 250.00;
        foreach ($cartoes as $cartao) {
            $gasto = (float) $cartao->total_gasto;
            $saldoRestante = $saldoInicial - $gasto;
 
            $sheet->setCellValue('A' . $row, $cartao->tag_code);
            $sheet->setCellValue('B' . $row, $cartao->identificacao_aluno ?: 'Não Identificado');
            $sheet->setCellValue('C' . $row, $cartao->grupo);
            $sheet->setCellValue('D' . $row, $cartao->classificacao->label());
            $sheet->setCellValue('E' . $row, $saldoInicial);
            $sheet->setCellValue('F' . $row, $gasto);
            $sheet->setCellValue('G' . $row, $saldoRestante);
 
            // Formata moedas
            $sheet->getStyle('E' . $row . ':G' . $row)->getNumberFormat()->setFormatCode('R$ #,##0.00');
            $row++;
        }
 
        // Dimensionamento automático das colunas
        foreach (range('A', 'G') as $col) {
            $sheet->getColumnDimension($col)->setAutoSize(true);
        }
 
        $filename = 'cartoes_feira_' . $feira->id . '_' . \Str::slug($feira->nome) . '.xlsx';
 
        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
 
    /**
     * Exporta Vendas e Transações em abas separadas para uma feira específica.
     */
    public function exportVendasTransacoes(Feira $feira)
    {
        // Barreira de Contenção (Filtro de Ouro)
        $vendasValidasQuery = Pagamento::where('id_feira', $feira->id)
            ->validosParaRateio()
            ->select('sell_number');
 
        // 1. Vendas (Filtradas para conter apenas as válidas)
        $vendas = VendaHeader::where('venda_headers.id_feira', $feira->id)
            ->whereIn('venda_headers.sell_number', $vendasValidasQuery)
            ->leftJoin(DB::raw('(
                SELECT sell_number, STRING_AGG(DISTINCT name, \', \') AS livros, SUM(amount) AS total_livros
                FROM itens_venda
                WHERE id_feira = ' . (int)$feira->id . '
                GROUP BY sell_number
            ) AS itens_agg'), 'venda_headers.sell_number', '=', 'itens_agg.sell_number')
            ->leftJoin(DB::raw('(
                SELECT p.sell_number, SUM(p.value) AS total_pago
                FROM pagamentos p
                JOIN cartoes c ON p.tag_code = c.tag_code AND p.id_feira = c.id_feira
                WHERE p.id_feira = ' . (int)$feira->id . '
                  AND UPPER(p.payment_way) NOT LIKE \'%DESCONTO%\'
                  AND UPPER(p.payment_group) NOT LIKE \'%PAGAMENTO SEM GRUPO%\'
                  AND c.classificacao != \'' . CartaoClassificacao::TESTE->value . '\'
                GROUP BY p.sell_number
            ) AS pag_agg'), 'venda_headers.sell_number', '=', 'pag_agg.sell_number')
            ->select('venda_headers.*', DB::raw('COALESCE(itens_agg.livros, \'N/A\') AS livros_lista'), DB::raw('COALESCE(itens_agg.total_livros, 0) AS total_livros'), DB::raw('COALESCE(pag_agg.total_pago, 0) AS total_pago'))
            ->orderBy('venda_headers.date_hour')
            ->get();
 
        // 2. Transações / Pagamentos (Filtradas usando o escopo validosParaRateio)
        $transacoes = Pagamento::where('pagamentos.id_feira', $feira->id)
            ->validosParaRateio()
            ->select('pagamentos.*')
            ->orderBy('pagamentos.sell_number')
            ->get();
 
        $spreadsheet = new Spreadsheet();
 
        // Aba 1: Vendas
        $sheetVendas = $spreadsheet->getActiveSheet();
        $sheetVendas->setTitle('Vendas');
 
        $headersVendas = ['ID Venda', 'Horário', 'Caixa', 'Qtd Livros', 'Livros Vendidos', 'Método de Venda', 'Valor Total'];
        $sheetVendas->fromArray($headersVendas, null, 'A1');
        $sheetVendas->getStyle('A1:G1')->getFont()->setBold(true);
 
        $row = 2;
        foreach ($vendas as $venda) {
            $sheetVendas->setCellValue('A' . $row, $venda->sell_number);
            $sheetVendas->setCellValue('B' . $row, $venda->date_hour ? $venda->date_hour->format('d/m/Y H:i:s') : '---');
            $sheetVendas->setCellValue('C' . $row, $venda->box ?: '---');
            $sheetVendas->setCellValue('D' . $row, (int) $venda->total_livros);
            $sheetVendas->setCellValue('E' . $row, $venda->livros_lista);
 
            $metodo = 'Não Informado';
            if ($venda->sale_type === 1) {
                $metodo = 'Múltiplos Pagamentos';
            } elseif ($venda->sale_type === -1) {
                $metodo = 'Pagamento Único';
            }
            $sheetVendas->setCellValue('F' . $row, $metodo);
            $sheetVendas->setCellValue('G' . $row, (float) $venda->total_pago);
 
            $sheetVendas->getStyle('G' . $row)->getNumberFormat()->setFormatCode('R$ #,##0.00');
            $row++;
        }
 
        foreach (range('A', 'G') as $col) {
            $sheetVendas->getColumnDimension($col)->setAutoSize(true);
        }
 
        // Aba 2: Transações
        $sheetTransacoes = $spreadsheet->createSheet();
        $sheetTransacoes->setTitle('Transações');
 
        $headersTransacoes = ['ID Transação', 'ID Venda', 'Meio de Pagamento', 'Código Cartão (Tag)', 'Grupo / Escola', 'Valor Pago', 'CPF'];
        $sheetTransacoes->fromArray($headersTransacoes, null, 'A1');
        $sheetTransacoes->getStyle('A1:G1')->getFont()->setBold(true);
 
        $row = 2;
        foreach ($transacoes as $tr) {
            $sheetTransacoes->setCellValue('A' . $row, $tr->pagamento_id_api);
            $sheetTransacoes->setCellValue('B' . $row, $tr->sell_number);
            $sheetTransacoes->setCellValue('C' . $row, $tr->payment_way);
            $sheetTransacoes->setCellValue('D' . $row, $tr->tag_code ?: '---');
            $sheetTransacoes->setCellValue('E' . $row, $tr->payment_group ?: '---');
            $sheetTransacoes->setCellValue('F' . $row, (float) $tr->value);
            $sheetTransacoes->setCellValue('G' . $row, $tr->cpf ?: '---');
 
            $sheetTransacoes->getStyle('F' . $row)->getNumberFormat()->setFormatCode('R$ #,##0.00');
            $row++;
        }
 
        foreach (range('A', 'G') as $col) {
            $sheetTransacoes->getColumnDimension($col)->setAutoSize(true);
        }
 
        $filename = 'vendas_e_transacoes_feira_' . $feira->id . '_' . \Str::slug($feira->nome) . '.xlsx';
 
        return response()->streamDownload(function () use ($spreadsheet) {
            $writer = new Xlsx($spreadsheet);
            $writer->save('php://output');
        }, $filename, [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Cache-Control' => 'max-age=0',
        ]);
    }
}
