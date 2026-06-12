# Seller / Company Billing Views

## Purpose

This UI phase adds ownership-aware billing views for company and seller scopes without inventing backend reporting APIs that do not exist yet.

The goal is to make the portfolio honest:
- show the intended company/seller billing structure
- surface the filters the backend will eventually need
- keep missing endpoints visible as explicit gaps
- avoid fake numbers, synthetic revenue, or fake list data

## Routes

- `/billing/company`
- `/billing/seller`

Both routes are frontend-only views inside the existing Angular billing module.

## What the Views Show

- scope summary
- scope filter contract
- payment history table shape
- invoice table shape
- customer table shape
- revenue summary table shape
- provider account status table shape
- webhook delivery status table shape

The pages intentionally do not load company/seller list data because the backend does not expose those scoped endpoints yet.

## Gap Policy

When the backend is missing a company/seller list endpoint, the UI must:
- show a gap note
- keep the expected table layout visible
- avoid fake rows
- avoid synthetic totals
- avoid pretending the data is live

## Future Backend Contract

When backend support lands, these pages should map to scoped endpoints for:
- payment history
- invoice history
- customer history
- revenue summaries
- provider account status
- webhook delivery rollups

The filter controls already cover the likely future query shape:
- date range
- status
- currency
- seller id
- customer id

## Status

Implemented as a UI-first ownership reporting shell for portfolio review.
