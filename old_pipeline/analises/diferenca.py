import pandas as pd
import sqlite3

def caca_ao_tesouro_1500():
    conn = sqlite3.connect('feira_livro.db')
    df_pagamentos = pd.read_sql_query("SELECT * FROM pagamentos", conn)
    df_cartoes = pd.read_sql_query("SELECT * FROM cartoes", conn)
    conn.close()

    df_pagamentos['valor_num'] = (
        df_pagamentos['value'].fillna('0')
        .astype(str).str.replace('R$', '', regex=False)
        .str.replace(' ', '', regex=False)
        .str.replace('.', '', regex=False)
        .str.replace(',', '.', regex=False)
        .astype(float)
    )

    grupos_invalidos = ['-', 'Teste Nowigo', 'nan', '']
    df_cartoes['Grupo_limpo'] = df_cartoes['Grupo'].astype(str).str.strip()
    df_cartoes_validos = df_cartoes[~df_cartoes['Grupo_limpo'].isin(grupos_invalidos)].copy()

    df_pagamentos['tagCode_limpo'] = df_pagamentos['tagCode'].astype(str).str.strip().str.upper()
    df_cartoes_validos['Codigo_limpo'] = df_cartoes_validos['Código'].astype(str).str.strip().str.upper()

    df_validos = df_pagamentos.merge(
        df_cartoes_validos, left_on='tagCode_limpo', right_on='Codigo_limpo', how='inner'
    )

    def formata_br(valor):
        return f"R$ {valor:,.2f}".replace(',', '_').replace('.', ',').replace('_', '.')

    print("\n" + "="*60)
    print("CAÇA AO TESOURO: ANALISANDO OS R$ 5.954.215,15 VÁLIDOS")
    print("="*60)
    
    print("\n--- POR FORMA DE PAGAMENTO (paymentWay) ---")
    agrup_way = df_validos.groupby('paymentWay')['valor_num'].sum().reset_index()
    for _, row in agrup_way.iterrows():
        print(f"{str(row['paymentWay']):<30} | {formata_br(row['valor_num'])}")

    print("\n--- POR GRUPO DA API (payment_group) ---")
    agrup_group = df_validos.groupby('payment_group')['valor_num'].sum().reset_index()
    for _, row in agrup_group.iterrows():
        print(f"{str(row['payment_group'])[:30]:<30} | {formata_br(row['valor_num'])}")
    print("\n" + "="*60)

if __name__ == '__main__':
    caca_ao_tesouro_1500()