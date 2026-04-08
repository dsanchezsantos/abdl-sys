import requests
import sqlite3
import time
import sys

def etapa_2_extracao_transacoes():
    # 1. Conectando ao banco de dados
    conn = sqlite3.connect('feira_livro.db')
    cursor = conn.cursor()

    # LIMPEZA: Apaga a tabela antiga caso o script tenha sido interrompido antes, evitando duplicatas
    cursor.execute('DROP TABLE IF EXISTS vendas_header')

    # 2. Criando a tabela de cabeçalho das vendas (list) zerada
    cursor.execute('''
        CREATE TABLE vendas_header (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sellNumber TEXT,
            totalValue TEXT,
            dateHour TEXT,
            box TEXT,
            type INTEGER,
            processado INTEGER DEFAULT 0
        )
    ''')
    conn.commit()

    url = "https://feiradolivro-saquarema2025.nowigo.com.br/app/sale.mdl"
    
    # Parâmetros base para a requisição
    params = {
        'action': 'list',
        'eventId': '771',
        'userId': '38881',
        'perPage': 100, 
        'dateTimeBegin': '21/09/2025 00:00:00',
        'dateTimeEnd': '20/11/2025 23:59:59',
        'search': ''
    }

    print("Iniciando extração da API (Fase 2)...")

    # 3. Descobrindo o total de páginas
    params['page'] = 1
    response = requests.get(url, params=params)
    
    if not response.ok:
        print(f"Erro ao acessar a API: {response.status_code}")
        return

    dados_iniciais = response.json()
    total_pages = dados_iniciais.get('pagination', {}).get('totalPages', 1)
    total_items = dados_iniciais.get('pagination', {}).get('totalItems', 0)
    
    print(f"Total de registros previstos: {total_items}")
    print(f"Total de páginas a processar: {total_pages}\n")

    # 4. Loop de Paginação
    for page in range(1, total_pages + 1):
        params['page'] = page
        
        try:
            res = requests.get(url, params=params, timeout=15)
            json_data = res.json()
            
            transacoes = json_data.get('data', [])
            
            # Preparando os dados para inserção em lote (mais rápido)
            registros = [
                (
                    t.get('sellNumber'), 
                    t.get('totalValue'), 
                    t.get('dateHour'), 
                    t.get('box'), 
                    t.get('type')
                ) 
                for t in transacoes
            ]
            
            # Inserindo no SQLite
            cursor.executemany('''
                INSERT INTO vendas_header (sellNumber, totalValue, dateHour, box, type)
                VALUES (?, ?, ?, ?, ?)
            ''', registros)
            
            conn.commit()
            
            # FEEDBACK VISUAL EM TEMPO REAL: Atualiza a mesma linha no terminal
            percentual = (page / total_pages) * 100
            sys.stdout.write(f"\rProcessando: Página {page}/{total_pages} --- {percentual:.2f}% concluído")
            sys.stdout.flush()
                
            # Pequena pausa para não derrubar a API
            time.sleep(0.1)
            
        except Exception as e:
            # Pula de linha para o erro não quebrar a barra de progresso
            print(f"\nErro ao processar a página {page}. Detalhe: {e}")
            continue

    conn.close()
    print("\n\nFase 2 concluída! Todas as transações foram salvas no banco de dados.")

if __name__ == '__main__':
    etapa_2_extracao_transacoes()