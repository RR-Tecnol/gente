#!/usr/bin/env python3
"""Corta gente/routes/web.php e gera fachada api v3. Linhas: intervalos 1-based [a,b] inclusive."""
from pathlib import Path

DIR = Path(__file__).resolve().parent.parent / "routes"
WEB = DIR / "web.php"

# 1-based inclusive line numbers (verificar com: sed -n 'START,ENDp' web.php | head)
SLICES = {
    "head": (1, 806),
    "p1": (810, 1422),  # interior 1.º bloco auth
    "mid": (1425, 2263),  # após L1424 `});` do 1.º grupo; até L2263 (comentários antes do 2.º Route)
    "p2": (2265, 4191),  # interior 2.º bloco auth (excl. L2264)
    "pub": (4196, 6334),  # corpo [web] só (L4195=Route; última rota fecha em L6334)
    # Não incluir L6336 `});` (fecho do grupo [web] antigo — já vem no block3)
    "pre_legacy": (6337, 6343),  # vazios + comentários antes do legacy
    "leg": (6345, 6404),  # interior 2.º bloco legado (L6344=Route, excl.)
}

# Em p2, apagar 1-based L2686-2748 (GET /ponto duplicado)
P2_CUT_1B = (2686, 2748)
# Em pub, substituir 1-based L4933-5068 por require pono
PUB_CUT_1B = (4933, 5068)

PHP_HDR = '<?php\n// gerado — não editar cegamente (regen_api_v3_fachada.py)\n\n'


def lines_to_idx(a1: int, b1: int) -> tuple[int, int]:
    """[a1,b1] 1-based -> [i0, i1) 0-based slice."""
    return a1 - 1, b1  # b1 exclusive: last line b1 = index b1-1, end = b1


def main() -> None:
    raw = WEB.read_text(encoding="utf-8", errors="replace")
    L = raw.splitlines(keepends=True)

    i0, i1 = lines_to_idx(*SLICES["p1"])
    p1 = L[i0:i1]
    (DIR / "api_v3_auth_part1.php").write_text(PHP_HDR + "".join(p1), encoding="utf-8")

    i0, i1 = lines_to_idx(*SLICES["p2"])
    p2 = L[i0:i1]
    (ca, cb) = P2_CUT_1B
    a = ca - SLICES["p2"][0]  # offset 0-based dentro de p2
    b = cb - SLICES["p2"][0] + 1
    p2 = p2[:a] + p2[b:]
    (DIR / "api_v3_auth_part2.php").write_text(PHP_HDR + "".join(p2), encoding="utf-8")

    i0, i1 = lines_to_idx(*SLICES["pub"])
    pub = L[i0:i1]
    (pa, pb) = PUB_CUT_1B
    a = pa - SLICES["pub"][0]
    b = pb - SLICES["pub"][0] + 1
    ins = "    require __DIR__ . '/ponto_mes_spa_get.php';\n\n"
    pub = pub[:a] + [ins] + pub[b:]
    (DIR / "api_v3_web_part1.php").write_text(PHP_HDR + "".join(pub), encoding="utf-8")

    i0, i1 = lines_to_idx(*SLICES["leg"])
    (DIR / "api_v3_autocadastro_public_legacy.php").write_text(PHP_HDR + "".join(L[i0:i1]), encoding="utf-8")

    h0, h1 = lines_to_idx(*SLICES["head"])
    m0, m1 = lines_to_idx(*SLICES["mid"])
    p0, p1_ = lines_to_idx(*SLICES["pre_legacy"])

    block1 = (
        "Route::prefix('api/v3')->middleware(['web', 'auth', 'audit'])->group(function () {\n"
        "    require __DIR__ . '/api_v3_auth_part1.php';\n"
        "});\n\n"
    )
    block2 = (
        "Route::prefix('api/v3')->middleware(['web', 'auth', 'audit'])->group(function () {\n"
        "    require __DIR__ . '/api_v3_auth_part2.php';\n"
        "});\n\n"
    )
    block3 = (
        "Route::prefix('api/v3')->middleware(['web'])->group(function () {\n"
        "    require __DIR__ . '/api_v3_web_part1.php';\n"
        "});\n"
    )
    block4 = (
        "\nRoute::prefix('api/v3')->middleware(['web'])->group(function () {\n"
        "    require __DIR__ . '/api_v3_autocadastro_public_legacy.php';\n"
        "});\n"
    )

    new = (
        L[h0:h1] + [block1] + L[m0:m1] + [block2] + [block3] + L[p0:p1_] + [block4] + ["\n"]
    )
    WEB.write_text("".join(new), encoding="utf-8")
    print("gerado: web.php + api_v3_*.php")


if __name__ == "__main__":
    main()
