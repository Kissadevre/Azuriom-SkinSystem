# SkinSystem for Azuriom

SkinSystem is an Azuriom plugin for uploading, previewing, and synchronizing Minecraft skins from a single account page.

## Project status

SkinSystem is under active development and is not ready for production use yet.

The plugin is intentionally standalone: the Azuriom Skin API and Skin3D Viewer plugins are design references, not runtime dependencies.

## Runtime dependencies

- SkinsRestorer on the Minecraft server is required to apply uploaded skins.
- One executable Azuriom Minecraft server connection, using AzLink or RCON, is required for automatic synchronization.
- The bundled 3D viewer renders the skin directly from SkinSystem's own PNG endpoint.

The separate Azuriom Skin API and Skin3D Viewer plugins are **not** required. SkinSystem owns its upload endpoint and includes the `skinview3d` browser bundle locally.

## Implemented foundation

- Authenticated, permission-protected skin management page.
- Strict PNG validation for modern 64×64 and legacy 64×32 Minecraft skins.
- Three-megabyte upload limit and GD re-encoding before storage.
- Immutable, revisioned SHA-256-addressed PNG endpoint for MineSkin and web clients.
- One current skin record per Azuriom user with explicit revision tracking.
- Permission-controlled personal skin libraries with administrator-defined per-user quotas.
- Separate upload and save actions: uploading activates and synchronizes a skin, while saving only stores it in the library.
- Alphanumeric library names limited to 16 characters, with an explicit replacement choice when the user's quota is full.
- Instant activation of a saved skin without uploading its PNG again; every switch creates a new immutable revision and uses the normal SkinsRestorer synchronization path.
- Integrated 3D preview with automatic, classic, and slim arm models.
- Configurable My Skin shortcut in Azuriom's authenticated user menu with a validated Bootstrap Icon.
- Direct, MineSkin, and Hybrid delivery modes with an encrypted, administrator-owned global MineSkin API key.
- Permission-controlled MineSkin cape selection that is completely absent when the integration is unavailable, plus live cape rendering in the 3D preview.
- Persistent MineSkin queue jobs, browser-assisted status updates, scheduler recovery, bounded retry delays, and reuse of identical completed appearances.
- Server-side classic/slim detection compatible with the bundled viewer heuristic.
- A dedicated public-image rate limit, independent from Azuriom's shared API limiter.
- Exact SkinsRestorer set and clear commands addressed to canonical Minecraft UUIDs.
- Automatic dispatch through an authoritative AzLink or RCON connection.
- Durable pending, submitted, failed, uncertain, and not-configured synchronization states.
- A conservative destination ledger that retains every UUID/server pair which may have observed a SkinSystem set command.
- Revision-bound clear generations that fan out to every recorded destination and reject stale result updates after a newer upload.
- Per-user atomic locks around upload, deletion, retry, and bridge dispatch.
- Exact ownership of SkinSystem-created AzLink set and per-target clear rows, so a newer operation can replace its own queued command without deleting commands from administrators or other plugins.
- Thirty-day retention for superseded immutable revisions and orphaned blobs, with daily scheduled cleanup.

The browser viewer is the official `skinview3d` 3.4.2 browser bundle, stored locally with its MIT license. SkinSystem does not require a CDN or a separate viewer plugin at runtime.

## SkinsRestorer commands

SkinSystem emits the current SkinsRestorer console grammar:

```text
skin set "<immutable-https-png-url>" <canonical-uuid> <classic|slim>
skin clear <canonical-uuid>
```

The selected UUID and server are snapshotted into each set operation. Every such destination remains in a conservative ledger because a queued AzLink command may already have been fetched when its database row is cancelled. Deleting a skin creates one revision-bound clear intent for every recorded destination, so moving from target A to target B cannot leave A behind. Retrying a clear operation reuses those immutable UUID/server pairs instead of silently moving to a newly selected account or server.

For AzLink, SkinSystem persists the command directly in Azuriom's normal `server_commands` queue and records that row's exact ID. SET ownership belongs to the current global operation; each CLEAR row belongs to exactly one destination and clear revision. It then mirrors AzLink's optional immediate notification behavior. For RCON, it records the possible destination before crossing the external dispatch boundary and uses the standard server bridge directly.

A normal return means **submitted**, not confirmed as applied. AzLink does not provide SkinSystem with an execution acknowledgement or a strict ordering guarantee for a command already fetched before cancellation, so destination tombstones are not discarded after submission and the user can safely submit the clear operation again. Conversely, if a new skin replaces an unacknowledged CLEAR, SkinSystem retains that risk and records the new SET as **uncertain** even though it was submitted; the user must verify the result in game and can explicitly synchronize again. An exception after an RCON dispatch begins is also recorded as **uncertain** and is never retried automatically.

## Installation checklist

1. Install SkinsRestorer at the layer that owns player skin data.
2. Connect that authoritative Minecraft server or proxy to Azuriom with AzLink or RCON.
3. In **Admin > SkinSystem**, select that server and enable synchronization.
4. Choose the maximum personal-library size and grant the `skinsystem.library` permission only to roles that may save and switch skins.
5. Choose Direct, MineSkin, or Hybrid delivery. MineSkin mode requires a verified global API key; Hybrid uses MineSkin only for appearances with a selected cape.
6. To offer capes, configure a MineSkin key with the capes grant and assign `skinsystem.cape` only to eligible roles. The key is encrypted and is never sent to a player's browser.
7. Configure Azuriom's canonical `APP_URL` as a publicly reachable HTTPS origin when Direct or Hybrid delivery can be used. The generated URL must remain within SkinsRestorer's 266-byte URL limit.
8. If `commands.restrictSkinUrls.enabled` is enabled in SkinsRestorer, allow the Azuriom HTTPS origin for Direct delivery and MineSkin's result origin for MineSkin delivery.
9. If the old Skin API plugin remains installed, disable AzLink's legacy `skinrestorer-integration` listener so it cannot overwrite SkinSystem on player join.
10. With BungeeCord or Velocity, dispatch to the proxy-side instance where SkinsRestorer is authoritative, not an arbitrary backend.
11. On multi-node Azuriom deployments, configure a shared atomic-lock cache such as Redis or database. The default file cache is suitable for a single shared web installation.
12. Run Azuriom's scheduler every minute. It invokes `skinsystem:mineskin:process` for pending jobs and `skinsystem:cleanup` daily; both commands can also be run manually.

Saving an HTTP or otherwise unreachable `APP_URL` is allowed so the site can be configured in stages. Uploads remain local and their synchronization state records the precise precondition failure until configuration is corrected.

The MineSkin API is only contacted server-side. A queued response is persisted before the web request completes; the authenticated page polls a local status endpoint while it remains open, and Azuriom's scheduler recovers the same job when the browser closes. SkinSystem never silently falls back to a capeless direct upload after a cape generation failure.

Changing the selected authoritative server affects newly created set operations. Previously recorded destinations remain attached to the account and are included in later clear generations; failed, submitted, and uncertain targets deliberately keep their original UUID/server pair so a retry cannot clear the wrong SkinsRestorer installation.

## Storage and cleanup

Every accepted PNG is decoded and re-encoded with GD, then stored under a SHA-256 path. The public endpoint resolves an exact `(user, revision, hash)` database tuple, preventing arbitrary revision aliases from serving current bytes.

Superseded revision mappings and unreferenced blobs are retained for 30 days so queued consumers do not receive premature 404 responses. The cleanup command serializes against user uploads before removing data and never deletes the active revision or a blob referenced by a saved library entry. Revision numbers remain monotonic even after historical mappings are purged. Destination tombstones are intentionally not expired because neither AzLink nor RCON supplies an authoritative applied/cleared acknowledgement.

## Development checks

From `plugins/skinsystem`, run:

```bash
../../vendor/bin/phpunit --configuration phpunit.xml
```

The suite covers upload lifecycle, immutable endpoint aliases and overflow input, migration portability, multi-target snapshots, AzLink fetch/cancel races, exact queue ownership, partial clear failures, stale clear generations, RCON uncertainty, cleanup, URL safety, and per-user lock contention.

Each baseline table is owned by its own dated anonymous migration and reverses only that table. Every later schema change must be shipped as a new migration; existing migrations must not be edited to add subsequent columns, indexes, or tables.

## Architecture boundaries

All plugin source code, views, translations, migrations, and assets live under `plugins/skinsystem`. SkinSystem does not modify Azuriom core files or write directly to SkinsRestorer's database.

## Author

- Kissadere
