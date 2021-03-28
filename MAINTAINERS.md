# Notes for Maintainers

## Publishing a Release to Wordpress.org

This process is controlled via GitHub actions.

Once changes have landed on master and manual verification has been completed, go to Releases -> Create a new release. 

Tag the version, e.g. "1.9.0" (must be semVer, no letters). 

A special action "Deploy to WordPress.org" starts automatically. It deploys the plugin to wp.org and creates an asset (zip file), adding it to the release.

This relies on repo secrets SVN_USERNAME and SVN_PASSWORD; if wp.org credentials are updated, the repo secrets must be updated as well.

## Creating translations

The easiest way is to [create translations online](https://translate.wordpress.org/projects/wp-plugins/hcaptcha-for-forms-and-more/). Such translations are loaded automatically by the plugin via WordPress Core and require no further amendments to the plugin code.
