# fqdn_issue Demo

Dieses Repository enthält eine kleine Demo, die sich auf **Cookie-Scoping / Cookie-Leakage zwischen Services** konzentriert.

## Doku
- Siehe: [`cookie-leakage_de.md`](./cookie-leakage_de.md)

## Domain-Bestandteile (Diagramm)

### `finanz.bund.de`

```text
finanz.bund.de
│     │    └─ TLD
│     └────── registrierbare Domain (eTLD+1): bund.de
└──────────── Host-/Service-Label
```

### `finanz.region.gv.at`

```text
finanz.region.gv.at
│      │      │  └─ TLD
│      │      └──── PSL-Suffix / Boundary: gv.at
│      └─────────── registrierbare Domain (eTLD+1): region.gv.at
└────────────────── Host-/Service-Label
```

## Warum sich `bund.de` anders verhält als `gv.at`

Browser bestimmen die **Grenze der registrierbaren Domain** (oft **eTLD+1** genannt) mithilfe der **Public Suffix List (PSL)**.
Diese Grenze wird für wichtige Sicherheitsentscheidungen verwendet (Cookies, „Same-Site“, usw.).

### `bund.de` (normale registrierbare Domain)

Für typische `.de`-Domains gilt:

- Die TLD ist `.de`.
- Ein Name wie `bund.de` ist eine normale registrierbare Domain.
- Hosts wie `finanz.bund.de` und `meldeamt.bund.de` sind Subdomains derselben registrierbaren Domain (`bund.de`).

Dadurch ist es möglich (und in Shared-SSO-Setups häufig), Cookies breit zu scopen:

- `Domain=.bund.de`

Diese eine Entscheidung erzeugt eine **gemeinsame Cookie-Zone** über `*.bund.de` und erhöht damit den „Blast Radius“:
Jeder Schwester-Service unter `*.bund.de` erhält diese Cookies automatisch.

### `gv.at` (PSL-Grenze)

`gv.at` ist in der **Public Suffix List** enthalten.
Das bedeutet: Browser behandeln `gv.at` für Cookie-Zwecke wie eine Grenze ähnlich einer TLD.

- Ein „Supercookie“ auf `Domain=.gv.at` ist nicht sinnvoll bzw. wird von Browsern abgelehnt.
- Ein Host wie `finanz.region.gv.at` liegt unter der registrierbaren Domain `region.gv.at` (nicht unter `gv.at`).

In der Praxis führt das zu **granulareren Session-Grenzen**:

- Jeder Service behält typischerweise einen Host-only Session-Cookie (z.B. `__Host-...` Cookies)
- Service-übergreifendes SSO ist weiterhin möglich, aber meist über explizite Flows (Redirects + Einmal-Codes/Tokens) statt über einen einzigen, shared Parent-Domain-Cookie

Der wichtigste Sicherheits-Effekt ist ein kleinerer Standard-Blast-Radius:
Die Kompromittierung eines Services führt weniger wahrscheinlich automatisch dazu, dass andere Schwester-Services eine wiederverwendbare gemeinsame Session „mitbekommen“.

Mit AI automatisch übersetzt von [`README.md`](./README.md).