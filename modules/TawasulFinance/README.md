# TawasulFinance

Fee scheduling, invoicing, expense requests and budgets for TawasulOS.

## Requirements

TawasulOS v31, PHP 8.0+, MySQL 8.

## Installation

1. Copy the `TawasulFinance` directory into `modules/`.
2. Go to **System Admin > Manage Modules** and press **Install** next to
   TawasulFinance.

Installation creates 15 `tawasulFinance*` tables and seeds one fee category
(`Other`). The manifest declares these tables, so a fresh install and an
upgraded install end up with identical schemas.

## Setup

1. **Manage Fee Categories** - create the categories your fees fall into. The
   seeded `Other` category is a fallback for anything unmatched.
2. **Manage Invoicees** - create the people or organisations that will be
   billed (usually a family or a company).
3. **Manage Fees** - define fees, optionally scoped by year group or context,
   and attach them to fee categories.
4. **Manage Billing Schedule** - define when fees are raised, and on which
   schedule each fee is billed.
5. **Manage Invoices** - raise invoices from the billing schedule, issue them
   and take payment.

### Expenses and budgets

1. **Manage Budgets** and **Manage Budget Cycles** - set the budget envelopes
   and the periods they run over.
2. **Manage Expense Approvers** - nominate who approves expenses per budget.
3. Staff raise expenses under **My Expense Requests**; approvers action them
   under **Manage Expenses**.
4. **Petty Cash** tracks small-value cash on hand.

## Permissions

Actions use grouped-action suffixes to scope access:

| Action suffix | Grants |
|---|---|
| `Manage Expenses_all` | every expense |
| `Manage Expenses_myBudgets` | only expenses in the approver's own budgets |
| `View Invoices_myChildren` | a parent sees their own children's invoices |
| `View Invoices_mine` | an invoicee sees their own invoices |

Note: `View Invoices_mine` currently has no role granted it. Assign it in
**User Admin > Manage Permissions** if invoicees should self-serve.

## Licence

GPL-3.0.
