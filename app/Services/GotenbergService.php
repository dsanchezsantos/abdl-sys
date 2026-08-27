<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * GotenbergService
 *
 * Client HTTP para a API do Gotenberg (Chromium headless).
 * Encapsula a comunicação multipart para converter HTML em PDF
 * e para fazer o merge de múltiplos PDFs em um único arquivo.
 *
 * Estratégia de Gráficos: O HTML (index.html) e os arquivos PNG
 * dos gráficos são enviados juntos no mesmo request multipart.
 * O Blade referencia os gráficos com <img src="chart_1.png">,
 * e o Chromium interno do Gotenberg resolve essas referências.
 */
class GotenbergService
{
    private string $baseUrl;

    public function __construct()
    {
        $this->baseUrl = rtrim(config('services.gotenberg.url', 'http://gotenberg:3000'), '/');
    }

    /**
     * Converte um HTML em PDF, enviando os arquivos de gráficos
     * como recursos anexos no mesmo request multipart.
     *
     * @param string $htmlContent  Conteúdo HTML da view Blade renderizada.
     * @param array  $chartFiles   Array de ['filename' => 'chart_1.png', 'path' => '/tmp/chart_xxx.png']
     * @param string $orientation  'Landscape' ou 'Portrait'
     * @param string|null $headerHtml  HTML para o cabeçalho nativo do Gotenberg
     * @param string|null $footerHtml  HTML para o rodapé nativo do Gotenberg
     * @return string              Bytes binários do PDF gerado.
     */
    public function htmlToPdf(string $htmlContent, array $chartFiles = [], string $orientation = 'Landscape', ?string $headerHtml = null, ?string $footerHtml = null): string
    {
        $request = Http::timeout(290)->asMultipart();

        // Sempre anexa o HTML principal como index.html
        $request = $request->attach('files', $htmlContent, 'index.html');

        // Anexa cabeçalho e rodapé nativos se fornecidos
        if ($headerHtml) {
            $request = $request->attach('files', $headerHtml, 'header.html');
        }
        if ($footerHtml) {
            $request = $request->attach('files', $footerHtml, 'footer.html');
        }

        // Anexa cada PNG de gráfico para que o Chromium resolva <img src="chart_1.png">
        foreach ($chartFiles as $chart) {
            if (file_exists($chart['path'])) {
                $request = $request->attach('files', file_get_contents($chart['path']), $chart['filename']);
            }
        }

        $response = $request->post("{$this->baseUrl}/forms/chromium/convert/html", [
            'paperOrientation' => $orientation,
            'marginTop'        => '25mm', // Aumentado para o cabeçalho nativo
            'marginBottom'     => '20mm', // Aumentado para o rodapé nativo
            'marginLeft'       => '10mm',
            'marginRight'      => '10mm',
            'printBackground'  => 'true',
        ]);

        if ($response->failed()) {
            Log::error('Gotenberg htmlToPdf falhou', [
                'status'  => $response->status(),
                'body'    => $response->body(),
            ]);
            throw new \RuntimeException('Gotenberg falhou ao converter HTML para PDF. Status: ' . $response->status());
        }

        return $response->body();
    }

    /**
     * Faz o merge de múltiplos PDFs em um único arquivo.
     * Usa o endpoint /forms/pdfengines/merge do Gotenberg.
     *
     * @param array $pdfPaths Array de caminhos absolutos para os PDFs parciais (chunks).
     * @return string         Bytes binários do PDF final unificado.
     */
    public function mergePdfs(array $pdfPaths): string
    {
        $request = Http::timeout(290)->asMultipart();
        $handles = [];

        try {
            foreach ($pdfPaths as $index => $path) {
                if (!file_exists($path)) {
                    throw new \RuntimeException("Chunk PDF não encontrado para merge: {$path}");
                }
                // Nomes ordenados numericamente para garantir a sequência correta no merge
                $filename = str_pad($index, 5, '0', STR_PAD_LEFT) . '_chunk.pdf';
                
                $handle = fopen($path, 'r');
                if ($handle === false) {
                    throw new \RuntimeException("Não foi possível abrir o chunk PDF para leitura: {$path}");
                }
                $handles[] = $handle;
                
                $request = $request->attach('files', $handle, $filename);
            }

            $response = $request->post("{$this->baseUrl}/forms/pdfengines/merge");
        } finally {
            foreach ($handles as $handle) {
                if (is_resource($handle)) {
                    fclose($handle);
                }
            }
        }

        if ($response->failed()) {
            Log::error('Gotenberg mergePdfs falhou', [
                'status'     => $response->status(),
                'body'       => $response->body(),
                'num_chunks' => count($pdfPaths),
            ]);
            throw new \RuntimeException('Gotenberg falhou ao fazer merge dos PDFs. Status: ' . $response->status());
        }

        return $response->body();
    }
}
