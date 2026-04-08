import pandas as pd
import sqlite3

def auditar_inflacao():
    conn = sqlite3.connect('feira_livro.db')
    df_vendas = pd.read_sql_query("SELECT * FROM vendas_header", conn)
    df_pag = pd.read_sql_query("SELECT * FROM pagamentos", conn)
    df_itens = pd.read_sql_query("SELECT * FROM itens_venda", conn)
    df_cartoes = pd.read_sql_query("SELECT * FROM cartoes", conn)
    conn.close()

    def limpar_moeda(serie):
        return serie.fillna('0').astype(str).str.replace('R$', '', regex=False).str.replace(' ', '', regex=False).str.replace('.', '', regex=False).str.replace(',', '.', regex=False).astype(float)

    df_pag['valor_num'] = limpar_moeda(df_pag['value'])
    df_itens['valor_total_num'] = limpar_moeda(df_itens['totalValue'])

    def formata_br(valor):
        return f"R$ {valor:,.2f}".replace(',', '_').replace('.', ',').replace('_', '.')

    print("\n" + "="*80)
    print(" 🕵️ DIAGNÓSTICO 1: A PROVA DAS LINHAS DUPLICADAS (EXTRAÇÕES REPETIDAS)")
    print("="*80)
    
    # Conta o tamanho atual vs tamanho sem duplicatas
    vendas_orig = len(df_vendas)
    vendas_unic = len(df_vendas.drop_duplicates(subset=['sellNumber']))
    
    itens_orig = len(df_pag)
    itens_unic = len(df_pag.drop_duplicates())
    
    print(f"Tabela VENDAS_HEADER: {vendas_orig} linhas originais | {vendas_unic} linhas reais ({(vendas_orig - vendas_unic)} duplicadas)")
    print(f"Tabela PAGAMENTOS:    {itens_orig} linhas originais | {itens_unic} linhas reais ({(itens_orig - itens_unic)} duplicadas)")
    
    # Mostra o impacto financeiro disso nos itens
    soma_itens_suja = df_itens['valor_total_num'].sum()
    soma_itens_limpa = df_itens.drop_duplicates()['valor_total_num'].sum()
    
    print(f"\n-> Impacto Financeiro na tabela de Livros (Itens):")
    print(f"   Soma com duplicadas: {formata_br(soma_itens_suja)}")
    print(f"   Soma SEM duplicadas: {formata_br(soma_itens_limpa)}")
    print(f"   Falso faturamento gerado pela sujeira: {formata_br(soma_itens_suja - soma_itens_limpa)}")

    # =========================================================================
    # APLICANDO A LIMPEZA PARA O TESTE 2
    # =========================================================================
    df_vendas = df_vendas.drop_duplicates(subset=['sellNumber'])
    df_pag = df_pag.drop_duplicates()
    df_itens = df_itens.drop_duplicates()

    print("\n" + "="*80)
    print(" 🕵️ DIAGNÓSTICO 2: A PROVA DO PAGAMENTO MISTO (POR QUE EDITORAS SÃO MAIORES?)")
    print("="*80)

    # Filtra cartões válidos
    grupos_invalidos = ['-', 'Teste Nowigo', 'nan', '']
    df_cartoes_v = df_cartoes[~df_cartoes['Grupo'].astype(str).str.strip().isin(grupos_invalidos)].copy()
    
    df_pag['tag_limpo'] = df_pag['tagCode'].astype(str).str.strip().str.upper()
    df_cartoes_v['cod_limpo'] = df_cartoes_v['Código'].astype(str).str.strip().str.upper()

    # Pega só pagamentos da feira (11/11 a 20/11)
    df_vendas['dt'] = pd.to_datetime(df_vendas['dateHour'], format='%d/%m/%Y %H:%M:%S', errors='coerce')
    df_vendas_f = df_vendas[(df_vendas['dt'] >= '2025-11-11') & (df_vendas['dt'] <= '2025-11-20 23:59:59')]
    
    # 1. Identifica os pagamentos com CARTÃO VÁLIDO DENTRO DA FEIRA
    df_pag_v = df_pag.merge(df_cartoes_v, left_on='tag_limpo', right_on='cod_limpo', how='inner')
    df_pag_v = df_pag_v[(df_pag_v['paymentWay'].str.upper() != 'DESCONTO') & (df_pag_v['payment_group'].str.upper() != 'PAGAMENTO SEM GRUPO')]
    df_pag_v = df_pag_v[df_pag_v['sellNumber'].isin(df_vendas_f['sellNumber'])]

    # Total efetivamente descontado dos cartões
    total_cartoes = df_pag_v['valor_num'].sum()

    # 2. Pega os LIVROS referentes a essas MESMAS VENDAS onde o cartão passou
    vendas_com_cartao = df_pag_v['sellNumber'].unique()
    df_itens_das_vendas = df_itens[df_itens['sellNumber'].isin(vendas_com_cartao)]
    total_livros = df_itens_das_vendas['valor_total_num'].sum()

    print(f"Total debitado dos Cartões Cashless: {formata_br(total_cartoes)}")
    print(f"Total Bruto dos Livros levados:      {formata_br(total_livros)}")
    print(f"Diferença não coberta pelo cartão:   {formata_br(total_livros - total_cartoes)}")
    
    print("\n-> O que compõe essa diferença? (Outros pagamentos nas mesmas vendas):")
    
    # 3. Pega TODOS os pagamentos dessas mesmas vendas para ver de onde veio o resto do dinheiro
    df_pag_das_vendas = df_pag[df_pag['sellNumber'].isin(vendas_com_cartao)]
    
    # Agrupa por forma de pagamento
    outros_pags = df_pag_das_vendas.groupby('paymentWay')['valor_num'].sum().reset_index()
    for _, row in outros_pags.iterrows():
        print(f"   {str(row['paymentWay']):<20}: {formata_br(row['valor_num'])}")

    print("\n" + "="*80)
    print("CONCLUSÃO MATEMÁTICA:")
    print("A diferença entre o Relatório de Editoras e o de Cartões ocorre porque, em algumas")
    print("vendas, o saldo do cartão não era suficiente, e o aluno completou o valor do")
    print("livro usando PIX, Dinheiro, Cartão de Crédito, ou recebeu Desconto no caixa.")
    print("O livro inteiro vai para a conta da Editora, mas só a fatia do Cashless")
    print("vai para o relatório de Transações do Cartão.")
    print("="*80 + "\n")

if __name__ == '__main__':
    auditar_inflacao()