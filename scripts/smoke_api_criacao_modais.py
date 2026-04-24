#!/usr/bin/env python3
"""
Smoke HTTP: login admin + chamadas GET/POST típicas dos modais de criação (via proxy Vite ou Laravel direto).
Uso: python3 scripts/smoke_api_criacao_modais.py [--base URL]
Saída: docs/smoke-api-criacao-RESULTADO.json + stdout resumido.
"""
from __future__ import annotations

import argparse
import json
import random
import sys
import time
from datetime import date, timedelta
from urllib.parse import unquote

import requests


def pick_base(candidates: list[str]) -> str | None:
    for b in candidates:
        try:
            r = requests.get(b.rstrip("/") + "/csrf-cookie", timeout=3)
            if r.status_code in (200, 204):
                return b.rstrip("/")
        except requests.RequestException:
            continue
    return None


def main() -> int:
    ap = argparse.ArgumentParser()
    ap.add_argument("--base", default="", help="Ex: http://127.0.0.1:5173 ou http://127.0.0.1:8081")
    args = ap.parse_args()
    bases = [args.base] if args.base else [
        "http://127.0.0.1:5173",
        "http://127.0.0.1:8081",
    ]
    base = pick_base([b for b in bases if b])
    if not base:
        print("ERRO: nenhum servidor respondeu em /csrf-cookie (5173 ou 8081). Suba Vite ou nginx.")
        return 2

    s = requests.Session()
    s.get(base + "/csrf-cookie", timeout=15)

    def hdr():
        return {
            "X-XSRF-TOKEN": unquote(s.cookies.get("XSRF-TOKEN", "")),
            "X-Requested-With": "XMLHttpRequest",
            "Accept": "application/json",
        }

    login = s.post(
        base + "/api/auth/login",
        json={"USUARIO_LOGIN": "admin", "USUARIO_SENHA": "admin123"},
        headers=hdr(),
        timeout=20,
    )
    results: list[dict] = []

    def run(name: str, method: str, path: str, **kwargs) -> dict:
        url = base + path
        try:
            r = s.request(method, url, headers=hdr(), timeout=45, **kwargs)
            body = (r.text or "")[:500]
            try:
                j = r.json()
                body = json.dumps(j, ensure_ascii=False)[:500]
            except Exception:
                pass
            row = {"name": name, "method": method, "path": path, "status": r.status_code, "body_preview": body}
        except requests.RequestException as e:
            row = {"name": name, "method": method, "path": path, "status": 0, "error": str(e)}
        results.append(row)
        st = row.get("status", "?")
        print(f"{st}\t{method}\t{path}\t{name}")
        return row

    print(f"BASE={base}\nlogin={login.status_code}")
    if login.status_code != 200:
        print(login.text[:400])
        return 1

    today = date.today()
    tomorrow = today + timedelta(days=1)
    comp = today.strftime("%Y-%m")
    mes, ano = today.month, today.year

    # --- Pré-requisitos (IDs reais) ---
    run("GET setores", "GET", "/api/v3/setores")
    se = s.get(base + "/api/v3/setores", headers=hdr(), timeout=20)
    setor_id = 1
    try:
        sj = se.json()
        arr = sj.get("setores") or sj.get("data") or []
        if isinstance(arr, list) and arr:
            first = arr[0]
            setor_id = int(first.get("id") or first.get("SETOR_ID") or 1)
    except Exception:
        pass

    run("GET servidores/buscar", "GET", "/api/v3/servidores/buscar?q=a")
    sb = s.get(base + "/api/v3/servidores/buscar", params={"q": "a"}, headers=hdr(), timeout=20)
    fid1, fid2 = 1, 2
    try:
        bj = sb.json()
        servs = bj.get("servidores") or []
        if len(servs) >= 1:
            fid1 = int(servs[0].get("id") or servs[0].get("FUNCIONARIO_ID") or 1)
        if len(servs) >= 2:
            fid2 = int(servs[1].get("id") or servs[1].get("FUNCIONARIO_ID") or 2)
        elif fid1 != 2:
            fid2 = fid1
    except Exception:
        pass

    run("GET escalas", "GET", "/api/v3/escalas")
    eg = s.get(base + "/api/v3/escalas", headers=hdr(), timeout=20)
    escala_id = 1
    try:
        ej = eg.json()
        rows = ej if isinstance(ej, list) else ej.get("escalas") or ej.get("data") or []
        if isinstance(rows, list) and rows:
            row0 = rows[0]
            escala_id = int(row0.get("ESCALA_ID") or row0.get("escala_id") or row0.get("id") or 1)
    except Exception:
        pass

    # --- Criação / mutação (payloads alinhados às views) ---
    run("POST ponto/registro", "POST", "/api/v3/ponto/registro", json={"tipo": "entrada", "origem": "smoke"})

    run(
        "POST escala-trabalho",
        "POST",
        "/api/v3/escala-trabalho",
        json={
            "setor_id": setor_id,
            "funcionario_id": fid1,
            "data": tomorrow.isoformat(),
            "turno": "M",
        },
    )

    run(
        "POST escalas (médica)",
        "POST",
        "/api/v3/escalas",
        json={"mes": f"{mes:02d}", "ano": str(ano), "setor_id": setor_id},
    )

    run(
        "POST substituicoes",
        "POST",
        "/api/v3/substituicoes",
        json={
            "escala_id": escala_id,
            "solicitante_id": fid1,
            "substituto_id": fid2,
            "data_plantao": tomorrow.isoformat(),
            "turno": "M",
            "motivo": "Smoke automático",
        },
    )

    run(
        "POST ferias",
        "POST",
        "/api/v3/ferias",
        json={
            "inicio": (today + timedelta(days=30)).isoformat(),
            "fim": (today + timedelta(days=37)).isoformat(),
            "abono": False,
        },
    )

    run(
        "POST afastamentos",
        "POST",
        "/api/v3/afastamentos",
        json={
            "tipo": "Licença médica",
            "inicio": (today + timedelta(days=5)).isoformat(),
            "fim": (today + timedelta(days=7)).isoformat(),
            "obs": "Smoke automático",
        },
    )

    run(
        "POST organograma/setor",
        "POST",
        "/api/v3/organograma/setor",
        json={
            "nome": f"Setor Smoke {today.isoformat()}",
            "sigla": "SMK",
            "responsavel": "Smoke Bot",
            "funcionario_id": fid1,
        },
    )

    run(
        "POST declaracoes",
        "POST",
        "/api/v3/declaracoes",
        json={"nome": "Declaração de tempo de serviço", "instantaneo": False},
    )

    run(
        "POST avaliacoes",
        "POST",
        "/api/v3/avaliacoes",
        json={
            "funcionario_id": fid1,
            "ciclo": comp,
            "criterios": [
                {"nome": "Assiduidade", "peso": 1, "nota": 8, "obs": "Smoke"},
            ],
        },
    )

    run(
        "POST plantoes-extras",
        "POST",
        "/api/v3/plantoes-extras",
        json={
            "data": tomorrow.isoformat(),
            "horaIni": "07:00",
            "horaFim": "19:00",
            "tipo": "programado",
            "setor": "UTI Adulto",
            "justificativa": "Smoke automático",
        },
    )

    run(
        "POST sobreaviso/acionamento",
        "POST",
        "/api/v3/sobreaviso/acionamento",
        json={
            "data": today.isoformat(),
            "local": "UTI",
            "hora_ini": "08:00",
            "hora_fim": "10:00",
            "motivo": "Smoke automático",
        },
    )

    run(
        "POST hora-extra",
        "POST",
        "/api/v3/hora-extra",
        json={
            "funcionario_id": fid1,
            "competencia": comp,
            "data_realizacao": today.isoformat(),
            "hora_inicio": "18:00",
            "hora_fim": "20:00",
            "total_horas": 2,
            "tipo_hora_extra": "50_PORCENTO",
            "percentual": 50,
            "observacao": "Smoke automático",
        },
    )

    run(
        "POST plantao-extra",
        "POST",
        "/api/v3/plantao-extra",
        json={
            "funcionario_id": fid1,
            "competencia": comp,
            "data_plantao": today.isoformat(),
            "hora_inicio": "19:00",
            "hora_fim": "23:00",
            "total_horas": 4,
            "valor_hora_plantao": 40,
            "horas_noturnas": 1,
        },
    )

    run(
        "POST diarias",
        "POST",
        "/api/v3/diarias",
        json={
            "funcionario_id": fid1,
            "destino": "São Luís",
            "objetivo": "Smoke automático",
            "data_ida": today.isoformat(),
            "data_volta": tomorrow.isoformat(),
            "destino_tipo": "CAPITAL_MA",
        },
    )

    # CPF único por execução (índice estagiario_cpf_unique no SQL Server).
    cpf_smoke = f"{random.randint(1, 9)}{abs(int(time.time() * 1_000_000)) % 10**10:010d}"
    run(
        "POST estagiarios",
        "POST",
        "/api/v3/estagiarios",
        json={
            "nome": "Smoke Estagiário",
            "cpf": cpf_smoke,
            "instituicao_ensino": "Universidade Smoke",
            "agente_integracao": "CIEE",
            "curso": "ADS",
            "periodo_letivo": "2026.1",
            "setor_id": setor_id,
            "data_inicio": today.isoformat(),
            "data_fim": (today + timedelta(days=180)).isoformat(),
            "carga_hr_dia": 6,
            "bolsa_valor": 800,
            "auxilio_transporte": 50,
        },
    )

    run(
        "POST seguranca/epis",
        "POST",
        "/api/v3/seguranca/epis",
        json={"nome": "Epi Smoke", "obs": "teste", "ico": "🦺"},
    )

    run(
        "POST seguranca/incidentes",
        "POST",
        "/api/v3/seguranca/incidentes",
        json={
            "tipo": "Quase acidente",
            "local": "Almoxarifado",
            "descricao": "Smoke automático",
        },
    )

    run(
        "POST medicina/agendar",
        "POST",
        "/api/v3/medicina/agendar",
        json={
            "funcionario_id": fid1,
            "tipo_exame": "Admissional",
            "data": tomorrow.isoformat(),
            "hora": "09:00",
            "obs": "Smoke",
        },
    )

    # GETs de leitura (sidebar / listas)
    for path in [
        "/api/v3/ponto",
        "/api/v3/banco-horas",
        "/api/v3/ferias",
        "/api/v3/afastamentos",
        "/api/v3/organograma",
        "/api/v3/escala-trabalho",
        "/api/v3/escalas",
        "/api/v3/substituicoes",
        "/api/v3/hora-extra",
        "/api/v3/plantao-extra",
        "/api/v3/plantoes-extras",
        "/api/v3/sobreaviso",
        "/api/v3/declaracoes",
        "/api/v3/avaliacoes",
        "/api/v3/diarias",
        "/api/v3/estagiarios",
    ]:
        run(f"GET {path}", "GET", path)

    out_path = "docs/smoke-api-criacao-RESULTADO.json"
    with open(out_path, "w", encoding="utf-8") as f:
        json.dump({"base": base, "results": results}, f, ensure_ascii=False, indent=2)
    print(f"\nJSON salvo em {out_path}")

    ok = sum(1 for r in results if 200 <= (r.get("status") or 0) < 300)
    bad = [r for r in results if (r.get("status") or 0) >= 400]
    print(f"\nResumo: {ok}/{len(results)} respostas 2xx. Falhas: {len(bad)}")
    for r in bad[:25]:
        print(f"  FAIL {r.get('status')}\t{r.get('method')}\t{r.get('path')}\t{r.get('name')}")
    return 0 if len(bad) == 0 else 1


if __name__ == "__main__":
    sys.exit(main())
