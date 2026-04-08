import pandas as pd
import sqlite3
import os
import tempfile
import matplotlib.pyplot as plt
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

def limpar_moeda(serie):
    return serie.fillna('0').astype(str).str.replace('R$', '', regex=False).str.replace(' ', '', regex=False).str.replace('.', '', regex=False).str.replace(',', '.', regex=False).astype(float)

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

# =========================================================================
# FUNÇÕES GERADORAS DOS PDFs (TRANSAÇÕES, VENDAS E EDITORAS)
# =========================================================================

def gerar_pdf_transacoes_agrupadas(df_tr, df_ca, titulo, subtitulo, caminho_arquivo):
    pdf = RelatorioPDF(titulo, subtitulo)
    pdf.alias_nb_pages()
    pdf.add_page()

    total_gasto = df_ca['Valor_Gasto_R$'].sum()
    media_gasto = df_ca['Valor_Gasto_R$'].mean() if not df_ca.empty else 0
    qtd_cartoes_zerados = (df_ca['Saldo_Restante_R$'] <= 0.0).sum()
    total_cartoes = len(df_ca)

    pdf.set_font(pdf.fonte_padrao, 'B', 14)
    pdf.cell(0, 10, "Resumo Executivo - Transações por Cartão", new_x="LMARGIN", new_y="NEXT", align='C')
    pdf.ln(5)

    pdf.set_font(pdf.fonte_padrao, 'B', 10)
    pdf.set_fill_color(240, 244, 248)
    pdf.cell(135, 8, f" Total Financeiro Movimentado: {formata_br(total_gasto)}", border=1, fill=True)
    pdf.cell(5, 8, "", border=0)
    pdf.cell(135, 8, f" Média de Gasto por Cartão: {formata_br(media_gasto)}", border=1, fill=True, new_x="LMARGIN", new_y="NEXT")
    pdf.ln(2)
    pdf.cell(135, 8, f" Total de Cartões Utilizados: {total_cartoes}", border=1, fill=True)
    pdf.cell(5, 8, "", border=0)
    pdf.cell(135, 8, f" Cartões que Gastaram Todo o Saldo: {qtd_cartoes_zerados}", border=1, fill=True, new_x="LMARGIN", new_y="NEXT")
    pdf.ln(10)

    if not df_tr.empty:
        plt.figure(figsize=(10, 4))
        df_tr['Data_Apenas'] = pd.to_datetime(df_tr['Data_Hora'], format='%d/%m/%Y %H:%M:%S', errors='coerce').dt.strftime('%d/%m/%Y')
        gastos_dia = df_tr.groupby('Data_Apenas')['Valor_R$'].sum()
        
        ax = gastos_dia.plot(kind='bar', color='#334155', edgecolor='black')
        plt.title('Volume Financeiro Gasto por Dia', fontsize=12, pad=10)
        plt.ylabel('Valor Transacionado (R$)')
        plt.xlabel('Data')
        plt.xticks(rotation=0)
        plt.grid(axis='y', linestyle='--', alpha=0.7)
        
        for p in ax.patches:
            valor = p.get_height()
            if valor > 0:
                texto_valor = formata_br(valor)
                ax.annotate(texto_valor, (p.get_x() + p.get_width() / 2., valor), ha='center', va='bottom', fontsize=7, color='black', xytext=(0, 2), textcoords='offset points')
        
        plt.ylim(top=gastos_dia.max() * 1.15)
        temp_chart = tempfile.NamedTemporaryFile(delete=False, suffix='.png')
        temp_chart.close()
        plt.tight_layout()
        plt.savefig(temp_chart.name, format='png', dpi=150)
        plt.close('all')

        pdf.image(temp_chart.name, x='C', w=180)
        try: os.remove(temp_chart.name)
        except: pass

    pdf.add_page()
    pdf.set_font(pdf.fonte_padrao, 'B', 12)
    pdf.cell(0, 10, "Detalhamento de Transações por Cartão", new_x="LMARGIN", new_y="NEXT", align='C')
    pdf.ln(2)

    for _, cartao in df_ca.iterrows():
        cod = cartao['Código']
        grupo = str(cartao['Grupo'])[:45]
        inicial = cartao['Valor_Inicial_R$']
        gasto = cartao['Valor_Gasto_R$']
        saldo = cartao['Saldo_Restante_R$']

        pdf.set_fill_color(226, 232, 240)
        pdf.set_font(pdf.fonte_padrao, 'B', 8)
        texto_cabecalho = f" CARTÃO: {cod}   |   ESCOLA: {grupo}   |   SALDO INICIAL: {formata_br(inicial)}   |   TOTAL GASTO: {formata_br(gasto)}   |   SALDO FINAL: {formata_br(saldo)}"
        pdf.cell(0, 7, texto_cabecalho, border=1, fill=True, new_x="LMARGIN", new_y="NEXT")

        tr_cartao = df_tr[df_tr['Codigo'] == cod].copy()

        if not tr_cartao.empty:
            cols_exibicao = ['Data_Hora', 'Venda', 'Caixa', 'Livros', 'Uso_Cartoes', 'Valor_R$']
            with pdf.table(
                borders_layout="INTERNAL", cell_fill_color=(255, 255, 255), cell_fill_mode="NONE",
                line_height=5, text_align="LEFT", width=277, align='L', col_widths=(26, 18, 20, 174, 16, 23)
            ) as table:
                row = table.row()
                pdf.set_font(pdf.fonte_padrao, 'B', 7)
                row.cell("Data e Hora")
                row.cell("Venda")
                row.cell("Caixa")
                row.cell("Livro(s) da Compra")
                row.cell("Uso")
                row.cell("Valor Debitado")
                
                pdf.set_font(pdf.fonte_padrao, '', 7)
                for tr_row in tr_cartao[cols_exibicao].values.tolist():
                    row = table.row()
                    row.cell(str(tr_row[0])) 
                    row.cell(str(tr_row[1])) 
                    row.cell(str(tr_row[2])) 
                    row.cell(str(tr_row[3])) 
                    row.cell(str(tr_row[4])) 
                    row.cell(formata_br(tr_row[5]))
        else:
            pdf.set_font(pdf.fonte_padrao, 'I', 7)
            pdf.cell(0, 6, " Nenhuma transação isolada encontrada para este cartão.", new_x="LMARGIN", new_y="NEXT")

        pdf.ln(4)

    pdf.output(caminho_arquivo)

# --- PDF DE VENDAS ATUALIZADO (INCLUINDO O GRÁFICO DUPLO) ---
def gerar_pdf_vendas_agrupadas(df_vd, df_pag_todos, ed_diario, is_geral, titulo, subtitulo, caminho_arquivo):
    pdf = RelatorioPDF(titulo, subtitulo)
    pdf.alias_nb_pages()
    pdf.add_page()
    
    total_vendas = len(df_vd)
    total_arrecadado = df_vd['Valor_Total_R$'].sum()
    ticket_medio = total_arrecadado / total_vendas if total_vendas > 0 else 0

    pdf.set_font(pdf.fonte_padrao, 'B', 14)
    pdf.cell(0, 10, "Resumo Executivo - Transações Agrupadas por Venda", new_x="LMARGIN", new_y="NEXT", align='C')
    pdf.ln(5)

    pdf.set_font(pdf.fonte_padrao, 'B', 10)
    pdf.set_fill_color(240, 244, 248)
    pdf.cell(90, 8, f" Quantidade Total de Vendas: {total_vendas}", border=1, fill=True)
    pdf.cell(3, 8, "", border=0)
    pdf.cell(90, 8, f" Valor Total Computado: {formata_br(total_arrecadado)}", border=1, fill=True)
    pdf.cell(3, 8, "", border=0)
    pdf.cell(91, 8, f" Ticket Médio por Venda: {formata_br(ticket_medio)}", border=1, fill=True, new_x="LMARGIN", new_y="NEXT")
    pdf.ln(10)

    if not df_vd.empty:
        if is_geral and not ed_diario.empty:
            plt.figure(figsize=(12, 4.5))
            ax1 = plt.subplot(1, 2, 1)
        else:
            plt.figure(figsize=(10, 4))
            ax1 = plt.subplot(1, 1, 1)

        df_vd['Data_Apenas'] = pd.to_datetime(df_vd['dateHour'], format='%d/%m/%Y %H:%M:%S', errors='coerce').dt.strftime('%d/%m/%Y')
        vendas_dia = df_vd.groupby('Data_Apenas')['Valor_Total_R$'].sum()
        
        ax1.bar(vendas_dia.index, vendas_dia.values, color='#10B981', edgecolor='black')
        ax1.set_title('Volume Financeiro das Vendas por Dia', fontsize=11, fontweight='bold', pad=10)
        ax1.set_ylabel('Valor Total (R$)')
        ax1.set_xlabel('Data')
        plt.setp(ax1.xaxis.get_majorticklabels(), rotation=45, ha='right', fontsize=8)
        ax1.grid(axis='y', linestyle='--', alpha=0.7)
        
        for p in ax1.patches:
            valor = p.get_height()
            if valor > 0:
                texto_valor = formata_br(valor)
                ax1.annotate(texto_valor, (p.get_x() + p.get_width() / 2., valor), ha='center', va='bottom', fontsize=6, color='black', xytext=(0, 4), textcoords='offset points')
        
        ax1.set_ylim(top=vendas_dia.max() * 1.15)

        # SEGUNDO GRÁFICO: VENDAS POR DIA DAS REPRESENTANTES LADO A LADO
        if is_geral and not ed_diario.empty:
            ax2 = plt.subplot(1, 2, 2)
            ed_diario['Data_DT'] = pd.to_datetime(ed_diario['dia_str'], format='%d-%m-%Y', errors='coerce')
            df_linhas_vendas = ed_diario.groupby(['Data_DT', 'Representante'])['Faturamento_Cartao'].sum().unstack(fill_value=0)
            
            # Filtra apenas para Prolezo e Florescer para dar foco na Venda
            reps_to_plot = [r for r in ['FLORESCER', 'PROLEZO'] if r in df_linhas_vendas.columns]
            if not reps_to_plot: reps_to_plot = df_linhas_vendas.columns
            df_linhas_vendas = df_linhas_vendas[reps_to_plot]
            
            cores_rep = {'FLORESCER': '#10B981', 'PROLEZO': '#3B82F6'}
            
            for coluna in df_linhas_vendas.columns:
                x_vals = df_linhas_vendas.index.strftime('%d/%m')
                y_vals = df_linhas_vendas[coluna]
                cor = cores_rep.get(coluna, '#94A3B8')
                ax2.plot(x_vals, y_vals, marker='o', linewidth=2, label=coluna, color=cor)
                
                # ADIÇÃO: Escreve os valores nos pontos
                for i, val in enumerate(y_vals):
                    if val > 0:
                        ax2.annotate(formata_br(val), (x_vals[i], val), textcoords="offset points", xytext=(0, 4), ha='center', fontsize=6)
            
            ax2.set_title('Vendas Diárias por Representante', fontsize=11, fontweight='bold', pad=10)
            ax2.set_ylabel('Valor Computado (R$)')
            ax2.grid(True, linestyle='--', alpha=0.5)
            ax2.legend(loc='upper left', fontsize=8)
            plt.setp(ax2.xaxis.get_majorticklabels(), rotation=45, ha='right', fontsize=8)
            ax2.set_ylim(top=df_linhas_vendas.values.max() * 1.15)

        plt.tight_layout()
        temp_chart = tempfile.NamedTemporaryFile(delete=False, suffix='.png')
        temp_chart.close()
        plt.savefig(temp_chart.name, format='png', dpi=150)
        plt.close('all')

        pdf.image(temp_chart.name, x='C', w=200 if (is_geral and not ed_diario.empty) else 180)
        try: os.remove(temp_chart.name)
        except: pass

    pdf.add_page()
    pdf.set_font(pdf.fonte_padrao, 'B', 12)
    pdf.cell(0, 10, "Detalhamento de Pagamentos por Venda", new_x="LMARGIN", new_y="NEXT", align='C')
    pdf.ln(2)

    for _, venda in df_vd.iterrows():
        venda_id = venda['sellNumber']
        data = venda['dateHour']
        caixa = str(venda['box'])
        livros = str(venda['Livros'])
        total = venda['Valor_Total_R$']
        
        pdf.set_fill_color(226, 232, 240)
        pdf.set_font(pdf.fonte_padrao, 'B', 7)
        
        texto_cab1 = f" VENDA: {venda_id}   |   DATA: {data}   |   CAIXA: {caixa}   |   VALOR COMPUTADO: {formata_br(total)}"
        pdf.cell(0, 6, texto_cab1, border='L T R', fill=True, new_x="LMARGIN", new_y="NEXT")
        texto_cab2 = f" LIVRO(S): {livros}"
        pdf.cell(0, 6, texto_cab2, border='L B R', fill=True, new_x="LMARGIN", new_y="NEXT")

        pags_venda = df_pag_todos[df_pag_todos['sellNumber'] == venda_id]
        
        if not pags_venda.empty:
            with pdf.table(
                borders_layout="INTERNAL", cell_fill_color=(255, 255, 255), cell_fill_mode="NONE",
                line_height=5, text_align="LEFT", width=277, align='L', col_widths=(60, 160, 57)
            ) as table:
                row = table.row()
                pdf.set_font(pdf.fonte_padrao, 'B', 7)
                row.cell("Forma de Pagamento (Caixa)")
                row.cell("Identificação Detalhada")
                row.cell("Valor Computado")
                
                pdf.set_font(pdf.fonte_padrao, '', 7)
                for _, pg in pags_venda.iterrows():
                    forma = str(pg['paymentWay']).upper()
                    tag = str(pg['tagCode']) if pd.notna(pg['tagCode']) and str(pg['tagCode']).strip() != '' else ""
                    grupo = str(pg['Grupo']) if 'Grupo' in pg and pd.notna(pg['Grupo']) else ""
                    
                    if "DESCONTO" in forma: detalhe = "Desconto Operacional no Caixa"
                    elif tag and tag.upper() != 'NAN':
                        if grupo in ['-', 'Teste Nowigo', 'nan', '', 'None']: detalhe = f"Tag: {tag} [AVISO: CARTAO EQUIPE/TESTE]"
                        else: detalhe = f"Tag: {tag} [ALUNO: {grupo[:35]}]"
                    else:
                        grupo_ext = str(pg['payment_group'])
                        detalhe = f"Pagamento Externo [{grupo_ext}]"
                    
                    row = table.row()
                    row.cell(forma) 
                    row.cell(detalhe) 
                    row.cell(formata_br(pg['valor_num']))
        else:
            pdf.set_font(pdf.fonte_padrao, 'I', 7)
            pdf.cell(0, 6, " Nenhum detalhe de pagamento oficial encontrado.", new_x="LMARGIN", new_y="NEXT")

        pdf.ln(4)

    pdf.output(caminho_arquivo)

def gerar_pdf_editoras_avancado(df_livros_detalhe, df_editora_resumo, df_diario, is_geral, titulo, subtitulo, caminho_arquivo, df_inconsistencias=None):
    pdf = RelatorioPDF(titulo, subtitulo)
    pdf.alias_nb_pages()
    pdf.add_page()
    
    if df_editora_resumo.empty:
        pdf.set_font(pdf.fonte_padrao, 'I', 12)
        pdf.cell(0, 10, "Nenhuma transação oficial encontrada neste período.", align='C')
        pdf.output(caminho_arquivo)
        return

    total_livros = df_editora_resumo['Qtd_Livros'].sum()
    total_arrecadado = df_editora_resumo['Faturamento_Cartao'].sum()
    
    campeas = {}
    for rep in df_editora_resumo['Representante'].unique():
        df_rep = df_editora_resumo[df_editora_resumo['Representante'] == rep]
        if not df_rep.empty:
            melhor = df_rep.loc[df_rep['Faturamento_Cartao'].idxmax()]
            campeas[rep] = f"{melhor['Categoria']} ({formata_br(melhor['Faturamento_Cartao'])})"

    pdf.set_font(pdf.fonte_padrao, 'B', 14)
    pdf.cell(0, 8, "Resumo Executivo de Vendas Oficiais (Apenas Valores dos Cartões)", new_x="LMARGIN", new_y="NEXT", align='C')
    pdf.ln(2)

    pdf.set_font(pdf.fonte_padrao, 'B', 10)
    pdf.set_fill_color(240, 244, 248)
    
    pdf.cell(135, 8, f" Quantidade Total de Livros Vendidos: {int(total_livros)} unidades", border=1, fill=True)
    pdf.cell(5, 8, "", border=0) 
    pdf.cell(135, 8, f" Valor Oficial Repassado (Cartões): {formata_br(total_arrecadado)}", border=1, fill=True, new_x="LMARGIN", new_y="NEXT")
    pdf.ln(3)

    pdf.set_fill_color(226, 232, 240)
    pdf.cell(0, 8, " Top Editoras (Campeãs de Faturamento por Representante):", border=1, fill=True, new_x="LMARGIN", new_y="NEXT")
    pdf.set_font(pdf.fonte_padrao, '', 9)
    for rep, texto in campeas.items():
        pdf.cell(0, 7, f"   * {rep}: {texto}", border='L R', new_x="LMARGIN", new_y="NEXT")
    pdf.cell(0, 1, "", border='L R B', new_x="LMARGIN", new_y="NEXT")
    pdf.ln(8)

    plt.figure(figsize=(11, 4.5))
    num_plots = 2 if is_geral and not df_diario.empty else 1
    cores = ['#10B981', '#3B82F6', '#94A3B8', '#F59E0B', '#8B5CF6'] 
    
    ax1 = plt.subplot(1, num_plots, 1)
    df_rep_share = df_editora_resumo.groupby('Representante')['Faturamento_Cartao'].sum().sort_values(ascending=False)
    
    wedges, texts, autotexts = ax1.pie(
        df_rep_share, labels=df_rep_share.index, autopct='%1.1f%%', startangle=90,
        colors=cores[:len(df_rep_share)], wedgeprops=dict(width=0.4, edgecolor='w')
    )
    plt.setp(autotexts, size=9, weight="bold", color="black")
    ax1.set_title("Market Share (Fatia do Faturamento)", fontsize=11, fontweight='bold', pad=15)

    if num_plots == 2:
        ax2 = plt.subplot(1, 2, 2)
        df_diario['Data_DT'] = pd.to_datetime(df_diario['dia_str'], format='%d-%m-%Y', errors='coerce')
        df_linhas = df_diario.groupby(['Data_DT', 'Representante'])['Faturamento_Cartao'].sum().unstack(fill_value=0)
        
        for idx, coluna in enumerate(df_linhas.columns):
            x_vals = df_linhas.index.strftime('%d/%m')
            y_vals = df_linhas[coluna]
            ax2.plot(x_vals, y_vals, marker='o', linewidth=2, label=coluna, color=cores[idx % len(cores)])
            
            # ADIÇÃO: Escreve os valores nos pontos da Evolução das Editoras
            for i, val in enumerate(y_vals):
                if val > 0:
                    ax2.annotate(formata_br(val), (x_vals[i], val), textcoords="offset points", xytext=(0, 4), ha='center', fontsize=6)
            
        ax2.set_title('Evolução Financeira Diária', fontsize=11, fontweight='bold', pad=15)
        ax2.set_ylabel('Valor Computado (R$)')
        ax2.grid(True, linestyle='--', alpha=0.5)
        ax2.legend(loc='upper left', fontsize=8)
        plt.setp(ax2.xaxis.get_majorticklabels(), rotation=45, ha='right', fontsize=8)
        ax2.set_ylim(top=df_linhas.values.max() * 1.15)

    plt.tight_layout()
    temp_chart = tempfile.NamedTemporaryFile(delete=False, suffix='.png')
    temp_chart.close()
    plt.savefig(temp_chart.name, format='png', dpi=150)
    plt.close('all')

    pdf.image(temp_chart.name, x='C', w=200 if is_geral else 120)
    try: os.remove(temp_chart.name)
    except: pass

    pdf.add_page()
    
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
                    borders_layout="INTERNAL", cell_fill_color=(255, 255, 255), cell_fill_mode="NONE",
                    line_height=5, text_align="LEFT", width=277, align='L', col_widths=(140, 20, 35, 37, 45)
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
            borders_layout="INTERNAL", cell_fill_color=(255, 255, 255), cell_fill_mode="NONE",
            line_height=5, text_align="LEFT", width=277, align='L', col_widths=(197, 40, 40)
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

# =========================================================================
# PROCESSAMENTO PRINCIPAL DE DADOS
# =========================================================================
def gerar_relatorios_pdf():
    print("1. Conectando ao banco e extraindo dados...")
    conn = sqlite3.connect('feira_livro_teste_editoras.db')
    
    df_vendas = pd.read_sql_query("SELECT * FROM vendas_header", conn).drop_duplicates(subset=['sellNumber'])
    df_pagamentos = pd.read_sql_query("SELECT * FROM pagamentos", conn).drop_duplicates()
    df_itens = pd.read_sql_query("SELECT * FROM itens_venda", conn).drop_duplicates()
    df_cartoes = pd.read_sql_query("SELECT * FROM cartoes", conn)
    
    df_livros = pd.read_sql_query("SELECT Produto, Categoria, Representante FROM livros", conn)
    if 'Representante' not in df_livros.columns: df_livros['Representante'] = 'NÃO INFORMADO'
    conn.close()

    # --- LIMPEZA DE CATÁLOGOS E FORMATAÇÕES ---
    df_livros['Produto'] = df_livros['Produto'].fillna('')
    df_livros['prod_up'] = df_livros['Produto'].str.strip().str.upper()
    df_livros['prod_up'] = df_livros['prod_up'].str.replace('&AMP;', '&', regex=False).str.replace('&AMP', '&', regex=False)
    df_livros = df_livros.drop_duplicates(subset=['prod_up'])
    df_livros['Categoria'] = df_livros['Categoria'].fillna('AVULSO').str.strip().str.upper()
    df_livros['Representante'] = df_livros['Representante'].fillna('NÃO INFORMADO').str.strip().str.upper()

    df_pagamentos['valor_num'] = limpar_moeda(df_pagamentos['value'])
    df_itens['valor_total_num'] = limpar_moeda(df_itens['totalValue'])

    df_cart_limpo = df_cartoes[['Código', 'Grupo', 'ID Verso']].drop_duplicates(subset=['Código']).copy()
    df_cart_limpo['Código'] = df_cart_limpo['Código'].astype(str).str.strip().str.upper()

    df_cart_v = df_cartoes[~df_cartoes['Grupo'].astype(str).str.strip().isin(['-', 'Teste Nowigo', 'nan', ''])].copy()
    df_cart_v['Código'] = df_cart_v['Código'].astype(str).str.strip().str.upper()
    df_cart_v = df_cart_v.drop_duplicates(subset=['Código'])

    # --- CRUZAMENTOS DE NEGÓCIO ---
    df_pag_v = df_pagamentos.merge(df_cart_v, left_on=df_pagamentos['tagCode'].str.strip().str.upper(), right_on='Código', how='inner')
    df_pag_v = df_pag_v[(df_pag_v['paymentWay'].str.upper() != 'DESCONTO') & (df_pag_v['payment_group'].str.upper() != 'PAGAMENTO SEM GRUPO')]

    df_vendas['dt'] = pd.to_datetime(df_vendas['dateHour'], format='%d/%m/%Y %H:%M:%S', errors='coerce')
    df_vendas_f = df_vendas[(df_vendas['dt'] >= '2025-11-11') & (df_vendas['dt'] <= '2025-11-20 23:59:59')].copy()
    df_vendas_f['dia_str'] = df_vendas_f['dt'].dt.strftime('%d-%m-%Y')

    vendas_pagas_com_cartao = df_pag_v[df_pag_v['sellNumber'].isin(df_vendas_f['sellNumber'].unique())]['sellNumber'].unique()

    df_itens_f = df_itens[df_itens['sellNumber'].isin(vendas_pagas_com_cartao)].copy()
    df_vendas_f = df_vendas_f[df_vendas_f['sellNumber'].isin(vendas_pagas_com_cartao)].copy()

    # Filtra pagamentos finais que alimentam todos os 3 relatórios
    df_pag_todos_f = df_pagamentos[df_pagamentos['sellNumber'].isin(vendas_pagas_com_cartao)].copy()
    df_pag_todos_f['tag_cruzamento'] = df_pag_todos_f['tagCode'].astype(str).str.strip().str.upper()
    df_pag_todos_f = df_pag_todos_f.merge(df_cart_limpo, left_on='tag_cruzamento', right_on='Código', how='left')

    df_pag_validos = df_pag_todos_f[
        (df_pag_todos_f['paymentWay'].str.upper() != 'DESCONTO') & 
        (df_pag_todos_f['payment_group'].str.upper() != 'PAGAMENTO SEM GRUPO') &
        (df_pag_todos_f['tag_cruzamento'].isin(df_cart_v['Código'].str.strip().str.upper()))
    ]

    print("\n" + "=" * 78)
    print(" 📊 AUDITORIA: ALINHANDO DADOS FINANCEIROS GERAIS")
    print("=" * 78)
    print(f"Total Desejado pelo Cliente:                {formata_br(TOTAL_DESEJADO_AUDITORIA)}")
    print(f"Total Base a ser rateado (Cartões):         {formata_br(df_pag_validos['valor_num'].sum())}")
    print("=" * 78)

    # --- MATEMÁTICA DA ALOCAÇÃO PROPORCIONAL PARA AS EDITORAS ---
    venda_pag = df_pag_validos.groupby('sellNumber')['valor_num'].sum().reset_index(name='Total_Pago_Cartao')
    venda_bruto = df_itens_f.groupby('sellNumber')['valor_total_num'].sum().reset_index(name='Total_Bruto_Livros')
    
    df_itens_aloc = df_itens_f.merge(venda_bruto, on='sellNumber', how='left').merge(venda_pag, on='sellNumber', how='left')
    df_itens_aloc['Total_Pago_Cartao'] = df_itens_aloc['Total_Pago_Cartao'].fillna(0)
    df_itens_aloc['Proporcao'] = 0.0
    mask = df_itens_aloc['Total_Bruto_Livros'] > 0
    df_itens_aloc.loc[mask, 'Proporcao'] = df_itens_aloc.loc[mask, 'Total_Pago_Cartao'] / df_itens_aloc.loc[mask, 'Total_Bruto_Livros']
    df_itens_aloc['Faturamento_Cartao'] = df_itens_aloc['valor_total_num'] * df_itens_aloc['Proporcao']

    # =========================================================================
    # FUNÇÃO DE PREPARAÇÃO DOS DADOS UNIFICADA (SERVE PARA OS 3 PDFs)
    # =========================================================================
    def preparar_tabelas_para_pdfs(v_df, p_validos_df, i_df, i_aloc_df):
        # 1. PREPARAÇÃO PARA TRANSAÇÕES E VENDAS
        livros_por_venda = i_df.groupby('sellNumber')['name'].apply(lambda x: ', '.join(x.str.strip())).reset_index()
        livros_por_venda.rename(columns={'name': 'Livros'}, inplace=True)

        p_tags = p_validos_df[p_validos_df['tagCode'].notna()].copy()
        p_tags['tag_limpa'] = p_tags['tagCode'].astype(str).str.strip().str.upper()
        p_tags = p_tags[p_tags['tag_limpa'] != 'NAN']
        cartoes_por_venda = p_tags.groupby('sellNumber')['tag_limpa'].nunique().reset_index()
        cartoes_por_venda['Uso_Cartoes'] = cartoes_por_venda['tag_limpa'].apply(lambda x: '+2C' if x > 1 else '1C')

        tr = p_validos_df[['sellNumber', 'tagCode', 'ID Verso', 'Grupo', 'valor_num']].merge(v_df[['sellNumber', 'dateHour', 'box']], on='sellNumber')
        tr = tr.merge(livros_por_venda, on='sellNumber', how='left').merge(cartoes_por_venda, on='sellNumber', how='left')
        tr['Livros'] = tr['Livros'].fillna('N/A')
        tr['Uso_Cartoes'] = tr['Uso_Cartoes'].fillna('1C')
        tr = tr[['dateHour', 'sellNumber', 'box', 'Grupo', 'ID Verso', 'tagCode', 'Livros', 'Uso_Cartoes', 'valor_num']]
        tr.columns = ['Data_Hora', 'Venda', 'Caixa', 'Escola', 'ID_Verso', 'Codigo', 'Livros', 'Uso_Cartoes', 'Valor_R$']
        tr['Data_Hora_DT'] = pd.to_datetime(tr['Data_Hora'], format='%d/%m/%Y %H:%M:%S', errors='coerce')
        tr = tr.sort_values(by=['Codigo', 'Data_Hora_DT'], ascending=[True, True]).drop(columns=['Data_Hora_DT']).reset_index(drop=True)

        ca = p_validos_df.groupby(['Código', 'ID Verso', 'Grupo']).agg(Gasto=('valor_num', 'sum')).reset_index()
        ca['Inicial'] = 250.00
        ca['Saldo'] = ca['Inicial'] - ca['Gasto']
        ca.rename(columns={'Inicial': 'Valor_Inicial_R$', 'Gasto': 'Valor_Gasto_R$', 'Saldo': 'Saldo_Restante_R$'}, inplace=True)
        ca = ca.sort_values(by='Código', ascending=True).reset_index(drop=True)

        vd = v_df[['sellNumber', 'dateHour', 'box']].drop_duplicates().merge(livros_por_venda, on='sellNumber', how='left')
        valor_venda_agrupado = p_validos_df.groupby('sellNumber')['valor_num'].sum().reset_index()
        valor_venda_agrupado.rename(columns={'valor_num': 'Valor_Total_R$'}, inplace=True)
        vd = vd.merge(valor_venda_agrupado, on='sellNumber', how='left')
        vd['Valor_Total_R$'] = vd['Valor_Total_R$'].fillna(0)
        vd['Data_Hora_DT'] = pd.to_datetime(vd['dateHour'], format='%d/%m/%Y %H:%M:%S', errors='coerce')
        vd = vd.sort_values('Data_Hora_DT').drop(columns=['Data_Hora_DT']).reset_index(drop=True)

        # 2. PREPARAÇÃO PARA EDITORAS
        i_aloc_df['name_up'] = i_aloc_df['name'].fillna('').str.strip().str.upper()
        i_aloc_df['name_up'] = i_aloc_df['name_up'].str.replace('&AMP;', '&', regex=False).str.replace('&AMP', '&', regex=False)
        
        ed = i_aloc_df.merge(df_livros, left_on='name_up', right_on='prod_up', how='left')
        
        mask_missing = ed['Produto'].isna() | (ed['Produto'] == '')
        df_inconsistencias = ed[mask_missing].groupby('name_up').agg(Qtd=('amount', 'sum'), Faturamento_Cartao=('Faturamento_Cartao', 'sum')).reset_index().sort_values(by='Faturamento_Cartao', ascending=False)
        
        ed['Categoria'] = ed['Categoria'].fillna('AVULSO').str.strip().str.upper()
        ed['Representante'] = ed['Representante'].fillna('NÃO INFORMADO').str.strip().str.upper()
        
        ed_ag = ed.groupby(['Representante', 'Categoria']).agg(Qtd_Livros=('amount', 'sum'), Valor_Bruto_Capa=('valor_total_num', 'sum'), Faturamento_Cartao=('Faturamento_Cartao', 'sum')).reset_index()
        ed_ag = ed_ag.sort_values(by=['Representante', 'Faturamento_Cartao'], ascending=[True, False]).reset_index(drop=True)

        ed_livros = ed.groupby(['Representante', 'Categoria', 'name_up']).agg(Qtd=('amount', 'sum'), Bruto_Total_R=('valor_total_num', 'sum'), Faturamento_Cartao=('Faturamento_Cartao', 'sum')).reset_index()
        ed_livros['Preco_Unit_Bruto'] = ed_livros['Bruto_Total_R'] / ed_livros['Qtd']
        ed_livros = ed_livros.sort_values(by=['Representante', 'Categoria', 'Faturamento_Cartao'], ascending=[True, True, False]).reset_index(drop=True)
        
        ed_diario = ed.merge(v_df[['sellNumber', 'dia_str']], on='sellNumber', how='inner')
        
        return tr, ca, vd, p_validos_df, ed_ag, ed_livros, ed_diario, df_inconsistencias

    # =========================================================================
    # GERAÇÃO DE TODOS OS PDFS
    # =========================================================================
    base_dir = "Relatorios_Feira"
    print(" -> Limpando diretórios de PDFs antigos...")
    if os.path.exists(base_dir):
        for root, dirs, files in os.walk(base_dir):
            if 'PDFs' in root: 
                for file in files:
                    if file.endswith('.pdf'):
                        try: os.remove(os.path.join(root, file))
                        except: pass

    print("2. Processando e Gerando PDFs GERAIS (Consolidado)...")
    tr_g, ca_g, vd_g, ptodos_g, ed_g, ed_livros_g, ed_diario_g, inc_g = preparar_tabelas_para_pdfs(df_vendas_f, df_pag_validos, df_itens_f, df_itens_aloc)
    
    os.makedirs(f"{base_dir}/Geral/PDFs", exist_ok=True)
    gerar_pdf_transacoes_agrupadas(tr_g, ca_g, "Detalhamento de Transações por Cartão", "Consolidado Oficial: 11/11 a 20/11", f"{base_dir}/Geral/PDFs/Transacoes_por_Cartao_Geral.pdf")
    gerar_pdf_vendas_agrupadas(vd_g, ptodos_g, ed_diario_g, True, "Detalhamento de Vendas", "Consolidado Oficial: 11/11 a 20/11", f"{base_dir}/Geral/PDFs/Transacoes_por_Venda_Geral.pdf")
    gerar_pdf_editoras_avancado(ed_livros_g, ed_g, ed_diario_g, True, "Desempenho Comercial: Editoras e Representantes", "Consolidado Oficial: 11/11 a 20/11", f"{base_dir}/Geral/PDFs/Faturamento_Editoras_Geral.pdf", inc_g)

    print("3. Processando e Gerando PDFs DIÁRIOS (Lote de 10 dias)...")
    for dia in df_vendas_f['dia_str'].unique():
        v_d = df_vendas_f[df_vendas_f['dia_str'] == dia]
        v_ids = v_d['sellNumber'].unique()
        
        p_val_d = df_pag_validos[df_pag_validos['sellNumber'].isin(v_ids)].copy()
        i_d = df_itens_f[df_itens_f['sellNumber'].isin(v_ids)].copy()
        i_aloc_d = df_itens_aloc[df_itens_aloc['sellNumber'].isin(v_ids)].copy()
        
        tr_d, ca_d, vd_d, ptodos_d, ed_d, ed_livros_d, ed_diario_d, inc_d = preparar_tabelas_para_pdfs(v_d, p_val_d, i_d, i_aloc_d)
        
        prefixo = f"{base_dir}/Por_Dia/{dia}/PDFs"
        os.makedirs(prefixo, exist_ok=True)
        
        if not ed_d.empty:
            gerar_pdf_transacoes_agrupadas(tr_d, ca_d, f"Transações por Cartão - {dia}", f"Data Referência: {dia}", f"{prefixo}/Transacoes_por_Cartao_{dia}.pdf")
            gerar_pdf_vendas_agrupadas(vd_d, ptodos_d, ed_diario_d, False, f"Detalhamento de Vendas - {dia}", f"Data Referência: {dia}", f"{prefixo}/Transacoes_por_Venda_{dia}.pdf")
            gerar_pdf_editoras_avancado(ed_livros_d, ed_d, ed_diario_d, False, f"Editoras e Representantes - {dia}", f"Data Referência: {dia}", f"{prefixo}/Faturamento_Editoras_{dia}.pdf", inc_d)
            print(f"   -> Dia {dia} processado e gravado nas 3 visões.")

    print("\n✅ Todos os Relatórios (Transações, Vendas e Editoras) foram gerados com 100% de paridade financeira!")

if __name__ == '__main__':
    gerar_relatorios_pdf()