#!/usr/bin/env python3
"""Extrai o esquema real das listas DIL a partir do pacote DIL.zip.

Fontes de evidência (somente leitura, nada é alterado):
  * CSVs exportados das listas  -> nomes de exibição e ordem das colunas;
  * pacotes .zip dos fluxos      -> nomes internos (field_N), tipos (Choice/Person/...).

Saídas:
  * schema/dil_schema.json             (público, sem URL do site, GUIDs ou dados pessoais)
  * docs/Mapeamento_Listas_DIL.md      (público, gerado a partir do JSON)
  * local/evidencias_fluxos.json       (local, ignorado pelo git; contém GUIDs)

Uso:
  python3 -I scripts/extrair_esquema.py local/DIL.zip [local/tenant_local.json]
"""
import csv
import datetime as dt
import hashlib
import io
import json
import re
import sys
import zipfile
from pathlib import Path

RAIZ = Path(__file__).resolve().parent.parent

LISTAS_CONHECIDAS = [
    "DIL_Importacao_SAP", "DIL_Requisicoes", "DIL_Itens_Requisicao", "DIL_Pedidos",
    "DIL_Itens_Pedido", "DIL_Vinculo_RC_PO", "DIL_Fornecedores", "DIL_Compromissos",
    "DIL_Marcos", "DIL_Ocorrencias", "DIL_Acoes", "DIL_Comunicacoes", "DIL_Historico",
    "DIL_SLA", "DIL_Configuracao", "DIL_Integracao_Execucao", "DIL_Documentos",
]

RE_DATA = re.compile(r"^\d{1,2}/\d{1,2}/\d{4}$")
RE_DATAHORA = re.compile(r"^\d{1,2}/\d{1,2}/\d{4} \d{1,2}:\d{2}")
BOOL_SIM = {"true", "verdadeiro"}
BOOL_NAO = {"false", "falso"}


def ler_csv(dados: bytes):
    texto = dados.decode("utf-8-sig")
    linhas = list(csv.reader(io.StringIO(texto)))
    return linhas[0], linhas[1:]


def coluna_nomeada(nome: str) -> bool:
    """Colunas acrescentadas depois da criação por planilha (têm nome interno real)."""
    return nome.endswith("_Pessoa") or re.search(r"_\d+$", nome) is not None


def tipo_por_valores(valores):
    v = [x.strip() for x in valores if x.strip()]
    if not v:
        return None, []
    baixos = {x.lower() for x in v}
    if baixos <= BOOL_SIM | BOOL_NAO:
        return "Booleano", sorted(set(v))
    if baixos & (BOOL_SIM | BOOL_NAO) and baixos <= BOOL_SIM | BOOL_NAO | {"sim", "não", "nao"}:
        return "AMBIGUO:Booleano|Texto", sorted(set(v))
    if all(RE_DATAHORA.match(x) for x in v):
        return "DataHora", []
    if all(RE_DATA.match(x) or RE_DATAHORA.match(x) for x in v):
        return "Data", []
    return None, []


def acoes_recursivas(acoes):
    for nome, a in acoes.items():
        yield nome, a
        for chave in ("actions",):
            if chave in a:
                yield from acoes_recursivas(a[chave])
        if "else" in a:
            yield from acoes_recursivas(a["else"].get("actions", {}))
        for caso in a.get("cases", {}).values():
            yield from acoes_recursivas(caso.get("actions", {}))
        if "default" in a:
            yield from acoes_recursivas(a["default"].get("actions", {}))


def tipo_por_escrita(chave: str, valor):
    """Infere tipo pelo modo como um fluxo grava o campo."""
    if chave.endswith("/Value"):
        return "Escolha"
    if chave.endswith("/Claims"):
        return "Pessoa"
    if isinstance(valor, dict) and "Value" in valor:
        return "Escolha"
    if valor is True or (isinstance(valor, str) and valor.strip() in ("True", "true")):
        return "Booleano"
    if isinstance(valor, str):
        s = valor.strip()
        if s.startswith("@") and "concat(" not in s and "formatDateTime(" not in s and (
                "utcNow()" in s or "addDays(" in s):
            return "DataHora"
        if s.startswith("@length("):
            return "Numero"
    return None


def ler_fluxos(z: zipfile.ZipFile):
    """Retorna lista de dicts {fluxo, acao, op, tabela, campos{interno:(tipo,literal)}, filtro}."""
    eventos = []
    for nome in z.namelist():
        if not (nome.startswith("DIL_") and nome.endswith(".zip")):
            continue
        interno = zipfile.ZipFile(io.BytesIO(z.read(nome)))
        for arq in interno.namelist():
            if not arq.endswith("definition.json"):
                continue
            prop = json.loads(interno.read(arq))["properties"]
            df = prop["definition"]
            fluxo = prop.get("displayName", nome[:-4])
            for tnome, t in df.get("triggers", {}).items():
                p = t.get("inputs", {}).get("parameters", {})
                if p.get("table"):
                    eventos.append({"fluxo": fluxo, "acao": tnome, "op": "Gatilho",
                                    "tabela": p["table"], "campos": {}, "filtro": None,
                                    "refs_gatilho": sorted(set(re.findall(
                                        r"triggerBody\(\)\?\['([A-Za-z0-9_]+)'\]", json.dumps(df))))})
            for anome, a in acoes_recursivas(df.get("actions", {})):
                ins = a.get("inputs")
                if not isinstance(ins, dict):
                    continue
                p = ins.get("parameters", {})
                if not p.get("table"):
                    continue
                campos = {}
                for k, v in p.items():
                    if not k.startswith("item/"):
                        continue
                    k2 = k[5:]
                    base = k2.split("/")[0]
                    lit = None
                    if isinstance(v, str) and not v.startswith("@") and k2.endswith("/Value"):
                        lit = v
                    if isinstance(v, dict) and isinstance(v.get("Value"), str) and not v["Value"].startswith("@"):
                        lit = v["Value"]
                    campos[base] = (tipo_por_escrita(k2, v), lit)
                eventos.append({"fluxo": fluxo, "acao": anome,
                                "op": ins.get("host", {}).get("operationId", ""),
                                "tabela": p["table"], "campos": campos,
                                "filtro": p.get("$filter")})
    return eventos


def main():
    if len(sys.argv) < 2:
        sys.exit(__doc__)
    caminho_zip = Path(sys.argv[1])
    mapa_local = Path(sys.argv[2]) if len(sys.argv) > 2 else RAIZ / "local" / "tenant_local.json"
    guid_para_lista = {}
    if mapa_local.exists():
        guid_para_lista = {g: v["lista"] for g, v in json.loads(mapa_local.read_text())["listas"].items()}

    z = zipfile.ZipFile(caminho_zip)
    eventos = ler_fluxos(z)
    por_lista = {}
    for e in eventos:
        lista = guid_para_lista.get(e["tabela"])
        if lista:
            por_lista.setdefault(lista, []).append(e)

    resultado = {
        "gerado_em": dt.datetime.now(dt.timezone.utc).strftime("%Y-%m-%dT%H:%M:%SZ"),
        "fonte": {"arquivo": caminho_zip.name,
                  "sha256": hashlib.sha256(caminho_zip.read_bytes()).hexdigest(),
                  "observacao": "CSV = nomes de exibição/ordem; fluxos = nomes internos e tipos."},
        "regras": [
            "Coluna Chave_* é a coluna Title renomeada (fluxos gravam Title com o prefixo da chave).",
            "Listas criadas por planilha: nome interno field_N = N-ésima coluna após Title, até a primeira coluna acrescentada depois (sufixo _Pessoa ou _N).",
            "Power Apps referencia colunas SharePoint pelo NOME DE EXIBIÇÃO; fluxos/OData usam o nome interno.",
        ],
        "listas": {},
    }

    csvs = {Path(n).stem: n for n in z.namelist() if n.endswith(".csv")}
    for lista in LISTAS_CONHECIDAS:
        if lista not in csvs:
            resultado["listas"][lista] = {"status": "AUSENTE no pacote DIL.zip — esquema não verificado"}
            continue
        cab, linhas = ler_csv(z.read(csvs[lista]))
        evs = por_lista.get(lista, [])
        internos_usados = set()
        for e in evs:
            internos_usados |= set(e["campos"])
            if e["filtro"]:
                internos_usados |= set(re.findall(r"\b(field_\d+|[A-Z][A-Za-z_]+)\s+eq\b", e["filtro"]))
            internos_usados |= set(e.get("refs_gatilho", []))
        estilo_field = any(re.fullmatch(r"field_\d+", c) for c in internos_usados)
        estilo = "field_N" if estilo_field else ("nomes_reais" if evs else "desconhecido")

        colunas = []
        bloco = True
        for i, nome in enumerate(cab):
            valores = [ln[i] if i < len(ln) else "" for ln in linhas]
            if i == 0:
                interno, st_int = "Title", "confirmado (fluxos gravam Title com o prefixo da chave)" if evs else "inferido"
            elif estilo == "field_N" and bloco and not coluna_nomeada(nome):
                interno = f"field_{i}"
                st_int = "confirmado (usado em fluxo)" if interno in internos_usados else "inferido (posição)"
            else:
                bloco = False if estilo == "field_N" else bloco
                interno = nome.replace("$", "_x0024_") if "$" in nome else nome
                if estilo == "desconhecido":
                    st_int = "NÃO CONFIRMADO (nenhum fluxo usa a lista)"
                else:
                    st_int = "confirmado (usado em fluxo)" if interno in internos_usados else "inferido (= nome de exibição)"

            tipo, st_tipo, evid, lits = None, None, [], set()
            for e in evs:
                if interno in e["campos"]:
                    t, lit = e["campos"][interno]
                    evid.append(f'{e["fluxo"]}:{e["acao"]}')
                    if lit:
                        lits.add(lit)
                    if t and not tipo:
                        tipo, st_tipo = t, "confirmado (gravação em fluxo)"
                if e["filtro"] and re.search(rf"\b{re.escape(interno)}\s+eq\s+1\b", e["filtro"]):
                    tipo, st_tipo = "Booleano", "confirmado (filtro OData eq 1)"
                    evid.append(f'{e["fluxo"]}:{e["acao"]} (filtro)')
                elif e["filtro"] and re.search(rf"\b{re.escape(interno)}\s+eq\s+''?'", e["filtro"]) and not tipo:
                    tipo, st_tipo = "Texto", "confirmado (filtro OData compara com texto; pode ser Escolha)"
                    evid.append(f'{e["fluxo"]}:{e["acao"]} (filtro)')
            t_csv, obs = tipo_por_valores(valores)
            if t_csv and t_csv.startswith("AMBIGUO"):
                st_tipo = f"AMBÍGUO: fluxo={tipo or '?'}; CSV contém {obs}"
                tipo = "Ambiguo"
            elif not tipo and t_csv:
                tipo, st_tipo = t_csv, "inferido (valores do CSV)"
            if not tipo and nome.endswith("_Pessoa"):
                tipo, st_tipo = "Pessoa", "inferido (sufixo _Pessoa)"
            if not tipo:
                tipo, st_tipo = "Texto", "inferido (padrão; sem evidência contrária)"
            if i == 0:
                tipo, st_tipo = "Texto", "confirmado (Title)"
            col = {"exibicao": nome, "interno": interno, "interno_status": st_int,
                   "tipo": tipo, "tipo_status": st_tipo, "evidencias": sorted(set(evid))}
            if tipo in ("Escolha", "Booleano", "Ambiguo"):
                vals = {x for x in valores if x.strip()} | lits
                col["valores_observados"] = sorted(vals)
            colunas.append(col)
        resultado["listas"][lista] = {
            "estilo_nomes_internos": estilo,
            "linhas_no_csv": len([ln for ln in linhas if any(c.strip() for c in ln)]),
            "fluxos_que_usam": sorted({e["fluxo"] for e in evs}),
            "colunas": colunas,
        }

    (RAIZ / "schema").mkdir(exist_ok=True)
    (RAIZ / "schema" / "dil_schema.json").write_text(json.dumps(resultado, ensure_ascii=False, indent=2) + "\n")
    (RAIZ / "local").mkdir(exist_ok=True)
    (RAIZ / "local" / "evidencias_fluxos.json").write_text(json.dumps(eventos, ensure_ascii=False, indent=1, default=list))
    escrever_markdown(resultado)
    print("OK:", sum(1 for v in resultado["listas"].values() if "colunas" in v), "listas mapeadas")


def escrever_markdown(r):
    out = ["# Mapeamento das listas DIL (gerado)", "",
           f"> Gerado por `scripts/extrair_esquema.py` em {r['gerado_em']} a partir de `{r['fonte']['arquivo']}` "
           f"(sha256 `{r['fonte']['sha256'][:16]}…`). **Não editar à mão** — rode o script de novo.", "",
           "## Regras aplicadas", ""]
    out += [f"- {x}" for x in r["regras"]]
    out += ["", "Legenda: *confirmado* = há evidência direta em fluxo/filtro; *inferido* = deduzido por posição/valores; "
            "itens **NÃO CONFIRMADO/AMBÍGUO/AUSENTE** precisam de verificação em *Configurações da lista* no SharePoint.", ""]
    for lista, d in r["listas"].items():
        out += [f"## {lista}", ""]
        if "colunas" not in d:
            out += [f"**{d['status']}**", ""]
            continue
        out += [f"- Nomes internos: `{d['estilo_nomes_internos']}`",
                f"- Linhas no CSV exportado: {d['linhas_no_csv']}",
                f"- Fluxos que usam: {', '.join(d['fluxos_que_usam']) or '—'}", "",
                "| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |",
                "|---|---|---|---|---|"]
        for c in d["colunas"]:
            tipo = c["tipo"]
            if c.get("valores_observados") and c["tipo"] != "Booleano":
                tipo += " (" + ", ".join(c["valores_observados"][:8]) + ")"
            out.append(f"| `{c['exibicao']}` | `{c['interno']}` | {c['interno_status']} | {tipo} | {c['tipo_status']} |")
        out.append("")
    (RAIZ / "docs" / "Mapeamento_Listas_DIL.md").write_text("\n".join(out))


if __name__ == "__main__":
    main()
