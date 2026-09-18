# Open Source Insta Feed

![Status: In Development](https://img.shields.io/badge/status-in%20development-yellow)
![Unofficial](https://img.shields.io/badge/plugin-unofficial-lightgrey)

A small, self-hosted WordPress Gutenberg block that pulls a venue's own Instagram posts onto their homepage, without pulling in a bloated feed plugin, its tracking scripts, or its cookies.

## Why this exists

Most "Instagram feed" WordPress plugins ship third-party JavaScript, set cookies, and add ongoing plugin-update overhead for something that's really just: fetch some JSON, cache it, render it. This project does exactly that, and nothing more.

It only ever pulls the connected account's own published media via the Instagram Graph API's `/media` endpoint. It never touches tagged or third-party posts, so there is no need for content moderation on this end.

## Status

Early development. Not yet functional, not yet installable, not yet recommended for use on a live site. Expect breaking changes without notice while this settles.

## Not a WordPress.org plugin

This is deliberately not submitted to the official plugin repository. It's maintained here, on GitHub, for anyone who wants to read the code, fork it, or use it at their own risk.

## License

TBD.