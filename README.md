# fqdn_issue demo

This repository contains a small demo that focuses on **cookie scope / cookie leakage across services**.

## Docs
- See: [`cookie-leakage.md`](./cookie-leakage.md)

## Domain parts (diagram)

### `finanz.bund.de`

```text
finanz.bund.de
│     │    └─ TLD
│     └────── registrable domain (eTLD+1): bund.de
└──────────── host/service label
```

### `finanz.region.gv.at`

```text
finanz.region.gv.at
│      │      │  └─ TLD
│      │      └──── PSL suffix / boundary: gv.at
│      └─────────── registrable domain (eTLD+1): region.gv.at
└────────────────── host/service label
```

## Why `bund.de` behaves differently than `gv.at`

Browsers determine the **registrable domain boundary** (often called **eTLD+1**) using the **Public Suffix List (PSL)**.
That boundary is what browsers use for important security decisions (cookies, same-site, etc.).

### `bund.de` (regular registrable domain)

For typical `.de` domains:

- The TLD is `.de`.
- A name like `bund.de` is a normal registrable domain.
- Hosts like `finanz.bund.de` and `meldeamt.bund.de` are just subdomains of the *same* registrable domain (`bund.de`).

This makes it possible (and common in shared-SSO designs) to scope cookies broadly:

- `Domain=.bund.de`

That single choice creates a **shared cookie zone** across `*.bund.de`, which increases blast radius:
any sibling service under `*.bund.de` will receive those cookies automatically.

### `gv.at` (PSL boundary)

`gv.at` is listed on the **Public Suffix List**.
That means browsers treat `gv.at` as a boundary similar to a TLD for cookie purposes:

- You cannot safely create a "super-cookie" at `Domain=.gv.at`.
- A host like `finanz.region.gv.at` is under the registrable domain `region.gv.at` (not `gv.at`).

In practice, this encourages **more granular session boundaries**:

- each service keeps a host-only session cookie (for example `__Host-...` cookies)
- cross-service SSO is still possible, but usually via explicit flows (redirects + one-time codes/tokens) rather than one shared parent-domain cookie

The key security effect is a reduced default blast radius:
compromise of one service is less likely to automatically expose a reusable shared session for sibling services.
