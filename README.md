# FibOutboxBridge

Plugin für eine transaktionale Outbox in Shopware 6.

## Warum dieses Plugin existiert (Problem)

Das typische Integrationsproblem sieht so aus:

1. Shopware schreibt Daten erfolgreich in die Datenbank (z. B. Bestand geändert)
2. Danach wird ein Event an RabbitMQ / Webhook / externes System gesendet
3. Genau dieser zweite Schritt schlägt fehl (Timeout, Netzwerk, Deploy, Worker-Crash, Broker down)

Ergebnis:

- Der Zustand in Shopware ist bereits geändert
- Das externe System erfährt nichts davon
- Beide Systeme laufen auseinander (Inkonsistenz)

Das ist kein Randfall, sondern ein normaler Betriebsfall bei verteilten Systemen.

### Warum "einfach direkt publishen" nicht robust ist

Ein direkter Publish im Request-/Service-Flow koppelt zwei unterschiedliche Fehlermodelle:

- Datenbank-Commit (lokal, transaktional, zuverlässig)
- Netzwerk-/MQ-Übertragung (extern, potenziell instabil)

Diese Kopplung ist fragil, weil man keinen gemeinsamen Commit über DB + MQ hast.
Wenn die DB committed ist und der Publish danach fehlschlägt, ist das Event verloren.

## Was die Outbox löst (Nutzen)

Die Outbox trennt Persistenz des Fachzustands von der Auslieferung nach außen.

Statt "DB schreiben und sofort publishen" passiert:

1. Fachänderung wird geschrieben
2. Event wird gleichzeitig in `fib_outbox_event` gespeichert
3. Beides in derselben DB-Transaktion (für eigene Writes via `OutboxEventBus::transactional()`)
4. Ein separater Dispatcher veröffentlicht die Events später asynchron

### Konkreter Benefit

- Kein Event-Verlust nach erfolgreichem DB-Commit
- Wiederholbare Zustellung bei temporären Fehlern (Retry/Backoff)
- Betriebssicherheit bei Deployments / Neustarts / Broker-Ausfällen
- Monitoring und Kontrolle (Pending/Dead/Reset/Replay)
- Entkopplung: Shopware-Request muss nicht auf externes System "gesund" warten

Praktisch bedeutet das:

- Wenn RabbitMQ 10 Minuten nicht erreichbar ist, gehen Events nicht verloren
- Sie bleiben in der Outbox und werden später zugestellt
- Downstream-Systeme können wieder synchronisiert werden, statt dass Daten still fehlen

## Wichtige semantische Einordnung (realistisch)

Dieses Plugin liefert standardmäßig eine at-least-once Zustellung.

Das ist in der Praxis die richtige Wahl, weil es robust ist.
"Exactly once" ist über Systemgrenzen hinweg nur mit zusätzlicher Idempotenz im Consumer realistisch.

### Was das für Consumer bedeutet

Ein Consumer sollte `eventId` als Dedupe-Key behandeln:

- bereits verarbeitetes `eventId` => ignorieren
- optional Versionierung / Sequenz pro Aggregat verwenden

Damit wirkt die Verarbeitung in der Praxis "exactly once", obwohl die Zustellung technisch at-least-once ist.

## Enthalten

- Outbox-Tabelle `fib_outbox_event` (Migration)
- DAL-Entity `fib_outbox_event` fuer Admin/Monitoring (Hybrid)
- `OutboxEventBus` fuer transaktionales `record()/flush()`
- Dispatcher mit Locking, Retry/Backoff und Dead-Letter-Status
- Publisher-Modi: `messenger`, `webhook`, `null`
- CLI:
  - `fib:outbox:enqueue-test`
  - `fib:outbox:dispatch`
  - `fib:outbox:stats`
  - `fib:outbox:reset-stuck`
- Scheduled Task (minütlich)
- Best-effort Bridge für `product.written` Stock-Änderungen
- Einfache Admin-Monitoring-Ansicht (Liste + Status-Counter + Pending-Lag)
  - inkl. Aktionen: Dispatch Batch, Reset Stuck, Replay Dead

Hinweis: `eventId` wird als Shopware-UUID-Hex (`CHAR(32)`) gespeichert.

## Architektur (Hybrid-Modell: DBAL + DAL)

Dieses Plugin nutzt bewusst ein Hybrid-Modell:

- DBAL für den Dispatch-Kern (Claim/Lock/Retry/Backoff)
- DAL für Admin/Monitoring (Listing, Filter, Debugging)

### Warum nicht nur DAL?

Die Outbox ist im Dispatcher-Pfad keine normale CRUD-Entity, sondern eine konkurrierende Work-Queue.
Für parallele Worker brauchst man robuste Claim-/Lock-Mechanik (z. B. `FOR UPDATE SKIP LOCKED` oder optimistische Claim-Updates).

Das ist in Shopware-DAL nicht sauber ausdrückbar. Deshalb bleibt der Queue-Kern SQL-/DBAL-nah.

### Warum trotzdem DAL?

Für Administration und Monitoring ist DAL ideal:

- Entity-Listing im Admin
- Filtern/Sortieren/Debugging
- Spätere Erweiterungen (Detailseite, Replay-Dialoge, Metriken)

So bekommst man beides:

- betriebssicheren Dispatch
- gute Backoffice-Transparenz

## Messenger für externes MQ (RabbitMQ etc.)

`publisherMode=messenger` dispatcht `Fib\OutboxBridge\Core\Outbox\Message\OutboundDomainEventMessage`
auf den Symfony Messenger Bus. Route diese Klasse in `config/packages/messenger.yaml`
auf einen externen Transport (AMQP/RabbitMQ), damit externe Consumer die Events lesen koennen.

Wichtig:

- Das Plugin "öffnet" nicht selbst RabbitMQ im Netzwerk
- Es publiziert in den Symfony Messenger

## Wann welche Event-Erzeugung gilt

### 1. Eigene Writes (stark empfohlen, transaktional)

Für eigene Use Cases / Services:

- Domain-Write + Outbox-Write in einer DB-Transaktion
- stärkste Garantie

Dafür ist `OutboxEventBus::transactional()` gedacht.

### 2. Core-/Fremd-Writes via Subscriber (best effort)

Beispiel im Plugin:

- `product.written` -> Stock-Event in Outbox

Das ist sehr nützlich, aber nicht dieselbe harte Garantie wie bei eigenen Writes,
weil man an den Shopware-Core-Write-Lifecycle gebunden ist.
Trotzdem ist es deutlich robuster als direkt extern zu publishen.

## Dispatch-Verhalten (kurz)

- Claim Batch mit Locking
- Publish je Event (`messenger` / `webhook` / `null`)
- Erfolg => `published`
- Fehler => Retry mit Backoff
- zu viele Fehler => `dead`

Backoff ist exponentiell (mit Cap), damit Fehler nicht dauerhaft das System fluten.

## Betrieb / Monitoring

Die Admin-Ansicht hilft bei typischen Betriebsfragen:

- Warum kommen externe Events verspätet an?
- Wie viele hängen in `pending`?
- Gibt es `dead` Events?
- Sind Worker hängen geblieben (`processing`, Lock abgelaufen)?

Aktionen im Admin:

- `Dispatch batch`
- `Reset stuck`
- `Replay dead`

Damit kannst man viele Inszidenz-Fälle ohne DB-Handarbeit beheben.

## Beispiel (eigene Domain-Transaktion)

```php
$outboxEventBus->transactional(function (OutboxEventBus $bus) use ($service) {
    $service->doWrite();

    $bus->recordNamed(
        'catalog.product.stock_changed.v1',
        'product',
        $productId,
        ['stock' => 9],
        ['correlationId' => '...']
    );
});
```

## Kurz gesagt (Plausibilisierung)

Ohne Outbox:

- "State geändert, Event weg" ist möglich und passiert in der Praxis.

Mit Outbox:

- "State geändert" impliziert zumindest: "Event ist persistent vorhanden und später dispatchbar".

Genau dieser Unterschied macht Integrationen deutlich robuster und reduziert stille Datenfehler in externen Systemen.
