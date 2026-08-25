# SkinSystem for Azuriom

SkinSystem is an Azuriom plugin for uploading, previewing, and synchronizing Minecraft skins from a single account page.

## Project status

SkinSystem is under active development and is not ready for production use yet.

The plugin is intentionally standalone: the Azuriom Skin API and Skin3D Viewer plugins are design references, not runtime dependencies.

## Planned runtime integration

- SkinsRestorer on the Minecraft server is required to apply uploaded skins.
- AzLink or another executable Azuriom server bridge is required for automatic synchronization.
- The bundled 3D viewer will render the skin directly from SkinSystem's own PNG endpoint.

## Architecture boundaries

All plugin source code, views, translations, migrations, and assets live under `plugins/skinsystem`. SkinSystem does not modify Azuriom core files or write directly to SkinsRestorer's database.

## Author

- Kissadere

