---
id: WP-002
title: 'WpConfigResolver: default neutro de lib, prefixo real do produto injetado pelo consumidor'
status: accepted
date: 2026-07-31
lang: pt-BR
domains: [wordpress, config]
deciders: ['Michael Meneses']
related: []
supersedes: []
superseded_by: null
enforced_by:
  mdgstan: []
  docs: []
decision: 'Os defaults do construtor de `WpConfigResolver` deixam de ser a marca MIDDAG (`MIDDAG_`/`middag_`) e passam a ser um valor neutro de lib (`MIDDAGLIB_`/`middaglib_`), que não identifica nenhum consumidor específico — só sinaliza "veio desta lib sem prefixo configurado". O par real da MIDDAG não desaparece: passa a ser injetado explicitamente pelos composition roots que a MIDDAG controla (hoje: `middag-php-core::WordPressContainerFactory::registerExtensions()`, e os bootstraps dos produtos WordPress que não passam pelo core), em vez de vir de silêncio do default da lib. O construtor continua aceitando um par de prefixos opcional para qualquer outro consumidor.'
---

# WP-002: WpConfigResolver — default neutro de lib, prefixo real do produto injetado pelo consumidor

## Context

`middag-io/wordpress` é uma lib OSS Apache-2.0, destinada a qualquer consumidor WordPress terceiro, não só a produtos MIDDAG — mesma natureza de `middag-io/moodle`. Apesar disso, `WpConfigResolver::__construct()` tinha os prefixos da MIDDAG hardcoded como default (`$envPrefix = 'MIDDAG_'`, `$optionPrefix = 'middag_'`). Consequência prática: um terceiro que instancia `new WpConfigResolver()` sem argumentos — o caminho mais óbvio ao ler a assinatura — herda silenciosamente a marca MIDDAG nas chaves de config que resolve (`MIDDAG_*` no ambiente, `middag_*` em `wp_options`), e dois terceiros diferentes que não configuram nada colidem exatamente no mesmo par de prefixos. Isso é o inverso do que uma lib genérica deveria fazer: o namespace de configuração de um consumidor não deveria depender de MIDDAG só porque ele não passou nada.

O mesmo problema, com a mesma forma, foi identificado e corrigido no adaptador irmão Moodle (`SettingsNamingPolicy::DEFAULT_PREFIX`). Esta decisão aplica o mesmo raciocínio aqui, para manter o ecossistema consistente.

Levantamento feito antes desta mudança (grep contra os composition roots reais que instanciam `WpConfigResolver` sem argumentos, portanto dependentes do default):

| Local | Situação no momento desta ADR |
|---|---|
| `middag-php-core` — `WordPressContainerFactory::registerExtensions()` | Já em correção concorrente (working tree modificado, não commitado): passou a chamar `->addArgument('MIDDAG_')->addArgument('middag_')` explicitamente |
| `wp-plugin-middag` — `MiddagBootstrap::bindPlatformAdapters()` | **Ainda registra `WpConfigResolver::class` sem argumentos** — depende do default da lib |
| `wp-plugin-middag-account` — `AccountBootstrap::bindPlatformAdapters()` | **Ainda registra `WpConfigResolver::class` sem argumentos** para o par `MIDDAG_`/`middag_` (o mesmo arquivo já injeta explicitamente um par *diferente*, `MDGA_`/`mdga_`, só para o stack JWT — isso não muda) |
| `middag-php-wordpress` (este repo) — `WpBootstrap::configure()`, `@internal` | Registra sem argumentos **de propósito** — é o baseline genérico da própria lib, não um produto; um composition root real deve sobrescrever antes de compilar |

## Considered Options

A decisão de produto (trocar o default por um valor neutro e mover a marca real para injeção explícita) já foi tomada pelo dono do projeto, com o mesmo racional aplicado ao adaptador Moodle irmão — não é uma escolha de design feita nesta ADR. O que ficou em aberto para esta implementação foi só a forma:

1. **Remover o default por completo** (parâmetro obrigatório). Rejeitada: quebra a ergonomia de "novo consumidor sobe rápido sem decorar nada"; a lib ainda precisa de *algum* comportamento sensato sem configuração, só não pode ser o de uma marca alheia.
2. **Manter `MIDDAG_`/`middag_` como default e documentar "troque em produção"**. Rejeitada: é exatamente o estado atual, e depende de disciplina de quem nunca vai ler o docblock; o próprio consumidor MIDDAG só não colide consigo mesmo por coincidência de ser o autor do default.
3. **Default neutro (`MIDDAGLIB_`/`middaglib_`), valor real injetado explicitamente pelo composition root de cada produto** ← escolhida. Nenhum terceiro herda marca alheia por omissão; dois terceiros que não configuram nada colidem só entre "vieram de lib sem configurar", nunca com MIDDAG; e o valor real da MIDDAG continua existindo, só que dito em voz alta no lugar certo.

## Decision

`WpConfigResolver::__construct()` passa a ter `$envPrefix = 'MIDDAGLIB_'` e `$optionPrefix = 'middaglib_'` como default. O docblock da classe deixa de descrever isso como "the defaults" sem qualificação e passa a nomear explicitamente que é o default neutro da lib, e que um produto real (MIDDAG incluída) injeta seu próprio par no composition root.

Isso é **extensível por construção**: o construtor continua recebendo `$envPrefix`/`$optionPrefix` como parâmetros normais (não `final` nem sealed), então qualquer consumidor — MIDDAG ou terceiro — passa seu próprio par sem precisar de nenhuma mudança nesta lib. Nenhum ponto de extensão foi removido; só o que caía em silêncio no default passou a precisar de uma linha explícita.

## Consequences

- Todo teste de `WpConfigResolverTest` que chamava `new WpConfigResolver()` sem argumentos e esperava ler `MIDDAG_*`/`middag_*` foi atualizado para `MIDDAGLIB_*`/`middaglib_*` — o teste continua cobrindo a mesma regra de precedência (env > option > default), só que contra o valor de default correto agora. `customPrefixesAreHonoured` (já passava prefixos explícitos) não mudou.
- **Item crítico, fora do escopo deste commit mas registrado aqui para rastreio**: enquanto os dois composition roots de produto listados na tabela acima (`wp-plugin-middag` e `wp-plugin-middag-account`) não passarem a injetar `'MIDDAG_', 'middag_'` explicitamente, atualizar a dependência de `middag-io/wordpress` neles fará toda chave de config em produção mudar de nome em silêncio (ex.: env `MIDDAG_SMTP_HOST` deixa de ser lida, o resolver passa a procurar `MIDDAGLIB_SMTP_HOST`) — sem exceção, sem log, sem erro. Esta versão da lib **não deve ser consumida** por esses dois produtos até essa injeção existir nos dois. A correção do lado `middag-php-core` já está em andamento (não commitada) numa sessão concorrente.
- `WpBootstrap::configure()` (deste repo, `@internal`) continua registrando sem argumentos — é o comportamento correto sob esta decisão, não uma lacuna: é o baseline genérico da própria lib, e um composition root real já sobrescreve `ConfigResolverInterface` antes de compilar.
- Mudança de comportamento real (o valor lido por um consumidor não configurado muda), sem mudança de assinatura pública — tratada como `fix(config)!` com `Release-As` explícito, não como bump automático de major.
