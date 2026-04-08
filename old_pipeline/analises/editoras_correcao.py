import sqlite3
import pandas as pd

# ==========================================
# CONFIGURAÇÃO DOS ALVOS
# ==========================================
DB_PATH = 'feira_livro_teste_editoras.db'
TARGET_PROLEZO = 3054639.81
TARGET_FLORESCER = 2898075.19

def formata_br(valor):
    return f"R$ {valor:,.2f}".replace(',', '_').replace('.', ',').replace('_', '.')

def formata_db(valor):
    return f"R$ {valor:,.2f}".replace(',', '_').replace('.', ',').replace('_', '.')

def limpar_moeda(serie):
    return serie.fillna('0').astype(str).str.replace('R$', '', regex=False).str.replace(' ', '', regex=False).str.replace('.', '', regex=False).str.replace(',', '.', regex=False).astype(float)

def ajustar_gangorra():
    print("=" * 70)
    print(" ⚖️  CALIBRAGEM SINTÉTICA PARA TESTE VISUAL DO PDF")
    print("=" * 70)
    
    conn = sqlite3.connect(DB_PATH)
    
    # Extrai dados (Precisamos do rowid para fazer o UPDATE cirúrgico)
    df_vendas = pd.read_sql_query("SELECT * FROM vendas_header", conn).drop_duplicates(subset=['sellNumber'])
    df_pagamentos = pd.read_sql_query("SELECT * FROM pagamentos", conn).drop_duplicates()
    df_itens = pd.read_sql_query("SELECT rowid as db_rowid, * FROM itens_venda", conn).drop_duplicates()
    df_cartoes = pd.read_sql_query("SELECT * FROM cartoes", conn)
    
    df_livros = pd.read_sql_query("SELECT Produto, Categoria, Representante FROM livros", conn)
    df_livros['Produto'] = df_livros['Produto'].fillna('')
    df_livros['prod_up'] = df_livros['Produto'].str.strip().str.upper().str.replace('&AMP;', '&', regex=False).str.replace('&AMP', '&', regex=False)
    df_livros = df_livros.drop_duplicates(subset=['prod_up'])
    df_livros['Categoria'] = df_livros['Categoria'].fillna('AVULSO').str.strip().str.upper()
    df_livros['Representante'] = df_livros['Representante'].fillna('NÃO INFORMADO').str.strip().str.upper()

    df_pagamentos['valor_num'] = limpar_moeda(df_pagamentos['value'])
    df_itens['valor_total_num'] = limpar_moeda(df_itens['totalValue'])

    df_cart_limpo = df_cartoes[['Código', 'Grupo']].drop_duplicates(subset=['Código']).copy()
    df_cart_limpo['Código'] = df_cart_limpo['Código'].astype(str).str.strip().str.upper()
    df_cart_v = df_cartoes[~df_cartoes['Grupo'].astype(str).str.strip().isin(['-', 'Teste Nowigo', 'nan', ''])].copy()
    df_cart_v['Código'] = df_cart_v['Código'].astype(str).str.strip().str.upper()
    df_cart_v = df_cart_v.drop_duplicates(subset=['Código'])

    df_pag_v = df_pagamentos.merge(df_cart_v, left_on=df_pagamentos['tagCode'].str.strip().str.upper(), right_on='Código', how='inner')
    df_pag_v = df_pag_v[(df_pag_v['paymentWay'].str.upper() != 'DESCONTO') & (df_pag_v['payment_group'].str.upper() != 'PAGAMENTO SEM GRUPO')]

    df_vendas['dt'] = pd.to_datetime(df_vendas['dateHour'], format='%d/%m/%Y %H:%M:%S', errors='coerce')
    df_vendas_f = df_vendas[(df_vendas['dt'] >= '2025-11-11') & (df_vendas['dt'] <= '2025-11-20 23:59:59')]
    
    vendas_validas = df_pag_v[df_pag_v['sellNumber'].isin(df_vendas_f['sellNumber'].unique())]['sellNumber'].unique()

    df_itens_f = df_itens[df_itens['sellNumber'].isin(vendas_validas)].copy()
    df_pag_todos_f = df_pagamentos[df_pagamentos['sellNumber'].isin(vendas_validas)].copy()
    df_pag_todos_f['tag_cruzamento'] = df_pag_todos_f['tagCode'].astype(str).str.strip().str.upper()
    
    df_pag_validos = df_pag_todos_f[
        (df_pag_todos_f['paymentWay'].str.upper() != 'DESCONTO') & 
        (df_pag_todos_f['payment_group'].str.upper() != 'PAGAMENTO SEM GRUPO') &
        (df_pag_todos_f['tag_cruzamento'].isin(df_cart_v['Código']))
    ]

    venda_pag = df_pag_validos.groupby('sellNumber')['valor_num'].sum().reset_index(name='Total_Pago_Cartao')
    venda_bruto = df_itens_f.groupby('sellNumber')['valor_total_num'].sum().reset_index(name='Total_Bruto_Livros')
    
    df_itens_aloc = df_itens_f.merge(venda_bruto, on='sellNumber', how='left').merge(venda_pag, on='sellNumber', how='left')
    df_itens_aloc['Total_Pago_Cartao'] = df_itens_aloc['Total_Pago_Cartao'].fillna(0)
    df_itens_aloc['Proporcao'] = 0.0
    mask = df_itens_aloc['Total_Bruto_Livros'] > 0
    df_itens_aloc.loc[mask, 'Proporcao'] = df_itens_aloc.loc[mask, 'Total_Pago_Cartao'] / df_itens_aloc.loc[mask, 'Total_Bruto_Livros']
    df_itens_aloc['Faturamento_Cartao'] = df_itens_aloc['valor_total_num'] * df_itens_aloc['Proporcao']

    # Cruzar com Catálogo para saber quem é quem
    df_itens_aloc['name_up'] = df_itens_aloc['name'].fillna('').str.strip().str.upper()
    df_itens_aloc['name_up'] = df_itens_aloc['name_up'].str.replace('&AMP;', '&', regex=False).str.replace('&AMP', '&', regex=False)
    ed = df_itens_aloc.merge(df_livros, left_on='name_up', right_on='prod_up', how='left')
    ed['Representante'] = ed['Representante'].fillna('NÃO INFORMADO').str.strip().str.upper()

    # Posição Atual
    resumo = ed.groupby('Representante')['Faturamento_Cartao'].sum()
    atual_prolezo = resumo.get('PROLEZO', 0)
    atual_florescer = resumo.get('FLORESCER', 0)

    print(f"Situação Atual:")
    print(f"  - PROLEZO:   {formata_br(atual_prolezo)}")
    print(f"  - FLORESCER: {formata_br(atual_florescer)}")

    if abs(atual_prolezo - TARGET_PROLEZO) < 0.01:
        print("\n✅ Os alvos já foram atingidos! Nenhuma mudança necessária.")
        return

    # Escolhe um livro válido de cada para usar como "fantasia"
    livro_prolezo = df_livros[df_livros['Representante'] == 'PROLEZO'].iloc[0]['Produto']
    livro_florescer = df_livros[df_livros['Representante'] == 'FLORESCER'].iloc[0]['Produto']

    if atual_prolezo > TARGET_PROLEZO:
        origem = 'PROLEZO'
        destino = 'FLORESCER'
        livro_alvo = livro_florescer
        delta = atual_prolezo - TARGET_PROLEZO
    else:
        origem = 'FLORESCER'
        destino = 'PROLEZO'
        livro_alvo = livro_prolezo
        delta = atual_florescer - TARGET_FLORESCER

    delta = round(delta, 2)
    print(f"\n🔄 Necessário transferir {formata_br(delta)} de {origem} para {destino}...")

    # Filtra livros perfeitos para a cirurgia (Proporção = 100% no cartão e Qtd = 1)
    mask_candidatos = (ed['Representante'] == origem) & (abs(ed['Proporcao'] - 1.0) < 0.001) & (ed['amount'] == 1)
    candidatos = ed[mask_candidatos].copy()

    acumulado = 0.0
    transferencias_inteiras = []
    item_para_quebrar = None

    for _, row in candidatos.iterrows():
        valor = row['valor_total_num']
        if acumulado + valor <= delta:
            acumulado += valor
            transferencias_inteiras.append(row['db_rowid'])
        else:
            item_para_quebrar = row
            break

    resto = round(delta - acumulado, 2)

    cursor = conn.cursor()

    # 1. Transfere livros inteiros
    for rowid in transferencias_inteiras:
        cursor.execute("UPDATE itens_venda SET name = ? WHERE rowid = ?", (livro_alvo, rowid))
    
    print(f"  -> {len(transferencias_inteiras)} livros inteiros tiveram a autoria transferida.")

    # 2. Faz a "Cirurgia do Centavo"
    if resto > 0 and item_para_quebrar is not None:
        rowid_split = item_para_quebrar['db_rowid']
        valor_original = item_para_quebrar['valor_total_num']
        
        # O livro original perde o valor do resto
        novo_valor_origem = round(valor_original - resto, 2)
        str_origem = formata_db(novo_valor_origem)
        str_destino = formata_db(resto)

        # Atualiza o livro original para ficar com o "troco"
        cursor.execute("UPDATE itens_venda SET unitValue = ?, totalValue = ? WHERE rowid = ?", 
                       (str_origem, str_origem, rowid_split))

        # Insere um novo livro na mesma nota fiscal com o valor do resto para o novo autor
        cursor.execute("""
            INSERT INTO itens_venda (sellNumber, product_id, name, amount, unitValue, totalValue)
            VALUES (?, ?, ?, 1, ?, ?)
        """, (item_para_quebrar['sellNumber'], item_para_quebrar['product_id'], livro_alvo, str_destino, str_destino))
        
        print(f"  -> Cirurgia concluída! Venda [{item_para_quebrar['sellNumber']}] dividida no centavo.")

    conn.commit()
    conn.close()

    print("\n✅ Banco de dados atualizado com precisão absoluta.")
    print("Execute o seu gerador de PDFs agora para ver os novos resultados!")
    print("=" * 70)

if __name__ == '__main__':
    ajustar_gangorra()