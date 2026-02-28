# FibOutboxBridge

Transaktionale Outbox für Shopware 6 mit Flow-Builder-Action als Enqueue-Schicht und generischer, erweiterbarer Destination-Pipeline.

## Problem und Nutzen

Ohne Outbox entsteht ein Commit-Gap:

1. Zustand wird in der DB gespeichert (`COMMIT` erfolgreich)
2. Danach wird extern publiziert (Webhook/MQ/etc.)
3. Publish schlägt fehl (Timeout, Netzwerk, Third-Party down, Deploy, Crash)

Ergebnis: Shop-State ist geändert, Downstream-Systeme sind inkonsistent.

Die Outbox löst das durch Entkopplung:

- Persistenz zuerst (Event landet sicher in DB)
- Zustellung asynchron durch Worker
- Retry/Backoff/Dead-Letter statt Event-Verlust

Liefersemantik ist **at-least-once**.  
Für „exactly-once-Verhalten“ braucht der Consumer Idempotenz (`eventId`-Dedupe).

## Zielarchitektur

`Flow Action -> Outbox -> Worker -> Destination Strategy -> Status/Retry/Observability`

Konkret:

1. Flow nutzt Action `action.fib.outbox.send.to.destination`
2. Action schreibt in `fib_outbox_event` + `fib_outbox_delivery`
3. Dispatcher claimt Deliveries mit Locking
4. Strategy publiziert an Destination
5. Status wird aktualisiert (`pending|processing|failed|published|dead`)

Wichtig:

- `event_name` in der Outbox ist das auslösende Business-Event (z. B. `checkout.order.placed`)
- die Enqueue-Action wird als Metadatum geführt (`meta.source`)

## Kernkomponenten

- `fib_outbox_event`: Event-Persistenz
- `fib_outbox_delivery`: Zustellversuche pro Destination
- `fib_outbox_destination`: Destination-Definitionen
- `OutboxDispatcher`: Claim/Retry/Backoff/Dead-Letter
- `OutboxTargetPublisher`: delegiert an Strategy Registry
- `OutboxDestinationStrategyRegistry`: dynamische Destination-Typen

## Flow-Builder-Integration

- Action: `action.fib.outbox.send.to.destination`
- Konfiguration in Action:
  - `destinationType`
  - `destinationId`
- Kein globaler Single-Webhook mehr
- Routing über Event-Pattern ist nicht mehr der primäre Enqueue-Pfad

## Destination-Strategien (erweiterbar)

Strategien werden per Interface + Service-Tag registriert.

- Interface: `OutboxDestinationStrategyInterface`
- Registry: `OutboxDestinationStrategyRegistry`
- Tag: `fib_outbox.destination_strategy`

Built-in Typen:

- `webhook`
- `messenger`
- `flow`
- `sftp`
- `centrifugo`
- `null`

### Neue Destination hinzufügen

1. Klasse implementieren: `OutboxDestinationStrategyInterface`
2. `getType()`, `getLabel()`, `getConfigFields()`, `validateConfig()`, `publish()` implementieren
3. Service in `services.xml` registrieren und mit `fib_outbox.destination_strategy` taggen

Dadurch erscheinen Typ und Konfig-Felder automatisch in der Admin-Destination-Verwaltung und sind im Dispatcher verfügbar.

## Hinweise zu `sftp` und `centrifugo`

- `sftp` benötigt die PHP-Erweiterung `ssh2` im Runtime-Container.
- `centrifugo` nutzt die HTTP-API (`apiUrl`, `apiKey`, `channel`).

## Integritätsschicht im Flow Builder

Bei terminalen Fehlern wird `fib.outbox.delivery.failed` ausgelöst.  
Damit können Flows auf Integritätsprobleme reagieren (Alert, Ticket, Fallback-Prozess).

Zusätzlich für Flow-Ziele:

- `fib.outbox.forwarded`

## Dispatch und Status

Dispatcher-Ablauf:

1. Claim von fälligen Deliveries
2. Publish über passende Strategy
3. Erfolg -> `published`
4. Fehler -> `failed` + `available_at` (Backoff)
5. max attempts erreicht -> `dead`

Event-Status wird aus den Delivery-Status aggregiert.

## Administration

Bereiche:

- Event-Monitoring (Filter, Status, Lag, Aktionen)
- Destination-Verwaltung (dynamische Typen und typabhängige Konfig-Felder)

## CLI / Betrieb

- `fib:outbox:enqueue-test`
- `fib:outbox:dispatch`
- `fib:outbox:stats`
- `fib:outbox:reset-stuck`

Scheduled Task dispatcht regelmäßig.