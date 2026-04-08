import sqlite3
import pandas as pd
import os
import unicodedata

DB_PATH = 'feira_livro_teste.db' # Mude para 'feira_livro.db' quando for para produção
CSV_PATH = 'editoras.csv'

def limpar_texto(texto):
    """
    Remove acentos, cedilhas e espaços extra, e converte para maiúsculas.
    Garante que 'novo século' e 'novo seculo' sejam idênticos no cruzamento.
    """
    if pd.isna(texto) or texto is None:
        return ""
    texto = str(texto)
    # Normaliza a string separando os caracteres dos seus acentos e remove-os
    texto = unicodedata.normalize('NFKD', texto).encode('ASCII', 'ignore').decode('utf-8')
    return texto.strip().upper()

def atualizar_banco_representantes():
    print("=" * 60)
    print(" 📚 ATUALIZAÇÃO DE REPRESENTANTES NA TABELA DE LIVROS")
    print("=" * 60)

    if not os.path.exists(CSV_PATH):
        print(f"❌ Erro: Arquivo '{CSV_PATH}' não encontrado na pasta.")
        return

    # 1. Carregar o CSV
    print(f"1. A ler o ficheiro {CSV_PATH}...")
    df_rep = pd.read_csv(CSV_PATH)
    
    # 2. Conectar ao Banco de Dados
    conn = sqlite3.connect(DB_PATH)
    
    # INJEÇÃO DA FUNÇÃO: Regista a função do Python dentro do SQLite
    conn.create_function("LIMPAR_TEXTO", 1, limpar_texto)
    
    cursor = conn.cursor()

    # 3. Verificar se a coluna 'Representante' já existe
    print("2. A verificar a estrutura da tabela 'livros'...")
    cursor.execute("PRAGMA table_info(livros)")
    colunas = [coluna[1] for coluna in cursor.fetchall()]
    
    if 'Representante' not in colunas:
        print("   -> A criar a coluna 'Representante' no banco de dados...")
        cursor.execute("ALTER TABLE livros ADD COLUMN Representante TEXT DEFAULT 'Não Informado'")
    else:
        print("   -> A coluna 'Representante' já existe.")
        
    # RESET: Garante que a tabela seja varrida do zero a cada execução
    print("   -> A repor os representantes atuais para aplicar a nova lista...")
    cursor.execute("UPDATE livros SET Representante = 'Não Informado'")

    # 4. Atualizar os dados (A iterar pelo CSV)
    print("3. A cruzar informações e a atualizar a base...")
    updates_sucesso = 0
    
    for _, row in df_rep.iterrows():
        # Normaliza o texto que vem do CSV usando a nossa nova função
        categoria_csv = limpar_texto(row['Categoria'])
        representante = str(row['Representante']).strip().upper()
        
        # Faz o update aplicando a função LIMPAR_TEXTO também nos dados do banco
        cursor.execute("""
            UPDATE livros 
            SET Representante = ? 
            WHERE LIMPAR_TEXTO(Categoria) = ?
        """, (representante, categoria_csv))
        
        # Conta quantas linhas do banco foram afetadas por este comando
        updates_sucesso += cursor.rowcount

    conn.commit()
    print(f"   -> {updates_sucesso} registos de livros atualizados com sucesso!")

    # 5. Auditoria Rápida (Quais categorias do banco ficaram de fora da sua lista?)
    cursor.execute("""
        SELECT DISTINCT Categoria 
        FROM livros 
        WHERE Representante = 'Não Informado' OR Representante IS NULL
    """)
    categorias_sem_representante = cursor.fetchall()
    
    conn.close()

    print("\n" + "=" * 60)
    print(" ✅ PROCESSO CONCLUÍDO!")
    
    # Remove 'Sem Categoria' da lista de alertas se ele já foi tratado de propósito
    alertas = [cat[0] for cat in categorias_sem_representante if cat[0] and limpar_texto(cat[0]) != 'SEM CATEGORIA']

    if alertas:
        print("\n⚠️  ATENÇÃO: As seguintes Categorias estão no Banco de Dados,")
        print("mas NÃO foram encontradas no seu CSV (ficaram como 'Não Informado'):")
        for cat in alertas:
            print(f"  - {cat}")
    else:
        print("🎉 100% das editoras válidas do banco receberam um representante!")
    print("=" * 60)

if __name__ == '__main__':
    atualizar_banco_representantes()