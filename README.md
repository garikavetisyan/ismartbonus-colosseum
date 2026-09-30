# iSmartBonus

**Turning everyday purchases into digital value.**

iSmartBonus is a universal loyalty ecosystem that connects customers, physical merchants, online marketplaces, affiliate partners, and Solana-based digital asset infrastructure in one platform.

Traditional loyalty points are usually locked to one merchant, may expire, and often cannot be converted into real value.

iSmartBonus is building a different model: one loyalty ecosystem where rewards from everyday purchases can generate digital value for the customer.

---

## The Problem

Traditional loyalty programs have three major limitations:

1. Rewards are usually locked to a specific merchant.
2. Rewards often expire.
3. Rewards usually cannot be converted into real value.

As a result, customers accumulate fragmented balances across different stores and services with limited flexibility.

---

## Our Solution

iSmartBonus creates a shared loyalty ecosystem for both offline and online commerce.

Customers can earn bonuses from participating physical merchants as well as supported online marketplaces, travel services, and affiliate partners.

Each eligible bonus corresponds to ISB, a Solana SPL token used as the digital asset layer of the ecosystem.

iSmartBonus bonuses:

- are not limited to a single merchant;
- do not expire;
- are connected to a digital asset;
- can become eligible for withdrawal according to platform rules;
- can be earned without the customer purchasing cryptocurrency.

The goal is simple:

**Turn everyday purchases into digital value.**

---

## How It Works

iSmartBonus supports two main purchase flows.

### Offline Purchases

For physical merchants, iSmartBonus uses a signed QR-based identification flow.

```text
Customer
    ↓
Physical Merchant
    ↓
Signed iSmartBonus QR
    ↓
User Identification
    ↓
Purchase Verification
    ↓
Bonus Calculation
    ↓
ISB Digital Asset Reserve
    ↓
User Bonus Balance
    ↓
Lock / Unlock / Burn Rules
    ↓
Eligible Withdrawal
```

The QR payload is cryptographically signed and verified by the platform before it can be accepted.

QR identification is used only for physical merchant transactions.

### Online Purchases

Online marketplaces and affiliate partners do not require QR identification.

The online flow uses tracked links and partner or affiliate reporting to attribute eligible purchases to iSmartBonus users.

```text
Customer
    ↓
iSmartBonus
    ↓
Tracked Partner / Affiliate Link
    ↓
Online Merchant
    ↓
Purchase / Commission Confirmation
    ↓
User Attribution
    ↓
Bonus Calculation
    ↓
ISB Digital Asset Reserve
    ↓
User Bonus Balance
```

This architecture allows the same bonus ecosystem to support both physical and online commerce while using different purchase-verification mechanisms.

---

## Business Model

iSmartBonus supports multiple merchant models.

### Performance-Based Model

The merchant defines a loyalty commission for eligible purchases.

iSmartBonus receives value only when a real purchase occurs and distributes the applicable portion according to the platform's bonus and revenue rules.

### SaaS Model

Businesses can pay a fixed platform subscription while their loyalty budget is directed toward customer bonuses.

This gives merchants flexibility in how they participate in the ecosystem while keeping the customer experience unified.

---

## Solana

Solana provides the blockchain infrastructure for the digital asset layer of iSmartBonus.

The ecosystem uses the **ISB SPL token**.

- **Network:** Solana
- **Token:** ISB
- **Mint Address:** `GDWtjpjHjtfJ3vD4tEePvHL5BxX6AMS3uQNBGM8jx61W`
- **Total Supply:** 100,000,000 ISB
- **Decimals:** 6
- **Bonus Mapping:** 1 bonus unit = 1 ISB

Users do not need to buy ISB or invest their own money in order to participate in the loyalty ecosystem.

The blockchain layer operates behind the consumer experience while providing the digital asset infrastructure for the bonus model.

---

## Scarcity Mechanics

iSmartBonus uses bonus and token rules designed to connect platform activity with digital asset scarcity.

These include:

- bonus locking periods;
- early-unlock burn mechanics;
- additional burn rules when applicable;
- withdrawal limits linked to verified customer spending.

The objective is to create a loyalty asset whose economics are connected to real commercial activity rather than simply issuing unlimited traditional reward points.

---

## Product Architecture

The current iSmartBonus platform combines:

- WordPress
- PHP
- JavaScript
- REST APIs
- Physical merchant integrations
- Affiliate and online merchant integrations
- Signed QR authentication for offline purchases
- Solana
- SPL Token infrastructure

At a high level:

```text
                    ┌─────────────────────┐
                    │     iSmartBonus     │
                    │      Platform       │
                    └──────────┬──────────┘
                               │
              ┌────────────────┴────────────────┐
              │                                 │
      Offline Commerce                  Online Commerce
              │                                 │
      Signed Partner QR                  Tracked Links
              │                                 │
      Physical Merchant              Marketplace / Affiliate
              │                                 │
              └───────────────┬─────────────────┘
                              │
                      Purchase Verification
                              │
                        Bonus Engine
                              │
                         ISB / Solana
                              │
                      User Bonus Balance
                              │
                    Unlock / Burn / Withdraw
```

The WordPress application handles the user and merchant experience while custom modules implement iSmartBonus-specific business logic and integrations.

---

## Repository Structure

```text
ismartbonus-colosseum/
├── README.md
├── docs/
│   └── hackathon-development.md
│
└── src/
    └── offline-partners/
        └── signed-partner-qr.php
```

Additional modules will be added as development progresses.

Planned repository organization:

```text
src/
├── offline-partners/
├── online-partners/
├── purchases/
├── bonuses/
├── withdrawals/
├── integrations/
└── solana/
```

---

## Current Source Module

### Signed Offline Partner QR

`src/offline-partners/signed-partner-qr.php`

This module contains the signed QR logic used for purchases at physical merchant locations.

The QR flow includes:

- user identification;
- unique token generation;
- timestamp validation;
- HMAC-SHA256 signatures;
- signature verification;
- expiration validation;
- protection against modified QR payloads.

This module is specifically designed for offline merchants.

Online marketplaces and affiliate partners use separate attribution mechanisms.

---

## Hackathon Development

iSmartBonus existed before the Colosseum hackathon as a working WordPress-based loyalty platform.

This repository documents and contains custom development associated with the project's work during the hackathon.

Pre-existing functionality and hackathon development are disclosed separately so that work completed during the hackathon can be clearly identified.

See:

`docs/hackathon-development.md`

As development progresses, additional iSmartBonus modules will be extracted, documented, improved, and added to this repository.

---

## Live Product

iSmartBonus is a working product with real users and merchant integrations.

**Website:** https://ismartbonus.com

---

## Vision

Our goal is to build a global loyalty infrastructure where rewards from everyday purchases are no longer isolated points inside individual stores.

One ecosystem.  
Physical and online merchants.  
Real purchases.  
Digital value.

**Your loyalty should become your wealth.**
