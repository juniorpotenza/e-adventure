# Perfis e permissões — proposta para validação

Esta matriz é uma proposta funcional, não uma configuração de segurança já aplicada.

| Capacidade | Diligenciador | Coordenador | Gestor | Auditor | Administrador |
|---|---|---|---|---|---|
| Consultar carteira | Própria/atribuída | Equipe | Escopo autorizado | Leitura autorizada | Escopo administrativo |
| Registrar ocorrência | Sim | Sim | Conforme política | Não por padrão | Sim, se necessário |
| Atualizar ações | Atribuídas | Equipe | Conforme política | Não | Sim, se necessário |
| Consultar indicadores | Próprios | Equipe | Área/organização autorizada | Leitura | Leitura |
| Editar regras funcionais | Não | Delegável | Delegável | Não | Sim |
| Editar modelos de comunicação | Não | Delegável | Delegável | Não | Sim |
| Gerenciar usuários/perfis | Não | Não por padrão | Não por padrão | Não | Sim |
| Consultar histórico/auditoria | Registros relacionados | Equipe | Escopo autorizado | Leitura autorizada | Sim |
| Alterar parâmetros técnicos | Não | Não | Não | Não | Restrito a administradores técnicos |

## Regras de implementação
1. Ocultar itens de menu por perfil melhora a experiência, mas não substitui segurança na fonte de dados.
2. Definir se as ações usam credenciais do usuário ou conexão do proprietário do fluxo; isso afeta o acesso efetivo.
3. Separar configuração funcional de configuração técnica.
4. Aplicar privilégio mínimo e testar com uma conta de cada perfil.
5. Registrar alterações de configuração e operações críticas em histórico/auditoria conforme a estrutura existente.
