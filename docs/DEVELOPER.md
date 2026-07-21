# ADAM BOT — documentação para desenvolvimento

## Visão geral

O ADAM BOT é um motor de respostas determinístico e extensível para WordPress. O núcleo não chama serviços de IA nem conhece plugins externos. Cada pergunta passa por deteção leve de intenção, seleção preguiçosa de fornecedores, normalização, ordenação por relevância e formatação de uma resposta segura.

```text
REST /adam-bot/v1/chat
  -> SearchService
     -> ProviderResolver -> fornecedores dinâmicos relevantes
     -> fornecedores estáticos ativos
     -> ResultRanker
  -> ResponseFormatter
  -> Analytics anónima
```

Todas as entradas editoriais vivem no tipo de conteúdo canónico `adam_bot_knowledge`. O metadado `Type` distingue Knowledge de FAQ, sem criar duas bases de dados ou dois pipelines de pesquisa. `Source` regista a proveniência e `Language` distingue PT de EN. As categorias usam a taxonomia hierárquica `adam_bot_category`. O WordPress gere permissões, revisões e estados editoriais.

## Registar um fornecedor dinâmico

Implemente `AdamBot\Knowledge\Dynamic\DynamicProviderInterface`. O fornecedor deve:

- devolver uma chave estável em `getKey()`;
- indicar disponibilidade sem produzir efeitos secundários;
- declarar apenas as intenções que consegue responder;
- devolver dados públicos em `search()`;
- devolver sugestões opcionais e uma prioridade entre 0 e 100;
- definir um TTL de cache adequado à frequência de atualização dos dados.

Registe-o depois de o ADAM BOT carregar:

```php
add_action(
    'adam_bot_register_dynamic_providers',
    static function ( $registry ): void {
        $registry->register( new CommunityProvider() );
    }
);
```

Também é possível usar a fachada estável:

```php
adam_bot()->providers()->register( new CommunityProvider() );
```

O plugin integrador deve verificar `function_exists( 'adam_bot' )` quando não usar a ação. Não deve incluir classes internas do ADAM BOT manualmente.

## Resultados e componentes

`search()` devolve uma lista de `DynamicSearchResult`. Use conteúdo curto, URLs públicas e metadados já sanitizados na origem. Nunca devolva contas, identificadores de sócio ou qualquer dado pessoal.

Os componentes disponíveis são `EventCard`, `TeamCard`, `FieldCard`, `PartnerCard`, `NewsCard`, `DocumentCard`, `ButtonGroup`, `InformationBox` e `WarningBox`. Um resultado pode incluir vários componentes; o formatador limita e volta a sanitizar todos os campos antes de os enviar ao navegador.

Para integrações simples, os fornecedores incorporados aceitam registos através destes filtros:

- `adam_bot_dynamic_events`
- `adam_bot_dynamic_teams`
- `adam_bot_dynamic_fields`
- `adam_bot_dynamic_partners`
- `adam_bot_dynamic_news`
- `adam_bot_dynamic_documents`
- `adam_bot_dynamic_membership`

Cada filtro recebe `( array $items, string $query, string $intent, $provider )`. Um item pode ter `title` ou `name`, `content` ou `description`, `keywords`, `synonyms`, `url`, `image`, `priority`, campos próprios do cartão e `public`. Use `matched => true` apenas quando o plugin já fez uma correspondência não lexical fiável.

## Intenções e prioridade

As intenções estáveis encontram-se em `AdamBot\Knowledge\Dynamic\Intent`. Declare o conjunto mínimo. O resolvedor só instancia fornecedores associados à intenção detetada, ordena-os por prioridade e prefere dados vivos quando existe uma correspondência útil. O conhecimento estático permanece disponível como alternativa.

Uma prioridade alta não deve compensar resultados irrelevantes. Prefira melhorar palavras-chave, sinónimos e a qualidade dos dados. A prioridade deve representar autoridade da fonte: por exemplo, dados oficiais de quotas podem preceder uma FAQ editorial.

## Cache e invalidação

O motor usa a cache de objetos do WordPress quando existe uma implementação persistente e mantém transients como alternativa. As chaves incluem uma versão de namespace. Quando o plugin proprietário altera dados pesquisáveis, execute:

```php
do_action( 'adam_bot_knowledge_invalidate_cache' );
```

Evite consultas no construtor do fornecedor. `isAvailable()` deve ser barato; a consulta real pertence a `search()`. Escolha TTL curtos para disponibilidade e eventos, e mais longos para documentos estáveis.

## Hooks e filtros principais

- `adam_bot_register_dynamic_providers`: registo de fornecedores durante a inicialização.
- `adam_bot_knowledge_provider_registry`: acrescenta rótulos/fornecedores estáticos às definições.
- `adam_bot_knowledge_invalidate_cache`: invalida resultados e força um novo namespace.
- `adam_bot_dynamic_provider_indexed_count`: fornece a contagem mostrada no Inspetor de fornecedores.
- `adam_bot_dynamic_provider_last_update`: fornece a última atualização de um fornecedor.
- `adam_bot_dynamic_provider_observed`: emitido após uma pesquisa de fornecedor, para telemetria operacional.
- `adam_bot_dynamic_provider_error`: comunica uma falha de fornecedor ao monitor.
- `adam_bot_maintenance_optimize_storage`: permite manutenção adicional diária sem acoplar o núcleo.
- `adam_bot_knowledge_event_post_types` e `adam_bot_knowledge_event_post_item`: compatibilidade opt-in para fontes de eventos WordPress.
- `adam_bot_site_index_post_types`: limita ou alarga os tipos de conteúdo públicos indexados.
- `adam_bot_site_index_include_post`: exclui uma página pública da indexação editorial.
- `adam_bot_site_index_source_html`: adapta o HTML de origem antes da extração de secções.
- `adam_bot_site_index_translation`: substitui a tradução PT→EN; devolva uma string traduzida ou `null` para usar o serviço predefinido.
- `adam_bot_site_index_remote_translation`: devolva `false` para impedir pedidos ao serviço de tradução predefinido.

## Indexação do website

Na primeira inicialização, `SiteKnowledgeIndexer` agenda uma importação única de todos os posts publicados pertencentes a tipos públicos. Cada secção editorial relevante origina um post normal `adam_bot_knowledge`, com `Type: Knowledge` ou `Type: FAQ`; estes não ficam bloqueados e participam em revisões, exportação e pesquisa como qualquer entrada criada à mão. Formulários, controlos, palavras-passe e linhas com dados de pagamento são removidos antes da extração.

O metadado de proveniência permite que **Reconstruir a base de conhecimento** detete alterações sem as aplicar. Uma entrada nova fica `Synced`; qualquer edição administrativa passa-a a `Modified`; uma alteração posterior na página guarda uma proposta separada e marca-a `Out of date`. O administrador compara as versões e decide entre **Atualizar a partir do website** e **Manter versão atual**. Nenhuma reconstrução substitui automaticamente trabalho editorial.

Páginas inglesas fornecidas por Polylang ou WPML são indexadas diretamente. Quando não existem, a tradução de conteúdo público decorre em pequenos lotes WP-Cron e cria uma variante inglesa independente na base de dados. O serviço remoto predefinido é MyMemory; integrações empresariais devem substituí-lo através de `adam_bot_site_index_translation`. Nunca envie conteúdo privado neste filtro.

## Segurança e privacidade

- Valide capacidades e nonces em todas as operações administrativas.
- Sanitize na entrada e escape no contexto de saída, mesmo quando o valor já veio de WordPress.
- Não devolva HTML de terceiros; use componentes estruturados.
- Não registe perguntas completas em logs técnicos. A analítica agrega perguntas após remoção de padrões comuns de dados pessoais.
- Não exponha dados privados de sócios. O fornecedor de sócios destina-se apenas a tipos, preços, benefícios, inscrição e renovação públicos.
- Limite quantidades, comprimentos e tempos de cache para impedir respostas e opções sem limites.

## Administração e diagnóstico

O modo de diagnóstico é ativado em **ADAM BOT > Definições**. Só administradores com `manage_options` recebem o objeto `debug` na resposta REST. O Inspetor de fornecedores mostra disponibilidade, prioridade, contagem indexada, latência, última atualização e erros sem carregar fornecedores durante conversas não relacionadas.

A manutenção diária é agendada pelo WP-Cron e limpa analítica antiga, expira namespaces de cache e pré-aquece perguntas frequentes. Instalações com tráfego irregular devem executar o WP-Cron através de uma tarefa de sistema.

## Compatibilidade e testes

O plugin requer WordPress 6.3+ e PHP 7.4+. Antes de publicar uma integração:

1. teste fornecedor indisponível, vazio, lento e com exceção;
2. confirme que apenas a intenção relevante o carrega;
3. confirme que a invalidação substitui resultados antigos;
4. teste cartões por teclado, leitor de ecrã e ecrã móvel;
5. confirme que a resposta e os logs não contêm dados privados;
6. execute `php tests/plugin-smoke.php public`, `admin` e `login`, além do lint de PHP e JavaScript.

Novos domínios — mercado, formação, voluntariado, marcação de jogos, equipamento, API pública, conteúdo multilingue ou um fornecedor de IA opcional — devem entrar como fornecedores, intenções e componentes autónomos. O pipeline central não deve ser alterado para acomodar um plugin específico.
