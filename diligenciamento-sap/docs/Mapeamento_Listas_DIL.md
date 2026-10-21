# Mapeamento das listas DIL (gerado)

> Gerado por `scripts/extrair_esquema.py` em 2026-10-21T22:20:08Z a partir de `DIL.zip` (sha256 `c40f37d6566455f2…`). **Não editar à mão** — rode o script de novo.

## Regras aplicadas

- Coluna Chave_* é a coluna Title renomeada (fluxos gravam Title com o prefixo da chave).
- Listas criadas por planilha: nome interno field_N = N-ésima coluna após Title, até a primeira coluna acrescentada depois (sufixo _Pessoa ou _N).
- Power Apps referencia colunas SharePoint pelo NOME DE EXIBIÇÃO; fluxos/OData usam o nome interno.

Legenda: *confirmado* = há evidência direta em fluxo/filtro; *inferido* = deduzido por posição/valores; itens **NÃO CONFIRMADO/AMBÍGUO/AUSENTE** precisam de verificação em *Configurações da lista* no SharePoint.

## DIL_Importacao_SAP

- Nomes internos: `nomes_reais`
- Linhas no CSV exportado: 109
- Fluxos que usam: DIL_01_Importar_SAP, DIL_02_Normalizar_SAP_BATCH_V10_CORRIGIDO_SEM_HTTP, DIL_03_Fechar_Execucao_SAP, DIL_04_Normalizar_RC_PO

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Linha` | `Title` | confirmado (fluxos gravam Title com o prefixo da chave) | Texto | confirmado (Title) |
| `ID_Importacao` | `ID_Importacao` | confirmado (usado em fluxo) | Texto | confirmado (filtro OData compara com texto; pode ser Escolha) |
| `Data_Importacao` | `Data_Importacao` | inferido (= nome de exibição) | Texto | inferido (padrão; sem evidência contrária) |
| `Arquivo_Origem` | `Arquivo_Origem` | inferido (= nome de exibição) | Texto | inferido (padrão; sem evidência contrária) |
| `Linha_Origem` | `Linha_Origem` | inferido (= nome de exibição) | Texto | inferido (padrão; sem evidência contrária) |
| `Status_Processamento` | `Status_Processamento` | confirmado (usado em fluxo) | Escolha (Pendente, Processado) | confirmado (gravação em fluxo) |
| `Mensagem_Erro` | `Mensagem_Erro` | inferido (= nome de exibição) | Texto | inferido (padrão; sem evidência contrária) |
| `Data_Processamento` | `Data_Processamento` | confirmado (usado em fluxo) | DataHora | confirmado (gravação em fluxo) |
| `Tip_Doc_Compra` | `Tip_Doc_Compra` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Descricao_Tipo_Doc` | `Descricao_Tipo_Doc` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Numero_RC_SAP` | `Numero_RC_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Item_RC_SAP` | `Item_RC_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Material_SAP` | `Material_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Descricao_Produto_SAP` | `Descricao_Produto_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Numero_PO_SAP` | `Numero_PO_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Item_PO_SAP` | `Item_PO_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Codigo_Fornecedor_SAP` | `Codigo_Fornecedor_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Nome_Fornecedor_SAP` | `Nome_Fornecedor_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Data_1_Remessa_Item_SAP` | `Data_1_Remessa_Item_SAP` | confirmado (usado em fluxo) | Data | inferido (valores do CSV) |
| `Grupo_Compradores_SAP` | `Grupo_Compradores_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `DIL_Importacao_SAP` | `DIL_Importacao_SAP` | inferido (= nome de exibição) | Texto | inferido (padrão; sem evidência contrária) |
| `Status_EF_SAP` | `Status_EF_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Enquadramento_Proc_SAP` | `Enquadramento_Proc_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Utilizacao_Material_SAP` | `Utilizacao_Material_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Tipo_Avaliacao_SAP` | `Tipo_Avaliacao_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Column19_SAP` | `Column19_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Column20_SAP` | `Column20_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Valor_R$_SAP` | `Valor_R_x0024__SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Valor_R$_1_SAP` | `Valor_R_x0024__1_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Descricao_Status_EF_SAP` | `Descricao_Status_EF_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Descricao_Status_EM_SAP` | `Descricao_Status_EM_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Status_EM_SAP` | `Status_EM_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Status_Liberacao_PO_SAP` | `Status_Liberacao_PO_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |

## DIL_Requisicoes

- Nomes internos: `nomes_reais`
- Linhas no CSV exportado: 6
- Fluxos que usam: DIL_02_Normalizar_SAP_BATCH_V10_CORRIGIDO_SEM_HTTP

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_RC` | `Title` | confirmado (fluxos gravam Title com o prefixo da chave) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Numero_RC` | `Numero_RC` | confirmado (usado em fluxo) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Tipo_Doc_Compra` | `Tipo_Doc_Compra` | confirmado (usado em fluxo) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Descricao_Tipo_Doc` | `Descricao_Tipo_Doc` | confirmado (usado em fluxo) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Status_RC` | `Status_RC` | inferido (= nome de exibição) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Data_Criacao_RC` | `Data_Criacao_RC` | inferido (= nome de exibição) | DataHora | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Grupo_Compradores` | `Grupo_Compradores` | confirmado (usado em fluxo) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Responsavel_Diligencia` | `Responsavel_Diligencia` | inferido (= nome de exibição) | Pessoa | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Prioridade` | `Prioridade` | inferido (= nome de exibição) | Escolha | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Nivel_Risco` | `Nivel_Risco` | inferido (= nome de exibição) | Escolha | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Data_Ultima_Atualizacao_SAP` | `Data_Ultima_Atualizacao_SAP` | confirmado (usado em fluxo) | DataHora | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Ativa` | `Ativa` | confirmado (usado em fluxo) | Booleano | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Observacao` | `Observacao` | inferido (= nome de exibição) | TextoMultilinha | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |

## DIL_Itens_Requisicao

- Nomes internos: `nomes_reais`
- Linhas no CSV exportado: 8
- Fluxos que usam: DIL_04_Normalizar_RC_PO

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Item_RC` | `Title` | confirmado (fluxos gravam Title com o prefixo da chave) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Numero_RC` | `Numero_RC` | confirmado (usado em fluxo) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Item_RC` | `Item_RC` | confirmado (usado em fluxo) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Material` | `Material` | confirmado (usado em fluxo) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Descricao_Produto` | `Descricao_Produto` | confirmado (usado em fluxo) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Utilizacao_Material` | `Utilizacao_Material` | confirmado (usado em fluxo) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Tipo_Avaliacao` | `Tipo_Avaliacao` | confirmado (usado em fluxo) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Grupo_Compradores` | `Grupo_Compradores` | confirmado (usado em fluxo) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Data_1_Remessa_Item_SAP` | `Data_1_Remessa_Item_SAP` | confirmado (usado em fluxo) | DataHora | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Ativo` | `Ativo` | confirmado (usado em fluxo) | Booleano | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |

## DIL_Pedidos

- Nomes internos: `nomes_reais`
- Linhas no CSV exportado: 8
- Fluxos que usam: DIL_02_Normalizar_SAP_BATCH_V10_CORRIGIDO_SEM_HTTP

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_PO` | `Title` | confirmado (fluxos gravam Title com o prefixo da chave) | Texto | confirmado (Title) |
| `Numero_PO` | `Numero_PO` | confirmado (usado em fluxo) | Texto | confirmado (filtro OData compara com texto; pode ser Escolha) |
| `Tipo_Doc_Compra` | `Tipo_Doc_Compra` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Descricao_Tipo_Doc` | `Descricao_Tipo_Doc` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Codigo_Fornecedor` | `Codigo_Fornecedor` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Nome_Fornecedor` | `Nome_Fornecedor` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Grupo_Compradores` | `Grupo_Compradores` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Status_Liberacao_PO` | `Status_Liberacao_PO` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Status_EF` | `Status_EF` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Status_EM` | `Status_EM` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Ativo` | `Ativo` | confirmado (usado em fluxo) | Booleano | confirmado (gravação em fluxo) |
| `Observacao` | `Observacao` | inferido (= nome de exibição) | Texto | inferido (padrão; sem evidência contrária) |

## DIL_Itens_Pedido

- Nomes internos: `nomes_reais`
- Linhas no CSV exportado: 8
- Fluxos que usam: DIL_02_Normalizar_SAP_BATCH_V10_CORRIGIDO_SEM_HTTP

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Item_PO` | `Title` | confirmado (fluxos gravam Title com o prefixo da chave) | Texto | confirmado (Title) |
| `Numero_PO` | `Numero_PO` | confirmado (usado em fluxo) | Texto | confirmado (filtro OData compara com texto; pode ser Escolha) |
| `Item_PO` | `Item_PO` | confirmado (usado em fluxo) | Texto | confirmado (filtro OData compara com texto; pode ser Escolha) |
| `Material` | `Material` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Descricao_Produto` | `Descricao_Produto` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Codigo_Fornecedor` | `Codigo_Fornecedor` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Nome_Fornecedor` | `Nome_Fornecedor` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Data_1_Remessa_Item_SAP` | `Data_1_Remessa_Item_SAP` | confirmado (usado em fluxo) | Data | inferido (valores do CSV) |
| `Status_Liberacao_PO` | `Status_Liberacao_PO` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Status_EF` | `Status_EF` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Status_EM` | `Status_EM` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Valor_1_SAP` | `Valor_1_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Valor_2_SAP` | `Valor_2_SAP` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Ativo` | `Ativo` | confirmado (usado em fluxo) | Booleano | confirmado (gravação em fluxo) |

## DIL_Vinculo_RC_PO

- Nomes internos: `nomes_reais`
- Linhas no CSV exportado: 8
- Fluxos que usam: DIL_04_Normalizar_RC_PO

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Vinculo` | `Title` | confirmado (fluxos gravam Title com o prefixo da chave) | Texto | confirmado (Title) |
| `Numero_RC` | `Numero_RC` | confirmado (usado em fluxo) | Texto | confirmado (filtro OData compara com texto; pode ser Escolha) |
| `Item_RC` | `Item_RC` | confirmado (usado em fluxo) | Texto | confirmado (filtro OData compara com texto; pode ser Escolha) |
| `Numero_PO` | `Numero_PO` | confirmado (usado em fluxo) | Texto | confirmado (filtro OData compara com texto; pode ser Escolha) |
| `Item_PO` | `Item_PO` | confirmado (usado em fluxo) | Texto | confirmado (filtro OData compara com texto; pode ser Escolha) |
| `Quantidade_Vinculada` | `Quantidade_Vinculada` | inferido (= nome de exibição) | Texto | inferido (padrão; sem evidência contrária) |
| `Percentual_Vinculado` | `Percentual_Vinculado` | inferido (= nome de exibição) | Texto | inferido (padrão; sem evidência contrária) |
| `Tipo_Vinculo` | `Tipo_Vinculo` | confirmado (usado em fluxo) | Escolha (Integral) | confirmado (gravação em fluxo) |
| `Origem_Vinculo` | `Origem_Vinculo` | confirmado (usado em fluxo) | Escolha (SAP) | confirmado (gravação em fluxo) |
| `Data_Vinculo` | `Data_Vinculo` | confirmado (usado em fluxo) | DataHora | confirmado (gravação em fluxo) |
| `Ativo` | `Ativo` | confirmado (usado em fluxo) | Booleano | confirmado (gravação em fluxo) |

## DIL_Fornecedores

- Nomes internos: `field_N`
- Linhas no CSV exportado: 8
- Fluxos que usam: DIL_02_Normalizar_SAP_BATCH_V10_CORRIGIDO_SEM_HTTP

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Fornecedor` | `Title` | confirmado (fluxos gravam Title com o prefixo da chave) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Codigo_Fornecedor` | `field_1` | confirmado (usado em fluxo) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Nome_Fornecedor` | `field_2` | confirmado (usado em fluxo) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `CNPJ` | `field_3` | inferido (posição; coluna confirmada pelo usuário) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Email_Principal` | `field_4` | inferido (posição; coluna confirmada pelo usuário) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Telefone` | `field_5` | inferido (posição; coluna confirmada pelo usuário) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Contato_Principal` | `field_6` | inferido (posição; coluna confirmada pelo usuário) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Categoria_Fornecedor` | `field_7` | inferido (posição; coluna confirmada pelo usuário) | TextoMultilinha | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Status_Fornecedor` | `field_8` | inferido (posição; coluna confirmada pelo usuário) | Escolha | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Nivel_Risco_Fornecedor` | `field_9` | inferido (posição; coluna confirmada pelo usuário) | Escolha | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Responsavel_Fornecedor` | `field_10` | inferido (posição; coluna confirmada pelo usuário) | Texto | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Observacao` | `field_11` | inferido (posição; coluna confirmada pelo usuário) | TextoMultilinha | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Ativo` | `field_12` | confirmado (usado em fluxo) | Booleano | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Responsavel_Fornecedor_Pessoa` | `Responsavel_Fornecedor_Pessoa` | inferido (= nome de exibição) | Pessoa | confirmado (usuário: texto de Configurações da lista > Colunas, enviado em 21/10/2026) |

## DIL_Compromissos

- Nomes internos: `field_N`
- Linhas no CSV exportado: 3
- Fluxos que usam: DIL_05_Compromissos_Marcos_Historico

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Compromisso` | `Title` | confirmado (fluxos gravam Title com o prefixo da chave) | Texto | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Numero_RC` | `field_1` | confirmado (usado em fluxo) | Texto | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Item_RC` | `field_2` | confirmado (usado em fluxo) | Texto | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Numero_PO` | `field_3` | confirmado (usado em fluxo) | Texto | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Item_PO` | `field_4` | confirmado (usado em fluxo) | Texto | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Codigo_Fornecedor` | `field_5` | inferido (posição; coluna confirmada pelo usuário) | Texto | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Tipo_Compromisso` | `field_6` | confirmado (usado em fluxo) | Escolha | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Data_Compromisso` | `field_7` | confirmado (usado em fluxo) | DataHora | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Data_Compromisso_Anterior` | `field_8` | inferido (posição; coluna confirmada pelo usuário) | DataHora | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Origem_Compromisso` | `field_9` | inferido (posição; coluna confirmada pelo usuário) | Escolha | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Status_Compromisso` | `field_10` | confirmado (usado em fluxo) | Escolha | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Data_Registro` | `field_11` | inferido (posição; coluna confirmada pelo usuário) | DataHora | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Responsavel_Registro` | `field_12` | inferido (posição; coluna confirmada pelo usuário) | Texto | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Observacao` | `field_13` | inferido (posição; coluna confirmada pelo usuário) | TextoMultilinha | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Ativo` | `field_14` | inferido (posição; coluna confirmada pelo usuário) | Booleano | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Responsavel_Registro_Pessoa` | `Responsavel_Registro_Pessoa` | inferido (= nome de exibição) | Pessoa | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |

## DIL_Marcos

- Nomes internos: `field_N`
- Linhas no CSV exportado: 4
- Fluxos que usam: DIL_05_Compromissos_Marcos_Historico, DIL_06_Ocorrencias_Acoes_V6, DIL_07_Encerrar_Ocorrencias_Acoes_V8

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Marco` | `Title` | confirmado (fluxos gravam Title com o prefixo da chave) | Texto | confirmado (Title) |
| `Numero_RC` | `field_1` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Item_RC` | `field_2` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Numero_PO` | `field_3` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Item_PO` | `field_4` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Tipo_Marco` | `field_5` | confirmado (usado em fluxo) | Escolha (Entrega) | confirmado (gravação em fluxo) |
| `Descricao_Marco` | `field_6` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Data_Planejada` | `field_7` | confirmado (usado em fluxo) | Data | inferido (valores do CSV) |
| `Data_Realizada` | `field_8` | inferido (posição) | Data | inferido (valores do CSV) |
| `Status_Marco` | `field_9` | confirmado (usado em fluxo) | Escolha (Concluído, Pendente) | confirmado (gravação em fluxo) |
| `Responsavel` | `field_10` | inferido (posição) | Texto | inferido (padrão; sem evidência contrária) |
| `Observacao` | `field_11` | inferido (posição) | Texto | inferido (padrão; sem evidência contrária) |
| `Ativo` | `field_12` | confirmado (usado em fluxo) | Ambiguo (Sim, True) | AMBÍGUO: fluxo=Booleano; CSV contém ['Sim', 'True'] |
| `Responsavel_Pessoa` | `Responsavel_Pessoa` | inferido (= nome de exibição) | Pessoa | inferido (sufixo _Pessoa) |

## DIL_Ocorrencias

- Nomes internos: `field_N`
- Linhas no CSV exportado: 3
- Fluxos que usam: DIL_06_Ocorrencias_Acoes_V6, DIL_07_Encerrar_Ocorrencias_Acoes_V8, DIL_08_Escalonamento_SLA_V2

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Ocorrencia` | `Title` | confirmado (fluxos gravam Title com o prefixo da chave) | Texto | confirmado (Title) |
| `Numero_RC` | `field_1` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Item_RC` | `field_2` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Numero_PO` | `field_3` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Item_PO` | `field_4` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Codigo_Fornecedor` | `field_5` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Tipo_Ocorrencia` | `field_6` | confirmado (usado em fluxo) | Escolha (Atraso) | confirmado (gravação em fluxo) |
| `Severidade` | `field_7` | confirmado (usado em fluxo) | Escolha (Alta, Crítica) | confirmado (gravação em fluxo) |
| `Descricao` | `field_8` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Data_Ocorrencia` | `field_9` | confirmado (usado em fluxo) | DataHora | confirmado (gravação em fluxo) |
| `Status_Ocorrencia` | `field_10` | confirmado (usado em fluxo) | Escolha (Aberta, Resolvida) | confirmado (gravação em fluxo) |
| `Responsavel` | `field_11` | inferido (posição) | Texto | inferido (padrão; sem evidência contrária) |
| `Data_Resolucao` | `field_12` | confirmado (usado em fluxo) | Data | inferido (valores do CSV) |
| `Observacao` | `field_13` | inferido (posição) | Texto | inferido (padrão; sem evidência contrária) |
| `Ativo` | `field_14` | confirmado (usado em fluxo) | Booleano | confirmado (gravação em fluxo) |
| `Responsavel_Pessoa` | `Responsavel_Pessoa` | inferido (= nome de exibição) | Pessoa | inferido (sufixo _Pessoa) |

## DIL_Acoes

- Nomes internos: `field_N`
- Linhas no CSV exportado: 5
- Fluxos que usam: DIL_06_Ocorrencias_Acoes_V6, DIL_07_Encerrar_Ocorrencias_Acoes_V8, DIL_08_Escalonamento_SLA_V2, DIL_09_Comunicacoes_SLA_V2, DIL_10_Enviar_Comunicacoes_Outlook_V8

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Acao` | `Title` | confirmado (fluxos gravam Title com o prefixo da chave) | Texto | confirmado (Title) |
| `Numero_RC` | `field_1` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Item_RC` | `field_2` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Numero_PO` | `field_3` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Item_PO` | `field_4` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Chave_Ocorrencia` | `field_5` | confirmado (usado em fluxo) | Texto | confirmado (filtro OData compara com texto; pode ser Escolha) |
| `Tipo_Acao` | `field_6` | confirmado (usado em fluxo) | Escolha (Cobrança, Escalonamento) | confirmado (gravação em fluxo) |
| `Descricao_Acao` | `field_7` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Responsavel` | `field_8` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Prazo` | `field_9` | confirmado (usado em fluxo) | DataHora | confirmado (gravação em fluxo) |
| `Status_Acao` | `field_10` | confirmado (usado em fluxo) | Escolha (Aberta, Concluída) | confirmado (gravação em fluxo) |
| `Data_Conclusao` | `field_11` | confirmado (usado em fluxo) | Data | inferido (valores do CSV) |
| `Prioridade` | `field_12` | confirmado (usado em fluxo) | Escolha (Alta, Crítica) | confirmado (gravação em fluxo) |
| `Observacao` | `field_13` | inferido (posição) | Texto | inferido (padrão; sem evidência contrária) |
| `Ativo` | `field_14` | confirmado (usado em fluxo) | Booleano | confirmado (gravação em fluxo) |
| `Responsavel_Pessoa` | `Responsavel_Pessoa` | confirmado (usado em fluxo) | Pessoa | confirmado (gravação em fluxo) |

## DIL_Comunicacoes

- Nomes internos: `field_N`
- Linhas no CSV exportado: 2
- Fluxos que usam: DIL_09_Comunicacoes_SLA_V2, DIL_10_Enviar_Comunicacoes_Outlook_V8

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Comunicacao` | `Title` | confirmado (fluxos gravam Title com o prefixo da chave) | Texto | confirmado (Title) |
| `Numero_RC` | `field_1` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Item_RC` | `field_2` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Numero_PO` | `field_3` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Item_PO` | `field_4` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Codigo_Fornecedor` | `field_5` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Tipo_Comunicacao` | `field_6` | confirmado (usado em fluxo) | Escolha (Outro) | confirmado (gravação em fluxo) |
| `Data_Comunicacao` | `field_7` | confirmado (usado em fluxo) | DataHora | confirmado (gravação em fluxo) |
| `Sentido` | `field_8` | confirmado (usado em fluxo) | Escolha (Enviada) | confirmado (gravação em fluxo) |
| `Assunto` | `field_9` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Resumo` | `field_10` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Responsavel` | `field_11` | inferido (posição) | Texto | inferido (padrão; sem evidência contrária) |
| `Anexo_Link` | `field_12` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Ativo` | `field_13` | confirmado (usado em fluxo) | Booleano | confirmado (filtro OData eq 1) |
| `Responsavel_Pessoa` | `Responsavel_Pessoa` | inferido (= nome de exibição) | Pessoa | inferido (sufixo _Pessoa) |
| `Anexo_Link_1` | `Anexo_Link_1` | inferido (= nome de exibição) | Texto | inferido (padrão; sem evidência contrária) |

## DIL_Historico

- Nomes internos: `field_N`
- Linhas no CSV exportado: 13
- Fluxos que usam: DIL_05_Compromissos_Marcos_Historico, DIL_07_Encerrar_Ocorrencias_Acoes_V8, DIL_08_Escalonamento_SLA_V2, DIL_09_Comunicacoes_SLA_V2, DIL_10_Enviar_Comunicacoes_Outlook_V8

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Historico` | `Title` | confirmado (fluxos gravam Title com o prefixo da chave) | Texto | confirmado (Title) |
| `Entidade` | `field_1` | confirmado (usado em fluxo) | Escolha (Ação, Compromisso, Comunicação, Ocorrência) | confirmado (gravação em fluxo) |
| `Chave_Entidade` | `field_2` | confirmado (usado em fluxo) | Texto | confirmado (filtro OData compara com texto; pode ser Escolha) |
| `Tipo_Evento` | `field_3` | confirmado (usado em fluxo) | Escolha (Alteração, Envio_Email, Inclusão, SLA, Status) | confirmado (gravação em fluxo) |
| `Campo_Alterado` | `field_4` | confirmado (usado em fluxo) | Texto | confirmado (filtro OData compara com texto; pode ser Escolha) |
| `Valor_Anterior` | `field_5` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Valor_Novo` | `field_6` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Data_Evento` | `field_7` | confirmado (usado em fluxo) | DataHora | confirmado (gravação em fluxo) |
| `Usuario_Evento` | `field_8` | inferido (posição) | Texto | inferido (padrão; sem evidência contrária) |
| `Origem_Evento` | `field_9` | confirmado (usado em fluxo) | Escolha (Power Automate) | confirmado (gravação em fluxo) |
| `Observacao` | `field_10` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Usuario_Evento_Pessoa` | `Usuario_Evento_Pessoa` | inferido (= nome de exibição) | Pessoa | inferido (sufixo _Pessoa) |

## DIL_SLA

- Nomes internos: `field_N`
- Linhas no CSV exportado: 3
- Fluxos que usam: DIL_09_Comunicacoes_SLA_V2

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_SLA` | `Title` | confirmado (fluxos gravam Title com o prefixo da chave) | Texto | confirmado (Title) |
| `Nome_SLA` | `field_1` | inferido (posição) | Texto | inferido (padrão; sem evidência contrária) |
| `Tipo_SLA` | `field_2` | confirmado (usado em fluxo) | Texto | confirmado (filtro OData compara com texto; pode ser Escolha) |
| `Ativo` | `field_3` | inferido (posição) | Booleano | inferido (valores do CSV) |
| `Quantidade_Horas` | `field_4` | inferido (posição) | Texto | inferido (padrão; sem evidência contrária) |
| `Prioridade` | `field_5` | confirmado (usado em fluxo) | Texto | confirmado (filtro OData compara com texto; pode ser Escolha) |
| `Nivel_Risco` | `field_6` | inferido (posição) | Texto | inferido (padrão; sem evidência contrária) |
| `Grupo_Compradores` | `field_7` | inferido (posição) | Texto | inferido (padrão; sem evidência contrária) |
| `Notificar_Email` | `field_8` | inferido (posição) | Booleano | inferido (valores do CSV) |
| `Notificar_Teams` | `field_9` | inferido (posição) | Booleano | inferido (valores do CSV) |
| `Observacao` | `field_10` | inferido (posição) | Texto | inferido (padrão; sem evidência contrária) |

## DIL_Configuracao

- Nomes internos: `desconhecido`
- Linhas no CSV exportado: 3
- Fluxos que usam: —

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Configuracao` | `Title` | inferido | Texto | confirmado (Title) |
| `Categoria` | `Categoria` | NÃO CONFIRMADO (nenhum fluxo usa a lista) | Texto | inferido (padrão; sem evidência contrária) |
| `Nome_Parametro` | `Nome_Parametro` | NÃO CONFIRMADO (nenhum fluxo usa a lista) | Texto | inferido (padrão; sem evidência contrária) |
| `Valor_Texto` | `Valor_Texto` | NÃO CONFIRMADO (nenhum fluxo usa a lista) | Texto | inferido (padrão; sem evidência contrária) |
| `Valor_Numero` | `Valor_Numero` | NÃO CONFIRMADO (nenhum fluxo usa a lista) | Texto | inferido (padrão; sem evidência contrária) |
| `Valor_Data` | `Valor_Data` | NÃO CONFIRMADO (nenhum fluxo usa a lista) | Texto | inferido (padrão; sem evidência contrária) |
| `Ativo` | `Ativo` | NÃO CONFIRMADO (nenhum fluxo usa a lista) | Booleano | inferido (valores do CSV) |
| `Descricao` | `Descricao` | NÃO CONFIRMADO (nenhum fluxo usa a lista) | Texto | inferido (padrão; sem evidência contrária) |

## DIL_Integracao_Execucao

- Nomes internos: `field_N`
- Linhas no CSV exportado: 14
- Fluxos que usam: DIL_01_Importar_SAP, DIL_03_Fechar_Execucao_SAP, DIL_04_Normalizar_RC_PO

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Execucao` | `Title` | confirmado (fluxos gravam Title com o prefixo da chave) | Texto | confirmado (Title) |
| `Tipo_Integracao` | `field_1` | confirmado (usado em fluxo) | Escolha (SAP_Importacao) | confirmado (gravação em fluxo) |
| `Data_Inicio` | `field_2` | confirmado (usado em fluxo) | DataHora | confirmado (gravação em fluxo) |
| `Data_Fim` | `field_3` | confirmado (usado em fluxo) | DataHora | confirmado (gravação em fluxo) |
| `Status_Execucao` | `field_4` | confirmado (usado em fluxo) | Escolha (Concluída, Iniciada) | confirmado (gravação em fluxo) |
| `Registros_Lidos` | `field_5` | confirmado (usado em fluxo) | Numero | confirmado (gravação em fluxo) |
| `Registros_Processados` | `field_6` | confirmado (usado em fluxo) | Numero | confirmado (gravação em fluxo) |
| `Registros_Erro` | `field_7` | confirmado (usado em fluxo) | Numero | confirmado (gravação em fluxo) |
| `Arquivo_Origem` | `field_8` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Mensagem` | `field_9` | confirmado (usado em fluxo) | Texto | inferido (padrão; sem evidência contrária) |
| `Executado_Por` | `field_10` | inferido (posição) | Texto | inferido (padrão; sem evidência contrária) |
| `Executado_Por_Pessoa` | `Executado_Por_Pessoa` | inferido (= nome de exibição) | Pessoa | inferido (sufixo _Pessoa) |

## DIL_Documentos

**AUSENTE no pacote DIL.zip — esquema não verificado**
