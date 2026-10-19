import os
import requests

BASE_URL = "https://feiradolivro-arraialdocabo2026.nowigo.com.br"

url = f"{BASE_URL}/app/signin.mdl"

params = {
    "action": "signin",
        "username": "feiramaster",
        "password": "leitura@",
}

try:
    resposta = requests.post(
        url,
        params=params,
        timeout=30,
    )

    resposta.raise_for_status()

except requests.exceptions.Timeout:
    print("A requisição excedeu o tempo limite.")

except requests.exceptions.RequestException as erro:
    print(f"Erro na requisição: {erro}")

sessao = requests.Session()

resposta_login = sessao.post(
    f"{BASE_URL}/app/signin.mdl",
    params={
        "action": "signin",
        "username": "feiramaster",
        "password": "leitura@",
    },
    timeout=15,
)

cookies = sessao.cookies.get_dict()
jsessionid = cookies.get("JSESSIONID")

if not jsessionid:
    raise RuntimeError("O login não retornou o cookie JSESSIONID.")


url = f"{BASE_URL}/app/sale.mdl"

params = {
    "action": "list",
    "eventId": 1104,
    "userId": 55240,
    "page": 1,
    "perPage": 10,
    "orderBy": "",
    "orderByDir": "",
    "search": "",
}

headers = {
    "Cookie": f"JSESSIONID={jsessionid}",
    "Accept": "*/*",
}

try:
    resposta = requests.get(
        url,
        params=params,
        headers=headers,
        timeout=30,
    )

    print("Resposta:")
    print(resposta.text)

    resposta.raise_for_status()

except requests.exceptions.Timeout:
    print("A requisição excedeu o tempo limite.")

except requests.exceptions.RequestException as erro:
    print(f"Erro na requisição: {erro}")