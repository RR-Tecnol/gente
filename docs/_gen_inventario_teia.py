#!/usr/bin/env python3
"""Gera tabela de inventário para RELATORIO_VITALIDADE_TEIA — executar a partir de gente/."""
import json
import re
import subprocess
import sys
from collections import Counter
from pathlib import Path

GENTE = Path(__file__).resolve().parents[1]
SRC = GENTE / "resources/gente-v3/src"
ROUTER = SRC / "router/index.js"
DASH = SRC / "layouts/DashboardLayout.vue"

out = subprocess.run(
    ["php", "artisan", "route:list", "--json"],
    cwd=str(GENTE),
    capture_output=True,
    text=True,
    check=True,
)
LRV = json.loads(out.stdout)
laroutes = []
for r in LRV:
    uri = (r.get("uri") or "").lstrip("/")
    if uri.startswith("dev/"):
        uri = uri[4:]
    mth = (r.get("method") or "GET").upper()
    act = str(r.get("action") or "")
    laroutes.append((mth, uri, act))


def method_ok(need: str, route_mth: str) -> bool:
    for one in route_mth.split("|"):
        if one.strip() == need:
            return True
    if need == "GET" and "GET" in route_mth:
        return True
    if need == "POST" and "POST" in route_mth:
        return True
    if need == "PUT" and "PUT" in route_mth:
        return True
    if need == "DELETE" and "DELETE" in route_mth:
        return True
    if need == "PATCH" and "PATCH" in route_mth:
        return True
    return False


def segs_match(req_path: str, laravel_uri: str) -> bool:
    a = [x for x in req_path.split("/") if x]
    b = [x for x in laravel_uri.split("/") if x]
    if len(a) != len(b):
        return False
    for p, q in zip(a, b):
        if q.startswith("{") and q.endswith("}"):
            continue
        if p != q:
            return False
    return True


def find_route(method: str, path: str):
    path = path.lstrip("/").split("?")[0]
    if not path:
        return None
    cands = []
    for mth, uri, act in laroutes:
        if not method_ok(method, mth):
            continue
        if segs_match(path, uri):
            cands.append((mth, uri, act))
    if not cands:
        return None
    cands.sort(key=lambda x: (x[1].count("{"), x[1]))
    return cands[0]


def backend_kind(act: str) -> str:
    if "Closure" in act and "Controller" not in act.split("::", 1)[-1]:
        if "function (" in act or "Closure" in act:
            return "Closure"
    if "Controller" in act and "@__invoke" not in act:
        return "Controller"
    if "Controller" in act:
        return "Controller"
    if "Closure" in act:
        return "Closure"
    return "?"


router = ROUTER.read_text(encoding="utf-8", errors="ignore")
cstart = router.find("children: [")
block = router[cstart : cstart + 70000] if cstart >= 0 else router
path_to_vue: dict = {}
for m in re.finditer(r"\{\s*path:\s*'([^']+)'", block):
    sub = block[m.start() : m.start() + 2000]
    imp = re.search(r"import\('\.\./(views/[^']+\.vue)'\)", sub)
    red = re.search(r"redirect:\s*'/#?([^']+)'", sub) or re.search(r"redirect:\s*'([^']+)'", sub)
    if imp:
        path_to_vue[m.group(1)] = imp.group(1)
    elif red:
        tgt = red.group(1).strip("/")
        if tgt.startswith("/"):
            tgt = tgt.strip("/")
        path_to_vue[m.group(1)] = ("REDIRECT", tgt)


def resolve_component(pth: str):
    pth = pth.strip("/")
    v = path_to_vue.get(pth)
    if v is None:
        return None
    if isinstance(v, tuple) and v[0] == "REDIRECT":
        return path_to_vue.get(v[1])
    return v


def norm_backtick(raw: str) -> str:
    s = raw.strip()
    if not s:
        return s
    s = re.sub(r"\$\{slug\}", "aprovar", s)  # atestados: aprovar|rejeitar — aprovar cobre rota canónica
    s = re.sub(r"\$\{funcId\}", "{id}", s)
    s = re.sub(r"\$\{data\.id\}", "{id}", s)
    s = re.sub(r"\$\{[^}]+\}", "{id}", s)
    return s.split("?")[0]


def extract_api(t: str):
    out = []
    for m in re.finditer(r"api\.(get|post|put|patch|delete)\s*\(\s*['\"]([^'\"]+)['\"]", t, re.I):
        out.append((m.group(1).upper(), m.group(2).split("?")[0]))
    for m in re.finditer(r"api\.(get|post|put|patch|delete)\s*\(\s*`([^`]+)`", t, re.I):
        raw = m.group(2)
        if "\n" in raw:
            continue
        if "${ep}" in raw or "abaAtual" in raw or re.match(r"^\s*\$\{", raw):
            continue  # endpoint montado em runtime (ex.: TabelasAuxiliares)
        n = norm_backtick(raw)
        if not n.startswith("/") and "api" not in n:
            continue
        out.append((m.group(1).upper(), n))
        if m.group(1).upper() == "PUT" and "/atestados/" in n and n.endswith("/aprovar"):
            out.append(("PUT", n.replace("/aprovar", "/rejeitar", 1)))
    # Tabelas auxiliares: endpoints declarados no array `abas`
    for em in re.finditer(r"endpoint:\s*'(/api/[^']+)'", t):
        out.append(("GET", em.group(1)))
    return out


dash = DASH.read_text(encoding="utf-8", errors="ignore")
nav: list = []
for m in re.finditer(r"\{ type: 'item', to: '([^']+)'", dash):
    to = m.group(1).rstrip("/")
    if not to.startswith("/"):
        to = "/" + to
    rest = dash[m.end() : m.end() + 350]
    lm = re.search(r"label: '([^']+)'", rest)
    nav.append((to, lm.group(1) if lm else to))
nav78 = nav + [
    ("/escala-matriz-v3", "Matriz de Escala (rota ativa; **não** consta de `ALL_NAV` — linha 78)"),
]

rows = []
annex_r = []

for to, label in nav78:
    n = len(rows) + 1
    rel = to.strip("/")
    mval = resolve_component(rel)
    if mval is None and rel in path_to_vue:
        raw = path_to_vue[rel]
        mval = raw[1] if isinstance(raw, tuple) else raw

    if mval is None:
        rows.append(
            {
                "n": n,
                "to": to,
                "label": label,
                "view": "— `router` **sem** child mapeada",
                "api": "—",
                "back": "—",
                "v": "FANTASMA",
            }
        )
        continue
    vfile = SRC / mval
    if not vfile.exists():
        rows.append(
            {
                "n": n,
                "to": to,
                "label": label,
                "view": f"**Falta** `{mval}`",
                "api": "—",
                "back": "—",
                "v": "FANTASMA",
            }
        )
        continue
    text = vfile.read_text(encoding="utf-8", errors="ignore")
    calls = extract_api(text)
    vshort = f"✓ `src/{mval}`"
    if not calls:
        rows.append(
            {
                "n": n,
                "to": to,
                "label": label,
                "view": vshort,
                "api": "Nenhum `api.*(…)` detetado",
                "back": "—",
                "v": "ZUMBI",
            }
        )
        continue
    uq = {}
    for meth, pth in calls:
        pth = pth.split("?")[0]
        uq[(meth, pth)] = True
    uql = list(uq.keys())
    miss = []
    hit = []
    for meth, pth in uql:
        p2 = pth.lstrip("/")
        if not p2.startswith("api/"):
            hit.append(f"`{meth} {pth}` (fora /api, não contabiliza)")
            continue
        fr = find_route(meth, p2)
        if not fr:
            miss.append((meth, pth, None))
        else:
            k = backend_kind(fr[2])
            hit.append(f"`{meth} {pth}` → `/{fr[1]}` **{k}**")

    nmiss = len([m for m in miss if m])
    nh = len([x for x in uql if x[1].lstrip("/").startswith("api/")])
    nok = nh - nmiss

    api_cell = " · ".join(f"`{a} {b}`" for a, b in uql[:10])
    if len(uql) > 10:
        api_cell += f" · … **(+{len(uql)-10}** únicos)"
    if miss:
        api_cell += " · " + " · ".join(
            f"**❌ {m} `{p}`**" for m, p, _ in miss[:5]
        )
        if len(miss) > 5:
            api_cell += f" **(+{len(miss)-5})**"

    back = f"**{nok}/{nh}** `api/…` com rota; " + (
        " · ".join(hit[:4]) if hit else "—"
    )
    if len(hit) > 4:
        back += f" …"

    v = "VIVA" if nmiss == 0 else "RACHADA"
    if nmiss:
        detail = "Endpoints **sem** rota Laravel: " + ", ".join(
            f"`{m} {p}`" for m, p, _ in miss
        )
        annex_r.append(
            (to, label, mval, v, detail)
        )
    rows.append(
        {
            "n": n,
            "to": to,
            "label": label,
            "view": vshort,
            "api": api_cell,
            "back": back,
            "v": v,
        }
    )

# Markdown
md = []
md.append("## Inventário de integração estrita (78 linhas)\n")
md.append(
    "> **Contagem:** 77 itens `type: 'item'` em `DashboardLayout.vue` (`ALL_NAV_ITEMS`) + 1 linha **78** = rota `/escala-matriz-v3` (existe no `router/index.js`, título mapeado em `routeMap`, **ausente** do `ALL_NAV`).\n"
)
md.append("### Legenda de vitalidade (critério estrito)\n")
md.append(
    "| Valor | Significado |\n|--------|-------------|\n"
    "| **VIVA** | Ficheiro `.vue` no disco; **≥1** `api.get/post/put/patch/delete`; **todos** os caminhos `api/...` (strings estáticas + `\\`...\\`\\` com `${}` normalizado) casam com **alguma** rota do `php artisan route:list` para o **mesmo** método. |\n"
    "| **ZUMBI** | Ficheiro existe, **não** há chamadas `api.*(`. (Interface sem cliente HTTP). |\n"
    "| **FANTASMA** | Item sem ficheiro `.vue` mapeado no `router` para esse `path` **ou** ficheiro em falta. |\n"
    "| **RACHADA** | Ficheiro existe, há `api.*`, mas **≥1** URL `api/...` **não** tem rota registada (fio partido; **não** cumpre meta de conectividade total). |\n"
)
md.append(
    "*(Tipos alvo: **RACHADA** não estava no pedido original de três estados — adiciona-se para marcar *parcial* sem classificar a view como viva.)*\n\n"
    "**Coluna “Backend”:** `Closure` = `Route` inline em `routes/*.php`; ainda é backend “real”, com lógica no ficheiro de rotas, não *Controller* dedicado. **Controller** = classe em `app/Http/Controllers/…` (ou `::class` em rota). *(Verificação automática por substring na coluna `action` do `route:list` — envolver verificação humana se necessário.)*\n\n"
)
md.append(
    "| # | Rota (sidebar) | Label | Status da view | Conexão API (resumo) | Status backend (resumo) | Veredito |\n"
    "|---|----------------|-------|----------------|------------------------|------------------------|----------|\n"
)
for r in rows:
    lab = (r["label"] or "").replace("\n", " ")[:200]
    md.append(
        f'| {r["n"]} | `{r["to"]}` | {lab} | {r["view"]} | {r["api"]} | {r["back"]} | **{r["v"]}** |\n'
    )

c = Counter(x["v"] for x in rows)
md.append("\n**Resumo automático:** ")
md.append(
    ", ".join(f"**{k}:** {c[k]}" for k in ("VIVA", "RACHADA", "ZUMBI", "FANTASMA",))
)
md.append("\n\n")

# Semad priority
md.append("### Abas prioritárias para conexão (SEMAD: Folha, Ponto, RH) — RACHADA ou Pior\n")
md.append(
    "Critério: módulos **Folha** (`/folha*`, `holerite*`, `remessa*`, `esocial*`, `consig*`, `verba*`, `rpps*`, `sagres*`, `transparen*`, `parametro*`, `config*folha*`, `evento*`, `remessa*`), **Ponto** (`/ponto*`, `abono*`, `falta*`, `atest*`, `banco-horas*`, `planta*`, `escala*`, `hor*extra`), **RH** (`/func*`, `autocad*`, `cargo*`, `vinc*`, `progress*`, `exon*`, `pss*`, `estag*`, `terce*`, `acum*`, `diar*`, `avali*`, `benef*`, `trein*`, `medic*`, `segur*`, `feri*`, `férias*`, `decl*`, `gest*decl*`, `org*`, `portal*`, `ouvid*`, `relat*`).\n\n"
)
semad_kw = re.compile(
    r"folha|ponto|holerit|remessa|esocial|consig|verba|rpps|sagres|transparen|abono|falta|"
    r"atest|banco-hor|func|autocad|cargo|vincul|progress|exon|pss|estag|tercei|acum|diar|avali|"
    r"benef|trein|medic|segur|feria|licen|decl|orga|portal|ouvid|relat|exoner|gesta|"
    r"configur|parametr|tabel|evento|vincu|turno|feriado|tesour|orçamento|orcamento|execu|desp|"
    r"contab|receita|municipal|controle|motor",
    re.I,
)
prio = [
    r
    for r in rows
    if r["v"] in ("RACHADA", "FANTASMA", "ZUMBI")
    and semad_kw.search((r.get("to") or "") + " " + (r.get("label") or ""))
]
if not prio:
    md.append("*Nada classificado RACHADA/FANTASMA/ZUMBI nesse conjunto semântico — alargar o critério manualmente se desejado.*\n")
else:
    md.append("| Rota | Label | Veredito | Notas |\n|------|--------|----------|--------|\n")
    for r in prio:
        md.append(f'| `{r["to"]}` | {r["label"]} | **{r["v"]}** | Ver tabela acima, coluna *Conexão API*. |\n')

# Explicit RACHADE list
md.append("\n### Fios partidos (detalhe) — entradas **RACHADA**\n")
rach = [r for r in rows if r["v"] == "RACHADA"]
if annex_r:
    for to, label, mval, _v, detail in annex_r:
        md.append(f"- **`{to}`** — {label} → `src/{mval}`: {detail}\n")
else:
    md.append("*(Nenhum.)*\n")

md.append(
    "\n*Para regenerar esta secção após alterações em rotas ou views:* `python3 docs/_gen_inventario_teia.py` (a partir do diretório `gente/`).\n"
)

sys.stdout.write("".join(md))
