import pandas as pd
import sqlite3

def analise_centavos_padre_manuel():
    conn = sqlite3.connect('feira_livro.db')
    # Puxando também o totalValue da venda para conferirmos se a quebra veio do livro ou do pagamento
    df_vendas = pd.read_sql_query("SELECT sellNumber, dateHour, box, totalValue FROM vendas_header", conn)
    df_pagamentos = pd.read_sql_query("SELECT * FROM pagamentos", conn)
    conn.close()

    # 1. Função de Limpeza de Moeda
    def limpar_moeda(serie):
        return serie.fillna('0').astype(str).str.replace('R$', '', regex=False)\
                                .str.replace(' ', '', regex=False)\
                                .str.replace('.', '', regex=False)\
                                .str.replace(',', '.', regex=False)\
                                .astype(float)

    df_pagamentos['valor_num'] = limpar_moeda(df_pagamentos['value'])
    df_vendas['total_venda_num'] = limpar_moeda(df_vendas['totalValue'])

    # 2. Filtro da Janela Oficial da Feira nas Vendas
    df_vendas['data_hora_dt'] = pd.to_datetime(df_vendas['dateHour'], format='%d/%m/%Y %H:%M:%S', errors='coerce')
    df_vendas_validas = df_vendas[
        (df_vendas['data_hora_dt'] >= '2025-11-11') & 
        (df_vendas['data_hora_dt'] <= '2025-11-20 23:59:59')
    ]

    # 3. Encontrar quais vendas tiveram a escola Padre Manuel envolvida
    df_pags_escola = df_pagamentos[df_pagamentos['payment_group'].astype(str).str.contains('Padre Manuel', case=False, na=False)]
    
    # Interseção: Vendas que ocorreram nas datas certas E tem a escola no meio
    vendas_alvo = set(df_vendas_validas['sellNumber']).intersection(set(df_pags_escola['sellNumber']))

    # Reduzindo os dataframes apenas para as vendas alvo
    df_vendas_alvo = df_vendas_validas[df_vendas_validas['sellNumber'].isin(vendas_alvo)]
    df_pags_alvo = df_pagamentos[df_pagamentos['sellNumber'].isin(vendas_alvo)]

    # 4. A CAÇA AOS CENTAVOS
    vendas_com_centavos = set()

    # Checa se o total da venda tem centavos (o resto da divisão por 1 é diferente de zero)
    vendas_header_centavos = df_vendas_alvo[df_vendas_alvo['total_venda_num'] % 1 != 0]['sellNumber']
    vendas_com_centavos.update(vendas_header_centavos)

    # Checa se algum pagamento individual daquelas vendas tem centavos
    pags_centavos = df_pags_alvo[df_pags_alvo['valor_num'] % 1 != 0]['sellNumber']
    vendas_com_centavos.update(pags_centavos)

    def formata_br(valor):
        return f"{valor:,.2f}".replace(',', '_').replace('.', ',').replace('_', '.')

    print("\n" + "="*85)
    print("🔍 ANÁLISE QUANTITATIVA: TRANSAÇÕES COM CENTAVOS (PADRE MANUEL)")
    print("="*85)

    if not vendas_com_centavos:
        print("✅ Nenhuma transação com centavos (fracionada) foi encontrada no período da feira.")
    else:
        print(f"⚠️  Foram encontradas {len(vendas_com_centavos)} venda(s) com valores fracionados:\n")
        
        for vd in vendas_com_centavos:
            info_venda = df_vendas_alvo[df_vendas_alvo['sellNumber'] == vd].iloc[0]
            
            alerta_total = " ⚠️ (CENTAVOS NO TOTAL)" if info_venda['total_venda_num'] % 1 != 0 else ""
            
            print(f"🏷️  VENDA NÚMERO: {vd}")
            print(f"📅  Data/Hora: {info_venda['dateHour']} | Caixa: {info_venda['box']}")
            print(f"💰  Total da Venda Registrado: R$ {formata_br(info_venda['total_venda_num'])}{alerta_total}")
            print("💳  Detalhes dos Pagamentos nesta Venda:")

            pags_desta_venda = df_pags_alvo[df_pags_alvo['sellNumber'] == vd]
            for _, p in pags_desta_venda.iterrows():
                grupo = p['payment_group'] if pd.notna(p['payment_group']) and p['payment_group'] != '' else 'Sem Grupo Registrado'
                forma = p['paymentWay']
                valor_pag = p['valor_num']
                
                alerta_pag = " ⚠️ (CONTÉM CENTAVOS)" if valor_pag % 1 != 0 else ""
                
                print(f"    -> Forma: {forma[:15]:<15} | Valor: R$ {formata_br(valor_pag):>8}{alerta_pag} | Grupo: {grupo[:35]}")
            print("-" * 85)
            
    print("="*85 + "\n")

if __name__ == '__main__':
    analise_centavos_padre_manuel()