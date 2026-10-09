# Paws Theme

Warm, friendly design for veterinary clinics and animal hospitals with navy, coral and cream colors, serif headings, rounded cards and soft wave edges for [Pagible CMS](https://pagible.com).

This package is part of the [Pagible CMS monorepo](https://github.com/aimeos/pagible).

## Installation

```bash
composer require aimeos/pagible-themes-paws
php artisan vendor:publish --tag=cms-theme
```

## Design

- **Style**: Warm and welcoming but calm, with a cream header, navy footer and dark sections with a soft dot pattern, a wave below the hero, organic image shapes and small paw markers above headings
- **Colors**: Warm cream (#FBF6F0), navy (#16324F), coral (#C2412B) and peach (#F4C6A8)
- **Typography**: Classic serif fonts for headings, system sans-serif body
- **Borders**: Rounded corners, pill-shaped buttons, thin borders and soft shadows
- **CSS framework**: Pico CSS with `--pico-*` custom property overrides

## Page Types

| Type | Description |
|------|-------------|
| `page` | Landing and service pages |
| `docs` | Documentation with sidebar navigation |
| `blog` | Pet care guides and news listed by the blog element |

## Clinic Details

The **Clinic** settings in the page config add a veterinary business JSON-LD to every page below the configured page:

| Field | Description |
|-------|-------------|
| Clinic type | schema.org type: `VeterinaryCare`, `EmergencyService`, `AnimalShelter` or `LocalBusiness` |
| Name, address, telephone, email | Clinic details, the telephone is also used by the action bar |
| Emergency number | Shown in the top bar of every page and rendered as emergency `contactPoint` |
| Booking link | Appointment page or online booking, shown as a button in the header and rendered as `ReserveAction` |
| Languages | Comma separated languages, rendered as `knowsLanguage` |
| Animals treated | Comma separated animals, rendered as `knowsAbout` |
| New clients | Rendered as `isAcceptingNewPatients` |
| Price range | Price level, e.g. `€€` |
| Opening hours | Opening and closing time per day of the week |
| Action bar | Call and booking buttons at the bottom of the screen on phones |

## Customization

Theme colors and properties can be customized in the admin panel:

| Property | Default | Description |
|----------|---------|-------------|
| `--pico-color` | `#2B3A4A` | Body text color |
| `--pico-background-color` | `#FBF6F0` | Page background |
| `--pico-primary` | `#C2412B` | Primary accent (coral) |
| `--pico-secondary` | `#F4C6A8` | Secondary accent (peach) |
| `--pico-border-radius` | `1rem` | Base border radius |

## Demo

```bash
php artisan cms:demo --theme=paws
```

## Structure

```
├── composer.json
├── schema.json          Theme and clinic configuration schema
├── database/seeders/    PawsDemo seeder
├── lang/                Frontend translations
├── src/
│   └── PawsServiceProvider.php
├── public/              CSS and admin translations published to public/vendor/cms/paws/
│   ├── cms.css          Base styles, header, footer and action bar
│   ├── i18n/            Admin translations of the config fields
│   └── *.css            Content element and layout styles
├── tests/
└── views/
    └── layouts/
        └── main.blade.php
```

## License

MIT
