export interface InvoiceSummary {
    id: number;
    invoice_number: string;
    customer: { id: number; name: string } | null;
    issue_date: string;
    due_date: string;
    status: 'unpaid' | 'partial' | 'paid';
    overdue: boolean;
    total: string;
    total_paid: string;
    outstanding_balance: string;
}

export interface PaginatedInvoices {
    data: InvoiceSummary[];
    current_page: number;
    last_page: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}

export interface InvoiceReportFilters {
    from: string;
    to: string;
    date_range: boolean;
    filter_by_invoice_number: boolean;
    invoice_number: string | null;
    status: InvoiceSummary['status'] | null;
    balance: 'outstanding' | 'overdue' | null;
    customer_id: number | string | null;
}
