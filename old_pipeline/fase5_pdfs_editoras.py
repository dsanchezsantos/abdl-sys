import pandas as pd
import sqlite3
import os
import tempfile
import matplotlib.pyplot as plt
import numpy as np
from fpdf import FPDF
from datetime import datetime

# ==========================================
# CONFIGURAÇÃO DA LOGO
# Insira o caminho completo da logo abaixo
# ==========================================
CAMINHO_LOGO = "C:/feiralivros/abdl_logo.png"
TOTAL_DESEJADO_AUDITORIA = 5_952_715.00

# Força o matplotlib a rodar em background
plt.switch_backend('Agg')

def formata_br(valor):
    return f"R$ {valor:,.2f}".replace(',', '_').replace('.', ',').replace('_', '.')

# --- CLASSE DE FORMATAÇÃO DO PDF ---
class RelatorioPDF(FPDF):
    def __init__(self, titulo_relatorio, subtitulo):
        super().__init__(orientation='L', unit='mm', format='A4') # Paisagem
        self.titulo_relatorio = titulo_relatorio
        self.subtitulo = subtitulo
        
        # --- INCORPORAÇÃO DE FONTE PARA HABILITAR PESQUISA NO CHROME/EDGE ---
        font_dir = "C:/Windows/Fonts/"
        if os.path.exists(font_dir + "arial.ttf"):
            self.add_font('ArialUnicode', '', font_dir + 'arial.ttf')
            self.add_font('ArialUnicode', 'B', font_dir + 'arialbd.ttf')
            self.add_font('ArialUnicode', 'I', font_dir + 'ariali.ttf')
            self.fonte_padrao = 'ArialUnicode'
        else:
            self.fonte_padrao = 'Helvetica'

    def header(self):
        self.set_fill_color(255, 255, 255)
        self.rect(0, 0, 297, 25, 'F')
        
        self.set_draw_color(0, 0, 0)
        self.set_line_width(0.5)
        self.line(10, 25, 287, 25)
        
        if os.path.exists(CAMINHO_LOGO):
            self.image(CAMINHO_LOGO, 10, 3, h=19)

        self.set_y(8)
        self.set_font(self.fonte_padrao, 'B', 16)
        self.set_text_color(0, 0, 0)
        self.cell(0, 5, self.titulo_relatorio, new_x="LMARGIN", new_y="NEXT", align='C')
        
        self.set_font(self.fonte_padrao, '', 10)
        self.cell(0, 8, self.subtitulo, new_x="LMARGIN", new_y="NEXT", align='C')
        self.ln(8)

    def footer(self):
        self.set_y(-12)
        self.set_font(self.fonte_padrao, 'I', 8)
        self.set_text_color(128, 128, 128)
        self.cell(0, 10, f'Página {self.page_no()}/{{nb}} - Relatório de Auditoria Financeira', align='C')

# --- GERADOR AVANÇADO DO PDF DE EDITORAS (COM REPRESENTANTES) ---
def gerar_pdf_editoras_avancado(df_livros_detalhe, df_editora_resumo, df_diario, is_geral, titulo, subtitulo, caminho_arquivo, df_inconsistencias=None):
    pdf = RelatorioPDF(titulo, subtitulo)
    pdf.alias_nb_pages()
    pdf.add_page()
    
    if df_editora_resumo.empty:
        pdf.set_font(pdf.fonte_padrao, 'I', 12)
        pdf.cell(0, 10, "Nenhuma transação oficial de cartões encontrada neste período.", align='C')
        pdf.output(caminho_arquivo)
        return

    # 1. Cálculos Macros da Primeira Página
    total_livros = df_editora_resumo['Qtd_Livros'].sum()
    total_arrecadado = df_editora_resumo['Faturamento_Cartao'].sum()
    
    # Campeãs por Representante
    campeas = {}
    for rep in df_editora_resumo['Representante'].unique():
        df_rep = df_editora_resumo[df_editora_resumo['Representante'] == rep]
        if not df_rep.empty:
            melhor = df_rep.loc[df_rep['Faturamento_Cartao'].idxmax()]
            campeas[rep] = f"{melhor['Categoria']} ({formata_br(melhor['Faturamento_Cartao'])})"

    # --- DESENHA RESUMO EXECUTIVO ---
    pdf.set_font(pdf.fonte_padrao, 'B', 14)
    pdf.cell(0, 8, "Resumo Executivo de Vendas Oficiais (Apenas Valores dos Cartões)", new_x="LMARGIN", new_y="NEXT", align='C')
    pdf.ln(2)

    pdf.set_font(pdf.fonte_padrao, 'B', 10)
    pdf.set_fill_color(240, 244, 248)
    
    pdf.cell(135, 8, f" Quantidade Total de Livros Vendidos: {int(total_livros)} unidades", border=1, fill=True)
    pdf.cell(5, 8, "", border=0) 
    pdf.cell(135, 8, f" Valor Oficial Repassado (Cartões): {formata_br(total_arrecadado)}", border=1, fill=True, new_x="LMARGIN", new_y="NEXT")
    pdf.ln(3)

    # --- PÓDIOS ---
    pdf.set_fill_color(226, 232, 240)
    pdf.cell(0, 8, " Top Editoras (Campeãs de Faturamento por Representante):", border=1, fill=True, new_x="LMARGIN", new_y="NEXT")
    pdf.set_font(pdf.fonte_padrao, '', 9)
    for rep, texto in campeas.items():
        pdf.cell(0, 7, f"   * {rep}: {texto}", border='L R', new_x="LMARGIN", new_y="NEXT")
    pdf.cell(0, 1, "", border='L R B', new_x="LMARGIN", new_y="NEXT")
    pdf.ln(8)

    # --- GRÁFICOS GERADOS DINAMICAMENTE ---
    plt.figure(figsize=(11, 4.5))
    
    num_plots = 2 if is_geral and not df_diario.empty else 1
    
    cores = ['#10B981', '#3B82F6', '#94A3B8', '#F59E0B', '#8B5CF6'] 
    
    ax1 = plt.subplot(1, num_plots, 1)
    df_rep_share = df_editora_resumo.groupby('Representante')['Faturamento_Cartao'].sum().sort_values(ascending=False)
    
    wedges, texts, autotexts = ax1.pie(
        df_rep_share, 
        labels=df_rep_share.index, 
        autopct='%1.1f%%', 
        startangle=90,
        colors=cores[:len(df_rep_share)],
        wedgeprops=dict(width=0.4, edgecolor='w')
    )
    plt.setp(autotexts, size=9, weight="bold", color="black")
    ax1.set_title("Market Share (Fatia do Faturamento)", fontsize=11, fontweight='bold', pad=15)

    if num_plots == 2:
        ax2 = plt.subplot(1, 2, 2)
        df_diario['Data_DT'] = pd.to_datetime(df_diario['dia_str'], format='%d-%m-%Y', errors='coerce')
        df_linhas = df_diario.groupby(['Data_DT', 'Representante'])['Faturamento_Cartao'].sum().unstack(fill_value=0)
        
        for idx, coluna in enumerate(df_linhas.columns):
            ax2.plot(df_linhas.index.strftime('%d/%m'), df_linhas[coluna], marker='o', linewidth=2, label=coluna, color=cores[idx % len(cores)])
            
        ax2.set_title('Evolução Financeira Diária', fontsize=11, fontweight='bold', pad=15)
        ax2.set_ylabel('Valor Computado (R$)')
        ax2.grid(True, linestyle='--', alpha=0.5)
        ax2.legend(loc='upper left', fontsize=8)
        plt.xticks(rotation=45, ha='right', fontsize=8)

    plt.tight_layout()
    temp_chart = tempfile.NamedTemporaryFile(delete=False, suffix='.png')
    temp_chart.close()
    plt.savefig(temp_chart.name, format='png', dpi=150)
    plt.close('all')

    pdf.image(temp_chart.name, x='C', w=200 if is_geral else 120)
    try:
        os.remove(temp_chart.name)
    except:
        pass

    pdf.add_page()
    
    # ==========================================
    # CAPÍTULOS POR REPRESENTANTE
    # ==========================================
    representantes = df_editora_resumo['Representante'].unique()
    representantes = sorted(representantes, key=lambda x: (x == 'NÃO INFORMADO', x))

    for rep in representantes:
        df_ed_rep = df_editora_resumo[df_editora_resumo['Representante'] == rep].sort_values(by='Faturamento_Cartao', ascending=False)
        if df_ed_rep.empty: continue
        
        faturamento_rep = df_ed_rep['Faturamento_Cartao'].sum()
        livros_rep = df_ed_rep['Qtd_Livros'].sum()

        pdf.set_fill_color(30, 41, 59)
        pdf.set_text_color(255, 255, 255)
        pdf.set_font(pdf.fonte_padrao, 'B', 12)
        pdf.cell(0, 10, f" REPRESENTANTE: {rep}   |   SUBTOTAL FATURAMENTO: {formata_br(faturamento_rep)}   |   LIVROS: {int(livros_rep)} un", border=1, fill=True, new_x="LMARGIN", new_y="NEXT", align='L')
        pdf.ln(3)

        pdf.set_text_color(0, 0, 0)

        for _, ed in df_ed_rep.iterrows():
            nome_editora = str(ed['Categoria'])
            fat_total = ed['Faturamento_Cartao']
            bruto_total = ed['Valor_Bruto_Capa']

            pdf.set_fill_color(226, 232, 240)
            pdf.set_font(pdf.fonte_padrao, 'B', 8)
            
            texto_cabecalho = f" EDITORA: {nome_editora[:60]}   |   VALOR DE CAPA (BRUTO): {formata_br(bruto_total)}   |   VALOR PAGO NO CARTÃO: {formata_br(fat_total)}"
            pdf.cell(0, 7, texto_cabecalho, border=1, fill=True, new_x="LMARGIN", new_y="NEXT")

            livros_da_editora = df_livros_detalhe[(df_livros_detalhe['Categoria'] == nome_editora) & (df_livros_detalhe['Representante'] == rep)].copy()

            if not livros_da_editora.empty:
                with pdf.table(
                    borders_layout="INTERNAL",
                    cell_fill_color=(255, 255, 255),
                    cell_fill_mode="NONE",
                    line_height=5,
                    text_align="LEFT",
                    width=277,
                    align='L',
                    col_widths=(140, 20, 35, 37, 45)
                ) as table:
                    row = table.row()
                    pdf.set_font(pdf.fonte_padrao, 'B', 7)
                    row.cell("Nome do Livro")
                    row.cell("Qtd")
                    row.cell("Valor Médio Capa")
                    row.cell("Total Bruto (Etiqueta)")
                    row.cell("Computado (Pago Cartão)")
                    
                    pdf.set_font(pdf.fonte_padrao, '', 7)
                    for _, lr in livros_da_editora.iterrows():
                        row = table.row()
                        row.cell(str(lr['name_up'])) 
                        row.cell(str(int(lr['Qtd']))) 
                        row.cell(formata_br(lr['Preco_Unit_Bruto']))
                        row.cell(formata_br(lr['Bruto_Total_R']))
                        row.cell(formata_br(lr['Faturamento_Cartao']))
            else:
                pdf.set_font(pdf.fonte_padrao, 'I', 7)
                pdf.cell(0, 6, " Nenhum detalhe de livro encontrado.", new_x="LMARGIN", new_y="NEXT")

            pdf.ln(3)
        pdf.ln(5)

    # ==========================================
    # ANEXO DE INCONSISTÊNCIAS (FINAL DO PDF)
    # ==========================================
    if df_inconsistencias is not None and not df_inconsistencias.empty:
        pdf.add_page()
        pdf.set_fill_color(220, 38, 38)
        pdf.set_text_color(255, 255, 255)
        pdf.set_font(pdf.fonte_padrao, 'B', 12)
        pdf.cell(0, 10, " ANEXO DE AUDITORIA: LIVROS VENDIDOS MAS NÃO CATALOGADOS", border=1, fill=True, new_x="LMARGIN", new_y="NEXT", align='L')
        pdf.ln(3)

        pdf.set_text_color(0, 0, 0)
        pdf.set_font(pdf.fonte_padrao, '', 9)
        aviso = ("Os títulos abaixo constam nos logs de transações dos caixas (itens_venda), porém não "
                 "possuem correspondência na tabela oficial de catálogo do evento (livros). "
                 "Para garantir que a matemática do faturamento permaneça 100% exata com o valor dos cartões, "
                 "o sistema os consolidou automaticamente na seção do Representante 'NÃO INFORMADO', sob a Categoria 'AVULSO'.")
        pdf.multi_cell(0, 6, aviso)
        pdf.ln(5)

        with pdf.table(
            borders_layout="INTERNAL",
            cell_fill_color=(255, 255, 255),
            cell_fill_mode="NONE",
            line_height=5,
            text_align="LEFT",
            width=277,
            align='L',
            col_widths=(197, 40, 40)
        ) as table:
            row = table.row()
            pdf.set_font(pdf.fonte_padrao, 'B', 8)
            row.cell("Nome Original Capturado no Caixa")
            row.cell("Qtd Vendida")
            row.cell("Valor Computado")

            pdf.set_font(pdf.fonte_padrao, '', 8)
            for _, inc in df_inconsistencias.iterrows():
                row = table.row()
                row.cell(str(inc['name_up']))
                row.cell(str(int(inc['Qtd'])))
                row.cell(formata_br(inc['Faturamento_Cartao']))

    pdf.output(caminho_arquivo)

# --- PROCESSAMENTO PRINCIPAL ---
def gerar_relatorios_pdf():
    print("1. Conectando ao banco e extraindo dados (Foco em Editoras/Representantes)...")
    conn = sqlite3.connect('feira_livro_teste_editoras.db')
    
    df_vendas = pd.read_sql_query("SELECT * FROM vendas_header", conn)
    df_pagamentos = pd.read_sql_query("SELECT * FROM pagamentos", conn)
    df_itens = pd.read_sql_query("SELECT * FROM itens_venda", conn)
    df_cartoes = pd.read_sql_query("SELECT * FROM cartoes", conn)
    
    df_livros = pd.read_sql_query("SELECT Produto, Categoria, Representante FROM livros", conn)
    if 'Representante' not in df_livros.columns:
        df_livros['Representante'] = 'NÃO INFORMADO'

    conn.close()

    df_vendas = df_vendas.drop_duplicates(subset=['sellNumber'])
    df_pagamentos = df_pagamentos.drop_duplicates()
    df_itens = df_itens.drop_duplicates()

    # === CORREÇÃO: Tratamento de &AMP para & no catálogo de livros ===
    df_livros['Produto'] = df_livros['Produto'].fillna('')
    df_livros['prod_up'] = df_livros['Produto'].str.strip().str.upper()
    df_livros['prod_up'] = df_livros['prod_up'].str.replace('&AMP;', '&', regex=False).str.replace('&AMP', '&', regex=False)
    df_livros = df_livros.drop_duplicates(subset=['prod_up'])
    
    df_livros['Categoria'] = df_livros['Categoria'].fillna('AVULSO').str.strip().str.upper()
    df_livros['Representante'] = df_livros['Representante'].fillna('NÃO INFORMADO').str.strip().str.upper()

    def limpar_moeda(serie):
        return serie.fillna('0').astype(str).str.replace('R$', '', regex=False).str.replace(' ', '', regex=False).str.replace('.', '', regex=False).str.replace(',', '.', regex=False).astype(float)

    df_pagamentos['valor_num'] = limpar_moeda(df_pagamentos['value'])
    df_itens['valor_total_num'] = limpar_moeda(df_itens['totalValue'])

    df_cart_limpo = df_cartoes[['Código', 'Grupo']].drop_duplicates(subset=['Código']).copy()
    df_cart_limpo['Código'] = df_cart_limpo['Código'].astype(str).str.strip().str.upper()

    grupos_invalidos = ['-', 'Teste Nowigo', 'nan', '']
    df_cart_v = df_cartoes[~df_cartoes['Grupo'].astype(str).str.strip().isin(grupos_invalidos)].copy()
    
    df_cart_v['Código'] = df_cart_v['Código'].astype(str).str.strip().str.upper()
    df_cart_v = df_cart_v.drop_duplicates(subset=['Código'])

    df_pag_v = df_pagamentos.merge(df_cart_v, left_on=df_pagamentos['tagCode'].str.strip().str.upper(), 
                                   right_on='Código', how='inner')
    
    df_pag_v = df_pag_v[(df_pag_v['paymentWay'].str.upper() != 'DESCONTO') & 
                        (df_pag_v['payment_group'].str.upper() != 'PAGAMENTO SEM GRUPO')]

    df_vendas['dt'] = pd.to_datetime(df_vendas['dateHour'], format='%d/%m/%Y %H:%M:%S', errors='coerce')
    df_vendas_f = df_vendas[(df_vendas['dt'] >= '2025-11-11') & (df_vendas['dt'] <= '2025-11-20 23:59:59')].copy()
    df_vendas_f['dia_str'] = df_vendas_f['dt'].dt.strftime('%d-%m-%Y')

    vendas_ids_data = df_vendas_f['sellNumber'].unique()
    df_pag_f = df_pag_v[df_pag_v['sellNumber'].isin(vendas_ids_data)]

    vendas_pagas_com_cartao = df_pag_f['sellNumber'].unique()
    df_itens_f = df_itens[df_itens['sellNumber'].isin(vendas_pagas_com_cartao)].copy()
    df_vendas_f = df_vendas_f[df_vendas_f['sellNumber'].isin(vendas_pagas_com_cartao)].copy()

    df_ca_auditoria = df_pag_f.groupby(['Código', 'ID Verso', 'Grupo']).agg(Gasto=('valor_num', 'sum')).reset_index()
    total_gasto_cartoes = df_ca_auditoria['Gasto'].sum()

    print("\n" + "=" * 78)
    print(" 📊 AUDITORIA: ALINHANDO DADOS FINANCEIROS")
    print("=" * 78)
    print(f"Total Desejado pelo Cliente:                {formata_br(TOTAL_DESEJADO_AUDITORIA)}")
    print(f"1. Faturamento Base (Cartões):              {formata_br(total_gasto_cartoes)}")
    print("=" * 78)
    
    df_pag_todos_f = df_pagamentos[df_pagamentos['sellNumber'].isin(vendas_pagas_com_cartao)].copy()
    df_pag_todos_f['tag_cruzamento'] = df_pag_todos_f['tagCode'].astype(str).str.strip().str.upper()
    df_pag_todos_f = df_pag_todos_f.merge(df_cart_limpo, left_on='tag_cruzamento', right_on='Código', how='left')

    df_pag_validos = df_pag_todos_f[
        (df_pag_todos_f['paymentWay'].str.upper() != 'DESCONTO') & 
        (df_pag_todos_f['payment_group'].str.upper() != 'PAGAMENTO SEM GRUPO') &
        (df_pag_todos_f['tag_cruzamento'].isin(df_cart_v['Código'].str.strip().str.upper()))
    ]

    venda_pag = df_pag_validos.groupby('sellNumber')['valor_num'].sum().reset_index(name='Total_Pago_Cartao')
    venda_bruto = df_itens_f.groupby('sellNumber')['valor_total_num'].sum().reset_index(name='Total_Bruto_Livros')
    
    df_itens_aloc = df_itens_f.merge(venda_bruto, on='sellNumber', how='left').merge(venda_pag, on='sellNumber', how='left')
    df_itens_aloc['Total_Pago_Cartao'] = df_itens_aloc['Total_Pago_Cartao'].fillna(0)
    
    df_itens_aloc['Proporcao'] = 0.0
    mask = df_itens_aloc['Total_Bruto_Livros'] > 0
    df_itens_aloc.loc[mask, 'Proporcao'] = df_itens_aloc.loc[mask, 'Total_Pago_Cartao'] / df_itens_aloc.loc[mask, 'Total_Bruto_Livros']
    
    df_itens_aloc['Faturamento_Cartao'] = df_itens_aloc['valor_total_num'] * df_itens_aloc['Proporcao']
    
    print(f"✅ Alocação concluída. Total do Relatório de Editoras calibrado para: {formata_br(df_itens_aloc['Faturamento_Cartao'].sum())}")

    base_dir = "Relatorios_Feira"

    print(" -> Limpando PDFs de Editoras de execuções anteriores...")
    if os.path.exists(base_dir):
        for root, dirs, files in os.walk(base_dir):
            if 'PDFs' in root: 
                for file in files:
                    if 'Editoras' in file and file.endswith('.pdf'):
                        try:
                            os.remove(os.path.join(root, file))
                        except:
                            pass

    def preparar_dados_editoras(v_df, i_aloc_df):
        i_aloc_df['name_up'] = i_aloc_df['name'].fillna('').str.strip().str.upper()
        
        # === CORREÇÃO: Tratamento de &AMP para & nas transações antes de cruzar ===
        i_aloc_df['name_up'] = i_aloc_df['name_up'].str.replace('&AMP;', '&', regex=False).str.replace('&AMP', '&', regex=False)
        
        ed = i_aloc_df.merge(df_livros, left_on='name_up', right_on='prod_up', how='left')
        
        mask_missing = ed['Produto'].isna() | (ed['Produto'] == '')
        df_inconsistencias = ed[mask_missing].groupby('name_up').agg(
            Qtd=('amount', 'sum'),
            Faturamento_Cartao=('Faturamento_Cartao', 'sum')
        ).reset_index().sort_values(by='Faturamento_Cartao', ascending=False)
        
        ed['Categoria'] = ed['Categoria'].fillna('AVULSO').str.strip().str.upper()
        ed['Representante'] = ed['Representante'].fillna('NÃO INFORMADO').str.strip().str.upper()
        
        ed_ag = ed.groupby(['Representante', 'Categoria']).agg(
            Qtd_Livros=('amount', 'sum'), 
            Valor_Bruto_Capa=('valor_total_num', 'sum'),
            Faturamento_Cartao=('Faturamento_Cartao', 'sum')
        ).reset_index()
        ed_ag = ed_ag.sort_values(by=['Representante', 'Faturamento_Cartao'], ascending=[True, False]).reset_index(drop=True)

        ed_livros = ed.groupby(['Representante', 'Categoria', 'name_up']).agg(
            Qtd=('amount', 'sum'), 
            Bruto_Total_R=('valor_total_num', 'sum'),
            Faturamento_Cartao=('Faturamento_Cartao', 'sum')
        ).reset_index()
        ed_livros['Preco_Unit_Bruto'] = ed_livros['Bruto_Total_R'] / ed_livros['Qtd']
        ed_livros = ed_livros.sort_values(by=['Representante', 'Categoria', 'Faturamento_Cartao'], ascending=[True, True, False]).reset_index(drop=True)
        
        ed_diario = ed.merge(v_df[['sellNumber', 'dia_str']], on='sellNumber', how='inner')
        
        return ed_ag, ed_livros, ed_diario, df_inconsistencias

    print("2. Gerando PDF GERAL de Editoras/Representantes...")
    ed_g, ed_livros_g, ed_diario_g, inc_g = preparar_dados_editoras(df_vendas_f, df_itens_aloc)
    gerar_pdf_editoras_avancado(ed_livros_g, ed_g, ed_diario_g, True, "Desempenho Comercial: Editoras e Representantes", "Consolidado Oficial: 11/11 a 20/11", f"{base_dir}/Geral/PDFs/Faturamento_Editoras_Geral.pdf", inc_g)

    print("3. Gerando PDFs DIÁRIOS de Editoras/Representantes...")
    for dia in df_vendas_f['dia_str'].unique():
        v_d = df_vendas_f[df_vendas_f['dia_str'] == dia]
        v_ids = v_d['sellNumber'].unique()
        i_aloc_d = df_itens_aloc[df_itens_aloc['sellNumber'].isin(v_ids)].copy()
        
        ed_d, ed_livros_d, ed_diario_d, inc_d = preparar_dados_editoras(v_d, i_aloc_d)
        
        prefixo = f"{base_dir}/Por_Dia/{dia}/PDFs"
        os.makedirs(prefixo, exist_ok=True)
        
        if not ed_d.empty:
            gerar_pdf_editoras_avancado(ed_livros_d, ed_d, ed_diario_d, False, f"Desempenho Comercial - {dia}", f"Data Referência: {dia}", f"{prefixo}/Faturamento_Editoras_{dia}.pdf", inc_d)
            print(f"   -> Dia {dia} Finalizado com sucesso.")

    print("\n✅ Relatórios de Editoras otimizados e gerados com sucesso!")

if __name__ == '__main__':
    gerar_relatorios_pdf()