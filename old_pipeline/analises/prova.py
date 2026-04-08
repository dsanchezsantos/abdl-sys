import pandas as pd
import sqlite3

def extrair_provas_reais():
    conn = sqlite3.connect('feira_livro.db')
    df_vendas = pd.read_sql_query("SELECT * FROM vendas_header", conn)
    df_pag = pd.read_sql_query("SELECT * FROM pagamentos", conn)
    df_itens = pd.read_sql_query("SELECT * FROM itens_venda", conn)
    df_cartoes = pd.read_sql_query("SELECT * FROM cartoes", conn)
    conn.close()

    # Limpeza básica (remoção de duplicatas por segurança)
    df_vendas = df_vendas.drop_duplicates(subset=['sellNumber'])
    df_pag = df_pag.drop_duplicates()
    df_itens = df_itens.drop_duplicates()

    df_pag['valor_num'] = df_pag['value'].fillna('0').astype(str).str.replace('R$', '', regex=False).str.replace(' ', '', regex=False).str.replace('.', '', regex=False).str.replace(',', '.', regex=False).astype(float)
    df_itens['valor_total_num'] = df_itens['totalValue'].fillna('0').astype(str).str.replace('R$', '', regex=False).str.replace(' ', '', regex=False).str.replace('.', '', regex=False).str.replace(',', '.', regex=False).astype(float)

    def formata_br(valor):
        return f"R$ {valor:,.2f}".replace(',', '_').replace('.', ',').replace('_', '.')

    print("\n" + "="*80)
    print(" PROVA 1: ALUNOS QUE NÃO GASTARAM TODO O SALDO DO CARTÃO")
    print("="*80)
    print("Controles manuais frequentemente assumem o gasto total ou perdem o rastro de centavos.")
    print("Abaixo estão 5 exemplos REAIS de cartões que deixaram 'troco' na feira:")
    
    # Filtra cartões válidos
    grupos_invalidos = ['-', 'Teste Nowigo', 'nan', '']
    df_cartoes_v = df_cartoes[~df_cartoes['Grupo'].astype(str).str.strip().isin(grupos_invalidos)]
    
    # Cruza pagamentos com cartões
    df_pag['tag_limpo'] = df_pag['tagCode'].astype(str).str.strip().str.upper()
    df_cartoes_v['cod_limpo'] = df_cartoes_v['Código'].astype(str).str.strip().str.upper()
    df_pag_v = df_pag.merge(df_cartoes_v, left_on='tag_limpo', right_on='cod_limpo', how='inner')
    
    # Agrupa gastos por cartão
    gastos_cartao = df_pag_v.groupby(['Código', 'Grupo'])['valor_num'].sum().reset_index()
    cartoes_com_sobra = gastos_cartao[gastos_cartao['valor_num'] < 250.00].head(5)
    
    for _, row in cartoes_com_sobra.iterrows():
        gasto = row['valor_num']
        sobra = 250.00 - gasto
        escola = str(row['Grupo'])[:40]
        print(f"Cartão: {row['Código']} | Gastou: {formata_br(gasto):<10} | Deixou sobrar: {formata_br(sobra):<10} | {escola}")
    
    print("-> A existência desses 'trocos' prova que controles que arredondam falham.")

    print("\n" + "="*80)
    print(" PROVA 2: O PAGAMENTO MISTO (POR QUE EDITORAS FATURARAM MAIS QUE OS CARTÕES)")
    print("="*80)
    print("Buscando uma venda real no banco de dados onde o aluno usou o cartão da prefeitura")
    print("mas precisou completar o valor do livro com outra forma de pagamento (ex: PIX)...\n")

    # Busca vendas que tiveram Cashless E outra forma de pagamento
    vendas_count = df_pag.groupby('sellNumber')['paymentWay'].nunique().reset_index()
    vendas_mistas = vendas_count[vendas_count['paymentWay'] > 1]
    
    # Pega uma venda mista aleatória (Exemplo)
    venda_exemplo = None
    for venda_id in vendas_mistas['sellNumber']:
        pagamentos_desta_venda = df_pag[df_pag['sellNumber'] == venda_id]
        formas_pagamento = pagamentos_desta_venda['paymentWay'].str.upper().tolist()
        
        # Se tem Cashless e alguma outra coisa (Pix, Dinheiro, Cartão), achamos o exemplo
        if any('CASHLESS' in p or 'NOWIGO' in p for p in formas_pagamento) and any('PIX' in p or 'CREDITO' in p or 'DINHEIRO' in p for p in formas_pagamento):
            venda_exemplo = venda_id
            break
            
    if venda_exemplo:
        print(f"EXEMPLO ENCONTRADO: Venda Nº {venda_exemplo}")
        print("-" * 40)
        
        print("1. O QUE FOI LEVADO DA EDITORA:")
        itens_venda = df_itens[df_itens['sellNumber'] == venda_exemplo]
        total_livros = itens_venda['valor_total_num'].sum()
        for _, item in itens_venda.iterrows():
            print(f"   - {item['name'][:40]:<42} | {formata_br(item['valor_total_num'])}")
        print(f"   TOTAL LANÇADO PARA A EDITORA: {formata_br(total_livros)}\n")
        
        print("2. COMO FOI PAGO (O QUE APARECE NO CAIXA):")
        pags_venda = df_pag[df_pag['sellNumber'] == venda_exemplo]
        for _, pag in pags_venda.iterrows():
            print(f"   - Pago via {pag['paymentWay']:<30} | {formata_br(pag['valor_num'])}")
        
        print("\nCONCLUSÃO:")
        print(f"Nesta única venda, o relatório de Editoras somou {formata_br(total_livros)}.")
        print(f"Mas o relatório de Cartões somou apenas o valor debitado no Cashless.")
        print("É por isso que as Editoras sempre terão um valor maior no final da feira.")
    else:
        print("Nenhum exemplo de pagamento misto encontrado nos dados atuais.")
        
    print("="*80 + "\n")

if __name__ == '__main__':
    extrair_provas_reais()