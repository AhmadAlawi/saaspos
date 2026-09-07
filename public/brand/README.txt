Brand assets for the installer (and pre-login screens)
======================================================

Drop your files in THIS folder (public/brand/). They are shown during the
install wizard before any database/company record exists, so they must be
plain static files here — not uploads in the admin.

Filenames the app looks for (first match wins):

  Logo (installer header)
    logo.svg          preferred — crisp at any size
    logo.png          used if no .svg (transparent background recommended)
    logo-dark.png     optional — shown in dark mode; falls back to logo.png
    logo-dark.svg     optional dark-mode SVG (checked before logo-dark.png)

  Favicon (browser tab)
    favicon.png       preferred (32x32 or 48x48)
    favicon.ico       used if no .png
    (if neither exists here, the shipped /public/favicon.ico is used)

Sizing:
  - The header logo is capped to 36px tall / 200px wide and scales down to
    fit, so a horizontal wordmark or a square mark both look right.

Until you add a logo here, the installer falls back to a letter-avatar +
the product name automatically — nothing breaks if the folder is empty.
