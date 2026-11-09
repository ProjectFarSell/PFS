# Icon and button migration

Counts are Blade source call sites. Shared layouts, chat, and shared components are counted once under Shared. Repeated rows and runtime states do not multiply the counts. The dashboard KPI action uses two mutually exclusive component branches in place of one original anchor.

| Panel or surface | Button and action-link call sites migrated | Legacy icon source sites replaced |
| --- | ---: | ---: |
| Buyer, storefront, and authentication | 86 | 21 |
| Seller | 19 | 0 |
| Admin | 69 | 8 |
| Rider | 15 | 6 |
| Shared layouts, chat, and cross-panel views | 85 | 47 |
| **Total** | **274** | **82** |

The Seller-specific templates had no legacy glyph sites. Add Product, Edit Listing, and CSV Export now use the mapped Heroicons. The icon counts exclude newly added icons and styleguide examples.

## Intentional exceptions

- Standard text hyperlinks, navigation links, linked cards, and native disclosures remain semantically appropriate links or summaries. The migration covered native application buttons and styled action links. Native buttons are now rendered by the shared button component.
- The two remaining hand-authored SVGs are illustrations in the discovery and chat empty states. Brand lettermarks, avatar initials, favicon, product images, and verification QR codes serve separate visual or functional roles.
- The category editor retains the legacy icon field for stored metadata. Category navigation and previews use the standard catalog glyph.
- Custom range sliders, file pickers, and form field wrappers retain their existing interaction-specific layout. Status pills, shared fields, modal surfaces, and alert styles use shared tones or tokens.
- Livewire loading attributes are supported by the button contract. The application currently uses Blade and Alpine, so the local styleguide exercises the explicit loading prop.
- No npm icon packages were present to remove. Composer added Blade Heroicons and Blade Icons.

## Review

The local-only styleguide is registered at `/styleguide`. The route is absent in production.

The migration was applied in panel groups. Separate commits were left pending because this workspace already had uncommitted changes in the same files, including dashboard, navigation, and performance changes. No earlier work was staged or bundled into a migration commit.
