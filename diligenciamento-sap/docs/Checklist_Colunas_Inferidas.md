# Checklist — colunas usadas pelo app sem evidência direta (gerado)

> Gerado por `scripts/validar_fontes.py`. Conferir cada item em *Configurações da lista* no SharePoint (nome e tipo) antes de liberar o app. Se algo divergir, corrija o gerador, não o Studio.

| Coluna (exibição) | Lista | Interno | Tipo usado | Evidência |
|---|---|---|---|---|
| `Anexo_Link` | DIL_Comunicacoes | `field_12` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Codigo_Fornecedor` | DIL_Itens_Pedido | `Codigo_Fornecedor` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Codigo_Fornecedor` | DIL_Compromissos | `field_5` (inferido (posição; coluna confirmada pelo usuário)) | Texto | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Codigo_Fornecedor` | DIL_Ocorrencias | `field_5` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Codigo_Fornecedor` | DIL_Comunicacoes | `field_5` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Data_1_Remessa_Item_SAP` | DIL_Itens_Pedido | `Data_1_Remessa_Item_SAP` (confirmado (usado em fluxo)) | Data | inferido (valores do CSV) |
| `Data_Compromisso_Anterior` | DIL_Compromissos | `field_8` (inferido (posição; coluna confirmada pelo usuário)) | DataHora | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Data_Conclusao` | DIL_Acoes | `field_11` (confirmado (usado em fluxo)) | Data | inferido (valores do CSV) |
| `Data_Planejada` | DIL_Marcos | `field_7` (confirmado (usado em fluxo)) | Data | inferido (valores do CSV) |
| `Data_Realizada` | DIL_Marcos | `field_8` (inferido (posição)) | Data | inferido (valores do CSV) |
| `Data_Registro` | DIL_Compromissos | `field_11` (inferido (posição; coluna confirmada pelo usuário)) | DataHora | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Data_Resolucao` | DIL_Ocorrencias | `field_12` (confirmado (usado em fluxo)) | Data | inferido (valores do CSV) |
| `Descricao_Acao` | DIL_Acoes | `field_7` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Descricao_Marco` | DIL_Marcos | `field_6` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Descricao_Produto` | DIL_Itens_Pedido | `Descricao_Produto` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Item_PO` | DIL_Marcos | `field_4` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Item_PO` | DIL_Ocorrencias | `field_4` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Item_PO` | DIL_Acoes | `field_4` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Item_PO` | DIL_Comunicacoes | `field_4` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Item_RC` | DIL_Marcos | `field_2` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Item_RC` | DIL_Ocorrencias | `field_2` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Item_RC` | DIL_Acoes | `field_2` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Item_RC` | DIL_Comunicacoes | `field_2` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Nome_Fornecedor` | DIL_Itens_Pedido | `Nome_Fornecedor` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Nome_Parametro` | DIL_Configuracao | `Nome_Parametro` (NÃO CONFIRMADO (nenhum fluxo usa a lista)) | Texto | inferido (padrão; sem evidência contrária) |
| `Nome_SLA` | DIL_SLA | `field_1` (inferido (posição)) | Texto | inferido (padrão; sem evidência contrária) |
| `Numero_PO` | DIL_Marcos | `field_3` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Numero_PO` | DIL_Ocorrencias | `field_3` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Numero_PO` | DIL_Acoes | `field_3` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Numero_PO` | DIL_Comunicacoes | `field_3` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Numero_RC` | DIL_Marcos | `field_1` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Numero_RC` | DIL_Ocorrencias | `field_1` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Numero_RC` | DIL_Acoes | `field_1` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Numero_RC` | DIL_Comunicacoes | `field_1` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Origem_Compromisso` | DIL_Compromissos | `field_9` (inferido (posição; coluna confirmada pelo usuário)) | Escolha | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Responsavel_Pessoa` | DIL_Marcos | `Responsavel_Pessoa` (inferido (= nome de exibição)) | Pessoa | inferido (sufixo _Pessoa) |
| `Responsavel_Pessoa` | DIL_Ocorrencias | `Responsavel_Pessoa` (inferido (= nome de exibição)) | Pessoa | inferido (sufixo _Pessoa) |
| `Responsavel_Pessoa` | DIL_Comunicacoes | `Responsavel_Pessoa` (inferido (= nome de exibição)) | Pessoa | inferido (sufixo _Pessoa) |
| `Responsavel_Registro` | DIL_Compromissos | `field_12` (inferido (posição; coluna confirmada pelo usuário)) | Texto | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Responsavel_Registro_Pessoa` | DIL_Compromissos | `Responsavel_Registro_Pessoa` (inferido (= nome de exibição)) | Pessoa | confirmado (usuário: print de Configurações da lista > Colunas, enviado em 21/10/2026) |
| `Status_EF` | DIL_Itens_Pedido | `Status_EF` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Status_EM` | DIL_Itens_Pedido | `Status_EM` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Status_Liberacao_PO` | DIL_Itens_Pedido | `Status_Liberacao_PO` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Usuario_Evento` | DIL_Historico | `field_8` (inferido (posição)) | Texto | inferido (padrão; sem evidência contrária) |
| `Usuario_Evento_Pessoa` | DIL_Historico | `Usuario_Evento_Pessoa` (inferido (= nome de exibição)) | Pessoa | inferido (sufixo _Pessoa) |
| `Valor_Anterior` | DIL_Historico | `field_5` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Valor_Novo` | DIL_Historico | `field_6` (confirmado (usado em fluxo)) | Texto | inferido (padrão; sem evidência contrária) |
| `Valor_Texto` | DIL_Configuracao | `Valor_Texto` (NÃO CONFIRMADO (nenhum fluxo usa a lista)) | Texto | inferido (padrão; sem evidência contrária) |

Total pendente de conferência: **48** coluna(s).

Observação: o mesmo nome de exibição existe em várias listas; a tabela lista cada lista usada pelo app em que a coluna ainda não tem evidência direta (pode haver itens não usados naquela lista específica).
