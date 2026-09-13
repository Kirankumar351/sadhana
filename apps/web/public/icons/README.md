# Icons — required before launch

| File | Size | Purpose |
|---|---|---|
| `icon-192.png` | 192×192 | Home screen, notification icon |
| `icon-512.png` | 512×512 | Splash screen |
| `maskable-512.png` | 512×512 | Android adaptive icon |
| `badge-72.png` | 72×72 | Monochrome notification badge |

`maskable-512.png` needs its content inside the **safe zone** — a circle of 80% diameter,
centred. Android crops the rest to whatever mask the launcher uses, and artwork drawn to
the edges gets its corners cut off.

`badge-72.png` must be a **solid white silhouette on transparency**. Android tints it and
discards colour; a full-colour badge shows as a grey blob.

Brand green is `#0F6B4F`.
