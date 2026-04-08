import requests
import sqlite3
import time
import sys

def etapa_3_extracao_detalhes():
    conn = sqlite3.connect('feira_livro.db')
    cursor = conn.cursor()

    # Tabelas já devem estar criadas, mas mantemos por segurança
    cursor.execute('''
        CREATE TABLE IF NOT EXISTS pagamentos (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sellNumber TEXT,
            tagCode TEXT,
            cpf TEXT,
            paymentWay TEXT,
            payment_id INTEGER,
            value TEXT,
            payment_group TEXT
        )
    ''')

    cursor.execute('''
        CREATE TABLE IF NOT EXISTS itens_venda (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            sellNumber TEXT,
            product_id INTEGER,
            name TEXT,
            amount INTEGER,
            unitValue TEXT,
            totalValue TEXT
        )
    ''')
    conn.commit()

    print("Iniciando extração de detalhes da API (Fase 3) com Sessão Persistente...")

    cursor.execute('SELECT DISTINCT sellNumber, type FROM vendas_header WHERE processado = 0')
    vendas_pendentes = cursor.fetchall()
    
    total_pendentes = len(vendas_pendentes)
    print(f"Total de vendas únicas pendentes: {total_pendentes}\n")

    if total_pendentes == 0:
        print("Todas as vendas já foram processadas! Pode avançar para a Fase 4.")
        conn.close()
        return

    url = "https://feiradolivro-saquarema2025.nowigo.com.br/app/sale.mdl"
    
    # 1. CRIANDO A SESSÃO PERSISTENTE (Resolve 99% dos problemas de conexão)
    session = requests.Session()
    
    count = 0
    for sellNumber, saleType in vendas_pendentes:
        count += 1
        params = {
            'action': 'detail',
            'saleId': sellNumber,
            'saleType': saleType
        }
        
        # 2. SISTEMA DE RETENTATIVAS
        max_tentativas = 3
        sucesso_requisicao = False
        json_data = {}
        
        for tentativa in range(max_tentativas):
            try:
                # Usando session.get no lugar de requests.get
                res = session.get(url, params=params, timeout=20)
                
                if res.ok:
                    json_data = res.json()
                    sucesso_requisicao = True
                    break # Sai do loop de tentativas pois deu certo
                else:
                    # Se deu erro 500, etc., espera 2 segundos e tenta de novo
                    time.sleep(2)
                    
            except Exception as e:
                # Se deu timeout, avisa na tela e espera 2 segundos
                if tentativa < max_tentativas - 1:
                    sys.stdout.write(f"\r[Aviso] Timeout na venda {sellNumber}. Tentando de novo ({tentativa+1}/{max_tentativas})...")
                    sys.stdout.flush()
                    time.sleep(2)
                else:
                    print(f"\nErro definitivo ao processar a venda {sellNumber} após 3 tentativas. Detalhe: {e}")
        
        # Se falhou nas 3 tentativas, pula para a próxima venda
        if not sucesso_requisicao:
            continue
            
        # O resto do código continua igual, processando os dados extraídos
        data = json_data.get('data', {})
        
        if not data:
            cursor.execute('UPDATE vendas_header SET processado = 1 WHERE sellNumber = ?', (sellNumber,))
            continue

        payments = data.get('payments', [])
        products = data.get('products', [])
        
        if payments:
            pagamentos_records = [
                (sellNumber, p.get('tagCode'), p.get('cpf'), p.get('paymentWay'),
                 p.get('id'), p.get('value'), p.get('group')) for p in payments
            ]
            cursor.executemany('''
                INSERT INTO pagamentos (sellNumber, tagCode, cpf, paymentWay, payment_id, value, payment_group)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ''', pagamentos_records)
        
        if products:
            produtos_records = [
                (sellNumber, pr.get('id'), pr.get('name'), pr.get('amount'),
                 pr.get('unitValue'), pr.get('totalValue')) for pr in products
            ]
            cursor.executemany('''
                INSERT INTO itens_venda (sellNumber, product_id, name, amount, unitValue, totalValue)
                VALUES (?, ?, ?, ?, ?, ?)
            ''', produtos_records)
        
        cursor.execute('UPDATE vendas_header SET processado = 1 WHERE sellNumber = ?', (sellNumber,))
        
        if count % 50 == 0:
            conn.commit()
            
        percentual = (count / total_pendentes) * 100
        sys.stdout.write(f"\rProcessando detalhes: Venda {count}/{total_pendentes} --- {percentual:.2f}% concluído")
        sys.stdout.flush()
            
        time.sleep(0.05)

    conn.commit()
    conn.close()
    print("\n\nFase 3 concluída! Todos os detalhes das vendas foram extraídos.")

if __name__ == '__main__':
    etapa_3_extracao_detalhes()