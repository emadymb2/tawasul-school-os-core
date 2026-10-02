# Tawasul OS Tools (TawasulOS module)

Companion module for the **Tawasul OS Theme**. A TawasulOS theme can only change appearance, so these features live in this module.

## Install
1. Copy the `Tawasul OS Tools` folder into TawasulOS's `modules/` folder (keep the exact folder name).
2. System Admin → Manage Modules → install **Tawasul OS Tools**.
3. Make sure the web server can write to `uploads/` (the platform already requires this).

## Features
- **Announcement Writer** (Other → Tawasul OS Tools): enter title, audience, date, tone and details; AI drafts editable Arabic and English versions.
- **Login Branding**: school name, logo and colour presets/custom colours, with a live login-page preview before you submit. Name and logo are saved to the platform's own System settings. Custom colours are written to `uploads/tawasul/brand.css`, which the Tawasul OS Theme loads on every page, including login.
- **Announcement Service Settings**: service address and access token.

## Connect the AI service
The Announcement Writer calls the Tawasul OS drafting service. Enter the same access token here and in the service's `TAWASUL_MODULE_TOKEN` secret.
