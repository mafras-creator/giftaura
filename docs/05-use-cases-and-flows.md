# GiftAura — Use Cases and System Flows

Version 1.0 — Phase 9. The diagrams are written in Mermaid; open `docs/diagrams.html` (double-click) to see them as pictures and download each one as SVG for the report. Every flow below describes the system **as built and tested** (see `docs/test-plan.md`).

## 1. Actors

| Actor | Description |
|---|---|
| Guest | Visitor who is not logged in |
| Customer | Registered user with role `customer` |
| Administrator | Store staff with role `admin` (created only from the command line) |
| System | Server-side logic that runs automatically (totals, stock, history, logs) |

## 2. Use-case diagram

Mermaid has no dedicated UML use-case shape, so actors are drawn as rounded boxes and use cases as ovals (stadium shapes); arrows show which actor can perform each use case. «include» relationships are listed in section 3.

```mermaid
flowchart LR
    G(["👤 Guest"])
    C(["👤 Customer"])
    A(["👤 Administrator"])

    subgraph Shop["GiftAura online shop"]
        UC1(["Browse categories"])
        UC2(["Search, filter and sort products"])
        UC3(["View product details"])
        UC4(["Manage cart"])
        UC5(["Register"])
        UC6(["Log in / log out"])
        UC7(["Track order by reference"])
        UC8(["Check out and place order"])
        UC9(["View order history and status"])
        UC10(["Cancel pending order"])
        UC11(["Submit bank-transfer reference"])
        UC12(["Manage profile, password and addresses"])
    end

    subgraph Admin["Administration panel"]
        UA1(["View dashboard"])
        UA2(["Manage categories"])
        UA3(["Manage products and images"])
        UA4(["Adjust stock / view stock history"])
        UA5(["Manage orders and status"])
        UA6(["Record payments"])
        UA7(["Manage customers"])
        UA8(["Generate reports and export CSV"])
        UA9(["Edit store settings"])
        UA10(["View activity log"])
    end

    G --> UC1 & UC2 & UC3 & UC4 & UC5 & UC6 & UC7
    C --> UC1 & UC2 & UC3 & UC4 & UC6 & UC7 & UC8 & UC9 & UC10 & UC11 & UC12
    A --> UC6
    A --> UA1 & UA2 & UA3 & UA4 & UA5 & UA6 & UA7 & UA8 & UA9 & UA10
```

## 3. Use-case descriptions (main cases)

| ID | Use case | Actor | Pre-condition | Main flow | Alternative / exception flows | Post-condition |
|---|---|---|---|---|---|---|
| UC8 | Check out and place order | Customer | Logged in; cart not empty; no cart problems | 1. Open checkout 2. Enter contact details 3. Choose saved or new address 4. Choose payment method 5. Review totals 6. Place order | Invalid field → error shown, nothing saved. Item out of stock / price changed since the page opened → order refused, cart shown again. Administrator → redirected (admins cannot order) | Order, items, payment and history stored; stock reduced; cart emptied; reference shown |
| UC10 | Cancel pending order | Customer | Own order with status Pending | 1. Open order 2. Cancel order 3. Confirm | Order no longer pending (store already started) → refused with message | Status Cancelled; stock restored once; history row |
| UC11 | Submit bank-transfer reference | Customer | Own bank-transfer order; payment pending or failed | 1. Open order 2. Enter reference 3. Send | Invalid characters → refused | Payment "Awaiting verification" |
| UC7 | Track order | Guest / Customer | Has order reference and order e-mail | 1. Enter both 2. Submit | Mismatch → "could not find"; more than 10 lookups in 10 min → wait message | Status and timeline shown (no address) |
| UA3 | Manage products and images | Administrator | Logged in as admin | Add/edit product; upload image; set main image; deactivate | Duplicate slug/SKU, invalid price → errors. Bad image (type, size, fake image) → refused | Product saved; image re-encoded with random name; action logged |
| UA5 | Manage order status | Administrator | Order exists | Choose next allowed status, optional note, save | Not an allowed step → refused. Unpaid bank transfer → confirm only with override | Status changed; history row; cancellation restores stock |
| UA6 | Record payment | Administrator | Order exists | Choose Paid / Failed / Refunded, note, save | Not allowed (e.g. refund before paid, pay a cancelled order) → refused. Refund without note → refused | Payment status, date and admin recorded |
| UA8 | Reports and CSV | Administrator | — | Choose report and date range; optionally Export CSV | Invalid dates → defaults with a message | Figures calculated from records; CSV downloaded |

«include» relationships: *Check out* includes *Log in* and *Manage cart*; *Manage orders* includes *Record payments* (same screen); every administrator use case includes *Log in* with the admin role.

## 4. Customer purchase flow

```mermaid
flowchart TD
    S([Customer opens the shop]) --> B[Browse, search or filter]
    B --> P[Open product page]
    P --> Q{In stock?}
    Q -- No --> B
    Q -- Yes --> AC[Add to cart<br/>quantity limited to stock and setting]
    AC --> CART[Cart page<br/>live prices and stock check]
    CART --> PR{Any item with a problem?}
    PR -- Yes --> FIX[Change quantity or remove item] --> CART
    PR -- No --> LOG{Logged in?}
    LOG -- No --> L[Log in or register<br/>guest cart is merged] --> CO
    LOG -- Yes --> CO[Checkout: contact, address, payment method]
    CO --> V{Form valid?}
    V -- No --> CO
    V -- Yes --> TX[[Place order transaction<br/>see section 6]]
    TX --> OK{Succeeded?}
    OK -- No, stock or price changed --> CART
    OK -- Yes --> CONF([Confirmation with order reference])
    CONF --> PM{Payment method}
    PM -- Cash on delivery --> WAIT[Wait for delivery]
    PM -- Bank transfer --> REF[Transfer money and submit reference] --> WAIT
```

## 5. Administrator order-fulfilment flow

```mermaid
flowchart TD
    N([New order appears: Pending<br/>badge in sidebar]) --> O[Open order: items, customer, address]
    O --> M{Payment method}
    M -- Bank transfer --> R{Reference received<br/>and money in bank?}
    R -- Yes --> PAID[Record payment: Paid] --> CF
    R -- No / wrong --> FAIL[Record payment: Failed<br/>customer can resubmit] --> O
    M -- Cash on delivery --> CF[Status: Confirmed]
    CF --> PRC[Status: Processing<br/>prepare the gift]
    PRC --> RD[Status: Ready for delivery]
    RD --> DL[Status: Delivered]
    DL --> COD{Cash on delivery?}
    COD -- Yes --> CASH[Record payment: Paid<br/>note: cash collected]
    COD -- No --> END([Order complete])
    CASH --> END
    O -.->|customer request or problem| CAN[Status: Cancelled<br/>stock restored automatically]
    CF -.-> CAN
    PRC -.-> CAN
```

## 6. Order placement transaction (inside the server)

```mermaid
flowchart TD
    A([Place order clicked]) --> B[Check CSRF token, login, form fields]
    B --> C[START TRANSACTION]
    C --> D[Read cart lines]
    D --> E[Lock the products: SELECT ... FOR UPDATE]
    E --> F{All active and enough stock?}
    F -- No --> X[ROLLBACK<br/>nothing changed] --> Y([Show message, back to cart])
    F -- Yes --> G[Recalculate subtotal, delivery and total<br/>from database prices]
    G --> H{Matches what the customer saw?<br/>cart fingerprint}
    H -- No --> X
    H -- Yes --> I[Insert order with contact and address snapshot]
    I --> J[Insert order items: name, SKU, price, quantity snapshot]
    J --> K[Reduce stock + write ledger row per product]
    K --> L[Insert payment: pending]
    L --> M[Insert status history: pending]
    M --> N[Empty the cart]
    N --> O[COMMIT]
    O --> P([Confirmation page])
```

## 7. Login and access-control flow

```mermaid
flowchart TD
    A([Login form submitted]) --> B{CSRF token valid?}
    B -- No --> B1([Page expired])
    B -- Yes --> C{Too many failures?<br/>5 per e-mail / 20 per IP in 15 min}
    C -- Yes --> C1([Too many attempts — wait])
    C -- No --> D{E-mail exists, password correct,<br/>account active?}
    D -- No --> D1[Record failed attempt] --> D2([Same generic error message])
    D -- Yes --> E[Record success, new session ID,<br/>merge guest cart]
    E --> F{Role}
    F -- admin --> G([Admin dashboard])
    F -- customer --> H([My account or the page they wanted])

    R([Any admin page requested]) --> S{Logged in?}
    S -- No --> S1([Redirect to login])
    S -- Yes --> T{Role = admin<br/>read from database}
    T -- No --> T1([403 Access denied, logged])
    T -- Yes --> T2([Page shown])
```

## 8. Secure image-upload flow

```mermaid
flowchart TD
    A([Admin chooses a file and clicks Upload]) --> B{CSRF valid and admin?}
    B -- No --> Z([Refused])
    B -- Yes --> C{Size ≤ 2 MB?}
    C -- No --> E1([Too large])
    C -- Yes --> D{Extension jpg/png/webp<br/>AND real type by finfo matches?}
    D -- No --> E2([Only JPG, PNG, WebP])
    D -- Yes --> E{getimagesize: real image,<br/>100–5000 px?}
    E -- No --> E3([Not a valid image])
    E -- Yes --> F[Re-encode with GD into a new image<br/>max 1600 px, metadata removed]
    F --> G[Save as random 32-character name]
    G --> H[Insert product_images row<br/>first image becomes main]
    H --> I([Shown in admin and shop])
```

## 9. Inventory flow (every stock change)

```mermaid
flowchart LR
    S1[Opening stock] --> INV
    S2[Restock by admin] --> INV
    S3[Correction by admin<br/>note required] --> INV
    S4[Order placed] --> INV
    S5[Order cancelled] --> INV
    INV[[InventoryService::adjust<br/>inside a transaction]] --> U[UPDATE stock<br/>only if result ≥ 0]
    U --> L[Insert inventory_movements row<br/>change, stock after, reason, who]
    L --> R[Dashboard low-stock alerts<br/>and inventory reports]
```
