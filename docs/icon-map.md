# FarSell icon map

Use Heroicons through the `blade-ui-kit/blade-heroicons` Composer package. The frontend audit found hand-written SVGs were dominant and already included Heroicons-style paths. The new Blade component maps semantic names to the official glyphs.

```blade
<x-ui.icon name="delivery" />
<x-ui.icon name="save" variant="solid" size="sm" />
<x-ui.button type="submit" variant="primary" icon="add">Add product</x-ui.button>
<x-ui.icon-button icon="delete" variant="danger" label="Delete address" />
```

Use outline by default. Use solid for a selected or filled state, such as a saved product or a filled rating star. Sizes are `sm` (16 px), `md` (20 px), and `lg` (24 px). Keep the glyph decorative when visible text or a control label supplies its meaning. Icon-only controls use `x-ui.icon-button` with a descriptive `label`. The component uses that label for the accessible name and native tooltip. Supply `tooltip` when the tooltip needs different wording.

| Semantic name | Heroicons glyph | Concept |
| --- | --- | --- |
| `edit` | pencil-square | Edit an existing record |
| `delete` | trash | Delete a record |
| `view` / `hide` | eye / eye-slash | View or hide details or a password |
| `add` / `subtract` | plus / minus | Add or decrease quantity |
| `search` | magnifying-glass | Search |
| `filter` | adjustments-horizontal | Open or apply filters |
| `export` | arrow-down-tray | Export or download |
| `cart` | shopping-cart | Shopping cart |
| `order` | clipboard-document-list | Orders and order history |
| `delivery` | truck | Delivery, shipping or dispatch |
| `payout` | banknotes | Earnings, payouts and settlements |
| `settings` | cog-6-tooth | Configuration or settings |
| `home` / `browse` | home / squares-2x2 | Home and catalog browsing |
| `account` / `buyers` | user-circle / users | Account and buyer group |
| `notification` | bell | Notifications |
| `chat` | chat-bubble-left-right | Messaging |
| `save` | heart | Wishlist or saved product |
| `rating` | star | Rating or loyalty |
| `product` | cube | Product or listing |
| `location` | map-pin | Location or address |
| `camera` / `image` | camera / photo | Capture or attach a photo |
| `time` | clock | Pending work or elapsed time |
| `more` / `menu` | ellipsis-horizontal / bars-3 | More actions and navigation menu |
| `close` / `expand` | x-mark / chevron-down | Dismiss and disclose |
| `previous` / `next` | arrow-left / arrow-right | Back and forward |
| `increase` / `decrease` | arrow-up / arrow-down | Metric direction |
| `light` / `dark` | sun / moon | Light and dark themes |
| `confirm` / `warning` | check / exclamation-triangle | Confirmed state and alerts |
| `play` / `pause` | play / pause | Start or pause playback |
| `loading` | arrow-path | Busy indicator |

Full-size discovery and chat illustrations remain artwork. FarSell and Google lettermarks, avatar initials, media, and the favicon retain their original roles. Category labels use the catalog glyph; existing category metadata remains editable.
