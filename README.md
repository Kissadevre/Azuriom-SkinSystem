# SkinSystem for Azuriom

SkinSystem is an Azuriom plugin for uploading, previewing, and synchronizing Minecraft skins from a single account page.

## Project status

SkinSystem is under active development and is not ready for production use yet.

The plugin is intentionally standalone: the Azuriom Skin API and Skin3D Viewer plugins are design references, not runtime dependencies.

## Planned runtime integration

- SkinsRestorer on the Minecraft server is required to apply uploaded skins.
- AzLink or another executable Azuriom server bridge is required for automatic synchronization.
- The bundled 3D viewer renders the skin directly from SkinSystem's own PNG endpoint.

## Implemented foundation

- Authenticated, permission-protected skin management page.
- Strict PNG validation for modern 64×64 and legacy 64×32 Minecraft skins.
- Three-megabyte upload limit and GD re-encoding before storage.
- Immutable, revisioned SHA-256-addressed PNG endpoint for MineSkin and web clients.
- One current skin record per Azuriom user with explicit revision tracking.
- Integrated 3D preview with automatic, classic, and slim arm models.
- Server-side classic/slim detection compatible with the bundled viewer heuristic.
- A dedicated public-image rate limit, independent from Azuriom's shared API limiter.

The browser viewer is the official `skinview3d` 3.4.2 browser bundle, stored locally with its MIT license. SkinSystem does not require a CDN or a separate viewer plugin at runtime.

## Architecture boundaries

All plugin source code, views, translations, migrations, and assets live under `plugins/skinsystem`. SkinSystem does not modify Azuriom core files or write directly to SkinsRestorer's database.

## Author

- Kissadere
