# Unified HOBC Website Portal Specification

## Goal

`hobbyhashcoin.com` must feel like one complete HOBC command center. Every page should look, read, and behave like part of the same HobbyHash Coin portal instead of separate one-off pages.

The site is for beginner-friendly home solo mining with honest live status. It must use the provided black, gold, and mining-themed HOBC logos, banners, and icon set. Images should be placed where they support navigation and user understanding, not stretched into awkward shapes.

## Required Pages

1. Home (`/`)
2. About HOBC (`/about/`)
3. Mining (`/mining/`)
4. Main Pool (`/pool/main/`)
5. Nano Pool (`/pool/nano/`)
6. Explorer (`/explorer/`)
7. Wallet (`/wallet/`)
8. Stats (`/stats/`)
9. Downloads (`/downloads/`)
10. Docs (`/docs/`)
11. Launch Reserve (`/launch-reserve/`)
12. Burn Tracker (`/burn/`)
13. Roadmap (`/roadmap/`)
14. FAQ (`/faq/`)
15. Contact/Support (`/contact/`)

## Shared Layout

Every page must include:

- Shared header with the HOBC logo, wordmark, and command-center identity.
- Shared navigation with links to every major section.
- Shared status bar with honest chain, pool, wallet, explorer, reserve, and burn status.
- Shared footer with brand links, risk notes, and support links.
- A visible link back to Home/Dashboard.
- Section subnav where useful, especially Mining, Pools, Wallet, Explorer, Docs, Launch Reserve, and Burn Tracker.

Implementation rule for future additions:

- New public portal pages must use the shared PHP includes in `/home/hobbyhashcoin/public_html/includes/`.
- New public styles must extend `/home/hobbyhashcoin/public_html/assets/css/hobc.css` instead of creating a separate visual system.
- Wallet app pages must keep using `/home/hobbyhashcoin/public_html/app/view.php` so login, register, dashboard, receive, withdraw, transactions, support, and future wallet additions inherit the same HOBC command-center style.
- Wallet-specific styling belongs in `/home/hobbyhashcoin/public_html/assets/css/hobc-wallet.css` and must stay visually aligned with the main page.
- Future additions must keep the black/gold HOBC theme, shared logos, clear cards, clear buttons, mobile behavior, and honest unavailable-data wording.

## Page Requirements

### Home

The Home page is the command-center dashboard. It must include:

- HOBC headline.
- Plain explanation of HobbyHash Coin.
- Chain height card.
- Network difficulty card.
- Network hashrate estimate card.
- Latest block card.
- Circulating supply card.
- Launch reserve card.
- Burn amount card.
- Main pool status card.
- Nano pool status card.
- Wallet status card.
- Explorer status card.
- Buttons: Start Mining, Open Wallet, View Explorer, Download Node, Read Docs.

### About HOBC

Explain:

- HobbyHash Coin public name and HOBC ticker.
- SHA-256 proof-of-work.
- Home solo mining focus.
- Nano miner friendly pool option.
- Solo pools only.
- Fair launch and transparent 10% launch reserve.
- No market price or market cap claims unless real market data exists.

### Mining

Beginner setup hub:

- What solo mining means.
- Difference between Main Pool and Nano Pool.
- Address/worker naming format.
- Copy buttons for pool URLs and worker examples.
- Simple ASIC and nano miner setup boxes.
- Troubleshooting checklist.

### Main Pool

Must show:

- `stratum+tcp://pool.hobbyhashcoin.com:5555`
- Start difficulty `5000`.
- Username format `YOUR_HOBC_ADDRESS.worker1`.
- Password `x`.
- Solo only.
- Payout goes to the address in the worker name.
- ASIC setup examples.
- Main pool status with real data only.

### Nano Pool

Must show:

- `stratum+tcp://pool.hobbyhashcoin.com:5556`
- Start difficulty `0.005`.
- Username format `YOUR_HOBC_ADDRESS.nano1`.
- Password `x`.
- Solo only.
- Payout goes to the address in the worker name.
- Nano miner setup examples.
- Nano pool status with real data only.

### Explorer

Must provide UI for:

- Search by block hash.
- Search by txid.
- Search by address.
- Latest blocks.
- Latest transactions.
- Launch reserve block clearly labeled.
- Burn addresses clearly labeled.
- Pending/syncing message if explorer is not ready.

### Wallet

Must provide:

- Custodial wallet login/register links.
- Deposit/withdraw explanation.
- Very visible custodial risk notice.
- Statement that the website controls funds in the web wallet.
- Recommendation to use a local wallet for larger balances.
- No fake balances.
- No fake txids.

Risk notice must be visible on the Wallet landing page and repeated before deposit/withdraw flows inside the wallet application.

### Stats

Stats page must show real data only:

- Chain height.
- Latest block hash.
- Latest block time.
- Current difficulty.
- Estimated network hashrate.
- Circulating supply.
- Total mined.
- Launch reserve balance.
- Burned supply.
- Active nodes if available.
- Mempool tx count if available.
- Main pool hashrate.
- Nano pool hashrate.
- Main pool workers.
- Nano pool workers.
- Main accepted/rejected shares.
- Nano accepted/rejected shares.
- Pool last share.
- Pool last block found.
- Recent blocks.
- Recent transactions.
- Wallet backend health.
- Explorer sync status.

Unavailable fields must show `Syncing`, `Not available yet`, `Offline`, or `Pending launch`.

### Downloads

Must include:

- Linux node section.
- Windows wallet section if built.
- Sample config.
- Checksums only for real downloadable files.
- No fake downloads.

### Docs

Must include:

- Node setup.
- Wallet setup.
- Main pool mining.
- Nano pool mining.
- Pool troubleshooting.
- Solo mining explanation.
- Custodial wallet risk.
- Launch reserve explanation.
- FAQ links.

### Launch Reserve

Must show:

- Total supply `84,000,000 HOBC`.
- Launch reserve `8,400,000 HOBC`.
- 10% public reserve.
- Split categories.
- Reserve addresses.
- Current balances when available.
- Outgoing transactions when available.
- Transparency statement.

### Burn Tracker

Must show:

- Burn address or addresses.
- Total burned.
- Yearly burn plan.
- Burn tx list.
- No fake burns.

### Roadmap

Must show current and upcoming work as statuses, not promises of completed features. Pending work must be labeled pending.

### FAQ

Must answer beginner questions about HOBC, SHA-256 mining, solo pools, nano miners, wallet custody, launch reserve, burns, and support.

### Contact/Support

Must link to public support and wallet support. It must not expose admin details or backend errors.

## Design Rules

- Use the provided black/gold HOBC logo and banners as primary brand assets.
- Keep the palette: near-black backgrounds, gold highlights, soft white text, muted gray secondary text.
- Do not distort images; use `max-width: 100%`, `height: auto`, and `object-fit: contain` or `cover` depending on placement.
- Use the wide banners for hero/dashboard areas and the small icons for card/category accents.
- Use clean cards with clear headings and plain language.
- Use obvious buttons for primary actions.
- Keep mining setup boxes copy-friendly.
- Avoid cluttered advanced charts until real endpoints exist.

## Branding Rules

Use these terms consistently:

- `HobbyHash Coin`
- `HOBC`
- `Home solo mining`
- `Nano miner friendly`
- `SHA-256`
- `Transparent 10% launch reserve`
- `Custodial web wallet with risk notice`
- `Solo pools only`

Do not use abandoned or old project names on public pages.

## Data Honesty Rules

Never return or display made-up:

- Hashrate.
- Workers.
- Blocks.
- Txids.
- Market price.
- Market cap.
- Burns.
- Wallet balances.

If data is unavailable, the UI must show one of:

- `Syncing`
- `Offline`
- `Pending launch`
- `Not available yet`

Market price and market cap must be omitted entirely until a real market data source exists.

## Site Status Modes

The admin Control Center must provide a Site Status section with three modes:

- `pre_launch`: normal visitors see a branded HOBC pre-launch page explaining what is coming. The configured bypass IP can still view the full website for testing.
- `maintenance`: normal visitors see a branded maintenance page with admin-configured start/end date text and maintenance message. The configured bypass IP can still view the full website.
- `full_launch`: normal operations. The website is open to everyone.

Admin pages must remain accessible in all modes. API/job/admin paths should not be blocked by the public launch gate. The default mode is `full_launch` so the site is not accidentally hidden until an admin explicitly changes it.

## Mobile Behavior

- Header stacks cleanly on small screens.
- Nav becomes a wrapping button grid or horizontal scroll without hiding major sections.
- Cards use single-column layout on phones.
- Copy buttons remain touch friendly.
- Long pool URLs, worker names, block hashes, addresses, and txids must wrap safely.
- Banners must crop gently or scale down without covering text.

## Copy Button Behavior

Copy buttons must be used for:

- Pool URLs.
- Worker examples.
- Password examples.
- Config snippets.
- Reserve addresses.
- Burn addresses.

Behavior:

- Button copies exact visible value or associated code block.
- Button label temporarily changes to `Copied` on success.
- Button label changes to `Copy failed` if clipboard access fails.
- Copying must not require user login.

## Wallet Risk Notice Placement

The custodial wallet risk notice must appear:

- On the Wallet landing page above login/register buttons.
- Near deposit instructions.
- Near withdraw instructions.
- In wallet docs.
- In FAQ.

Required wording concept:

`The HOBC web wallet is custodial. The website controls the wallet keys and funds until you withdraw. Use a local wallet for larger balances or long-term storage.`

## Launch Reserve Visibility

The launch reserve must be visible from:

- Header/nav Launch Reserve link.
- Home dashboard reserve card.
- Explorer labels.
- Stats page.
- Docs explanation.
- Footer transparency link.

Reserve data must include static supply facts now and live balances/outgoing transactions only when real tracking is available.
