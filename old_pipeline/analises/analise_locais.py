import pandas as pd
import sqlite3

def formata_br(valor):
    return f"R$ {valor:,.2f}".replace(',', '_').replace('.', ',').replace('_', '.')

def limpar_moeda(serie):
    return serie.fillna('0').astype(str).str.replace('R$', '', regex=False).str.replace(' ', '', regex=False).str.replace('.', '', regex=False).str.replace(',', '.', regex=False).astype(float)

def analisar_artistas_locais():
    print("1. Conectando ao banco e extraindo dados...")
    conn = sqlite3.connect('feira_livro_teste.db') # Ajuste para feira_livro.db em produção
    
    df_vendas = pd.read_sql_query("SELECT * FROM vendas_header", conn)
    df_pagamentos = pd.read_sql_query("SELECT * FROM pagamentos", conn)
    df_itens = pd.read_sql_query("SELECT * FROM itens_venda", conn)
    df_cartoes = pd.read_sql_query("SELECT * FROM cartoes", conn)
    df_livros = pd.read_sql_query("SELECT Produto, Categoria FROM livros", conn)
    conn.close()

    print("2. Aplicando limpezas e regras de negócio oficiais...")
    # Limpezas básicas
    df_vendas = df_vendas.drop_duplicates(subset=['sellNumber'])
    df_pagamentos = df_pagamentos.drop_duplicates()
    df_itens = df_itens.drop_duplicates()

    # Tratamento de Livros (removendo duplicatas e corrigindo o &AMP)
    df_livros['Produto'] = df_livros['Produto'].fillna('')
    df_livros['prod_up'] = df_livros['Produto'].str.strip().str.upper()
    df_livros['prod_up'] = df_livros['prod_up'].str.replace('&AMP;', '&', regex=False).str.replace('&AMP', '&', regex=False)
    df_livros = df_livros.drop_duplicates(subset=['prod_up'])
    df_livros['Categoria'] = df_livros['Categoria'].fillna('AVULSO').str.strip().str.upper()

    # Limpeza de moedas
    df_pagamentos['valor_num'] = limpar_moeda(df_pagamentos['value'])
    df_itens['valor_total_num'] = limpar_moeda(df_itens['totalValue'])

    # Filtro de Cartões
    df_cart_limpo = df_cartoes[['Código', 'Grupo']].drop_duplicates(subset=['Código']).copy()
    df_cart_limpo['Código'] = df_cart_limpo['Código'].astype(str).str.strip().str.upper()
    grupos_invalidos = ['-', 'Teste Nowigo', 'nan', '']
    df_cart_v = df_cartoes[~df_cartoes['Grupo'].astype(str).str.strip().isin(grupos_invalidos)].copy()
    df_cart_v['Código'] = df_cart_v['Código'].astype(str).str.strip().str.upper()
    df_cart_v = df_cart_v.drop_duplicates(subset=['Código'])

    # Cruzamento Pagamentos x Cartões
    df_pag_v = df_pagamentos.merge(df_cart_v, left_on=df_pagamentos['tagCode'].str.strip().str.upper(), right_on='Código', how='inner')
    df_pag_v = df_pag_v[(df_pag_v['paymentWay'].str.upper() != 'DESCONTO') & (df_pag_v['payment_group'].str.upper() != 'PAGAMENTO SEM GRUPO')]

    # Filtro de Data
    df_vendas['dt'] = pd.to_datetime(df_vendas['dateHour'], format='%d/%m/%Y %H:%M:%S', errors='coerce')
    df_vendas_f = df_vendas[(df_vendas['dt'] >= '2025-11-11') & (df_vendas['dt'] <= '2025-11-20 23:59:59')]
    vendas_ids_data = df_vendas_f['sellNumber'].unique()

    # Vendas válidas
    df_pag_f = df_pag_v[df_pag_v['sellNumber'].isin(vendas_ids_data)]
    vendas_pagas_com_cartao = df_pag_f['sellNumber'].unique()

    # Itens e Pagamentos Finais
    df_itens_f = df_itens[df_itens['sellNumber'].isin(vendas_pagas_com_cartao)].copy()
    df_pag_todos_f = df_pagamentos[df_pagamentos['sellNumber'].isin(vendas_pagas_com_cartao)].copy()
    df_pag_todos_f['tag_cruzamento'] = df_pag_todos_f['tagCode'].astype(str).str.strip().str.upper()
    
    df_pag_validos = df_pag_todos_f[
        (df_pag_todos_f['paymentWay'].str.upper() != 'DESCONTO') & 
        (df_pag_todos_f['payment_group'].str.upper() != 'PAGAMENTO SEM GRUPO') &
        (df_pag_todos_f['tag_cruzamento'].isin(df_cart_v['Código']))
    ]

    print("3. Calculando Alocação Proporcional Financeira...")
    # Alocação Proporcional
    venda_pag = df_pag_validos.groupby('sellNumber')['valor_num'].sum().reset_index(name='Total_Pago_Cartao')
    venda_bruto = df_itens_f.groupby('sellNumber')['valor_total_num'].sum().reset_index(name='Total_Bruto_Livros')
    df_itens_aloc = df_itens_f.merge(venda_bruto, on='sellNumber', how='left').merge(venda_pag, on='sellNumber', how='left')
    df_itens_aloc['Total_Pago_Cartao'] = df_itens_aloc['Total_Pago_Cartao'].fillna(0)
    df_itens_aloc['Proporcao'] = 0.0
    mask = df_itens_aloc['Total_Bruto_Livros'] > 0
    df_itens_aloc.loc[mask, 'Proporcao'] = df_itens_aloc.loc[mask, 'Total_Pago_Cartao'] / df_itens_aloc.loc[mask, 'Total_Bruto_Livros']
    df_itens_aloc['Faturamento_Cartao'] = df_itens_aloc['valor_total_num'] * df_itens_aloc['Proporcao']

    # Cruzar com Livros para pegar a Categoria
    df_itens_aloc['name_up'] = df_itens_aloc['name'].fillna('').str.strip().str.upper()
    df_itens_aloc['name_up'] = df_itens_aloc['name_up'].str.replace('&AMP;', '&', regex=False).str.replace('&AMP', '&', regex=False)
    ed = df_itens_aloc.merge(df_livros, left_on='name_up', right_on='prod_up', how='left')
    ed['Categoria'] = ed['Categoria'].fillna('AVULSO').str.strip().str.upper()

    print("4. Consolidando resultados dos Autores Locais...")
    # Filtrar Categorias Locais (Atenção: como forçamos UPPER, a busca é por 'LOCAL -')
    mask_local = ed['Categoria'].str.startswith('LOCAL -')
    df_locais = ed[mask_local]

    total_bruto = df_locais['valor_total_num'].sum()
    total_alocado = df_locais['Faturamento_Cartao'].sum()
    qtd_livros = df_locais['amount'].sum()

    print("\n" + "="*75)
    print(" 🎨 ANÁLISE DE VENDAS: ARTISTAS LOCAIS INDEPENDENTES")
    print("="*75)
    print(f"Quantidade de livros vendidos:       {int(qtd_livros)} unidades")
    print(f"Valor Bruto de Capa (Etiqueta):      {formata_br(total_bruto)}")
    print(f"Valor Oficial Repassado (Cartões):   {formata_br(total_alocado)}")
    print("-" * 75)
    
    # Detalhamento por artista
    print("Detalhamento por Artista (Baseado no Repasse do Cartão):")
    resumo_artistas = df_locais.groupby('Categoria')['Faturamento_Cartao'].sum().sort_values(ascending=False)
    for artista, valor in resumo_artistas.items():
        print(f"  - {artista:<40}: {formata_br(valor)}")
    print("="*75 + "\n")

if __name__ == '__main__':
    analisar_artistas_locais()