<style>
    .header-native {
        width: 100%;
        display: -webkit-box;
        display: -webkit-flex;
        display: flex;
        -webkit-box-align: center;
        -webkit-align-items: center;
        align-items: center;
        font-family: 'Helvetica', sans-serif;
        font-size: 8pt;
        border-bottom: 0.5pt solid #000;
        margin: 0 10mm;
        padding-bottom: 2mm;
    }
    .header-native img { height: 12mm; }
    .header-native .titles { -webkit-box-flex: 1; -webkit-flex-grow: 1; flex-grow: 1; text-align: center; }
    .header-native h1 { font-size: 14pt; margin: 0; text-transform: uppercase; letter-spacing: 1pt; }
</style>
<div class="header-native">
    <div style="width: 20%">
        <img src="abdl_logo.png" alt="Logo">
    </div>
    <div class="titles">
        <h1>{{ $titulo }}</h1>
        <div style="font-size: 9pt; color: #64748b;">{{ $subtitulo }}</div>
    </div>
    <div style="width: 20%; text-align: right; font-size: 7pt; color: #64748b;">
        Gerado em: {{ now()->format('d/m/Y H:i') }}
    </div>
</div>
