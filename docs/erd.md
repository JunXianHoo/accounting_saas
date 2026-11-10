# Accounting database ERD

The central PostgreSQL database holds the `tenants` and `users` tables. Each company has its own PostgreSQL database containing the business tables. The diagram omits framework infrastructure tables.

```mermaid
---
config:
    theme: base
    themeVariables:
        darkMode: true
        primaryColor: '#0f172a'
        primaryTextColor: '#f8fafc'
        primaryBorderColor: '#3b82f6'
        textColor: '#f8fafc'
        nodeTextColor: '#f8fafc'
        lineColor: '#3b82f6'
        tertiaryColor: '#1e293b'
        edgeLabelBackground: '#1e293b'
        attributeBackgroundColorOdd: '#1e293b'
        attributeBackgroundColorEven: '#334155'
---
erDiagram
    TENANTS o|..o{ USERS : has
    CUSTOMERS ||--o{ INVOICES : receives
    INVOICES ||--o{ INVOICE_ITEMS : contains
    INVOICES ||--o{ PAYMENTS : receives

    TENANTS {
        string id PK
        json data
    }
    USERS {
        bigint id PK
        string tenant_id FK
        string name
        string email UK
    }
    CUSTOMERS {
        ulid id PK
        string name
        string email
        string phone
        text address
        string status "AC or DA"
    }
    INVOICES {
        ulid id PK
        ulid customer_id FK
        string invoice_number UK
        date issue_date
        date due_date
        string status "unpaid, partial, or paid"
        decimal subtotal
        decimal total
        timestamp emailed_at
        json email_payload "nullable"
        timestamp email_first_attempt_at "nullable"
    }
    INVOICE_NUMBER_SEQUENCES {
        date issue_date PK
        bigint last_number
    }
    INVOICE_ITEMS {
        bigint id PK
        ulid invoice_id FK
        text description
        decimal quantity
        decimal unit_price
        decimal line_total
    }
    PAYMENTS {
        bigint id PK
        ulid invoice_id FK
        decimal amount
        date paid_at
        string reference "optional"
        uuid submission_id "nullable"
    }
```

Each invoice references a customer in its company's database. Items and payments reference invoices in the same database. Invoice and customer IDs are ULIDs, so links from another company do not resolve even when two companies have the same invoice number.

The tenant database enforces invoice-number uniqueness and keeps one sequence per issue date. Invoice edits preserve the number, and deletion leaves the counter intact.

The authenticated user's company selects the tenant connection before Laravel binds route models. The overdue command iterates companies, and the mail job selects its tenant database using the company ID in the job payload.
