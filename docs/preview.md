# Putting the site on fixlisted.com/preview

The holding page stays at `fixlisted.com`. The site being built appears at
`fixlisted.com/preview`, so it can be clicked through without anything public
changing and without the waitlist stopping.

Nothing about this is temporary-hack shaped: the app works out where it is
mounted from where `index.php` sits, so moving it to the root at launch is
moving files, not editing code.

## On the server, once

```bash
cd ~/fixlisted
git pull
php bin/migrate.php          # adds the is_demo flag
php bin/check.php            # everything should pass
```

Then mount it. **`~/public_html` is leedixon.com's document root — not this
site's.** fixlisted.com's is its own directory:

```bash
ls -d ~/fixlisted.com        # confirm this is the docroot before continuing
ln -s ~/fixlisted/public ~/fixlisted.com/preview
```

The symlink means `git pull` updates the live preview immediately. There is
nothing to copy and nothing that can drift out of date.

Finally, refresh the holding page so it stops swallowing `/preview` paths:

```bash
cp ~/fixlisted/maintenance/dist/.htaccess ~/fixlisted.com/.htaccess
```

That file now carries two rules it did not have before: one that lets
`/preview` through untouched, and `Options +SymLinksIfOwnerMatch` so Apache
follows the symlink.

## Check it

```
https://fixlisted.com            still the holding page
https://fixlisted.com/preview    the site
```

If `/preview` shows the holding page instead of the site, the `.htaccess` copy
did not happen. If it returns 403, the symlink is not being followed — see
below.

## If the symlink is refused

Some hosts disallow `Options` in `.htaccess`, which turns the symlink into a
403. Use a real directory instead:

```bash
rm ~/fixlisted.com/preview
mkdir -p ~/fixlisted.com/preview
cp -r ~/fixlisted/public/. ~/fixlisted.com/preview/
```

This works identically, but it is a copy: re-run that `cp -r` after every
`git pull`, or the preview will be showing old code.

## Sample listings

The directory currently shows ten invented tradespeople. Every one is labelled
**Sample** on its card, on its profile, and in a banner across the top of the
page, because an unlabelled invented business listing is indistinguishable from
a real one — and someone will eventually try to phone it.

```bash
php bin/demo.php status      # what is sample, what is real
php bin/demo.php purge       # delete the sample rows, permanently
```

To hide them without deleting them, set `'demo_data' => 'hide'` under `app` in
`config/config.php`. Hidden means filtered out of every query, not hidden with
CSS.

## At launch

See [launch.md](launch.md). The short version: move the mount to the root,
turn off `noindex`, and purge the sample data.


## If /admin starts returning 404

Your account is no longer an administrator. Every admin page 404s for a
non-admin — deliberately, so that somebody poking at `/admin` gets no
confirmation the URL exists — which makes a privilege change look exactly like
a broken site.

```bash
php bin/admin.php --list     # shows every admin account and its role
php bin/admin.php            # makes an account a superadmin again
```

The cause worth knowing about: **listing a business with the same email
address as your admin account** used to demote it to `pro`. Fixed — roles now
only ever go up — but an account demoted before that fix stays demoted until
`bin/admin.php` puts it back.
