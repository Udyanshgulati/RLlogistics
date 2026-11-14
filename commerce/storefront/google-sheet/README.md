# Schermo live Google Sheet

One Google Sheet that stays in sync with the store, in both directions. Share it with the client: they fill
prices, details and stock; the site updates within about 5 minutes.

## Set-up (once, about 5 minutes)

1. **Get the key:** wp-admin → TL Commerce Hub → Settings → **Google Sheet sync** → *Create sheet key*.
   Copy the site address and the key shown there. Keep the key private.
2. Open the Google Sheet (the existing product sheet, or a new one).
3. **Extensions → Apps Script.** Delete what is in `Code.gs`, paste the whole of
   [`Code.gs`](Code.gs) from this folder, and click **Save**.
4. Go back to the sheet and **reload the page**. A **Schermo** menu appears next to *Help*.
5. **Schermo → Set up / change site and key.** Paste the site address (`https://schermo.thodalogic.com`)
   and the key. Google asks once for permission (the script talks to your site and edits this sheet). Allow it.
6. The sheet fills four tabs: **Live · Guide, Live · Products, Live · Stock, Live · Store details**.
   Answer **Yes** to turn on auto-sync every 5 minutes.
7. **Share** the sheet with the client (*Editor*). They don't need the key or the menu.

## How it works

- **White cells** can be edited. **Grey cells** come from the site (SKU, photo, collection, type, whether the
  piece can be ordered now, totals) and are refreshed on every sync.
- An edited row turns **yellow** ("pending") and is sent on the next sync. Its **Sync note** then says
  *Saved …*, or *Not saved: …* with the reason, for example an unknown warehouse code. The row stays
  yellow until it is fixed.
- A refresh never overwrites rows that are still yellow, so nobody's unsent edits are lost.
- **Prices:** MRP and selling price include GST. A selling price below MRP shows as a sale on the site.
  The price applies to every size.
- **Stock:** type the exact count per size per warehouse. An empty cell means "not counted yet".
  Every change is recorded in TL Commerce Hub → Stock movements with the note *Google Sheet*.
- **Show on site = Hide** takes a product off the shop (draft); *Show* puts it back.
- **New products** (they need photos) are added on the site by the team, and appear in the sheet on the next sync.
- **Schermo → Pull from site** throws away edits that were not sent yet.
- If the key is ever shared by mistake, create a new key in Settings and run *Set up* again with it.
