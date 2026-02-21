# Finances

ChurchCRM includes comprehensive financial tracking for tithes, pledges, and gifts.

> **Permissions required:** Only users with Finance permissions can access financial features. See [Users & Permissions](https://github.com/ChurchCRM/CRM/wiki/Users) for details.

---

## Key Concepts

| Term | Description |
|------|-------------|
| **Pledge** | A promise of support — a planned donation of a specific total amount |
| **Payment** | An actual donation by cash, check, credit card, or bank draft |
| **Deposit Slip** | A batch of donations printed on a standard bank deposit form |
| **Reminder Statement** | Letters reminding families of their pledge and payment progress |
| **Tax Statement** | Year-end letters acknowledging donations for tax purposes |

---

## Donation Funds

Before entering pledges or payments, set up your donation funds (e.g., General Fund, Building Fund, Missions).

1. Go to **Fundraiser → Edit Fundraiser**
2. Use the on-screen editor to add or remove donation funds
3. Save your changes

---

## Pledges

A pledge is a family's commitment to give a certain amount over a period of time.

### Adding a Pledge

**From a Family View:**
1. Open the family's record
2. Click **Add a new pledge** near the bottom of the screen
3. Enter the pledge information and click **Save**

**Batch Entry (multiple families):**
1. Click **Save and Add** instead of **Save** — the form resets for the next entry
2. Select the next family and fill in their pledge
3. Repeat until all pledges are entered

---

## Payments (Donations)

Payments record actual donations received.

### Entering a Payment

**From a Family View:**
1. Open the family's record
2. Click **Add a new payment**
3. Enter the donation details and click **Save**

**Batch Entry:**
1. Click **Save and Add** to keep entering payments without returning to the family view
2. Select the next family and fill in their payment
3. Repeat until all payments are entered

---

## Deposit Slips

A deposit slip groups a batch of donations into one record for bank deposit.

### Creating and Closing a Deposit

1. **Open a new deposit:** Go to **Deposit → New Deposit Slip (checks and cash)**
2. **Enter donations:** Add individual payments to the deposit slip
3. **Print the deposit slip:**
   - Go to **Deposit → Edit Deposit Slip**
   - Click **Generate PDF** — this produces a form suitable for a standard bank deposit slip
4. **Close the deposit:** Go to **Deposit → Close deposit slip** once the deposit is packaged for the bank

### Automatic Credit Card and Bank Draft Deposits

For churches using an ECHO account, automatic payments can be configured per family:

1. Open the Family View, click **Add a new automatic payment**, and fill in the payment details
2. Create a new deposit slip: **Deposit → New Deposit Slip (credit card)** or **(bank draft)**
3. Click **Load Authorized Transactions** to pull in all authorized payments
4. Click **Run Transactions** to process them through the ECHO server
5. For any failed transactions, click **Details** to diagnose, correct, and resubmit
6. Close the deposit slip when finished

---

## Reports

ChurchCRM generates several financial reports:

- **Reminder Statements** — Remind families of pledge balances
- **Tax Statements** — Year-end donation acknowledgements for tax purposes
- **QuickBooks Deposit Tickets** — Formatted for QuickBooks

### Customizing the QuickBooks Deposit Ticket Layout

1. Go to **Admin → Edit General Settings**
2. Select the **Report Settings** tab
3. Find **sQBDTSettings** and click **Edit Settings**
4. Adjust position values for your printer and deposit ticket provider

---

## Related

- [Families](./families.md) — Pledges and payments are attached to family records
- [Search & Cart](./search-and-cart.md) — Batch operations on financial data
