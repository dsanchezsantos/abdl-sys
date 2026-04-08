"""
Script de Inserção de Dados Sintéticos para Teste (v4)
=======================================================
Gera vendas realistas com múltiplos pagamentos por cartão.
- Limpa dados antigos com prefixo SYN/TSYN.
- Gera novos dados SEM prefixo.
- IDs de cartões baseados na contagem total + 1.
- IDs de vendas baseados no valor máximo + 1.
"""

import sqlite3
import random
import pandas as pd
from datetime import datetime, timedelta

# ===================================================
# CONFIGURAÇÃO
# ===================================================
DB_PATH = 'feira_livro_teste.db'
TARGET_TOTAL = 5_952_715.00
SALDO_INICIAL = 250.00

GRUPOS_SINTETICOS = [
    'EM Marechal Henrique Batista',
    'EM Presidente Epitácio Pessoa',
    'EM Santos Dumont',
    'EM Dom Pedro I',
    'EM Tiradentes'
]
CAIXAS_SINTETICOS = [f'Livro {i}' for i in range(1, 101)]

DATA_INICIO = datetime(2025, 11, 11, 8, 0, 0)
DATA_FIM    = datetime(2025, 11, 20, 18, 0, 0)

# ===================================================
# FUNÇÕES UTILITÁRIAS
# ===================================================
def formata_br(valor: float) -> str:
    return f"R$ {valor:,.2f}".replace(',', '_').replace('.', ',').replace('_', '.')

def limpar_moeda(serie: pd.Series) -> pd.Series:
    return (
        serie.fillna('0').astype(str)
        .str.replace('R$', '', regex=False)
        .str.replace(' ',  '', regex=False)
        .str.replace('.', '', regex=False)
        .str.replace(',', '.', regex=False)
        .astype(float)
    )

def calcular_total_atual(conn: sqlite3.Connection) -> float:
    df_vendas = pd.read_sql_query("SELECT * FROM vendas_header", conn)
    df_pagamentos = pd.read_sql_query("SELECT * FROM pagamentos", conn)
    df_cartoes = pd.read_sql_query('SELECT * FROM cartoes', conn)

    df_vendas = df_vendas.drop_duplicates(subset=['sellNumber'])
    df_pagamentos = df_pagamentos.drop_duplicates()
    df_pagamentos['valor_num'] = limpar_moeda(df_pagamentos['value'])

    grupos_invalidos = ['-', 'Teste Nowigo', 'nan', '']
    df_cart_v = df_cartoes[~df_cartoes['Grupo'].astype(str).str.strip().isin(grupos_invalidos)].copy()
    
    df_cart_v['Código'] = df_cart_v['Código'].astype(str).str.strip().str.upper()
    df_cart_v = df_cart_v.drop_duplicates(subset=['Código'])

    df_pag_v = df_pagamentos.merge(
        df_cart_v,
        left_on=df_pagamentos['tagCode'].astype(str).str.strip().str.upper(),
        right_on='Código',
        how='inner'
    )
    df_pag_v = df_pag_v[
        (df_pag_v['paymentWay'].astype(str).str.upper()    != 'DESCONTO') &
        (df_pag_v['payment_group'].astype(str).str.upper() != 'PAGAMENTO SEM GRUPO')
    ]

    df_vendas['dt'] = pd.to_datetime(df_vendas['dateHour'], format='%d/%m/%Y %H:%M:%S', errors='coerce')
    df_vendas_f = df_vendas[
        (df_vendas['dt'] >= '2025-11-11') &
        (df_vendas['dt'] <= '2025-11-20 23:59:59')
    ]
    df_pag_final = df_pag_v[df_pag_v['sellNumber'].isin(df_vendas_f['sellNumber'].unique())]
    return round(df_pag_final['valor_num'].sum(), 2)

def gerar_data_aleatoria() -> str:
    delta_total = int((DATA_FIM - DATA_INICIO).total_seconds())
    dt = DATA_INICIO + timedelta(seconds=random.randint(0, delta_total))
    return dt.strftime('%d/%m/%Y %H:%M:%S')

def carregar_livros_por_preco(conn: sqlite3.Connection) -> dict:
    df = pd.read_sql_query("SELECT Produto, Valor FROM livros", conn)
    if df['Valor'].dtype == object:
        df['Valor'] = limpar_moeda(df['Valor'])
    livros = {}
    for _, row in df.iterrows():
        preco = round(row['Valor'], 2)
        if preco > 0 and preco % 10 == 0:
            livros.setdefault(preco, []).append(row['Produto'])
    return livros

def dividir_valor_em_vendas(total: float, precos_disponiveis: list[float]) -> list[float]:
    precos = sorted(precos_disponiveis)
    menor  = precos[0]
    partes = []
    restante = round(total, 2)

    while restante > 0:
        validos = [p for p in precos if p <= restante and (round(restante - p, 2) == 0 or round(restante - p, 2) >= menor)]
        if not validos:
            partes.append(restante)
            break
        escolhido = random.choice(validos)
        partes.append(escolhido)
        restante = round(restante - escolhido, 2)
    return partes

def limpar_dados_sinteticos_antigos():
    """ 
    Apaga explicitamente os testes antigos criados com prefixo SYN e TSYN 
    para garantir que a base volte ao valor original de 5.948.765,15.
    """
    conn = sqlite3.connect(DB_PATH)
    cursor = conn.cursor()
    print("🧹 Limpando dados sintéticos antigos (com prefixo SYN/TSYN)...")
    cursor.execute("DELETE FROM itens_venda WHERE sellNumber LIKE 'SYN%'")
    cursor.execute("DELETE FROM pagamentos WHERE sellNumber LIKE 'SYN%'")
    cursor.execute("DELETE FROM vendas_header WHERE sellNumber LIKE 'SYN%'")
    cursor.execute("DELETE FROM cartoes WHERE Código LIKE 'TSYN%'")
    conn.commit()
    conn.close()
    print("✨ Limpeza concluída.\n")

def inserir_dados_sinteticos():
    conn = sqlite3.connect(DB_PATH)

    print("=" * 70)
    print("🚀 INSERÇÃO DE DADOS SINTÉTICOS DE PRECISÃO (v4 - Sem Prefixos)")
    print("=" * 70)

    total_atual = calcular_total_atual(conn)
    delta = round(TARGET_TOTAL - total_atual, 2)
    
    if delta <= 0:
        print(f"\n✅ Total já está no alvo ou acima. Nenhuma inserção necessária. ({formata_br(total_atual)})")
        conn.close()
        return

    n_full = int(delta // SALDO_INICIAL)
    remainder = round(delta - n_full * SALDO_INICIAL, 2)
    has_remainder = remainder > 0.004

    livros_por_preco = carregar_livros_por_preco(conn)
    precos_m10 = sorted(livros_por_preco.keys())

    cursor = conn.cursor()

    # --- LÓGICA DE INCREMENTO DAS VENDAS ---
    # Busca o maior número de venda existente
    cursor.execute("SELECT MAX(CAST(sellNumber AS INTEGER)) FROM vendas_header")
    row = cursor.fetchone()
    sell_seq = (row[0] or 0) + 1

    # --- LÓGICA DE INCREMENTO DOS CARTÕES ---
    # Busca a quantidade total de cartões para continuar a contagem
    cursor.execute("SELECT COUNT(*) FROM cartoes")
    row_cartoes = cursor.fetchone()
    tag_counter = (row_cartoes[0] or 0) + 1

    lista_cartoes = []
    lista_vendas = []
    lista_pagamentos = []
    lista_itens = []

    def registrar_venda_cashless(tag_code, valor_livro, livro_nome):
        nonlocal sell_seq
        sell_num  = str(sell_seq)
        sell_seq += 1
        data_hora = gerar_data_aleatoria()
        caixa     = random.choice(CAIXAS_SINTETICOS)
        valor_str = formata_br(valor_livro)

        lista_vendas.append((sell_num, valor_str, data_hora, caixa, 1, 1))
        lista_pagamentos.append((sell_num, tag_code, '', 'Cashless Nowigo', None, valor_str, 'Cashless'))
        lista_itens.append((sell_num, None, livro_nome, 1, valor_str, valor_str))

    def registrar_venda_com_desconto(tag_code, valor_cashless, valor_livro, livro_nome):
        nonlocal sell_seq
        sell_num  = str(sell_seq)
        sell_seq += 1
        data_hora = gerar_data_aleatoria()
        caixa     = random.choice(CAIXAS_SINTETICOS)

        valor_desconto  = round(valor_livro - valor_cashless, 2)
        str_livro       = formata_br(valor_livro)
        str_cashless    = formata_br(valor_cashless)
        str_desconto    = formata_br(valor_desconto)

        lista_vendas.append((sell_num, str_livro, data_hora, caixa, 1, 1))
        lista_pagamentos.append((sell_num, tag_code, '', 'Cashless Nowigo', None, str_cashless, 'Cashless'))
        lista_pagamentos.append((sell_num, 'Não disponível', '', 'Desconto', None, str_desconto, 'Pagamento sem grupo'))
        lista_itens.append((sell_num, None, livro_nome, 1, str_livro, str_livro))

    for i in range(n_full):
        tag_code = str(tag_counter)
        id_verso = str(tag_counter)
        grupo = GRUPOS_SINTETICOS[i % len(GRUPOS_SINTETICOS)]
        tag_counter += 1
        lista_cartoes.append((tag_code, id_verso, grupo))

        for v in dividir_valor_em_vendas(SALDO_INICIAL, precos_m10):
            registrar_venda_cashless(tag_code, v, random.choice(livros_por_preco[v]))

    if has_remainder:
        tag_code = str(tag_counter)
        id_verso = str(tag_counter)
        grupo = GRUPOS_SINTETICOS[0]
        lista_cartoes.append((tag_code, id_verso, grupo))

        parte_inteira_m10 = int(remainder // 10) * 10
        parte_decimal = round(remainder - parte_inteira_m10, 2)

        if parte_inteira_m10 > 0:
            for v in dividir_valor_em_vendas(float(parte_inteira_m10), precos_m10):
                registrar_venda_cashless(tag_code, v, random.choice(livros_por_preco[v]))

        if parte_decimal > 0.004:
            preco_livro = precos_m10[0]
            registrar_venda_com_desconto(tag_code, parte_decimal, preco_livro, random.choice(livros_por_preco[preco_livro]))

    cursor.executemany('INSERT INTO cartoes (Código, "ID Verso", Grupo) VALUES (?,?,?)', lista_cartoes)
    cursor.executemany("INSERT INTO vendas_header (sellNumber, totalValue, dateHour, box, type, processado) VALUES (?,?,?,?,?,?)", lista_vendas)
    cursor.executemany("INSERT INTO pagamentos (sellNumber, tagCode, cpf, paymentWay, payment_id, value, payment_group) VALUES (?,?,?,?,?,?,?)", lista_pagamentos)
    cursor.executemany("INSERT INTO itens_venda (sellNumber, product_id, name, amount, unitValue, totalValue) VALUES (?,?,?,?,?,?)", lista_itens)
    conn.commit()

    total_novo = calcular_total_atual(conn)
    conn.close()

    print(f"📥 Base Limpa: {formata_br(total_atual)}")
    print(f"💰 Delta Inserido: {formata_br(delta)}")
    print(f"🎯 Novo Total: {formata_br(total_novo)}")
    print("=" * 70)

if __name__ == '__main__':
    limpar_dados_sinteticos_antigos()
    inserir_dados_sinteticos()