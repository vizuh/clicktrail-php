[English](README.md) | [Português](README.pt-BR.md) | [Deutsch](README.de.md) | [中文](README.zh-CN.md)

<div align="center">

**clicktrail/php-sdk**

Motor determinístico de contexto de aquisição para PHP. Ele interpreta dados
observados na requisição, aplica regras de primeiro e último toque e cria
eventos canônicos.

</div>

[![CI](https://github.com/vizuh/clicktrail-php/actions/workflows/ci.yml/badge.svg)](https://github.com/vizuh/clicktrail-php/actions/workflows/ci.yml)
[![License: MIT](https://img.shields.io/badge/License-MIT-blue.svg)](LICENSE)

## Índice

- [Por quê](#por-quê)
- [Instalação](#instalação)
- [Captura de atribuição](#captura-de-atribuição)
- [Controle de consentimento](#controle-de-consentimento)
- [Entrega](#entrega)
- [Paridade de fixtures](#paridade-de-fixtures)
- [Testes](#testes)
- [Licença](#licença)

## Por quê

O ClickTrail preserva o contexto de campanha observado em uma requisição. Ele
não prova qual campanha causou um lead ou uma venda. Este SDK centraliza as
regras determinísticas de primeiro e último toque e as valida com os mesmos
golden fixtures usados pelas integrações WordPress e GTM.

## Instalação

```bash
composer require clicktrail/php-sdk
```

## Captura de atribuição

```php
use ClickTrail\Core\{AttributionInput, StoredState, TouchMerger};

// Uma landing de busca paga...
$merged = TouchMerger::observe(
    StoredState::fromJson($session['ct'] ?? null),
    new AttributionInput(
        query: $_GET,
        host: 'example.com',
        landingPage: 'https://example.com/promo',
        touchTimestamp: '2026-08-24T10:00:00.000Z',
    ),
);

$session['ct'] = $merged->toJson();
// first->source === 'google', first->clickIds['gclid'] preenchido,
// e esse resultado exato se reproduz identicamente no WordPress, no GTM e na Shopware.
```

Uma visita direta depois disso não muda nada; o primeiro toque permanece e o último toque armazenado persiste. Essa é a lei de mesclagem: testada, não prometida.

## Controle de consentimento

```php
use ClickTrail\Consent\{ConsentBehavior, ConsentSnapshot};

if (! ConsentBehavior::can($snapshot, 'ad_user_data')) {
    // enhanced conversion suprimido; motivo registrado:
    // "adUserData was unknown at lead capture (source: cookieyes)"
}
```

Consentimento desconhecido é negado por padrão. Os snapshots viajam com o lead, então meses depois o worker de conversão sabe exatamente quais permissões existiam na captura.

## Entrega

```php
$client->track(new Sale(eventId: 'order-8241-paid', orderId: '8241', value: 199.0, currency: 'EUR'));
$client->flush(); // POST em lote, chaves de idempotência, retries com backoff
```

Lotes que falham são capturados pela camada de persistência do seu adapter via `pending()` / `restore()` e reenviados após o diagnóstico.

## Paridade de fixtures

O `bin/replay-fixtures.php` repassa os golden fixtures do clicktrail-js: atualmente **12/12 fixtures, 57/57 campos MATCH**. Ledger: [docs/FIXTURE-PARITY-LEDGER.md](docs/FIXTURE-PARITY-LEDGER.md). Mudanças no classificador são releases MAJOR, por definição.

## Testes

```bash
vendor/bin/phpunit --testdox          # suíte completa
php bin/replay-fixtures.php <dir>     # ledger de paridade
```

## Licença

MIT.
