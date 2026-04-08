import pandas as pd
import sqlite3

def auditar_diferenca_datas():
    conn = sqlite3.connect('feira_livro.db')
    df_vendas = pd.read_sql_query("SELECT sellNumber, dateHour FROM vendas_header", conn)
    df_pag = pd.read_sql_query("SELECT sellNumber, tagCode, payment_group, paymentWay, value FROM pagamentos", conn)
    df_cartoes = pd.read_sql_query("SELECT Código, Grupo FROM cartoes", conn)
    conn.close()

    # 1. Limpeza
    df_pag['valor_num'] = df_pag['value'].fillna('0').astype(str).str.replace('R$', '', regex=False).str.replace(' ', '', regex=False).str.replace('.', '', regex=False).str.replace(',', '.', regex=False).astype(float)

    # 2. Filtro de cartoes e descontos válidos (mesmo do PDF e do Caça ao Tesouro)
    grupos_invalidos = ['-', 'Teste Nowigo', 'nan', '']
    df_cartoes_v = df_cartoes[~df_cartoes['Grupo'].astype(str).str.strip().isin(grupos_invalidos)].copy()
    
    df_pag['tag_limpo'] = df_pag['tagCode'].astype(str).str.strip().str.upper()
    df_cartoes_v['cod_limpo'] = df_cartoes_v['Código'].astype(str).str.strip().str.upper()

    df_pag_v = df_pag.merge(df_cartoes_v, left_on='tag_limpo', right_on='cod_limpo', how='inner')
    df_pag_v = df_pag_v[(df_pag_v['paymentWay'].str.upper() != 'DESCONTO') & (df_pag_v['payment_group'].str.upper() != 'PAGAMENTO SEM GRUPO')]

    # 3. Cruzamento com as Datas
    df_merged = df_pag_v.merge(df_vendas, on='sellNumber', how='inner')
    df_merged['dt'] = pd.to_datetime(df_merged['dateHour'], format='%d/%m/%Y %H:%M:%S', errors='coerce')

    # 4. A Guilhotina do Tempo
    df_dentro = df_merged[(df_merged['dt'] >= '2025-11-11') & (df_merged['dt'] <= '2025-11-20 23:59:59')]
    df_fora = df_merged[(df_merged['dt'] < '2025-11-11') | (df_merged['dt'] > '2025-11-20 23:59:59')]

    def formata_br(valor):
        return f"R$ {valor:,.2f}".replace(',', '_').replace('.', ',').replace('_', '.')

    print("\n" + "="*70)
    print("🔍 AUDITORIA DE DATAS: ONDE ESTÃO OS R$ 3.950,00?")
    print("="*70)
    print(f"Total DENTRO da Feira (11 a 20/11): {formata_br(df_dentro['valor_num'].sum())}")
    print(f"Total FORA da Feira (Testes):       {formata_br(df_fora['valor_num'].sum())}")
    print("="*70)
    
    print("\n--- DETALHAMENTO DO QUE ACONTECEU FORA DA FEIRA ---")
    if df_fora.empty:
        print("Nenhuma transação fora da feira.")
    else:
        # Agrupa os testes por dia para não poluir a tela
        resumo_fora = df_fora.groupby([df_fora['dt'].dt.strftime('%d/%m/%Y'), 'payment_group']).agg(
            Qtd_Transacoes=('valor_num', 'count'),
            Valor_Total=('valor_num', 'sum')
        ).reset_index()
        
        for _, r in resumo_fora.iterrows():
            escola = str(r['payment_group'])[:35]
            print(f"Data: {r['dt']} | Qtd: {r['Qtd_Transacoes']:<3} | Total: {formata_br(r['Valor_Total']):<12} | Escola: {escola}")

    print("\n" + "="*70 + "\n")

if __name__ == '__main__':
    auditar_diferenca_datas()