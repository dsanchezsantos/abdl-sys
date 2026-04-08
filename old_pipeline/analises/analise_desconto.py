import pandas as pd
import sqlite3

def auditoria_profunda_descontos():
    conn = sqlite3.connect('feira_livro.db')
    df_vendas = pd.read_sql_query("SELECT sellNumber, dateHour, box FROM vendas_header", conn)
    df_pagamentos = pd.read_sql_query("SELECT * FROM pagamentos", conn)
    conn.close()

    # 1. Limpeza e Formatação
    df_pagamentos['valor_num'] = (
        df_pagamentos['value'].fillna('0')
        .astype(str).str.replace('R$', '', regex=False)
        .str.replace(' ', '', regex=False)
        .str.replace('.', '', regex=False)
        .str.replace(',', '.', regex=False)
        .astype(float)
    )
    
    # Converter datas para filtragem
    df_vendas['dt'] = pd.to_datetime(df_vendas['dateHour'], format='%d/%m/%Y %H:%M:%S', errors='coerce')
    df_vendas_feira = df_vendas[(df_vendas['dt'] >= '2025-11-11') & (df_vendas['dt'] <= '2025-11-20 23:59:59')].copy()
    df_vendas_feira['data_dia'] = df_vendas_feira['dt'].dt.strftime('%d/%m/%Y')

    # 2. Identificar Vendas que possuem Desconto
    pags_desconto = df_pagamentos[df_pagamentos['paymentWay'].astype(str).str.strip().str.lower() == 'desconto'].copy()
    vendas_com_desconto_ids = pags_desconto['sellNumber'].unique()

    # 3. Filtrar apenas as vendas de desconto que ocorreram DENTRO da feira
    vendas_desconto_validas = df_vendas_feira[df_vendas_feira['sellNumber'].isin(vendas_com_desconto_ids)]
    
    if vendas_desconto_validas.empty:
        print("\n✅ NENHUM DESCONTO identificado no período oficial da feira (11/11 a 20/11).")
        return

    # 4. Cruzamento para análise
    # Pegamos todos os pagamentos das vendas que tiveram desconto para saber quem eram as escolas
    df_detalhe_descontos = df_pagamentos[df_pagamentos['sellNumber'].isin(vendas_desconto_validas['sellNumber'])]
    df_final = df_detalhe_descontos.merge(vendas_desconto_validas, on='sellNumber', how='inner')

    def formata_br(valor):
        return f"R$ {valor:,.2f}".replace(',', '_').replace('.', ',').replace('_', '.')

    print("\n" + "="*90)
    print("🔍 RELATÓRIO DE AUDITORIA: RADIOGRAFIA DOS DESCONTOS (11/11 a 20/11)")
    print("="*90)

    # --- ANÁLISE POR DIA ---
    print("\n📅 1. DISTRIBUIÇÃO POR DATA:")
    resumo_dia = df_final[df_final['paymentWay'].str.lower() == 'desconto'].groupby('data_dia').agg(
        Qtd_Descontos=('id', 'count'),
        Total_Desconto=('valor_num', 'sum')
    ).reset_index()
    for _, r in resumo_dia.iterrows():
        print(f"   Data: {r['data_dia']} | Qtd: {r['Qtd_Descontos']:<3} | Total: {formata_br(r['Total_Desconto'])}")

    # --- ANÁLISE POR CAIXA ---
    print("\n🏪 2. TOP CAIXAS (ONDE O DESCONTO FOI MAIS APLICADO):")
    resumo_caixa = df_final[df_final['paymentWay'].str.lower() == 'desconto'].groupby('box').agg(
        Qtd_Descontos=('id', 'count'),
        Total_Desconto=('valor_num', 'sum')
    ).reset_index().sort_values(by='Total_Desconto', ascending=False)
    for _, r in resumo_caixa.head(10).iterrows():
        print(f"   Caixa: {r['box']:<15} | Qtd: {r['Qtd_Descontos']:<3} | Total: {formata_br(r['Total_Desconto'])}")

    # --- ANÁLISE POR ESCOLA (QUEM ESTAVA NA VENDA) ---
    print("\n🎓 3. UNIDADES ESCOLARES ENVOLVIDAS NAS VENDAS COM DESCONTO:")
    # Aqui listamos as escolas que estavam presentes em vendas que geraram desconto
    escolas_beneficiadas = df_final[df_final['paymentWay'].str.lower() != 'desconto'].groupby('payment_group').agg(
        Vendas_Afetadas=('sellNumber', 'nunique'),
        Valor_Pago_pela_Escola=('valor_num', 'sum')
    ).reset_index().sort_values(by='Vendas_Afetadas', ascending=False)
    
    for _, r in escolas_beneficiadas.iterrows():
        nome = str(r['payment_group'])[:50]
        print(f"   {nome:<50} | Vendas: {r['Vendas_Afetadas']:<3}")

    # --- RESUMO FINAL ---
    total_desc = df_final[df_final['paymentWay'].str.lower() == 'desconto']['valor_num'].sum()
    print("\n" + "-"*90)
    print(f"VALOR TOTAL DE DESCONTOS IDENTIFICADOS NA FEIRA: {formata_br(total_desc)}")
    print("="*90 + "\n")

if __name__ == '__main__':
    auditoria_profunda_descontos()