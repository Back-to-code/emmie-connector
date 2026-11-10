# Emmie Connector

A WordPress plugin that connects an organisation's website to [Emmie](https://emmie.nl), the care-management
platform. It adds two things to the site:

- **An intake form** (`[wp2emmie_form]`). A visitor's answers become a new client in the organisation's own Emmie.
  Once the token checks out, the plugin creates a page `/kennismaken` with this form.
- **The booking form for introductory meetings** (`[emmie_kennismaking]`), which Emmie draws into the page.

The organisation enters its Emmie API address and an API token under *Settings › Emmie Connector*. Emmie shows both
under *Instellingen › Instroom*. reCAPTCHA v3 keys are optional and protect the intake form against bots.

The plugin is maintained by Emmie B.V. This repository is its source; `readme.txt` is the description the plugin
directory of WordPress.org shows.

## Requirements

- PHP 8.0 or higher
- An organisation on Emmie, and someone there with access to Instroom to make the API token

## Development

```sh
composer install      # the coding standards (PHPCS with WPCS and PHPCompatibilityWP)
composer lint         # php -l on every PHP file
composer phpcs        # the WordPress Coding Standards, as CI runs them
```

CI runs on every pull request: `php -l` on the lowest and the highest supported PHP version, and PHPCS with the
ruleset in `phpcs.xml.dist`.

### A local WordPress against a local Emmie

`docker-compose.test.yml` starts WordPress on <http://localhost:8080> with this directory mounted as the plugin.
The steps to point it at a local Emmie are in the comment at the top of that file.

```sh
docker compose -f docker-compose.test.yml up -d
docker compose -f docker-compose.test.yml down -v
```

## Files

| File | What it does |
|---|---|
| `emmie-connector.php` | Plugin header, activation, the setup redirect and the clean-up on deactivation |
| `admin-settings.php` | The settings page and the token check against Emmie |
| `form-handler.php` | The intake form and sending it to Emmie |
| `booking-form.php` | The booking form for introductory meetings |
| `class-wp2emmie-data-encryption.php` | Encrypts the API token at rest with the site's own keys |
| `functions.php` | The Emmie address, logging of failed HTTP calls and the privacy statement link |
| `languages/` | Dutch translation |

## License

GPL-2.0-or-later. See [LICENSE](LICENSE).

## Releasing

A tag publishes a version to the plugin directory of WordPress.org; nobody uses SVN by hand.

1. Raise `Version` in the header of `emmie-connector.php` and `Stable tag` in `readme.txt` to the same number, and add
   the changes to the changelog in `readme.txt`.
2. Merge that to `main`, then tag it: `git tag v1.4.1 && git push origin v1.4.1`.
3. The workflow *Deploy to WordPress.org* checks that the tag, `Stable tag` and the header agree, and publishes.

The workflow publishes as `emmieapp` and needs the repository secret `SVN_PASSWORD` (the password of that account on
WordPress.org). Without it the run skips the deploy and shows a warning. Icon, banner and screenshots for the plugin
page go in `.wordpress-org/` and are published along with a release.
