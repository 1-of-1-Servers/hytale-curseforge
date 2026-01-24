# Hytale CurseForge (Pelican Plugin)

This plugin adds a "Mods" page for Hytale servers in Pelican and lets users download mods from CurseForge directly into:

`/files/mods`

## Requirements

- A Hytale server egg that includes the feature or tag: `curse_forge_mod_plugin`
- A CurseForge API key (set in panel `.env`)

## Configuration

Add to your Pelican panel `.env`:

```
CURSEFORGE_API_KEY=your_API_token_here
CURSEFORGE_HYTALE_GAME_ID=70216
HYTALE_MODS_PATH=files/mods
```

## Preview
<img width="3398" height="1920" alt="image" src="https://github.com/user-attachments/assets/a8b4b91b-97fa-4247-9fad-52862cb96451" />


## Behavior

- Search & browse Hytale mods via CurseForge
- One-click "Download" installs the mod file into `/files/mods`
- **Hytale requires restart to apply mods**, so the plugin shows a restart reminder after downloading

## Notes

- This plugin intentionally does **not** cache CurseForge API responses.
- If the API key is missing, the page will show empty results and downloads will error.
- Credits to [Boy132](https://github.com/Boy132) as I took the base of his [rust-umod](https://github.com/pelican-dev/plugins/tree/main/rust-umod) and modified it for this project. 
