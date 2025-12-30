# Cookie leakage demo (bund.de)

## Goal
Show that a cookie set by one service is sent to another service on the same parent domain when the cookie is scoped too broadly (e.g. `Domain=.bund.de`).

## Key observation (the proof)
When you open `https://meldeamt.bund.de/`, the browser sends the cookie that was originally set by `https://finanz.bund.de/`.

This is not because the two apps are the same app.
It is because the cookie was set with a domain scope that includes both hosts.

## Steps
1. Open `https://finanz.bund.de/`
2. Click `Set demo cookie`
3. Open `https://meldeamt.bund.de/`
4. In the section `Cookies the browser sends to this host`, verify that the cookie from `finanz.bund.de` is present.

## What this demonstrates
If a service sets cookies loosely, e.g.

`Set-Cookie: session=...; Domain=.bund.de`

then **every subdomain** under `bund.de` will receive that cookie.

## Why the configs must differ in real life
In real deployments, you choose a cookie policy based on your intent:

- **Shared SSO cookie (high blast radius)**
  - You set `Domain=.bund.de` when you want one shared session cookie to be valid on many services under `*.bund.de`.
  - This is common in SSO setups, but it is inherently risky: any one compromised subdomain can often impact the rest.

- **Host-only cookies (low blast radius)**
  - You omit the `Domain` attribute when the cookie should only apply to the single host that set it (e.g. only `finanz.bund.de`).
  - A common hardened pattern is using `__Host-` cookies, which are required to be host-only (no `Domain`) and `Secure` with `Path=/`.

  This is often the safer default, but it has real operational/product downsides:

  - You cannot use one single cookie to authenticate the user across sibling services like `finanz.bund.de` and `meldeamt.bund.de`.
  - To still provide SSO, you typically need an explicit meldeamt/SSO flow between services (redirects + one-time codes/tokens), and each service ends up with its own session cookie.
  - Users may see additional redirects, and system design becomes more complex (token exchange, session handoff, logout propagation, etc.).
  - Some legacy applications cannot be easily adapted to token-based SSO and therefore push teams toward shared-cookie designs.

## Real-world example: why an organization would share cookies across subdomains
An organization might operate a suite of web applications under one domain, for example:

- `portal.bund.de` (main portal)
- `finanz.bund.de` (tax/finance application)
- `meldeamt.bund.de` (citizen registry)
- `service.bund.de` (support/helpdesk)

To deliver a seamless user experience (“sign in once, then access everything”), they may implement a shared session cookie:

- A central meldeamt/SSO component authenticates the user.
- The user receives a session cookie that is scoped to the parent domain (e.g. `Domain=.bund.de`).
- Every sibling service then accepts that cookie as proof of authentication.

This design can be a conscious tradeoff:

- **Pros**
  - One sign-in for many apps
  - Simplifies integration for legacy systems that all expect a cookie
- **Cons**
  - Larger blast radius: compromise of one subdomain can impact other apps that trust the same cookie
  - Harder to separate security boundaries between teams/services under the same parent domain

## The analogous goal on `gv.at` (and why it can be safer)
Browsers define the “site boundary” using the **Public Suffix List (PSL)**.
A PSL entry is a suffix under which registrants can create independent domains (similar to how `.de` works as a TLD).

`gv.at` is listed on the PSL, which means:

- `foo.gv.at` and `bar.gv.at` are treated as **separate registrable domains** (separate sites/tenants).
- A site like `foo.gv.at` cannot set a cookie for the public suffix itself (e.g. `Domain=.gv.at`). Browsers reject that to prevent “supercookies”.

This is the key difference to `bund.de`:

- `foo.bund.de` and `bar.bund.de` are *subdomains* of the same registrable domain (`bund.de`).
- Scoping a cookie to `Domain=.bund.de` is allowed, so cross-subdomain shared-cookie SSO is technically easy (but increases blast radius).

So you can still meet the same user-facing goals (“sign in once, access multiple services”), but the implementation typically looks different:

- Each service (e.g. `finanz.region.gv.at` and `meldeamt.region.gv.at`) keeps **host-only** session cookies (no broad `Domain=...`).
- A central identity provider / meldeamt service performs authentication and then uses **redirects + one-time codes/tokens** (OIDC/OAuth-style) to establish a session on each service.

The security benefit is reduced blast radius:

- A cookie set by `finanz.region.gv.at` is not automatically sent to `meldeamt.region.gv.at` unless you explicitly design a shared-cookie zone.
- This makes it harder for “one compromised service” to automatically inherit authentication state for all sibling services.

Compared to `bund.de`, where sibling hosts commonly share a single obvious parent (`Domain=.bund.de`), the `gv.at` style boundary helps treat sibling services as more independent units by default.

### What `gv.at` prevents here (and what it does not)
In this demo, the `gv.at` side is intended to show that **cookie leakage via broad parent-domain scoping is not an inherent requirement**.
If you keep session cookies host-only (no broad `Domain=...`), then a cookie set by `finanz.region.gv.at` is not automatically sent to `meldeamt.region.gv.at`.

However, this does not mean `gv.at` magically prevents application vulnerabilities.
If `finanz.region.gv.at` has an XSS bug, it can still be exploited; the difference is that the common “shared-cookie SSO across all siblings” blast radius is reduced.

## How cookie leakage works
1. Service A (`finanz.bund.de`) responds with a `Set-Cookie` header.
2. The browser stores the cookie along with its **scope** (Domain + Path).
3. On later requests, the browser automatically attaches matching cookies.
4. If the cookie is scoped to `Domain=.bund.de`, it matches **all** `*.bund.de` hosts.
5. Result: Service B (`meldeamt.bund.de`) receives the cookie even though it did not set it.

## Why this is dangerous
If any subdomain is compromised (e.g. via XSS or a server-side bug), the attacker may be able to:

- Read or exfiltrate non-`HttpOnly` cookies via JavaScript
- Use shared cookies to impersonate users across sibling services
- Perform actions as the victim on other services that trust the shared cookie

Even if cookies are `HttpOnly`, broad scoping can still enable cross-service attacks, because the browser will attach the cookie automatically to requests.

## Why the gv.at setup is safer (conceptual model)
Browsers enforce a registrable-domain boundary using the Public Suffix List (PSL).

- On `bund.de`, the registrable domain is typically `bund.de`.
  - Scoping a cookie to `Domain=.bund.de` will make it available to `x.bund.de` and `y.bund.de`.

- For Austrian government namespaces, many deployments rely on the fact that some second-level domains behave like public suffixes.
  - This prevents setting cookies at an overly broad boundary (for example, browsers may reject cookies scoped to `Domain=.gv.at`).
  - Practically, this encourages host-only or narrower-scoped cookies, reducing cross-service cookie sharing.

This is why a shared-cookie design is more dangerous on `bund.de`: a broad `Domain=.bund.de` cookie easily spans multiple independent services.

## Expected outcome
- `finanz.bund.de` sets `session=...; Domain=.bund.de`
- `meldeamt.bund.de` receives that `session` cookie even though it is a different service

## Cookie sharing across sibling subdomains (why `Domain=.bund.de`)
If you need one single cookie (same name/value) to be automatically sent to multiple sibling hosts such as:

- `finanz.bund.de`
- `meldeamt.bund.de`

then the cookie must be scoped to a common parent domain suffix:

- `Domain=.bund.de`

There is no way to say “only share this cookie with exactly these 2–3 subdomains” in cookie syntax.
A cookie’s scope is not an allowlist.

A cookie has exactly one effective domain scope:

- **Host-only** (no `Domain` attribute)
  - cookie is sent only to the exact host that set it (e.g. only `finanz.bund.de`)
- **Domain-scoped** (`Domain=...`)
  - cookie is sent to any host whose name matches that suffix (e.g. `*.bund.de`)

So if you choose cross-subdomain shared-cookie SSO on `bund.de`, you inherently increase blast radius: any compromised subdomain under `*.bund.de` may receive or be able to exploit that shared session cookie.

### Exploitability if a single subdomain is vulnerable
If just one application on one subdomain is vulnerable (for example, a reflected XSS on `finanz.bund.de` or a server-side bug on `helpdesk.bund.de`), an attacker can often use the victim’s browser as a pivot point against other sibling services.
Because the shared session cookie is automatically sent to multiple `*.bund.de` hosts, the attacker may be able to:

- Read and exfiltrate session cookies if they are not `HttpOnly`
- Trigger authenticated requests against other services that trust the shared cookie (cross-service impersonation)
- Escalate from “one weak app” to “many apps” because the browser attaches the same cookie across subdomains

#### Why `HttpOnly` does not fix the core risk
Marking a session cookie as `HttpOnly` prevents JavaScript (including injected XSS payloads) from reading it via `document.cookie`.
However, `HttpOnly` does not change the browser’s cookie sending behavior.
If a cookie is scoped to `Domain=.bund.de`, the browser will still automatically attach that cookie to requests to other sibling services such as `meldeamt.bund.de`.
So `HttpOnly` can reduce “cookie theft via JavaScript”, but it does not reduce the blast radius created by broad `Domain` scoping.
