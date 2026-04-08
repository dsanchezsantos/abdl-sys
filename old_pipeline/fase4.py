import pandas as pd
import sqlite3

def etapa_4_validacao_totais():
    print("Lendo dados do banco SQLite...")
    conn = sqlite3.connect('feira_livro.db')
    
    df_pagamentos = pd.read_sql_query("SELECT * FROM pagamentos", conn)
    df_cartoes = pd.read_sql_query("SELECT * FROM cartoes", conn)
    conn.close()

    print("Formatando valores financeiros...")
    def limpar_moeda(serie):
        return serie.fillna('0').astype(str).str.replace('R$', '', regex=False)\
                                .str.replace(' ', '', regex=False)\
                                .str.replace('.', '', regex=False)\
                                .str.replace(',', '.', regex=False)\
                                .astype(float)

    df_pagamentos['valor_num'] = limpar_moeda(df_pagamentos['value'])

    # ==========================================
    # 1. FILTRO DE CARTÕES DA PLANILHA (Teste e Vazios)
    # ==========================================
    grupos_invalidos = ['-', 'Teste Nowigo', 'nan', '']
    df_cartoes['Grupo_limpo'] = df_cartoes['Grupo'].astype(str).str.strip()
    df_cartoes_validos = df_cartoes[~df_cartoes['Grupo_limpo'].isin(grupos_invalidos)].copy()

    df_pagamentos['tagCode_limpo'] = df_pagamentos['tagCode'].astype(str).str.strip().str.upper()
    df_cartoes_validos['Codigo_limpo'] = df_cartoes_validos['Código'].astype(str).str.strip().str.upper()

    df_pagamentos_validos = df_pagamentos.merge(
        df_cartoes_validos, left_on='tagCode_limpo', right_on='Codigo_limpo', how='inner'
    )

    # ==========================================
    # 2. FILTRO DE DESCONTOS (paymentWay)
    # ==========================================
    df_pagamentos_validos['paymentWay_limpo'] = df_pagamentos_validos['paymentWay'].astype(str).str.strip().str.upper()
    
    # Isola o que é desconto e o que não é
    df_sem_desconto = df_pagamentos_validos[df_pagamentos_validos['paymentWay_limpo'] != 'DESCONTO'].copy()
    
    # ==========================================
    # 3. FILTRO "PAGAMENTO SEM GRUPO" (payment_group)
    # ==========================================
    df_sem_desconto['payment_group_limpo'] = df_sem_desconto['payment_group'].astype(str).str.strip().str.upper()
    
    # Mantém TUDO (todas as escolas), EXCETO a string 'PAGAMENTO SEM GRUPO'
    df_consumo_final = df_sem_desconto[df_sem_desconto['payment_group_limpo'] != 'PAGAMENTO SEM GRUPO'].copy()
    df_jogados_fora_sem_grupo = df_sem_desconto[df_sem_desconto['payment_group_limpo'] == 'PAGAMENTO SEM GRUPO']

    # ==========================================
    # CÁLCULOS PARA O TERMINAL
    # ==========================================
    total_bruto = df_pagamentos['valor_num'].sum()
    total_apos_cartoes = df_pagamentos_validos['valor_num'].sum()
    total_apos_desconto = df_sem_desconto['valor_num'].sum()
    total_final = df_consumo_final['valor_num'].sum()
    
    # Descobrindo o tamanho exato de cada "sujeira" filtrada
    sujeira_cartoes_planilha = total_bruto - total_apos_cartoes
    sujeira_descontos = total_apos_cartoes - total_apos_desconto
    sujeira_sem_grupo = df_jogados_fora_sem_grupo['valor_num'].sum()

    def formata_br(valor):
        return f"{valor:,.2f}".replace(',', '_').replace('.', ',').replace('_', '.')

    print("\n" + "="*75)
    print("=== RESUMO FINANCEIRO DA FEIRA (CONSUMO DOS CARTÕES) ===")
    print("="*75)
    print(f"1. Total Bruto (Tudo da API):                           R$ {formata_br(total_bruto)}")
    print(f"2. (-) Cartões Inválidos (Planilha/Teste/Vazios):       R$ {formata_br(sujeira_cartoes_planilha)}")
    print(f"3. (=) Subtotal 1 (Apenas Cartões de Escolas):          R$ {formata_br(total_apos_cartoes)}")
    print(f"4. (-) Descontos Concedidos (Não consumido do saldo):   R$ {formata_br(sujeira_descontos)}")
    print(f"5. (=) Subtotal 2 (Gasto Bruto nas Escolas):            R$ {formata_br(total_apos_desconto)}")
    print(f"6. (-) Exclusão do 'Pagamento sem grupo':               R$ {formata_br(sujeira_sem_grupo)}")
    print("-" * 75)
    print(f"TOTAL LÍQUIDO VALIDADO PARA RELATÓRIOS:                 R$ {formata_br(total_final)}")
    print("="*75 + "\n")

if __name__ == '__main__':
    etapa_4_validacao_totais()