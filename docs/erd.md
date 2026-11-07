# Accounting database ERD

The application uses one PostgreSQL database. Each business record carries a tenant ID. Framework infrastructure tables are omitted from the diagram.

```mermaid
erDiagram
    TENANTS o|--o{ USERS : has
    TENANTS ||--o{ CUSTOMERS : owns
    TENANTS ||--o{ INVOICES : owns
    TENANTS ||--o{ INVOICE_NUMBER_SEQUENCES : owns
    TENANTS ||--o{ INVOICE_ITEMS : owns
    TENANTS ||--o{ PAYMENTS : owns
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
        string email
    }
    CUSTOMERS {
        bigint id PK
        string tenant_id FK
        string name
        string email
        string phone
        text address
        string status "AC or DA"
    }
    INVOICES {
        bigint id PK
        string tenant_id FK
        bigint customer_id FK
        string invoice_number
        date issue_date
        date due_date
        string status "unpaid, partial, or paid"
        decimal subtotal
        decimal total
        timestamp emailed_at
    }
    INVOICE_NUMBER_SEQUENCES {
        string tenant_id PK, FK
        date issue_date PK
        bigint last_number
    }
    INVOICE_ITEMS {
        bigint id PK
        string tenant_id FK
        bigint invoice_id FK
        text description
        decimal quantity
        decimal unit_price
        decimal line_total
    }
    PAYMENTS {
        bigint id PK
        string tenant_id FK
        bigint invoice_id FK
        decimal amount
        date paid_at
        string reference "optional"
    }
```

An invoice references a customer through `(tenant_id, customer_id)`. An item or payment references its invoice through `(tenant_id, invoice_id)`. These composite foreign keys reject references across companies, even if an application query omits its tenant filter.

Invoice numbers are unique within a company through `(tenant_id, invoice_number)`. The sequence table uses `(tenant_id, issue_date)` as its primary key so each company has a separate counter per issue date. Invoice edits preserve the number, and deletion leaves the counter intact.

The authenticated user's company initializes tenant context before route binding. Eloquent scopes restrict business queries to that company. The overdue command iterates companies, and the mail job initializes the company identified in its payload before loading the invoice.
