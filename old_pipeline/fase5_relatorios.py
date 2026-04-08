import pandas as pd
import sqlite3
import os

def gerar_relatorios_planilhas():
    print("1. Lendo dados do banco SQLite...")
    conn = sqlite3.connect('feira_livro.db')
    df_vendas = pd.read_sql_query("SELECT * FROM vendas_header", conn)
    df_pagamentos = pd.read_sql_query("SELECT * FROM pagamentos", conn)
    df_itens = pd.read_sql_query("SELECT * FROM itens_venda", conn)
    df_cartoes = pd.read_sql_query("SELECT * FROM cartoes", conn)
    df_livros = pd.read_sql_query("SELECT * FROM livros", conn)
    conn.close()

    print("2. Formatando valores financeiros...")
    def limpar_moeda(serie):
        return serie.fillna('0').astype(str).str.replace('R$', '', regex=False)\
                                .str.replace(' ', '', regex=False)\
                                .str.replace('.', '', regex=False)\
                                .str.replace(',', '.', regex=False)\
                                .astype(float)

    df_pagamentos['valor_num'] = limpar_moeda(df_pagamentos['value'])
    df_itens['valor_total_num'] = limpar_moeda(df_itens['totalValue'])

    print("3. Aplicando filtros de Cartões, Descontos e Grupos...")
    grupos_invalidos = ['-', 'Teste Nowigo', 'nan', '']
    df_cartoes['Grupo_limpo'] = df_cartoes['Grupo'].astype(str).str.strip()
    df_cartoes_validos = df_cartoes[~df_cartoes['Grupo_limpo'].isin(grupos_invalidos)].copy()

    df_pagamentos['tagCode_limpo'] = df_pagamentos['tagCode'].astype(str).str.strip().str.upper()
    df_cartoes_validos['Codigo_limpo'] = df_cartoes_validos['Código'].astype(str).str.strip().str.upper()

    # Cruzamento 1: Apenas cartões válidos
    df_pag_validos = df_pagamentos.merge(df_cartoes_validos, left_on='tagCode_limpo', right_on='Codigo_limpo', how='inner')
    
    # Cruzamento 2 e 3: Sem descontos e sem 'Pagamento sem grupo'
    df_pag_validos['paymentWay_limpo'] = df_pag_validos['paymentWay'].astype(str).str.strip().str.upper()
    df_pag_validos['payment_group_limpo'] = df_pag_validos['payment_group'].astype(str).str.strip().str.upper()
    
    df_pag_validos = df_pag_validos[
        (df_pag_validos['paymentWay_limpo'] != 'DESCONTO') & 
        (df_pag_validos['payment_group_limpo'] != 'PAGAMENTO SEM GRUPO')
    ]

    print("4. Aplicando Filtro de Data Oficial da Feira (11/11 a 20/11)...")
    vendas_ids = df_pag_validos['sellNumber'].unique()
    df_vendas_validas = df_vendas[df_vendas['sellNumber'].isin(vendas_ids)].copy()
    
    df_vendas_validas['data_hora_dt'] = pd.to_datetime(df_vendas_validas['dateHour'], format='%d/%m/%Y %H:%M:%S', errors='coerce')
    df_vendas_validas = df_vendas_validas[
        (df_vendas_validas['data_hora_dt'] >= '2025-11-11') & 
        (df_vendas_validas['data_hora_dt'] <= '2025-11-20 23:59:59')
    ]

    vendas_finais_ids = df_vendas_validas['sellNumber'].unique()
    df_pag_finais = df_pag_validos[df_pag_validos['sellNumber'].isin(vendas_finais_ids)].copy()
    df_itens_finais = df_itens[df_itens['sellNumber'].isin(vendas_finais_ids)].copy()

    df_vendas_validas['data_apenas'] = df_vendas_validas['data_hora_dt'].dt.strftime('%d-%m-%Y')

    print("5. Criando a estrutura de pastas...")
    base_dir = "Relatorios_Feira"
    os.makedirs(f"{base_dir}/Geral/Planilhas", exist_ok=True)
    os.makedirs(f"{base_dir}/Geral/PDFs", exist_ok=True)

    dias_unicos = df_vendas_validas['data_apenas'].dropna().unique()
    for dia in dias_unicos:
        os.makedirs(f"{base_dir}/Por_Dia/{dia}/Planilhas", exist_ok=True)
        os.makedirs(f"{base_dir}/Por_Dia/{dia}/PDFs", exist_ok=True)

    # Função interna corrigida
    def processar_tabelas(vendas, pags, itens):
        # 1. Total por Editora
        itens.loc[:, 'name_limpo'] = itens['name'].astype(str).str.strip().str.upper()
        df_livros.loc[:, 'Produto_limpo'] = df_livros['Produto'].astype(str).str.strip().str.upper()
        
        df_editora = itens.merge(df_livros, left_on='name_limpo', right_on='Produto_limpo', how='left')
        df_editora['Categoria'] = df_editora['Categoria'].fillna('Sem Editora Cadastrada/Avulso')
        
        df_editora_agrup = df_editora.groupby('Categoria').agg(
            Quantidade_Livros_Vendidos=('amount', 'sum'),
            Faturamento_Total=('valor_total_num', 'sum')
        ).reset_index()
        df_editora_agrup.rename(columns={'Faturamento_Total': 'Faturamento_Total_R$'}, inplace=True)

        # 2. Todas as Transações
        itens_agrup = itens.groupby('sellNumber')['name'].apply(lambda x: ' | '.join(x)).reset_index()
        itens_agrup.rename(columns={'name': 'Livros_Comprados'}, inplace=True)
        vendas_unicas = vendas[['sellNumber', 'box', 'dateHour']].drop_duplicates()
        
        df_transacoes = pags[['sellNumber', 'tagCode', 'ID Verso', 'Grupo', 'valor_num']].merge(
            vendas_unicas, on='sellNumber', how='inner'
        ).merge(itens_agrup, on='sellNumber', how='left')
        
        df_transacoes = df_transacoes[['dateHour', 'box', 'Grupo', 'ID Verso', 'tagCode', 'valor_num', 'Livros_Comprados']]
        df_transacoes.columns = ['Data_Hora', 'Caixa', 'Escola', 'Cartao_ID_Verso', 'Cartao_Codigo', 'Valor_Gasto_R$', 'Livros_Comprados']

        # 3. Dados por Cartão
        df_cartoes_agrup = pags.groupby(['Código', 'ID Verso', 'Grupo']).agg(
            Valor_Gasto=('valor_num', 'sum')
        ).reset_index()
        
        df_cartoes_agrup['Valor_Inicial'] = 250.00
        df_cartoes_agrup['Saldo_Restante'] = df_cartoes_agrup['Valor_Inicial'] - df_cartoes_agrup['Valor_Gasto']
        
        df_cartoes_agrup.rename(columns={
            'Valor_Inicial': 'Valor_Inicial_R$',
            'Valor_Gasto': 'Valor_Gasto_R$',
            'Saldo_Restante': 'Saldo_Restante_R$'
        }, inplace=True)
        
        df_cartoes_agrup = df_cartoes_agrup[['Grupo', 'ID Verso', 'Código', 'Valor_Inicial_R$', 'Valor_Gasto_R$', 'Saldo_Restante_R$']]

        return df_editora_agrup, df_transacoes, df_cartoes_agrup

    print("6. Gerando Arquivo Excel GERAL...")
    df_ed_geral, df_tr_geral, df_ca_geral = processar_tabelas(df_vendas_validas, df_pag_finais, df_itens_finais)
    
    with pd.ExcelWriter(f"{base_dir}/Geral/Planilhas/Relatorio_Geral_Feira.xlsx", engine='openpyxl') as writer:
        df_tr_geral.to_excel(writer, sheet_name='Todas_Transacoes', index=False)
        df_ed_geral.to_excel(writer, sheet_name='Total_Por_Editora', index=False)
        df_ca_geral.to_excel(writer, sheet_name='Dados_Por_Cartao', index=False)

    print("7. Gerando Arquivos Excel POR DIA...")
    for dia in dias_unicos:
        vendas_dia = df_vendas_validas[df_vendas_validas['data_apenas'] == dia]
        vendas_dia_ids = vendas_dia['sellNumber'].unique()
        pags_dia = df_pag_finais[df_pag_finais['sellNumber'].isin(vendas_dia_ids)]
        itens_dia = df_itens_finais[df_itens_finais['sellNumber'].isin(vendas_dia_ids)]

        df_ed_dia, df_tr_dia, df_ca_dia = processar_tabelas(vendas_dia, pags_dia, itens_dia)

        caminho_arquivo = f"{base_dir}/Por_Dia/{dia}/Planilhas/Relatorio_{dia}.xlsx"
        with pd.ExcelWriter(caminho_arquivo, engine='openpyxl') as writer:
            df_tr_dia.to_excel(writer, sheet_name='Todas_Transacoes', index=False)
            df_ed_dia.to_excel(writer, sheet_name='Total_Por_Editora', index=False)
            df_ca_dia.to_excel(writer, sheet_name='Dados_Por_Cartao', index=False)
            
        print(f"  -> Concluído: {dia}")

    print("\nPROCESSO FINALIZADO! Verifique a pasta 'Relatorios_Feira' no seu projeto.")

if __name__ == '__main__':
    gerar_relatorios_planilhas()