# Owner PII Key Setup — Linux Production

This runbook configures the encryption key used for `building_info.owners` PII. Run these steps separately in each environment. **Never reuse the local or staging key in production.**

> Production recommendation: store the key in AWS KMS, Azure Key Vault, Google Cloud KMS, HashiCorp Vault, or an HSM by implementing the corresponding `PiiKeyProvider`. The file procedure below is the secure fallback supported by the current application.

## 1. Choose the protected locations

Use a directory outside the application repository and web root. Replace `www-data` if PHP-FPM runs as another user.

```bash
sudo install -d -m 0700 -o root -g www-data /etc/lang-web-app/secrets
sudo install -m 0640 -o root -g www-data /dev/null /etc/lang-web-app/secrets/pii-v1.key
```

The application user/group needs read access only. It must not have permission to modify the key.

## 2. Generate the key securely

Generate 32 random bytes and encode them as Base64:

```bash
openssl rand -base64 32 | tr -d '\n' | sudo tee /etc/lang-web-app/secrets/pii-v1.key >/dev/null
sudo chown root:www-data /etc/lang-web-app/secrets/pii-v1.key
sudo chmod 0640 /etc/lang-web-app/secrets/pii-v1.key
```

Do not display the key afterward. Do not send it through chat/email, paste it into tickets, or commit it to Git.

## 3. Verify permissions and key length

```bash
sudo stat -c '%U %G %a %n' /etc/lang-web-app/secrets/pii-v1.key
sudo -u www-data test -r /etc/lang-web-app/secrets/pii-v1.key
sudo php -r '$v=trim(file_get_contents("/etc/lang-web-app/secrets/pii-v1.key")); $k=base64_decode($v,true); if ($k===false || strlen($k)!==32) {fwrite(STDERR,"Invalid key\n"); exit(1);} echo "Valid 32-byte key\n";'
```

Expected permission result: `root www-data 640`. The validation command reports only whether the key is valid; it does not print the key.

## 4. Configure Laravel

Add these values to the production environment configuration. Store only the path here, not the secret value:

```env
PII_KEY_PROVIDER=file
PII_ACTIVE_KEY_VERSION=v1
PII_KEY_V1_FILE=/etc/lang-web-app/secrets/pii-v1.key
PII_ALLOW_LEGACY_PLAINTEXT=true
PII_AUDIT_ENABLED=true
```

Then refresh Laravel configuration from the application directory:

```bash
php artisan config:clear
php artisan pii:check-configuration --production
```

Important: the current production checker intentionally rejects the `file` provider. This is a warning that the final production deployment should use KMS/Vault/HSM. Do not weaken that check merely to make it pass; obtain an approved production key provider or a documented security exception.

## 5. Deploy and migrate existing data

First take a tested database backup. Then run:

```bash
php artisan migrate --force
php artisan test --filter=Pii
php artisan pii:encrypt-existing-owners --dry-run --chunk=100
php artisan pii:encrypt-existing-owners --chunk=100
php artisan pii:verify-owner-encryption --chunk=100
```

Only after verification reports no plaintext or invalid protected values, change:

```env
PII_ALLOW_LEGACY_PLAINTEXT=false
```

Then run `php artisan config:clear` again (and rebuild the configuration cache if the deployment normally uses it).

## 6. Backup and recovery requirements

- Store an encrypted recovery copy of the production key in an approved secret manager or offline escrow.
- Restrict access to named infrastructure/security administrators and audit key access.
- Back up the key separately from the database, but ensure both can be recovered together.
- Test recovery in a controlled non-production environment.
- Never replace or overwrite `pii-v1.key` while `enc:v1:` records exist.

If the key is missing, Laravel cannot encrypt new PII or decrypt existing `enc:v1:` values. If it is permanently lost, the encrypted owner data is permanently unrecoverable. If it is exposed, treat that as a PII security incident and rotate to a new version such as `v2`; do not overwrite the old key until all `v1` data has been safely re-encrypted and verified.

## Deployment checklist

- [ ] Unique production key generated on the production host/secret platform
- [ ] Key is outside the repository and web root
- [ ] Correct owner/group and `0640` file permissions
- [ ] PHP-FPM user can read but cannot modify the key
- [ ] Key value never printed, logged, or committed
- [ ] Encrypted recovery copy exists and recovery was tested
- [ ] PII configuration check reviewed
- [ ] Database backup completed before backfill
- [ ] Backfill and verification completed
- [ ] Legacy plaintext disabled after successful verification
