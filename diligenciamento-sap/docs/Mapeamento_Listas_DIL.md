# Mapeamento das listas DIL (gerado)

> Gerado por `scripts/extrair_esquema.py` em 2026-10-21T23:00:54Z a partir de `DIL.zip` (sha256 `c40f37d6566455f2…`). **Não editar à mão** — rode o script de novo.

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
| `Chave_RC` | `Title` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Numero_RC` | `Numero_RC` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Tipo_Doc_Compra` | `Tipo_Doc_Compra` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Descricao_Tipo_Doc` | `Descricao_Tipo_Doc` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Status_RC` | `Status_RC` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Data_Criacao_RC` | `Data_Criacao_RC` | confirmado (esquema do Power Apps Studio) | Data | confirmado (esquema do Power Apps Studio) |
| `Grupo_Compradores` | `Grupo_Compradores` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Responsavel_Diligencia` | `Responsavel_Diligencia` | confirmado (esquema do Power Apps Studio) | Pessoa | confirmado (esquema do Power Apps Studio) |
| `Prioridade` | `Prioridade` | confirmado (esquema do Power Apps Studio) | Escolha | confirmado (esquema do Power Apps Studio) |
| `Nivel_Risco` | `Nivel_Risco` | confirmado (esquema do Power Apps Studio) | Escolha | confirmado (esquema do Power Apps Studio) |
| `Data_Ultima_Atualizacao_SAP` | `Data_Ultima_Atualizacao_SAP` | confirmado (esquema do Power Apps Studio) | Data | confirmado (esquema do Power Apps Studio) |
| `Ativa` | `Ativa` | confirmado (esquema do Power Apps Studio) | Booleano | confirmado (esquema do Power Apps Studio) |
| `Observacao` | `Observacao` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |

## DIL_Itens_Requisicao

- Nomes internos: `nomes_reais`
- Linhas no CSV exportado: 8
- Fluxos que usam: DIL_04_Normalizar_RC_PO

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Item_RC` | `Title` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Numero_RC` | `Numero_RC` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Item_RC` | `Item_RC` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Material` | `Material` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Descricao_Produto` | `Descricao_Produto` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Utilizacao_Material` | `Utilizacao_Material` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Tipo_Avaliacao` | `Tipo_Avaliacao` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Grupo_Compradores` | `Grupo_Compradores` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Data_1_Remessa_Item_SAP` | `Data_1_Remessa_Item_SAP` | confirmado (esquema do Power Apps Studio) | Data | confirmado (esquema do Power Apps Studio) |
| `Ativo` | `Ativo` | confirmado (esquema do Power Apps Studio) | Booleano | confirmado (esquema do Power Apps Studio) |

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
| `Chave_Item_PO` | `Title` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Numero_PO` | `Numero_PO` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Item_PO` | `Item_PO` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Material` | `Material` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Descricao_Produto` | `Descricao_Produto` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Codigo_Fornecedor` | `Codigo_Fornecedor` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Nome_Fornecedor` | `Nome_Fornecedor` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Data_1_Remessa_Item_SAP` | `Data_1_Remessa_Item_SAP` | confirmado (esquema do Power Apps Studio) | Data | confirmado (esquema do Power Apps Studio) |
| `Status_Liberacao_PO` | `Status_Liberacao_PO` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Status_EF` | `Status_EF` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Status_EM` | `Status_EM` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Valor_1_SAP` | `Valor_1_SAP` | confirmado (esquema do Power Apps Studio) | Numero | confirmado (esquema do Power Apps Studio) |
| `Valor_2_SAP` | `Valor_2_SAP` | confirmado (esquema do Power Apps Studio) | Numero | confirmado (esquema do Power Apps Studio) |
| `Ativo` | `Ativo` | confirmado (esquema do Power Apps Studio) | Booleano | confirmado (esquema do Power Apps Studio) |

## DIL_Vinculo_RC_PO

- Nomes internos: `nomes_reais`
- Linhas no CSV exportado: 8
- Fluxos que usam: DIL_04_Normalizar_RC_PO

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Vinculo` | `Title` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Numero_RC` | `Numero_RC` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Item_RC` | `Item_RC` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Numero_PO` | `Numero_PO` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Item_PO` | `Item_PO` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Quantidade_Vinculada` | `Quantidade_Vinculada` | confirmado (esquema do Power Apps Studio) | Numero | confirmado (esquema do Power Apps Studio) |
| `Percentual_Vinculado` | `Percentual_Vinculado` | confirmado (esquema do Power Apps Studio) | Numero | confirmado (esquema do Power Apps Studio) |
| `Tipo_Vinculo` | `Tipo_Vinculo` | confirmado (esquema do Power Apps Studio) | Escolha (Integral) | confirmado (esquema do Power Apps Studio) |
| `Origem_Vinculo` | `Origem_Vinculo` | confirmado (esquema do Power Apps Studio) | Escolha (SAP) | confirmado (esquema do Power Apps Studio) |
| `Data_Vinculo` | `Data_Vinculo` | confirmado (esquema do Power Apps Studio) | Data | confirmado (esquema do Power Apps Studio) |
| `Ativo` | `Ativo` | confirmado (esquema do Power Apps Studio) | Booleano | confirmado (esquema do Power Apps Studio) |

## DIL_Fornecedores

- Nomes internos: `field_N`
- Linhas no CSV exportado: 8
- Fluxos que usam: DIL_02_Normalizar_SAP_BATCH_V10_CORRIGIDO_SEM_HTTP

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Fornecedor` | `Title` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Codigo_Fornecedor` | `field_1` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Nome_Fornecedor` | `field_2` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `CNPJ` | `field_3` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Email_Principal` | `field_4` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Telefone` | `field_5` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Contato_Principal` | `field_6` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Categoria_Fornecedor` | `field_7` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Status_Fornecedor` | `field_8` | confirmado (esquema do Power Apps Studio) | Escolha | confirmado (esquema do Power Apps Studio) |
| `Nivel_Risco_Fornecedor` | `field_9` | confirmado (esquema do Power Apps Studio) | Escolha | confirmado (esquema do Power Apps Studio) |
| `Responsavel_Fornecedor` | `field_10` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Observacao` | `field_11` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Ativo` | `field_12` | confirmado (esquema do Power Apps Studio) | Booleano | confirmado (esquema do Power Apps Studio) |
| `Responsavel_Fornecedor_Pessoa` | `Responsavel_Fornecedor_Pessoa` | confirmado (esquema do Power Apps Studio) | Pessoa | confirmado (esquema do Power Apps Studio) |

## DIL_Compromissos

- Nomes internos: `field_N`
- Linhas no CSV exportado: 3
- Fluxos que usam: DIL_05_Compromissos_Marcos_Historico

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Compromisso` | `Title` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Numero_RC` | `field_1` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Item_RC` | `field_2` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Numero_PO` | `field_3` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Item_PO` | `field_4` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Codigo_Fornecedor` | `field_5` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Tipo_Compromisso` | `field_6` | confirmado (esquema do Power Apps Studio) | Escolha | confirmado (esquema do Power Apps Studio) |
| `Data_Compromisso` | `field_7` | confirmado (esquema do Power Apps Studio) | Data | confirmado (esquema do Power Apps Studio) |
| `Data_Compromisso_Anterior` | `field_8` | confirmado (esquema do Power Apps Studio) | Data | confirmado (esquema do Power Apps Studio) |
| `Origem_Compromisso` | `field_9` | confirmado (esquema do Power Apps Studio) | Escolha | confirmado (esquema do Power Apps Studio) |
| `Status_Compromisso` | `field_10` | confirmado (esquema do Power Apps Studio) | Escolha | confirmado (esquema do Power Apps Studio) |
| `Data_Registro` | `field_11` | confirmado (esquema do Power Apps Studio) | Data | confirmado (esquema do Power Apps Studio) |
| `Responsavel_Registro` | `field_12` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Observacao` | `field_13` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Ativo` | `field_14` | confirmado (esquema do Power Apps Studio) | Booleano | confirmado (esquema do Power Apps Studio) |
| `Responsavel_Registro_Pessoa` | `Responsavel_Registro_Pessoa` | confirmado (esquema do Power Apps Studio) | Pessoa | confirmado (esquema do Power Apps Studio) |

## DIL_Marcos

- Nomes internos: `field_N`
- Linhas no CSV exportado: 4
- Fluxos que usam: DIL_05_Compromissos_Marcos_Historico, DIL_06_Ocorrencias_Acoes_V6, DIL_07_Encerrar_Ocorrencias_Acoes_V8

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Marco` | `Title` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Numero_RC` | `field_1` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Item_RC` | `field_2` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Numero_PO` | `field_3` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Item_PO` | `field_4` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Tipo_Marco` | `field_5` | confirmado (esquema do Power Apps Studio) | Escolha (Entrega) | confirmado (esquema do Power Apps Studio) |
| `Descricao_Marco` | `field_6` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Data_Planejada` | `field_7` | confirmado (esquema do Power Apps Studio) | Data | confirmado (esquema do Power Apps Studio) |
| `Data_Realizada` | `field_8` | confirmado (esquema do Power Apps Studio) | Data | confirmado (esquema do Power Apps Studio) |
| `Status_Marco` | `field_9` | confirmado (esquema do Power Apps Studio) | Escolha (Concluído, Pendente) | confirmado (esquema do Power Apps Studio) |
| `Responsavel` | `field_10` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Observacao` | `field_11` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Ativo` | `field_12` | confirmado (esquema do Power Apps Studio) | Texto (Sim, True) | confirmado (esquema do Power Apps Studio) |
| `Responsavel_Pessoa` | `Responsavel_Pessoa` | confirmado (esquema do Power Apps Studio) | Pessoa | confirmado (esquema do Power Apps Studio) |

## DIL_Ocorrencias

- Nomes internos: `field_N`
- Linhas no CSV exportado: 3
- Fluxos que usam: DIL_06_Ocorrencias_Acoes_V6, DIL_07_Encerrar_Ocorrencias_Acoes_V8, DIL_08_Escalonamento_SLA_V2

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Ocorrencia` | `Title` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Numero_RC` | `field_1` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Item_RC` | `field_2` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Numero_PO` | `field_3` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Item_PO` | `field_4` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Codigo_Fornecedor` | `field_5` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Tipo_Ocorrencia` | `field_6` | confirmado (esquema do Power Apps Studio) | Escolha (Atraso) | confirmado (esquema do Power Apps Studio) |
| `Severidade` | `field_7` | confirmado (esquema do Power Apps Studio) | Escolha (Alta, Crítica) | confirmado (esquema do Power Apps Studio) |
| `Descricao` | `field_8` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Data_Ocorrencia` | `field_9` | confirmado (esquema do Power Apps Studio) | Data | confirmado (esquema do Power Apps Studio) |
| `Status_Ocorrencia` | `field_10` | confirmado (esquema do Power Apps Studio) | Escolha (Aberta, Resolvida) | confirmado (esquema do Power Apps Studio) |
| `Responsavel` | `field_11` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Data_Resolucao` | `field_12` | confirmado (esquema do Power Apps Studio) | Data | confirmado (esquema do Power Apps Studio) |
| `Observacao` | `field_13` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Ativo` | `field_14` | confirmado (esquema do Power Apps Studio) | Booleano | confirmado (esquema do Power Apps Studio) |
| `Responsavel_Pessoa` | `Responsavel_Pessoa` | confirmado (esquema do Power Apps Studio) | Pessoa | confirmado (esquema do Power Apps Studio) |

## DIL_Acoes

- Nomes internos: `field_N`
- Linhas no CSV exportado: 5
- Fluxos que usam: DIL_06_Ocorrencias_Acoes_V6, DIL_07_Encerrar_Ocorrencias_Acoes_V8, DIL_08_Escalonamento_SLA_V2, DIL_09_Comunicacoes_SLA_V2, DIL_10_Enviar_Comunicacoes_Outlook_V8

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Acao` | `Title` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Numero_RC` | `field_1` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Item_RC` | `field_2` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Numero_PO` | `field_3` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Item_PO` | `field_4` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Chave_Ocorrencia` | `field_5` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Tipo_Acao` | `field_6` | confirmado (esquema do Power Apps Studio) | Escolha (Cobrança, Escalonamento) | confirmado (esquema do Power Apps Studio) |
| `Descricao_Acao` | `field_7` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Responsavel` | `field_8` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Prazo` | `field_9` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Status_Acao` | `field_10` | confirmado (esquema do Power Apps Studio) | Escolha (Aberta, Concluída) | confirmado (esquema do Power Apps Studio) |
| `Data_Conclusao` | `field_11` | confirmado (esquema do Power Apps Studio) | Data | confirmado (esquema do Power Apps Studio) |
| `Prioridade` | `field_12` | confirmado (esquema do Power Apps Studio) | Escolha (Alta, Crítica) | confirmado (esquema do Power Apps Studio) |
| `Observacao` | `field_13` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Ativo` | `field_14` | confirmado (esquema do Power Apps Studio) | Booleano | confirmado (esquema do Power Apps Studio) |
| `Responsavel_Pessoa` | `Responsavel_Pessoa` | confirmado (esquema do Power Apps Studio) | Pessoa | confirmado (esquema do Power Apps Studio) |

## DIL_Comunicacoes

- Nomes internos: `field_N`
- Linhas no CSV exportado: 2
- Fluxos que usam: DIL_09_Comunicacoes_SLA_V2, DIL_10_Enviar_Comunicacoes_Outlook_V8

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Comunicacao` | `Title` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Numero_RC` | `field_1` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Item_RC` | `field_2` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Numero_PO` | `field_3` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Item_PO` | `field_4` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Codigo_Fornecedor` | `field_5` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Tipo_Comunicacao` | `field_6` | confirmado (esquema do Power Apps Studio) | Escolha (Outro) | confirmado (esquema do Power Apps Studio) |
| `Data_Comunicacao` | `field_7` | confirmado (esquema do Power Apps Studio) | Data | confirmado (esquema do Power Apps Studio) |
| `Sentido` | `field_8` | confirmado (esquema do Power Apps Studio) | Escolha (Enviada) | confirmado (esquema do Power Apps Studio) |
| `Assunto` | `field_9` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Resumo` | `field_10` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Responsavel` | `field_11` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Anexo_Link` | `field_12` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Ativo` | `field_13` | confirmado (esquema do Power Apps Studio) | Booleano | confirmado (esquema do Power Apps Studio) |
| `Responsavel_Pessoa` | `Responsavel_Pessoa` | confirmado (esquema do Power Apps Studio) | Pessoa | confirmado (esquema do Power Apps Studio) |
| `Anexo_Link_1` | `Anexo_Link_1` | confirmado (esquema do Power Apps Studio) | Hyperlink | confirmado (esquema do Power Apps Studio) |

## DIL_Historico

- Nomes internos: `field_N`
- Linhas no CSV exportado: 13
- Fluxos que usam: DIL_05_Compromissos_Marcos_Historico, DIL_07_Encerrar_Ocorrencias_Acoes_V8, DIL_08_Escalonamento_SLA_V2, DIL_09_Comunicacoes_SLA_V2, DIL_10_Enviar_Comunicacoes_Outlook_V8

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Historico` | `Title` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Entidade` | `field_1` | confirmado (esquema do Power Apps Studio) | Escolha (Ação, Compromisso, Comunicação, Ocorrência) | confirmado (esquema do Power Apps Studio) |
| `Chave_Entidade` | `field_2` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Tipo_Evento` | `field_3` | confirmado (esquema do Power Apps Studio) | Escolha (Alteração, Envio_Email, Inclusão, SLA, Status) | confirmado (esquema do Power Apps Studio) |
| `Campo_Alterado` | `field_4` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Valor_Anterior` | `field_5` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Valor_Novo` | `field_6` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Data_Evento` | `field_7` | confirmado (esquema do Power Apps Studio) | DataHora | confirmado (esquema do Power Apps Studio) |
| `Usuario_Evento` | `field_8` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Origem_Evento` | `field_9` | confirmado (esquema do Power Apps Studio) | Escolha (Power Automate) | confirmado (esquema do Power Apps Studio) |
| `Observacao` | `field_10` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Usuario_Evento_Pessoa` | `Usuario_Evento_Pessoa` | confirmado (esquema do Power Apps Studio) | Pessoa | confirmado (esquema do Power Apps Studio) |

## DIL_SLA

- Nomes internos: `field_N`
- Linhas no CSV exportado: 3
- Fluxos que usam: DIL_09_Comunicacoes_SLA_V2

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_SLA` | `Title` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Nome_SLA` | `field_1` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Tipo_SLA` | `field_2` | confirmado (esquema do Power Apps Studio) | Escolha | confirmado (esquema do Power Apps Studio) |
| `Ativo` | `field_3` | confirmado (esquema do Power Apps Studio) | Booleano | confirmado (esquema do Power Apps Studio) |
| `Quantidade_Horas` | `field_4` | confirmado (esquema do Power Apps Studio) | Numero | confirmado (esquema do Power Apps Studio) |
| `Prioridade` | `field_5` | confirmado (esquema do Power Apps Studio) | Escolha | confirmado (esquema do Power Apps Studio) |
| `Nivel_Risco` | `field_6` | confirmado (esquema do Power Apps Studio) | Escolha | confirmado (esquema do Power Apps Studio) |
| `Grupo_Compradores` | `field_7` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Notificar_Email` | `field_8` | confirmado (esquema do Power Apps Studio) | Booleano | confirmado (esquema do Power Apps Studio) |
| `Notificar_Teams` | `field_9` | confirmado (esquema do Power Apps Studio) | Booleano | confirmado (esquema do Power Apps Studio) |
| `Observacao` | `field_10` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |

## DIL_Configuracao

- Nomes internos: `desconhecido`
- Linhas no CSV exportado: 3
- Fluxos que usam: —

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Configuracao` | `Title` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Categoria` | `field_1` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Nome_Parametro` | `field_2` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Valor_Texto` | `field_3` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Valor_Numero` | `field_4` | confirmado (esquema do Power Apps Studio) | Numero | confirmado (esquema do Power Apps Studio) |
| `Valor_Data` | `field_5` | confirmado (esquema do Power Apps Studio) | Data | confirmado (esquema do Power Apps Studio) |
| `Ativo` | `field_6` | confirmado (esquema do Power Apps Studio) | Booleano | confirmado (esquema do Power Apps Studio) |
| `Descricao` | `field_7` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |

## DIL_Integracao_Execucao

- Nomes internos: `field_N`
- Linhas no CSV exportado: 14
- Fluxos que usam: DIL_01_Importar_SAP, DIL_03_Fechar_Execucao_SAP, DIL_04_Normalizar_RC_PO

| Exibição (Power Apps) | Interno (fluxo/OData) | Status interno | Tipo | Status tipo |
|---|---|---|---|---|
| `Chave_Execucao` | `Title` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Tipo_Integracao` | `field_1` | confirmado (esquema do Power Apps Studio) | Escolha (SAP_Importacao) | confirmado (esquema do Power Apps Studio) |
| `Data_Inicio` | `field_2` | confirmado (esquema do Power Apps Studio) | DataHora | confirmado (esquema do Power Apps Studio) |
| `Data_Fim` | `field_3` | confirmado (esquema do Power Apps Studio) | DataHora | confirmado (esquema do Power Apps Studio) |
| `Status_Execucao` | `field_4` | confirmado (esquema do Power Apps Studio) | Escolha (Concluída, Iniciada) | confirmado (esquema do Power Apps Studio) |
| `Registros_Lidos` | `field_5` | confirmado (esquema do Power Apps Studio) | Numero | confirmado (esquema do Power Apps Studio) |
| `Registros_Processados` | `field_6` | confirmado (esquema do Power Apps Studio) | Numero | confirmado (esquema do Power Apps Studio) |
| `Registros_Erro` | `field_7` | confirmado (esquema do Power Apps Studio) | Numero | confirmado (esquema do Power Apps Studio) |
| `Arquivo_Origem` | `field_8` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Mensagem` | `field_9` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Executado_Por` | `field_10` | confirmado (esquema do Power Apps Studio) | Texto | confirmado (esquema do Power Apps Studio) |
| `Executado_Por_Pessoa` | `Executado_Por_Pessoa` | confirmado (esquema do Power Apps Studio) | Pessoa | confirmado (esquema do Power Apps Studio) |

## DIL_Documentos

**AUSENTE no pacote DIL.zip — esquema não verificado**
