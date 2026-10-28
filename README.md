# Henshe Site

Site features for a WordPress magazine site: site styles, dark mode, phone menu, back-to-top, story links, reactions, trending slider, year filter, maintenance mode and small fixes. Each part is switched on or off under **Settings > Henshe Site**.

## Updates

The plugin checks this repository's latest release and WordPress shows **Update available** under Plugins (turn on auto-updates there to install them by themselves).

To release a new version: change `Version:` and `HENSHE_SITE_VERSION` in `henshe-site/henshe-site.php` and push to `main`. The Release workflow builds `henshe-site.zip` and publishes it as release `vX.Y.Z`.

Admins can also install the latest release straight away: `POST /wp-json/henshe/v1/update` (`GET` shows the installed and latest version).
