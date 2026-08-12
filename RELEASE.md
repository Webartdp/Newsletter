# DnepritNewsletter release guide

## Current prerelease

```text
Version: 0.1.0-beta5
Package: dnepritnewsletter-0.1.0-beta5.transport.zip
Target: MODX Revolution 2.8.1 / PHP 7.4+
```

The beta label is retained while the component continues to receive real-site testing before a stable `0.1.0-pl` release.

## Automated verification

Release branches matching `release/*` run both the normal syntax checks and the clean-install package job. The release job installs a fresh MODX Revolution 2.8.1 instance, builds the transport ZIP, verifies its SHA-256 checksum, installs the package and validates the namespace, menu, snippets, settings, database tables and copied component files. A separate compatibility test verifies the exact root manager controller class name expected by MODX 2.8.1.

## Downloading the package from GitHub Actions

1. Open the repository on GitHub.
2. Open **Actions**.
3. Select **Build release package**.
4. Open the latest successful run.
5. Download the artifact named `dnepritnewsletter-0.1.0-beta5`.
6. Extract the downloaded artifact ZIP. It contains:
   - `dnepritnewsletter-0.1.0-beta5.transport.zip`;
   - `dnepritnewsletter-0.1.0-beta5.transport.zip.sha256`;
   - `release.json`.

The transport ZIP itself must not be extracted before installing it in MODX.

## Installing through MODX Packages

1. Copy `dnepritnewsletter-0.1.0-beta5.transport.zip` to:

```text
/core/packages/
```

2. Open the MODX manager.
3. Go to **Extras → Installer** / **Package Management**.
4. Click **Search Locally for Packages**.
5. Locate `DnepritNewsletter 0.1.0-beta5`.
6. Install it over the previous beta version. Do not uninstall the previous package first if existing newsletter data must be preserved.
7. Clear the MODX cache and reload the manager with a hard refresh.

The installer must create or update:

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

The component reads the standard MODX mail transport configuration. SMTP credentials are not stored separately by DnepritNewsletter.

### Unsubscribe page

Create a normal MODX resource with an uncached snippet call:

```modx
[[!DnepritNewsletterUnsubscribe]]
```

Set its resource ID in the DnepritNewsletter settings tab or in:

```text
dnepritnewsletter.unsubscribe_resource_id
```

### Subscription form

Place the uncached public form where needed:

```modx
[[!DnepritNewsletterSubscribe]]
```

## Sending campaigns

The normal manager workflow is browser-driven:

1. create the campaign;
2. prepare the queue;
3. start sending immediately or press **Start mailing**;
4. keep the manager tab open while browser batches are being sent;
5. if the tab is closed, reopen the component and resume the remaining queue.

The queue persists on the server, so closing the browser does not delete unsent messages.

### Optional Cron worker

Cron remains available for unattended queue processing, but it is not required for ordinary button-driven sending from the manager:

```cron
* * * * * /usr/bin/php /path/to/site/core/components/dnepritnewsletter/cron/send.php >> /path/to/site/core/cache/logs/dnepritnewsletter-cron.log 2>&1
```

## Manual staging checklist

1. Add a test subscriber through the manager.
2. Subscribe a second address through the public AJAX form.
3. Confirm duplicate public submissions do not create duplicate rows.
4. Create a campaign containing the supported placeholders.
5. Prepare the queue for immediate delivery.
6. Start browser-driven sending and confirm progress updates.
7. Confirm HTML content, plain-text alternative, From and Reply-To headers.
8. Confirm sent/failed counters and log events in CMP.
9. Open the unsubscribe URL and verify GET only shows confirmation.
10. Submit the confirmation form and verify the subscriber becomes `unsubscribed`.
11. Confirm a later campaign skips that subscriber.
12. Test a forced SMTP failure and manual retry.
13. Test queue deletion for selected rows and confirm campaign counters are recalculated.
14. Confirm the settings tab scrolls and Save/Reload controls remain accessible.

## Local build

Run against an installed MODX 2.8.1 instance:

```bash
MODX_BASE_PATH=/path/to/modx php _build/build.transport.php
```

Generated files appear in `_dist/`.

Install the generated package into the same clean test installation and run the automated smoke test:

```bash
MODX_BASE_PATH=/path/to/modx php _build/install.smoke.php \
  _dist/dnepritnewsletter-0.1.0-beta5.transport.zip
```

## Promoting to stable

After the beta5 staging checklist passes:

1. change the release identifier from `beta5` to `pl` in `_build/config.php`;
2. add the stable entry to `CHANGELOG.md`;
3. update workflow artifact names from `beta5` to `pl`;
4. rebuild and rerun the clean-install workflow;
5. create tag `v0.1.0-pl`;
6. attach the generated transport ZIP and checksum to the GitHub release.
