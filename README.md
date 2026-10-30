# goingsolo.ie

Solo holiday deals in Ireland: hotels, B&Bs and tours with solo rates or no single supplement, organised by county, with guides, memberships and ads. Built on Drupal 11.

## What's in here

| Path | What it does |
|---|---|
| `web/profiles/custom/goingsolo` | Install profile. Installs everything below and sets up roles, footer and ad slots. |
| `web/modules/custom/goingsolo_core` | Content model: solo deals, counties, stay types, guides, pages. Deal search, county pages, homepage, members-only deals. |
| `web/modules/custom/goingsolo_maps` | County map of Ireland shaded by number of deals, and maps of deal locations (Leaflet). |
| `web/modules/custom/goingsolo_ads` | Google AdSense slots and `/ads.txt`. Ads are hidden for members. Settings at **Configuration → Web services → Ads**. |
| `web/modules/custom/goingsolo_content` | Starter content: 27 counties, 7 checked solo deals, 8 deals to confirm by phone (unpublished), 38 guides and 6 pages. |
| `web/modules/custom/goingsolo_commerce` | Drupal Commerce: Solo Club monthly and annual memberships, Provider Pro subscription and paid featured listings. |
| `web/modules/custom/goingsolo_seo` | Pathauto URL patterns, Metatag defaults and the XML sitemap. |
| `web/themes/custom/goingsolo_theme` | Theme, built on Olivero. |
| `deploy/` | Settings file and the script that runs on Hostinger. |
| `.github/workflows/build-deploy.yml` | Builds with Composer, installs and tests the site, then deploys to Hostinger. |

Contrib modules: Commerce, Commerce Recurring, Commerce Stripe, Pathauto, Token, Metatag, Simple XML Sitemap, Redirect.

## Money

- **Solo Club** (€4.99 a month or €39 a year): members-only deals, no ads. Members get the `solo_club_member` role while their subscription is active.
- **Provider Pro** (€29 a month): providers get the `provider` role and can add their own deals, which wait for review.
- **Featured listing** (€49 for 30 days): the chosen deal is featured as soon as the order is paid, and cron removes the flag when the period ends.
- **Google AdSense**: add your publisher ID and ad unit IDs under Configuration → Web services → Ads.

Prices are set on the products under Commerce → Products.

The preview uses a **test payment gateway** (card 4111 1111 1111 1111, any future expiry). Before launch, add a Stripe gateway under Commerce → Configuration → Payment gateways with your Stripe keys and disable the test gateway.

## Deploying

Every push to `main` builds the site on GitHub Actions and deploys it to Hostinger over SSH. The deploy needs these repository settings:

- Secrets: `SSH_HOST`, `SSH_PORT`, `SSH_USER`, `SSH_KEY` (private deploy key), and optionally `DB_NAME`, `DB_USER`, `DB_PASS` for a MySQL database (SQLite is used otherwise).
- Variables: `SITE_DOMAIN` (for example the Hostinger preview domain, later `goingsolo.ie`) and `SITE_MAIL`.

The code goes to `~/domains/<SITE_DOMAIN>/goingsolo`, and `public_html` becomes a link to its `web` folder. Secrets live in `~/domains/<SITE_DOMAIN>/goingsolo/private/secrets.php`, outside the web root. The first deploy installs the site. Each deploy prints a one-time admin login link, encrypted with `deploy/login.pub` because the repository is public. The site domain defaults to `goingsolo.ie` (override with the `SITE_DOMAIN` variable). The site stays in maintenance mode, so only logged-in admins can see it, until the `MAINTENANCE_MODE` variable is set to `0` (or an admin turns it off at /admin/config/development/maintenance).

## Working locally

```
composer install
cp deploy/settings.php web/sites/default/settings.php
# create private/secrets.php with a $databases array and a hash_salt
vendor/bin/drush site:install goingsolo -y
vendor/bin/drush pm:install goingsolo_commerce goingsolo_seo -y
```
