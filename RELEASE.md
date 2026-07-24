# DnepritNewsletter release guide

## Current prerelease

```text
Version: 0.1.0-beta1
Package: dnepritnewsletter-0.1.0-beta1.transport.zip
Target: MODX Revolution 2.8.1 / PHP 7.4+
```

The beta label is intentional until SMTP and browser behavior are checked on a real staging site.

## Automated verification

Release branches matching `release/*` run both the normal syntax checks and the clean-install package job. The release job installs a fresh MODX Revolution 2.8.1 instance, builds the transport ZIP, verifies its SHA-256 checksum, installs the package and validates the namespace, menu, snippets, settings, database tables and copied component files.

## Downloading the package from GitHub Actions

1. Open the repository on GitHub.
2. Open **Actions**.
3. Select **Build release package**.
4. Open the latest successful run.
5. Download the artifact named `dnepritnewsletter-0.1.0-beta1`.
6. Extract the downloaded artifact ZIP. It contains:
   - `dnepritnewsletter-0.1.0-beta1.transport.zip`;
   - `dnepritnewsletter-0.1.0-beta1.transport.zip.sha256`;
   - `release.json`.

The transport ZIP itself must not be extracted before installing it in MODX.

## Installing through MODX Packages

1. Copy `dnepritnewsletter-0.1.0-beta1.transport.zip` to:

```text
/core/packages/
```

2. Open the MODX manager.
3. Go to **Extras → Installer** / **Package Management**.
4. Click **Search Locally for Packages**.
5. Locate `DnepritNewsletter 0.1.0-beta1`.
6. Click **Install**.
7. Clear the MODX cache and reload the manager.

The installer must create:

- namespace `dnepritnewsletter`;
- the DnepritNewsletter manager menu;
- snippets `DnepritNewsletterSubscribe` and `DnepritNewsletterUnsubscribe`;
- component system settings;
- subscriber, campaign, queue and log tables;
- files under `core/components/dnepritnewsletter/` and `assets/components/dnepritnewsletter/`.

## Required configuration after installation

### SMTP

Configure the standard MODX mail settings:

```text
mail_use_smtp
mail_smtp_hosts
mail_smtp_port
mail_smtp_user
mail_smtp_pass
mail_smtp_prefix
```

### Unsubscribe page

Create a normal MODX resource with an uncached snippet call:

```modx
[[!DnepritNewsletterUnsubscribe]]
```

Set its resource ID in:

```text
dnepritnewsletter.unsubscribe_resource_id
```

### Subscription form

Place the uncached public form where needed:

```modx
[[!DnepritNewsletterSubscribe]]
```

### Cron

Run the sender every minute:

```cron
* * * * * /usr/bin/php /path/to/site/core/components/dnepritnewsletter/cron/send.php >> /path/to/site/core/cache/logs/dnepritnewsletter-cron.log 2>&1
```

## Manual staging checklist

1. Add a test subscriber through the manager.
2. Subscribe a second address through the public AJAX form.
3. Confirm that duplicate public submissions do not create duplicate rows.
4. Create a campaign containing all four placeholders.
5. Prepare the queue for immediate delivery.
6. Run the Cron command manually once.
7. Confirm HTML content, plain-text alternative, From and Reply-To headers.
8. Confirm sent/failed counters and log events in CMP.
9. Open the unsubscribe URL and verify that GET only shows confirmation.
10. Submit the confirmation form and verify the subscriber becomes `unsubscribed`.
11. Confirm a later campaign skips that subscriber.
12. Test a forced SMTP failure and the manual retry action.

## Local build

Run against an installed MODX 2.8.1 instance:

```bash
MODX_BASE_PATH=/path/to/modx php _build/build.transport.php
```

Generated files appear in `_dist/`.

Install the generated package into the same clean test installation and run the automated smoke test:

```bash
MODX_BASE_PATH=/path/to/modx php _build/install.smoke.php \
  _dist/dnepritnewsletter-0.1.0-beta1.transport.zip
```

## Promoting to stable

After the staging checklist passes:

1. change the release identifier from `beta1` to `pl` in `_build/config.php`;
2. add the stable entry to `CHANGELOG.md`;
3. rebuild and rerun the clean-install workflow;
4. create tag `v0.1.0-pl`;
5. attach the generated transport ZIP and checksum to the GitHub release.
