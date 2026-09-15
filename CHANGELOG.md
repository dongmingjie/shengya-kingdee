# Changelog

All notable changes to this package will be documented in this file.

## [Unreleased]

## [0.1.5] - 2026-09-15

- Stopped writing the finance-subhead `FNoTaxAmountFor` field into `BillHead`; Kingdee now derives it from the source relation as in the legacy flow.

## [0.1.4] - 2026-09-15

- Added support for receivable View responses using `AP_PAYABLEENTRY.Id` and `AP_PAYABLEENTRY.Seq`.

## [0.1.3] - 2026-09-15

- Fixed deferred-income source links by persisting and requiring the source invoice entry ID.
- Changed deferred allocation limits and line amounts to the finance-approved amount-without-tax basis.
- Added the source invoice's full amount-without-tax total to the deferred-income header.

## [0.1.2] - 2026-09-15

- Added package-owned invoice-line, deferred-income application and deferred allocation tables.
- Added line-level deferred-income quota locking and invoice-line synchronization services.
- Added the Kingdee deferred-income payload and standalone create operation.

## [0.1.1] - 2026-09-14

- Added employee-number and salesperson-position mappings for invoice applications.
- Added full payment-plan and source-relation fields for invoice-to-receipt conversion.
- Added package-owned invoice-application and receipt models with configurable tables.
- Added publishable document-application migrations and optimized business-data relationships.
- Added regression tests for invoice payloads and source relations.

## [0.1.0] - 2026-08-26

- Added the framework-independent Kingdee K3Cloud WebAPI client.
- Added Laravel service registration and database call recording.
- Added migrations and idempotent synchronization for 14 base-data types.
- Added sensitive-data masking, call tracing and typed exception handling.
