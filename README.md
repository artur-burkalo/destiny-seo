# Destiny SEO 0.5.0

This version adds a much stronger workflow for WordPress content review.

## New in 0.5.0

### Content Audit dashboard

Destiny SEO → Content Audit now shows a table across Posts, Pages, and admin-visible custom post types.

Columns include:

- Content
- Type
- SEO score
- Impressions
- Clicks
- CTR
- Average position
- Opportunity
- Open post

You can:

- filter by post type
- search content
- select individual items
- review selected content
- review all matching content (up to 50 per run)

### Editor integration

Every supported post type gets a Destiny SEO meta box.

It shows:

- score
- Search Console metrics
- top issues
- top opportunities
- Review again
- Generate improved draft

### Safe improved drafts

Generate improved draft:

- never overwrites the original post
- never publishes automatically
- creates a separate draft
- uses the latest SEO review and Search Console data
- asks the WordPress AI Client to return Gutenberg block markup

New ability:

`destiny-seo/create-improved-draft`

### Search Console

Direct per-site Google OAuth is unchanged.

The plugin requires:

- Google Client ID
- Google Client Secret
- successful Google OAuth connection
- selected Search Console property

before Search Console-powered reviews can run.

## Abilities

- `destiny-seo/get-content`
- `destiny-seo/get-search-performance`
- `destiny-seo/review-content`
- `destiny-seo/create-improved-draft`

## Safety behaviour

The plugin does not automatically publish or overwrite source content.
