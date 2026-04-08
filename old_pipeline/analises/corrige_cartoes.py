import pandas as pd
import sqlite3

def corrige_tabela_cartoes():
    print("Lendo a planilha de cartões problemática...")
    df_cartoes = pd.read_excel('cartoes.xls', engine='calamine')

    # 1. Limpando os nomes das colunas (como fizemos antes)
    df_cartoes.columns = [str(col).replace('\x00', '') for col in df_cartoes.columns]
    df_cartoes = df_cartoes[['Excursão', 'ID Verso', 'Código']]
    df_cartoes = df_cartoes.rename(columns={'Excursão': 'Grupo'})

    # 2. LIMPEZA AGRESSIVA DOS DADOS (O Pulo do Gato)
    print("Destruindo os bytes nulos e convertendo para texto puro...")
    
    def vassoura_de_bytes(valor):
        if pd.isna(valor):
            return valor
        # Converte para string, arranca o \x00, tira espaços sobrando e deixa maiúsculo
        return str(valor).replace('\x00', '').strip().upper()

    for col in df_cartoes.columns:
        df_cartoes[col] = df_cartoes[col].apply(vassoura_de_bytes)

    # 3. Salvando no banco (substitui apenas a tabela cartoes, mantendo as vendas intactas)
    print("Salvando a tabela corrigida no SQLite...")
    conn = sqlite3.connect('feira_livro.db')
    df_cartoes.to_sql('cartoes', conn, if_exists='replace', index=False)
    conn.close()

    print("Tabela 'cartoes' recriada com sucesso! Textos agora são 100% legíveis.")

if __name__ == '__main__':
    corrige_tabela_cartoes()