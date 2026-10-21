# Matriz de permissões

> Proposta funcional + o que o app já aplica na interface. **Interface não é segurança**: quem tem permissão de
> edição numa lista SharePoint pode alterá-la fora do app. Aplicar também as permissões da §3.

## 1. Perfil no app

`gblPerfil` = `Valor_Texto` da linha de `DIL_Configuracao` com `Categoria = "Perfil_Usuario"` e
`Nome_Parametro` = e-mail do usuário em minúsculas. Sem linha → **Diligenciador** (privilégio mínimo).
⏳ Proposta pendente de aprovação; nenhuma linha foi criada.

## 2. O que o app libera por perfil (implementado)

| Capacidade | Diligenciador | Coordenador | Gestor | Auditor | Administrador |
|---|---|---|---|---|---|
| Início, Carteira, Dossiê, Agenda, Comunicações (consulta) | ✔ | ✔ | ✔ | ✔ | ✔ |
| Registrar ocorrência / alterar status | ✔ | ✔ | ✔ | — | ✔ |
| Registrar ação | ✔ | ✔ | ✔ | — | ✔ |
| Atualizar andamento de ação | só as próprias | todas | todas | — | todas |
| Criar ação do tipo Escalonamento | — | — | — | — | — (exclusivo do DIL_08) |
| Enviar e-mail | — | — | — | — | — (exclusivo do DIL_10) |
| Visão da gestão | — | ✔ | ✔ | ✔ | ✔ |
| Administração (somente leitura nesta versão) | — | — | ✔ (funcional) | — | ✔ |

Telas restritas também checam o perfil no `OnVisible` (acesso por navegação direta é bloqueado).

## 3. Permissões SharePoint recomendadas (⏳ a configurar e testar — S-03)

| Lista | Diligenciador / Coordenador / Gestor | Auditor | Administrador técnico | Conta dos fluxos |
|---|---|---|---|---|
| `DIL_Ocorrencias`, `DIL_Acoes`, `DIL_Historico` | Contribuir (necessário para gravar pelo app) | Leitura | Controle total | Contribuir |
| `DIL_Configuracao` | **Leitura** (senão o usuário pode se autoatribuir perfil) | Leitura | Controle total | — |
| `DIL_Vinculo_RC_PO`, `DIL_Itens_Pedido`, `DIL_Marcos`, `DIL_Comunicacoes`, `DIL_SLA`, `DIL_Integracao_Execucao` | Leitura | Leitura | Controle total | Contribuir (fluxos que gravam) |
| `DIL_Importacao_SAP`, `DIL_Requisicoes`, `DIL_Itens_Requisicao`, `DIL_Pedidos`, `DIL_Fornecedores`, `DIL_Compromissos` | Leitura (ou sem acesso) | Leitura | Controle total | Contribuir |

Observações:
- Os fluxos rodam com a **conexão do proprietário**; o acesso deles independe do usuário do app.
- SharePoint não restringe "editar só as próprias ações" por coluna Pessoa sem permissão por item; o app
  restringe na interface, mas um Diligenciador com Contribuir pode editar qualquer ação direto na lista. Se isso
  for inaceitável, a alternativa é gravar via fluxo com conexão de serviço — mudança de arquitetura a aprovar.
- Testar com uma conta de cada perfil (Plano de Testes, seção D).

## 4. Configuração funcional × técnica

- **Funcional (delegável ao Gestor):** perfis de usuários, parâmetros de negócio. Hoje somente leitura no app.
- **Técnica (restrita):** horas de SLA (fixas no DIL_08), modelos de e-mail (DIL_10), integrações, permissões.
  Mudanças exigem plano de regressão e aprovação.
