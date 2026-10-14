# Zero Banner: how to use it

A banner is a carousel of slides. Create the banner first, then place it on
pages with a widget.

## Install

Requires Magento Open Source 2.4.7–2.4.9 and PHP 8.2–8.4.

```bash
composer require fixstacks/module-zero-banner
bin/magento setup:upgrade
bin/magento setup:di:compile           # production mode
bin/magento setup:static-content:deploy # production mode
bin/magento cache:flush
```

Part of [`fixstacks/zero`](https://fixstacks.com/zero), which installs the Zero theme and every Zero module at once.

## 1. Create a banner

**Content → Banners → Add New Banner**

- **Name**: shown in the grid and in the widget's banner picker.
- **Identifier**: a Latin code, e.g. `summer-sale`.
- **Store View**: where it is shown.

**Display**, how the banner looks:

| Setting | What it does |
|---|---|
| Aspect Ratio | The frame on desktop: 5:2 a wide hero, 3:1 a narrow strip, 16:9, 1:1 and so on. |
| Aspect Ratio on Phones | The frame on phones (up to 768px). Usually taller, e.g. 4:5. "Same as desktop" keeps the desktop one. |
| Width | Content width, or Full width (edge to edge). |
| Shown at the Top of the Page | On for a banner that is the first thing on the page (a hero): its first image loads right away. Off for banners further down: images load as the visitor scrolls. |
| Show Arrows / Dots / Slide Numbers | Arrows (desktop), dots under the banner, "01 / 03" before the heading. |
| Autoplay, Seconds per Slide | Off by default; visitors get a pause button. |

## 2. Add slides

**Slides** → **Add Slide**. Drag the ⋮⋮ handle to reorder; the bin on the
right deletes a slide.

- **Image**: JPG, PNG or GIF, 2000px wide or more. Upload it or pick it from
  the media gallery (Select from Gallery). WebP copies are made automatically.
- **Mobile Image**: optional, a separate image for phones.
- **Image Fit**
  - *Fill the frame*: the image fills the frame and is cropped to the
    banner's format (the usual choice for photos).
  - *Show the whole image*: the whole image shows, possibly with empty space
    around it (for images with text, logos, collages).
- **Focal Point**: the part kept when cropping: Top when a person's head gets
  cut off, Left/Right when the subject is at the side.
- **Text Position**: left, centre or right; the image is darkened on that
  side so the text stays readable.
- **Image Alt Text**: a short description (screen readers, search engines).
- **Eyebrow**: a small line above the heading; **Heading**; **Text**: a line
  or two.
- **Button Text** + **Link URL**: the button and where it goes (`/women.html`
  or a full URL). Without button text the whole slide is the link; without a
  link the slide isn't clickable.
- **Show From / Show To**: the dates it shows (inclusive). Empty: always.
- **Enabled**: hides a slide without deleting it.

**Save Banner**. All image sizes are made on save.

## 3. Put the banner on a page

**Content → Widgets → Add Widget**

1. Type: **Zero Banner**, Design Theme: **Zero** → Continue.
2. **Storefront Properties**: Widget Title (for yourself), Assign to Store Views.
3. **Layout Updates → Add Layout Update**: where it shows (home page:
   Specified Page → CMS Home Page; categories: Anchor Categories; products:
   All Product Types, and so on) and **Container**, the place on the page
   (e.g. Main Content Top, above the content).
4. **Widget Options** → Banner: pick the banner. **Save**.

Or insert it into a CMS page or block:
`{{widget type="Zero\Banner\Block\Widget\Banner" identifier="summer-sale"}}`

The "Homepage hero" banner is already on the home page through the "Homepage
hero" widget.

## 4. When changes show on the site

Pages are cached. New or changed slides show once the page cache refreshes
(up to a day); to see them at once, ask a developer to flush the cache
(Varnish). Slides with dates update by themselves, once an hour.

## General settings

**Stores → Configuration → Zero → Banners**: image quality (80 is a good
balance) and WebP (on). They apply to all banners.

## Disabling or removing

Its data lives in its own tables (`zero_banner_entity`, `zero_banner_entity_store`, `zero_banner_slide`, `zero_banner`, `zero_banner_store`). With the module's code still in
place, `bin/magento module:disable Zero_Banner` followed by `setup:upgrade`
**drops those tables** (Magento's declarative schema), and enabling the module
again brings them back empty. To keep the data, switch the feature off in
Stores > Configuration where it has a setting, or back up first:
`bin/magento setup:upgrade --safe-mode=1` dumps the data before anything is
dropped (`--data-restore=1` puts it back). Deleting the module's code instead
leaves the tables in place.

## License

OSL-3.0 or AFL-3.0, your choice: see `NOTICE.md`.

---

Zero is made by [Fixstacks](https://fixstacks.com/zero) in partnership with [Vilmate](https://vilmate.com).
Documentation: https://fixstacks.com/zero/docs. Demo store: https://zero.fixstacks.com.
