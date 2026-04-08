import pandas as pd
import sqlite3

def etapa_1_carga_planilhas():
    # Conectando ao banco de dados SQLite
    conn = sqlite3.connect('feira_livro.db')
    print("Banco de dados 'feira_livro.db' conectado com sucesso.")

    # ==========================================
    # PROCESSAMENTO DA PLANILHA DE LIVROS
    # ==========================================
    print("Processando planilha de Livros...")
    df_livros = pd.read_excel('livros.xlsx', engine='calamine', usecols=['Categoria', 'Produto', 'Valor'])
    
    # Limpeza da coluna 'Valor' para formato numérico
    df_livros['Valor'] = (
        df_livros['Valor']
        .astype(str)
        .str.replace('R$', '', regex=False)
        .str.replace(' ', '', regex=False)
        .str.replace('.', '', regex=False)
        .str.replace(',', '.', regex=False)
        .astype(float)
    )
    
    df_livros.to_sql('livros', conn, if_exists='replace', index=False)
    print(f"Tabela 'livros' criada com {len(df_livros)} registros.")

    # ==========================================
    # PROCESSAMENTO DA PLANILHA DE CARTÕES
    # ==========================================
    print("Processando planilha de Cartões...")
    
    # 1. Lemos sem o usecols para puxar tudo
    df_cartoes = pd.read_excel('cartoes.xls', engine='calamine')
    
    # 2. Limpamos os bytes nulos (\x00) dos nomes das colunas
    df_cartoes.columns = [str(col).replace('\x00', '') for col in df_cartoes.columns]
    
    # 3. Agora filtramos apenas as colunas que importam
    df_cartoes = df_cartoes[['Excursão', 'ID Verso', 'Código']]
    
    # 4. Limpamos os bytes nulos (\x00) de dentro das células de texto, por segurança
    for col in df_cartoes.columns:
        if df_cartoes[col].dtype == 'object':
            df_cartoes[col] = df_cartoes[col].astype(str).str.replace('\x00', '', regex=False)
            
    # 5. Renomeando a coluna 'Excursão' para 'Grupo' (conforme sua regra)
    df_cartoes = df_cartoes.rename(columns={'Excursão': 'Grupo'})
    
    # Mantemos TODOS os registros intactos para a etapa futura de higienização
    df_cartoes.to_sql('cartoes', conn, if_exists='replace', index=False)
    print(f"Tabela 'cartoes' criada com {len(df_cartoes)} registros limpos.")

    # Fechando a conexão
    conn.close()
    print("Fase 1 concluída com sucesso! Banco de dados pronto.")

if __name__ == '__main__':
    etapa_1_carga_planilhas()