# iSmartBonus

**Turning everyday purchases into digital value.**

iSmartBonus is a universal loyalty ecosystem that connects customers, merchants, online services, and blockchain infrastructure in one platform.

Instead of receiving traditional loyalty points that are locked to one merchant, expire, or cannot be withdrawn, users receive bonuses backed by the ISB digital asset on Solana.

## The Problem

Traditional loyalty programs have three major limitations:

1. Rewards are usually locked to a specific merchant.
2. Rewards often expire.
3. Rewards usually cannot be converted into real value.

This creates fragmented loyalty systems where customers accumulate points across many different businesses but have limited ways to use them.

## Our Solution

iSmartBonus creates a shared loyalty ecosystem.

Customers can earn bonuses from participating offline merchants and online services within the same platform.

Each eligible bonus corresponds to ISB, a Solana SPL token used as the digital asset layer of the ecosystem.

Bonuses:

- are not limited to a single merchant;
- do not expire;
- are backed by a digital asset;
- can become eligible for withdrawal according to platform rules.

The goal is simple:

**Make everyday purchases generate long-term digital value for the customer.**

## How It Works

The basic flow is:

```text
Customer Purchase
        ↓
Merchant / Affiliate Partner
        ↓
iSmartBonus Purchase Verification
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

Merchants only fund rewards when real purchases occur under the performance-based model.

iSmartBonus can also support a SaaS model where businesses pay a fixed platform fee while their loyalty budget is directed toward customer bonuses.

## Solana

Solana provides the blockchain infrastructure for the digital asset layer of iSmartBonus.

The ecosystem uses the **ISB SPL token**.

- Network: Solana
- Token: ISB
- Mint Address: `GDWtjpjHjtfJ3vD4tEePvHL5BxX6AMS3uQNBGM8jx61W`
- Total supply: 100,000,000 ISB
- Decimals: 6
- 1 bonus unit = 1 ISB

The blockchain layer provides a transparent digital asset infrastructure while the consumer experience remains simple.

Users do not need to purchase ISB in order to participate in the loyalty ecosystem.

## Scarcity Mechanics

iSmartBonus uses rules designed to control the circulating supply of bonus-related digital assets.

These include:

- bonus locking periods;
- early-unlock burn mechanics;
- additional burn rules when applicable;
- withdrawal limits linked to verified customer spending.

These mechanisms connect platform usage with the digital asset economy.

## Product Architecture

iSmartBonus currently combines:

- WordPress
- PHP
- JavaScript
- REST APIs
- Merchant integrations
- Affiliate integrations
- Solana
- SPL Token infrastructure

The existing WordPress application handles the consumer and merchant experience, while custom modules connect purchases, bonus calculations, partner operations, and blockchain-related processes.

## Hackathon Development

iSmartBonus existed before the Colosseum hackathon as a working WordPress-based loyalty platform.

This repository is used to document and contain custom development associated with the project's work during the hackathon.

Pre-existing functionality and hackathon development are disclosed separately so that the development completed during the hackathon can be clearly identified.

See:

`docs/hackathon-development.md`

## Repository Structure

```text
ismartbonus-colosseum/
├── README.md
├── docs/
│   └── hackathon-development.md
└── [additional source modules will be added as development progresses]
```

## Live Product

iSmartBonus is a working product with real users and merchant integrations.

Website: https://ismartbonus.com

## Vision

Our goal is to build a global loyalty infrastructure where rewards from everyday purchases are no longer isolated points inside individual stores.

One ecosystem.  
Multiple merchants.  
Real purchases.  
Digital value.

**Your loyalty should become your wealth.**
