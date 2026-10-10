# Medical Theme

Calm, clean design for medical and dental practices with navy, teal and mint accents, a white header, rounded cards, plus markers and soft dot backgrounds for [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-medical
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Calm and clinical but friendly, with a white header, navy footer and dark sections with a soft dot pattern, a curved edge below the hero and plus markers above headings
- **Colors**: Cool off-white (#F4F8F9), navy (#0F2D3A), teal (#0A7782) and mint (#8DD8C8)
- **Typography**: System sans-serif body, humanist sans-serif fonts for headings
- **Borders**: Rounded corners, pill-shaped buttons, thin borders and soft shadows
- **CSS framework**: Pico CSS with `--pico-*` custom property overrides

## Page Types

| Type | Description |
|------|-------------|
| `page` | Landing and treatment pages |
| `docs` | Documentation with sidebar navigation |
| `blog` | Patient guides and news listed by the blog element |

## Practice Details

The **Practice** settings in the page config add a medical business JSON-LD to every page below the configured page:

| Field | Description |
|-------|-------------|
| Practice type | schema.org type: `Dentist`, `Physician`, `MedicalClinic`, `Physiotherapy` or `MedicalBusiness` |
| Medical specialty | schema.org `MedicalSpecialty`, rendered as `medicalSpecialty` |
| Name, address, telephone, email | Practice details, the telephone is also used by the call button |
| Emergency number | Shown with the telephone in the top bar of every page |
| Booking link | Appointment page or online booking, shown as a button in the header |
| Languages | Comma separated languages, rendered as `knowsLanguage` |
| New patients | Rendered as `isAcceptingNewPatients` |
| Price range | Price level, e.g. `€€` |
| Opening hours | Opening and closing time per day of the week |
| Call button | Sticky call button at the bottom of the screen on phones |

## Customization

Theme colors and properties can be customized in the admin panel:

| Property | Default | Description |
|----------|---------|-------------|
| `--pico-color` | `#1E3440` | Body text color |
| `--pico-background-color` | `#F4F8F9` | Page background |
| `--pico-primary` | `#0A7782` | Primary accent (teal) |
| `--pico-secondary` | `#8DD8C8` | Secondary accent (mint) |
| `--pico-border-radius` | `0.75rem` | Base border radius |

## Demo

```bash
php artisan cms:demo --theme=medical
```

## Structure

```
├── composer.json
├── schema.json          Theme and practice configuration schema
├── database/seeders/    MedicalDemo seeder
├── lang/                Frontend translations
├── src/
│   └── MedicalServiceProvider.php
├── public/              CSS and admin translations published to public/vendor/cms/medical/
│   ├── cms.css          Base styles, header, footer and call button
│   ├── i18n/            Admin translations of the config fields
│   └── *.css            Content element and layout styles
├── tests/
└── views/
    └── layouts/
        └── main.blade.php
```

## License

MIT
