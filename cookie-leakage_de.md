# Cookie-Leakage Demo (bund.de)

## Ziel
Zeigen, dass ein Cookie, der von einem Service gesetzt wird, an einen anderen Service auf derselben Parent-Domain gesendet wird, wenn der Cookie zu breit gescoped ist (z.B. `Domain=.bund.de`).

## Kernaussage (der Beweis)
Wenn du `https://meldeamt.bund.de/` öffnest, sendet der Browser den Cookie, der ursprünglich von `https://finanz.bund.de/` gesetzt wurde.

Das liegt nicht daran, dass beide Apps „dieselbe App“ sind.
Es liegt daran, dass der Cookie mit einem Domain-Scope gesetzt wurde, der beide Hosts umfasst.

## Schritte
1. Öffne `https://finanz.bund.de/`
2. Klicke `Set demo cookie`
3. Öffne `https://meldeamt.bund.de/`
4. Prüfe im Abschnitt `Cookies the browser sends to this host`, dass der Cookie von `finanz.bund.de` vorhanden ist.

## Was das demonstriert
Wenn ein Service Cookies „locker“ setzt, z.B.

`Set-Cookie: session=...; Domain=.bund.de`

Dann erhält **jede Subdomain** unter `bund.de` diesen Cookie.

## Warum die Konfigurationen in der Praxis unterschiedlich sein müssen
In realen Deployments wählst du eine Cookie-Policy je nach Ziel:

- **Geteilter SSO-Cookie (großer Blast Radius)**
  - Du setzt `Domain=.bund.de`, wenn ein geteilter Session-Cookie für viele Services unter `*.bund.de` gültig sein soll.
  - Das ist in SSO-Setups verbreitet, ist aber inhärent riskant: eine kompromittierte Subdomain kann häufig Auswirkungen auf andere Schwester-Services haben.

- **Host-only Cookies (kleiner Blast Radius)**
  - Du lässt das `Domain`-Attribut weg, wenn der Cookie nur für den Host gelten soll, der ihn gesetzt hat (z.B. nur `finanz.bund.de`).
  - Ein verbreitetes Hardening-Pattern sind `__Host-` Cookies: sie müssen host-only sein (kein `Domain`), `Secure` sein und `Path=/` haben.

  Das ist oft der sicherere Default, hat aber echte operative/produktseitige Nachteile:

  - Du kannst keinen einzelnen Cookie nutzen, um Benutzer automatisch über Schwester-Services wie `finanz.bund.de` und `meldeamt.bund.de` zu authentifizieren.
  - Um trotzdem SSO zu ermöglichen, braucht es meist explizite Flows zwischen Services (Redirects + Einmal-Codes/Tokens). Jeder Service hat dann typischerweise seinen eigenen Session-Cookie.
  - Nutzer sehen ggf. zusätzliche Redirects und das Systemdesign wird komplexer (Token-Austausch, Session-Übergabe, Logout-Propagation, usw.).
  - Manche Legacy-Anwendungen lassen sich schwer auf tokenbasierte SSO-Flows umstellen und treiben Teams dadurch eher in Richtung Shared-Cookie-Designs.

## Praxisbeispiel: warum eine Organisation Cookies über Subdomains teilen würde
Eine Organisation betreibt eventuell eine Suite von Webanwendungen unter einer Domain, z.B.:

- `portal.bund.de` (Hauptportal)
- `finanz.bund.de` (Finanz-/Steueranwendung)
- `meldeamt.bund.de` (Bürger-/Melderegister)
- `service.bund.de` (Support/Helpdesk)

Um ein nahtloses Nutzererlebnis („einmal anmelden, dann alles nutzen“) zu ermöglichen, implementiert man ggf. einen geteilten Session-Cookie:

- Eine zentrale Meldeamt/SSO-Komponente authentifiziert den Benutzer.
- Der Benutzer erhält einen Session-Cookie, der auf die Parent-Domain gescoped ist (z.B. `Domain=.bund.de`).
- Jeder Schwester-Service akzeptiert diesen Cookie als Authentifizierungsnachweis.

Das ist eine bewusste Abwägung:

- **Vorteile**
  - Einmaliges Anmelden für viele Apps
  - Vereinfachte Integration, insbesondere bei Legacy-Systemen, die Cookies erwarten
- **Nachteile**
  - Größerer Blast Radius: Kompromittierung einer Subdomain kann andere Apps beeinträchtigen, die denselben Cookie vertrauen
  - Schwieriger, klare Sicherheitsgrenzen zwischen Teams/Services unter derselben Parent-Domain zu ziehen

## Das analoge Ziel auf `gv.at` (und warum es sicherer sein kann)
Browser definieren die „Site-Grenze“ mithilfe der **Public Suffix List (PSL)**.
Ein PSL-Eintrag ist ein Suffix, unter dem Registranten unabhängige Domains erstellen können (ähnlich wie `.de` als TLD funktioniert).

`gv.at` steht auf der PSL. Das bedeutet:

- `foo.gv.at` und `bar.gv.at` werden als **separate registrierbare Domains** behandelt (separate Sites/Tenants).
- Eine Site wie `foo.gv.at` kann keinen Cookie für das Public Suffix selbst setzen (z.B. `Domain=.gv.at`). Browser lehnen das ab, um „Supercookies“ zu verhindern.

Das ist der zentrale Unterschied zu `bund.de`:

- `foo.bund.de` und `bar.bund.de` sind Subdomains derselben registrierbaren Domain (`bund.de`).
- Ein Cookie mit `Domain=.bund.de` ist erlaubt und damit ist shared-cookie SSO technisch sehr einfach (aber mit höherem Blast Radius).

Das Nutzerziel („einmal anmelden, mehrere Services nutzen“) ist trotzdem erreichbar, aber typischerweise anders umgesetzt:

- Jeder Service (z.B. `finanz.region.gv.at` und `meldeamt.region.gv.at`) behält **host-only** Session-Cookies (kein breites `Domain=...`).
- Eine zentrale Identity-Provider-/Meldeamt-Komponente authentifiziert und nutzt **Redirects + Einmal-Codes/Tokens** (OIDC/OAuth-Style), um pro Service eine Session aufzubauen.

Der Sicherheitsgewinn ist ein kleinerer Blast Radius:

- Ein Cookie, der von `finanz.region.gv.at` gesetzt wurde, wird nicht automatisch an `meldeamt.region.gv.at` gesendet, solange du keine explizite shared-cookie Zone entwirfst.
- Das erschwert das „ein kompromittierter Service erbt automatisch die Auth-Session aller Schwester-Services“-Muster.

Im Vergleich zu `bund.de`, wo Schwester-Hosts häufig eine offensichtliche gemeinsame Parent-Domain haben (`Domain=.bund.de`), hilft die `gv.at`-artige Boundary dabei, Schwester-Services standardmäßig stärker als unabhängige Einheiten zu behandeln.

### Was `gv.at` hier verhindert (und was nicht)
Diese Demo auf der `gv.at`-Seite soll zeigen, dass **Cookie-Leakage durch zu breite Parent-Domain-Scopes keine zwingende Voraussetzung** ist.
Wenn du Session-Cookies host-only hältst (kein breites `Domain=...`), wird ein Cookie von `finanz.region.gv.at` nicht automatisch an `meldeamt.region.gv.at` gesendet.

Das bedeutet aber nicht, dass `gv.at` magisch App-Schwachstellen verhindert.
Wenn `finanz.region.gv.at` eine XSS-Schwachstelle hätte, könnte sie weiterhin ausgenutzt werden; der Unterschied ist, dass der typische „shared-cookie SSO über alle Schwester-Hosts“-Blast Radius reduziert ist.

## Wie Cookie-Leakage funktioniert
1. Service A (`finanz.bund.de`) antwortet mit einem `Set-Cookie` Header.
2. Der Browser speichert den Cookie zusammen mit seinem **Scope** (Domain + Path).
3. Bei späteren Requests hängt der Browser passende Cookies automatisch an.
4. Wenn der Cookie auf `Domain=.bund.de` gescoped ist, matcht er **alle** `*.bund.de` Hosts.
5. Ergebnis: Service B (`meldeamt.bund.de`) erhält den Cookie, obwohl er ihn nicht gesetzt hat.

## Warum das gefährlich ist
Wenn irgendeine Subdomain kompromittiert wird (z.B. durch XSS oder einen serverseitigen Bug), kann ein Angreifer u.a.:

- Nicht-`HttpOnly` Cookies per JavaScript auslesen/exfiltrieren
- Geteilte Cookies nutzen, um Benutzer über Schwester-Services hinweg zu impersonieren
- Aktionen als Opfer auf anderen Services ausführen, die dem shared Cookie vertrauen

Selbst wenn Cookies `HttpOnly` sind, kann breites Scoping weiterhin Cross-Service-Angriffe ermöglichen, weil der Browser den Cookie automatisch an Requests anhängt.

## Warum das gv.at-Setup konzeptionell sicherer ist
Browser erzwingen eine registrierbare Domain-Grenze mit Hilfe der Public Suffix List (PSL).

- Auf `bund.de` ist die registrierbare Domain typischerweise `bund.de`.
  - Ein Cookie mit `Domain=.bund.de` wird an `x.bund.de` und `y.bund.de` gesendet.

- Für österreichische Gov-Namespaces wird häufig ausgenutzt, dass manche Second-Level-Domains wie Public Suffixes wirken.
  - Das verhindert Cookies an einer zu breiten Grenze (z.B. werden Cookies auf `Domain=.gv.at` abgelehnt).
  - Praktisch fördert das host-only oder enger gescopte Cookies und reduziert Cross-Service Cookie-Sharing.

Deshalb ist ein shared-cookie Design auf `bund.de` riskanter: Ein breiter `Domain=.bund.de` Cookie überspannt leicht mehrere unabhängige Services.

## Erwartetes Ergebnis
- `finanz.bund.de` setzt `session=...; Domain=.bund.de`
- `meldeamt.bund.de` erhält diesen `session` Cookie, obwohl es ein anderer Service ist

## Cookie-Sharing über Schwester-Subdomains (warum `Domain=.bund.de`)
Wenn du einen einzigen Cookie (gleicher Name/Wert) automatisch an mehrere Schwester-Hosts senden willst, z.B.:

- `finanz.bund.de`
- `meldeamt.bund.de`

Dann muss der Cookie auf ein gemeinsames Parent-Domain-Suffix gescoped werden:

- `Domain=.bund.de`

In der Cookie-Syntax gibt es keine Möglichkeit zu sagen: „teile nur mit genau diesen 2–3 Subdomains“.
Ein Cookie-Scope ist keine Allowlist.

Ein Cookie hat genau einen effektiven Domain-Scope:

- **Host-only** (kein `Domain` Attribut)
  - Cookie wird nur an genau den Host gesendet, der ihn gesetzt hat (z.B. nur `finanz.bund.de`)
- **Domain-Scoped** (`Domain=...`)
  - Cookie wird an alle Hosts gesendet, die auf dieses Suffix matchen (z.B. `*.bund.de`)

Wenn du dich also für shared-cookie SSO auf `bund.de` entscheidest, vergrößerst du inhärent den Blast Radius: jede kompromittierte Subdomain unter `*.bund.de` kann diesen shared Session-Cookie empfangen oder ausnutzen.

### Ausnutzbarkeit, wenn eine einzelne Subdomain verwundbar ist
Wenn nur eine einzige Anwendung auf einer Subdomain verwundbar ist (z.B. eine reflektierte XSS auf `finanz.bund.de` oder ein serverseitiger Bug auf `helpdesk.bund.de`), kann ein Angreifer häufig den Browser des Opfers als Pivot gegen andere Schwester-Services nutzen.
Weil der shared Session-Cookie automatisch an mehrere `*.bund.de` Hosts gesendet wird, kann der Angreifer u.a.:

- Session-Cookies auslesen/exfiltrieren, wenn sie nicht `HttpOnly` sind
- Authentifizierte Requests gegen andere Services triggern, die dem shared Cookie vertrauen (Cross-Service-Impersonation)
- Von „eine schwache App“ zu „viele Apps“ eskalierten, weil der Browser denselben Cookie über Subdomains hinweg anhängt

#### Warum `HttpOnly` das Kernproblem nicht löst
`HttpOnly` verhindert, dass JavaScript (inkl. XSS-Payloads) Cookies über `document.cookie` auslesen kann.
Es ändert aber nicht das Sendeverhalten des Browsers.
Wenn ein Cookie auf `Domain=.bund.de` gescoped ist, hängt der Browser diesen Cookie weiterhin automatisch auch an Requests zu Schwester-Services wie `meldeamt.bund.de` an.
`HttpOnly` reduziert also „Cookie-Diebstahl via JavaScript“, reduziert aber nicht den Blast Radius, der durch breites `Domain`-Scoping entsteht.

Mit AI automatisch übersetzt von [`cookie-leakage.md`](./cookie-leakage.md).