# Regional B Laravel deployment

The Regional B application has been fully migrated to Laravel.

## Environments

- Source repository and staging checkout: `/var/www/regionalb-online-laravel`
- Staging document root: `/var/www/regionalb-online-laravel/public`
- Production deployment: `/var/www/regionalb.online/laravel`
- Production document root: `/var/www/regionalb.online/laravel/public`

The directories formerly named `public_html` contain the retired native-PHP
application. They are not web document roots and were archived on 2026-10-03.

## Deployment flow

1. Make and test changes in `/var/www/regionalb-online-laravel`.
2. Commit and push `main` to `origin`.
3. Prepare the production copy with `./scripts/prepare-staging.sh` or the
   documented deployment procedure for the repository.
4. Run Laravel migrations and clear/refresh application caches as needed.
5. Validate Nginx and verify both production and staging URLs.

Do not make application changes in the archived native-PHP tree.
