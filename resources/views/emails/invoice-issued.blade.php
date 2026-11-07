Hello {{ $invoice->customer->name }},

Invoice {{ $invoice->invoice_number }} has been issued.

Issue date: {{ $invoice->issue_date->format('d-M-Y') }}
Due date: {{ $invoice->due_date->format('d-M-Y') }}

@foreach ($invoice->items as $item)
- {{ $item->description }} ({{ $item->quantity }} × {{ $item->unit_price }}): {{ $item->line_total }}
@endforeach

Invoice total: {{ $invoice->total }}

Thank you.
