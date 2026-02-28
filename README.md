# FibOutboxBridge

Plugin für eine transaktionale Outbox in Shopware 6 mit Flow-Action-basierter Zustellung an generische Destination-Typen.

## Warum das Plugin notwendig ist

Das Kernproblem bei Integrationen ist ein Commit-Gap:

1. Shopware schreibt den neuen Zustand in die DB (`COMMIT` erfolgreich)
2. Danach wird extern publiziert (MQ/Webhook)
3. Genau dieser Publish schlägt fehl (Timeout, Netzwerk, Broker down, Deploy, Crash)

Dann ist der Shop-State bereits geändert, aber das externe System weiß nichts davon.
Dieses Inkonsistenzfenster ist in verteilten Systemen normal und nicht mit "mehr try/catch" lösbar.

## Was die Outbox konkret verbessert

Die Outbox entkoppelt **State-Persistenz** von **externer Auslieferung**:

1. Domain-Write + Outbox-Event werden zusammen persistent geschrieben
2. Dispatcher-Worker liefert später asynchron aus
3. Fehler führen zu Retry/Backoff statt Event-Verlust

Praktischer Nutzen:

- Kein stiller Event-Verlust nach DB-Commit
- Recoverbar nach Broker-/Webhook-Ausfall
- Kontrollierbarer Betrieb (Pending/Dead/Replay/Reset)
- Saubere Trennung von Request-Latenz und Integrationskanal

## Liefersemantik (realistisch)

Die Zustellung ist **at-least-once**.
Für "exactly-once-Verhalten" braucht der Consumer Idempotenz (`eventId` dedupe).

## Architektur (Hybrid-Modell)

Das Plugin nutzt bewusst **DBAL + DAL**:

- DBAL im Queue-Kern (Claim/Lock/Retry/Backoff, konkurrierende Worker)
- DAL für Admin-CRUD und Transparenz (Destinations, Events)

Warum DBAL im Kern:

- Dispatcher braucht Work-Queue-Semantik mit Row-Locking/Claiming
- Das ist im DAL nicht robust genug ausdrückbar

Warum DAL trotzdem:

- Gute Admin-Bedienbarkeit
- Entity-Listing/Filter/CRUD ohne Sonderlogik

## Enthaltene Komponenten

- `fib_outbox_event` (Events)
- `fib_outbox_delivery` (Delivery pro Destination)
- `fib_outbox_destination` (Destinationen)
- Flow Action als Enqueue-Schicht:
  - `action.fib.outbox.send.to.destination` (mit `destinationType` + `destinationId` in der Action-Konfiguration)
- `OutboxEventBus` für transaktionales Recording
- Dispatcher mit Locking, Retry, Backoff, Dead-Letter
- Admin-Modul:
  - Event-Monitoring
  - Destination-Verwaltung (ohne JSON-Datei)
- Flow-Business-Events:
  - `fib.outbox.forwarded` (Flow-Target)
  - `fib.outbox.delivery.failed`

## Zielarchitektur: Flow Action -> Outbox -> Worker -> Connector

Es gibt keine globale `webhookUrl`/`publisherMode`/`routingConfig` mehr.
Outbound wird explizit im Flow Builder ausgelöst:

1. In Flow die Action `send_to_destination` wählen
2. In der Action den Destination-Typ und die konkrete Destination auswählen
3. Action schreibt nur in die Outbox (`fib_outbox_event` + `fib_outbox_delivery`)
4. Worker liefert asynchron genau diese Destination aus

Beispiele:

- Flow „Bestellung platziert“ + Destination Typ `webhook` + Destination `erp-order-webhook`
- Flow „Kunde registriert“ + Destination Typ `messenger` + Destination `crm-events`

## Flow-Builder-Integritätsschicht

Für Reaktionen auf Integritätsprobleme steht ein Delivery-Fehler-Event bereit.
Damit kann ein Shopbetreiber in Flows sauber reagieren, z. B.:

- bei `fib.outbox.delivery.failed` internen Alert senden (nur bei terminalem `dead`)

Damit wird die Integritätsschicht in den Flow Builder verlagert, statt starr im Code.

## Dispatch-Ablauf

1. Deliveries claimen (Lock + Worker Owner)
2. Destination Connector publishen (`messenger`/`webhook`/`flow`/`null`)
3. Erfolg -> `published`
4. Fehler -> `failed` + Retry mit exponentiellem Backoff
5. Bei Max-Attempts -> `dead`
6. Event-Status wird aus Delivery-Status aggregiert

Tracking-Metadaten:

- `delivery_id` wird durchgereicht
- bei Webhook als Header (`X-Outbox-Delivery-Id`, `X-Outbox-Destination-*`)
- bei Flow als verfügbare Werte (`deliveryId`, `destinationId`, `destinationKey`)

## CLI / Betrieb

- `fib:outbox:enqueue-test`
- `fib:outbox:dispatch`
- `fib:outbox:stats`
- `fib:outbox:reset-stuck`

Scheduled Task dispatcht regelmäßig.

## Shopware Beyond / Community Einordnung

- Community: Plugin liefert fehlende, robuste Webhook-/MQ-Fähigkeit über Outbox
- Beyond: Auch mit Flow-Webhooks bleibt Outbox sinnvoll, wenn Zustellintegrität, Retry, Dead-Letter und Replay benötigt werden

## Beispiel: transaktionaler Write

```php
$outboxEventBus->transactional(function (OutboxEventBus $bus) use ($service, $productId) {
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
