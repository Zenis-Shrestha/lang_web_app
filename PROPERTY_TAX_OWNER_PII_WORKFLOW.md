# Property Tax Owner PII Workflow

## Scope

The Property Tax Collection ISS now protects these columns in
`taxpayment_info.tax_payments`:

- `owner_name`
- `owner_contact`

They reuse the existing external AES-256-GCM PII key and versioned
`enc:v1:` envelope. No additional encryption key is generated.

## Data flow

```text
CSV/API import
    -> TaxPayment model mutators
    -> AES-256-GCM
    -> taxpayment_info.tax_payments (encrypted)

tax_payment_status / GeoServer map layer
    -> tax code, BIN, ward and payment status only
    -> no owner PII columns

Property Tax list
    -> locked: ********
    -> permission + password re-entry
    -> plaintext for five minutes

Dedicated owner PII export
    -> uploaded tax_code CSV
    -> client and server validation
    -> permission + password re-entry
    -> audited, non-cacheable CSV download
```

## Permissions

- `View Property Tax Owner PII`
- `Unlock Property Tax Owner PII`
- `Export Property Tax Owner PII`

The ordinary Property Tax and unmatched exports no longer include owner
fields. The dedicated export is the only supported bulk PII extraction path.

## Production rollout

Back up both the database and external PII key before beginning.

```bash
php artisan migrate
php artisan db:seed --class=PropertyTaxPiiPermissionsSeeder
php artisan permission:cache-reset
php artisan config:clear
php artisan pii:check-configuration
php artisan pii:encrypt-existing-property-tax --dry-run
php artisan pii:encrypt-existing-property-tax
php artisan pii:verify-property-tax-encryption
php artisan test tests/Unit/PropertyTaxPiiTest.php
```

Assign the three permissions only to approved Property Tax roles. Test CSV
import, API import, locked list/network response, temporary reveal, timeout,
wrong password, normal exports, dedicated export, map info, and map exports
before production deployment.
