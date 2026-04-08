import argparse
import asyncio
import html
import json
import os
import re
import time
from dataclasses import dataclass
from datetime import datetime
from decimal import Decimal, InvalidOperation
from typing import Any

import httpx
import psycopg


@dataclass
class ETLConfig:
    base_url: str
    event_id: str
    user_id: str
    id_feira: int
    nome_feira: str
    date_time_begin: str # Usado para o filtro da API Nowigo
    date_time_end: str   # Usado para o filtro da API Nowigo
    per_page: int = 100
    timeout_seconds: int = 30
    max_retries: int = 3
    retry_seconds: int = 2


def normalize_text(value: Any) -> str | None:
    if value is None:
        return None
    text = str(value).replace("\x00", "")
    text = html.unescape(text)
    text = text.strip()
    if not text:
        return None
    return text.upper()


def parse_money(value: Any) -> Decimal | None:
    if value is None:
        return None

    text = str(value).replace("\x00", "").strip()
    if not text:
        return None

    text = text.replace("R$", "").replace(" ", "")
    if "," in text:
        text = text.replace(".", "").replace(",", ".")

    text = re.sub(r"[^0-9\-.]", "", text)
    if not text:
        return None

    try:
        return Decimal(text)
    except InvalidOperation:
        return None


def parse_datetime(value: Any) -> datetime | None:
    if value is None:
        return None

    text = str(value).strip()
    if not text:
        return None

    for fmt in ("%d/%m/%Y %H:%M:%S", "%Y-%m-%d %H:%M:%S"):
        try:
            return datetime.strptime(text, fmt)
        except ValueError:
            continue

    try:
        return datetime.fromisoformat(text.replace("Z", "+00:00"))
    except ValueError:
        return None


class ETLPipeline:
    def __init__(self, dsn: str, config: ETLConfig) -> None:
        self.dsn = dsn
        self.config = config

    async def run(self) -> None:
        print("Iniciando pipeline unificado (PostgreSQL + API Nowigo)...")
        start = time.time()

        # Usando a conexão assíncrona do psycopg3 (Ideal para o FastAPI)
        async with await psycopg.AsyncConnection.connect(self.dsn) as conn:
            await self._ensure_schema(conn)
            await self._upsert_feira(conn)
            await conn.commit()

        await self._extract_and_transform()

        elapsed = time.time() - start
        print(f"Pipeline concluido com sucesso em {elapsed:.1f}s.")

    async def _ensure_schema(self, conn: psycopg.AsyncConnection) -> None:
        async with conn.cursor() as cur:
            await cur.execute(
                """
                CREATE TABLE IF NOT EXISTS feiras (
                    id INTEGER PRIMARY KEY,
                    evento_id_api TEXT NOT NULL,
                    nome TEXT NOT NULL,
                    created_at TIMESTAMP DEFAULT NOW(),
                    updated_at TIMESTAMP DEFAULT NOW()
                )
                """
            )

            await cur.execute(
                """
                CREATE TABLE IF NOT EXISTS vendas_header (
                    id BIGSERIAL PRIMARY KEY,
                    id_feira INTEGER NOT NULL REFERENCES feiras(id),
                    sell_number TEXT NOT NULL,
                    sale_type INTEGER NULL,
                    total_value NUMERIC(12, 2) NULL,
                    date_hour TIMESTAMP NULL,
                    box TEXT NULL,
                    processado BOOLEAN DEFAULT FALSE,
                    raw_payload JSONB NULL,
                    created_at TIMESTAMP DEFAULT NOW(),
                    updated_at TIMESTAMP DEFAULT NOW(),
                    CONSTRAINT uq_vendas_header UNIQUE (id_feira, sell_number)
                )
                """
            )

            await cur.execute(
                """
                CREATE TABLE IF NOT EXISTS pagamentos (
                    id BIGSERIAL PRIMARY KEY,
                    id_feira INTEGER NOT NULL REFERENCES feiras(id),
                    sell_number TEXT NOT NULL,
                    pagamento_id_api BIGINT NULL,
                    tag_code TEXT NULL,
                    cpf TEXT NULL,
                    payment_way TEXT NULL,
                    value NUMERIC(12, 2) NULL,
                    payment_group TEXT NULL,
                    raw_payload JSONB NULL,
                    created_at TIMESTAMP DEFAULT NOW()
                )
                """
            )

            await cur.execute(
                """
                CREATE TABLE IF NOT EXISTS itens_venda (
                    id BIGSERIAL PRIMARY KEY,
                    id_feira INTEGER NOT NULL REFERENCES feiras(id),
                    sell_number TEXT NOT NULL,
                    produto_id_api BIGINT NULL,
                    name TEXT NULL,
                    amount INTEGER NULL,
                    unit_value NUMERIC(12, 2) NULL,
                    total_value NUMERIC(12, 2) NULL,
                    raw_payload JSONB NULL,
                    created_at TIMESTAMP DEFAULT NOW()
                )
                """
            )

            await cur.execute(
                """
                CREATE TABLE IF NOT EXISTS livros (
                    id BIGSERIAL PRIMARY KEY,
                    id_feira INTEGER NOT NULL REFERENCES feiras(id),
                    produto_id_api BIGINT NOT NULL,
                    categoria TEXT NULL,
                    produto TEXT NULL,
                    valor NUMERIC(12, 2) NULL,
                    editora TEXT DEFAULT 'NAO INFORMADO',
                    representante TEXT DEFAULT 'NAO INFORMADO',
                    isbn TEXT DEFAULT 'NAO INFORMADO',
                    created_at TIMESTAMP DEFAULT NOW(),
                    updated_at TIMESTAMP DEFAULT NOW(),
                    CONSTRAINT uq_livros UNIQUE (id_feira, produto_id_api)
                )
                """
            )

            await cur.execute(
                """
                CREATE TABLE IF NOT EXISTS cartoes (
                    id BIGSERIAL PRIMARY KEY,
                    id_feira INTEGER NOT NULL REFERENCES feiras(id),
                    tag_code TEXT NOT NULL,
                    grupo TEXT NULL,
                    codigo TEXT NULL,
                    created_at TIMESTAMP DEFAULT NOW(),
                    updated_at TIMESTAMP DEFAULT NOW(),
                    CONSTRAINT uq_cartoes UNIQUE (id_feira, tag_code)
                )
                """
            )

            await cur.execute("CREATE INDEX IF NOT EXISTS idx_vendas_header_feira_sell ON vendas_header(id_feira, sell_number)")
            await cur.execute("CREATE INDEX IF NOT EXISTS idx_pagamentos_feira_sell ON pagamentos(id_feira, sell_number)")
            await cur.execute("CREATE INDEX IF NOT EXISTS idx_itens_venda_feira_sell ON itens_venda(id_feira, sell_number)")

    async def _upsert_feira(self, conn: psycopg.AsyncConnection) -> None:
        async with conn.cursor() as cur:
            # Removidas as colunas data_inicio e data_fim para evitar que um filtro temporal
            # restrito (ex: apenas as vendas da tarde) sobrescreva as datas oficiais do evento.
            await cur.execute(
                """
                INSERT INTO feiras (id, evento_id_api, nome, updated_at)
                VALUES (%s, %s, %s, NOW())
                ON CONFLICT (id)
                DO UPDATE SET
                    evento_id_api = EXCLUDED.evento_id_api,
                    nome = EXCLUDED.nome,
                    updated_at = NOW()
                """,
                (
                    self.config.id_feira,
                    self.config.event_id,
                    self.config.nome_feira,
                ),
            )

    async def _extract_and_transform(self) -> None:
        timeout = httpx.Timeout(self.config.timeout_seconds)
        async with httpx.AsyncClient(timeout=timeout) as client:
            total_pages = await self._extract_headers(client)
            print(f"Cabecalhos carregados. Paginas processadas: {total_pages}")
            await self._extract_details(client)

    async def _extract_headers(self, client: httpx.AsyncClient) -> int:
        params = {
            "action": "list",
            "eventId": self.config.event_id,
            "userId": self.config.user_id,
            "perPage": self.config.per_page,
            "dateTimeBegin": self.config.date_time_begin,
            "dateTimeEnd": self.config.date_time_end,
            "search": "",
            "page": 1,
        }

        response = await client.get(self.config.base_url, params=params)
        response.raise_for_status()
        payload = response.json()

        pagination = payload.get("pagination", {})
        total_pages = int(pagination.get("totalPages", 1))
        total_items = int(pagination.get("totalItems", 0))

        print(f"Total de vendas previstas: {total_items}")
        print(f"Total de paginas na API: {total_pages}")

        async with await psycopg.AsyncConnection.connect(self.dsn) as conn:
            for page in range(1, total_pages + 1):
                params["page"] = page
                page_response = await client.get(self.config.base_url, params=params)
                page_response.raise_for_status()
                page_data = page_response.json().get("data", [])

                records = []
                for sale in page_data:
                    records.append(
                        (
                            self.config.id_feira,
                            str(sale.get("sellNumber")),
                            sale.get("type"),
                            parse_money(sale.get("totalValue")),
                            parse_datetime(sale.get("dateHour")),
                            normalize_text(sale.get("box")),
                            json.dumps(sale, ensure_ascii=True),
                        )
                    )

                async with conn.cursor() as cur:
                    await cur.executemany(
                        """
                        INSERT INTO vendas_header
                            (id_feira, sell_number, sale_type, total_value, date_hour, box, processado, raw_payload)
                        VALUES
                            (%s, %s, %s, %s, %s, %s, FALSE, %s::jsonb)
                        ON CONFLICT (id_feira, sell_number)
                        DO UPDATE SET
                            sale_type = EXCLUDED.sale_type,
                            total_value = EXCLUDED.total_value,
                            date_hour = EXCLUDED.date_hour,
                            box = EXCLUDED.box,
                            raw_payload = EXCLUDED.raw_payload,
                            processado = FALSE,
                            updated_at = NOW()
                        """,
                        records,
                    )
                await conn.commit()

                pct = (page / total_pages) * 100
                print(f"[Header] Pagina {page}/{total_pages} ({pct:.2f}%)")

        return total_pages

    async def _extract_details(self, client: httpx.AsyncClient) -> None:
        async with await psycopg.AsyncConnection.connect(self.dsn) as conn:
            async with conn.cursor() as cur:
                await cur.execute(
                    """
                    SELECT sell_number, sale_type
                    FROM vendas_header
                    WHERE id_feira = %s AND processado = FALSE
                    ORDER BY date_hour NULLS LAST, sell_number
                    """,
                    (self.config.id_feira,),
                )
                pendentes = await cur.fetchall()

        total_pendentes = len(pendentes)
        print(f"Total de vendas pendentes para detalhamento: {total_pendentes}")

        if total_pendentes == 0:
            print("Nenhuma venda pendente. Etapa de detalhes finalizada.")
            return

        for i, (sell_number, sale_type) in enumerate(pendentes, start=1):
            detail_payload = await self._fetch_sale_detail(client, sell_number, sale_type)
            if detail_payload is None:
                continue

            await self._replace_sale_details(sell_number, detail_payload)

            pct = (i / total_pendentes) * 100
            print(f"[Detail] Venda {i}/{total_pendentes} ({pct:.2f}%)")

    async def _fetch_sale_detail(
        self,
        client: httpx.AsyncClient,
        sell_number: str,
        sale_type: int | None,
    ) -> dict[str, Any] | None:
        params = {
            "action": "detail",
            "saleId": sell_number,
            "saleType": sale_type,
        }

        for attempt in range(1, self.config.max_retries + 1):
            try:
                response = await client.get(self.config.base_url, params=params)
                response.raise_for_status()
                return response.json().get("data", {})
            except Exception as exc:
                if attempt == self.config.max_retries:
                    print(f"Falha definitiva na venda {sell_number}: {exc}")
                    return None
                print(
                    f"Falha temporaria na venda {sell_number} (tentativa {attempt}/{self.config.max_retries})."
                )
                await asyncio.sleep(self.config.retry_seconds)

        return None

    async def _replace_sale_details(self, sell_number: str, data: dict[str, Any]) -> None:
        payments = data.get("payments", []) or []
        products = data.get("products", []) or []

        async with await psycopg.AsyncConnection.connect(self.dsn) as conn:
            async with conn.cursor() as cur:
                # Idempotencia por venda: remove detalhes antigos e reinsere o snapshot atual.
                await cur.execute(
                    "DELETE FROM pagamentos WHERE id_feira = %s AND sell_number = %s",
                    (self.config.id_feira, sell_number),
                )
                await cur.execute(
                    "DELETE FROM itens_venda WHERE id_feira = %s AND sell_number = %s",
                    (self.config.id_feira, sell_number),
                )

                if payments:
                    payment_records = [
                        (
                            self.config.id_feira,
                            sell_number,
                            p.get("id"),
                            normalize_text(p.get("tagCode")),
                            normalize_text(p.get("cpf")),
                            normalize_text(p.get("paymentWay")),
                            parse_money(p.get("value")),
                            normalize_text(p.get("group")),
                            json.dumps(p, ensure_ascii=True),
                        )
                        for p in payments
                    ]
                    await cur.executemany(
                        """
                        INSERT INTO pagamentos
                            (id_feira, sell_number, pagamento_id_api, tag_code, cpf, payment_way, value, payment_group, raw_payload)
                        VALUES
                            (%s, %s, %s, %s, %s, %s, %s, %s, %s::jsonb)
                        """,
                        payment_records,
                    )

                    # Filtrar tagCodes que sao vazias ou "NÃO DISPONÍVEL" antes de inserir na tabela de cartões
                    card_records = []
                    for p in payments:
                        tag = normalize_text(p.get("tagCode"))
                        if tag and tag not in ["NÃO DISPONÍVEL", "NAO DISPONIVEL", "N/A"]:
                            card_records.append((
                                self.config.id_feira,
                                tag,
                                normalize_text(p.get("group"))
                            ))

                    if card_records:
                        await cur.executemany(
                            """
                            INSERT INTO cartoes (id_feira, tag_code, grupo, updated_at)
                            VALUES (%s, %s, %s, NOW())
                            ON CONFLICT (id_feira, tag_code)
                            DO UPDATE SET
                                grupo = EXCLUDED.grupo,
                                updated_at = NOW()
                            """,
                            card_records,
                        )

                if products:
                    product_records = [
                        (
                            self.config.id_feira,
                            sell_number,
                            pr.get("id"),
                            normalize_text(pr.get("name")),
                            pr.get("amount"),
                            parse_money(pr.get("unitValue")),
                            parse_money(pr.get("totalValue")),
                            json.dumps(pr, ensure_ascii=True),
                        )
                        for pr in products
                    ]
                    await cur.executemany(
                        """
                        INSERT INTO itens_venda
                            (id_feira, sell_number, produto_id_api, name, amount, unit_value, total_value, raw_payload)
                        VALUES
                            (%s, %s, %s, %s, %s, %s, %s, %s::jsonb)
                        """,
                        product_records,
                    )

                    book_records = [
                        (
                            self.config.id_feira,
                            pr.get("id"),
                            normalize_text(pr.get("name")),
                            parse_money(pr.get("unitValue")),
                        )
                        for pr in products
                        if pr.get("id") is not None
                    ]
                    if book_records:
                        await cur.executemany(
                            """
                            INSERT INTO livros (id_feira, produto_id_api, produto, valor, updated_at)
                            VALUES (%s, %s, %s, %s, NOW())
                            ON CONFLICT (id_feira, produto_id_api)
                            DO UPDATE SET
                                produto = COALESCE(EXCLUDED.produto, livros.produto),
                                valor = COALESCE(EXCLUDED.valor, livros.valor),
                                updated_at = NOW()
                            """,
                            book_records,
                        )

                await cur.execute(
                    """
                    UPDATE vendas_header
                    SET processado = TRUE, updated_at = NOW()
                    WHERE id_feira = %s AND sell_number = %s
                    """,
                    (self.config.id_feira, sell_number),
                )

            await conn.commit()


def build_parser() -> argparse.ArgumentParser:
    parser = argparse.ArgumentParser(
        description="Pipeline unificado (guia) para extracao e transformacao via API Nowigo em PostgreSQL."
    )
    parser.add_argument("--dsn", default=os.getenv("DATABASE_URL"), help="DSN de conexao PostgreSQL")
    parser.add_argument(
        "--base-url",
        default="https://feiradolivro-saquarema2025.nowigo.com.br/app/sale.mdl",
        help="Endpoint base da API",
    )
    parser.add_argument("--event-id", required=True, help="eventId da API")
    parser.add_argument("--user-id", required=True, help="userId da API")
    parser.add_argument("--id-feira", type=int, required=True, help="ID interno da feira")
    parser.add_argument("--nome-feira", required=True, help="Nome da feira")
    parser.add_argument("--date-begin", required=True, help="Data inicial no formato dd/mm/yyyy HH:MM:SS")
    parser.add_argument("--date-end", required=True, help="Data final no formato dd/mm/yyyy HH:MM:SS")
    parser.add_argument("--per-page", type=int, default=100, help="Quantidade por pagina na acao list")
    parser.add_argument("--timeout", type=int, default=30, help="Timeout HTTP em segundos")
    parser.add_argument("--max-retries", type=int, default=3, help="Tentativas por venda no detail")
    parser.add_argument("--retry-seconds", type=int, default=2, help="Espera entre tentativas")
    return parser


def main() -> None:
    parser = build_parser()
    args = parser.parse_args()

    if not args.dsn:
        raise ValueError("Informe --dsn ou configure DATABASE_URL.")

    config = ETLConfig(
        base_url=args.base_url,
        event_id=args.event_id,
        user_id=args.user_id,
        id_feira=args.id_feira,
        nome_feira=args.nome_feira,
        date_time_begin=args.date_begin,
        date_time_end=args.date_end,
        per_page=args.per_page,
        timeout_seconds=args.timeout,
        max_retries=args.max_retries,
        retry_seconds=args.retry_seconds,
    )

    pipeline = ETLPipeline(dsn=args.dsn, config=config)
    asyncio.run(pipeline.run())


if __name__ == "__main__":
    main()