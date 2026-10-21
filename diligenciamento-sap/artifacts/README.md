# Artefatos

| Pasta | Conteúdo | Versionado? |
|---|---|---|
| `original/` | Cópia intocada do `.msapp` recebido, nomeada com o prefixo do sha256 (`<nome>__<sha12>.msapp`). Criada por `scripts/montar_msapp.py`. | Não (`.gitignore`) — guarde também fora do repositório. |
| `candidate/` | `.msapp` candidato + manifesto `.json` (hashes, telas geradas/preservadas, `packed.json`) + cópia de `Src` do original. | Não |
| `release/` | Somente versões aprovadas após validação no Power Apps Studio e testes no tenant. | Não |

Os `.msapp` não são versionados porque este repositório é público e o pacote carrega
referências de conexão/ambiente. O registro de cada build fica no manifesto e no `CHANGELOG.md`.
