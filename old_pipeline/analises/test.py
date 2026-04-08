import pandas as pd
import sqlite3

def descobre_nome_completo():
    conn = sqlite3.connect('feira_livro.db')
    
    # Busca o nome exato da escola que começa com esse trecho cortado
    query = """
    SELECT DISTINCT payment_group 
    FROM pagamentos 
    WHERE payment_group LIKE 'Centro Municipal de Educação P%'
    """
    
    df = pd.read_sql_query(query, conn)
    conn.close()

    print("\nO NOME COMPLETO DA ESCOLA É:")
    print("--------------------------------------------------")
    for nome in df['payment_group']:
        print(nome)
    print("--------------------------------------------------\n")

if __name__ == '__main__':
    descobre_nome_completo()