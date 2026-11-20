# Rent Book 🇰🇪

> Simple, reliable rental property management for small landlords in Kenya (1–30 units). Works offline on mobile phones and laptops with zero dependencies.

![Rent Book](https://img.shields.io/badge/Currency-KES-16382c)
![Stack](https://img.shields.io/badge/Stack-Vanilla_HTML_CSS_JS-2d8a5e)
![License](https://img.shields.io/badge/License-MIT-c26d1a)

---

## 📖 Overview

**Rent Book** is a phone-first, self-contained single-file web application tailored for Kenyan landlords and caretakers who manage rental units, collect rent through Safaricom M-Pesa, and need to know at a glance who has paid, who owes, and by how much.

---

## ⚡ Core Features

- **Unit & Tenant Management**: Track unit name (e.g. *House A1, Bedsitter B*), tenant contact, monthly rent, move-in date, and vacancy status.
- **Automatic Rent & Arrears Accrual**: Monthly rent charges calculate automatically from the move-in month, with unpaid balances carried forward month-to-month.
- **M-Pesa Payments & Surplus Credit**: Record payments with M-Pesa transaction codes and part-payment support. Overpayments automatically become credit toward next month's rent.
- **Monthly Utility Bills**: Log water, electricity, garbage, repairs, and other charges per unit per month.
- **Ledger Cashboard**: High-impact financial snapshot showing total outstanding arrears, collections this month, occupancy percentage, and a collection target progress bar.
- **Top Debtors Ranking**: Ranked list of tenants in arrears with 1-tap WhatsApp reminders.
- **Copy-Ready WhatsApp & SMS Reminders**: Instant personalized reminder text with tenant name, exact arrears amount, month, and M-Pesa Paybill/Till instructions.
- **Printable Receipts & Statements**: Clean, professional print views for official rent receipts and historical tenant ledger statements.
- **Month Selector**: Step backward or forward through any calendar month to audit historical ledger balances.
- **Data Privacy & Backup**: 100% client-side `localStorage`. Export full JSON backups anytime or export monthly summaries and payment ledgers to Excel (CSV).
- **Dark & Light Mode**: Accessible, tactile ledger styling inspired by East African financial record books.

---

## 🚀 How to Run

1. Clone this repository:
   ```bash
   git clone https://github.com/kim425716-dotcom/rent-book.git
   cd rent-book
   ```
2. Double-click `index.html` (or `rentbook.html`) in your file explorer to open it in any browser on your phone, tablet, or PC.
3. No server, node packages, or internet connection required.

---

## 📄 License

MIT License. Built for Kenyan property owners and property managers.
