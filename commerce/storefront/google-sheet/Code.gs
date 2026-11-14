/**
 * Schermo live sheet — two-way sync between this Google Sheet and the Schermo store (TL Commerce Hub).
 *
 * Tabs it manages: "Live · Products", "Live · Stock", "Live · Store details", "Live · Guide".
 * Edit the white cells. Edited rows are marked "pending" and sent to the site on the next sync (every 5 minutes when
 * auto-sync is on, or at once with Schermo → Sync now). After each sync the tabs are refreshed from the site, except
 * rows that are still pending, so nobody's unsent edits are overwritten. Grey columns come from the site.
 *
 * Set-up (once): Extensions → Apps Script → paste this file → Save → reload the sheet → Schermo → Set up.
 * You need the site address and the sheet key from TL Commerce Hub → Settings → Google Sheet sync.
 */

const TABS = { products: 'Live · Products', stock: 'Live · Stock', store: 'Live · Store details', guide: 'Live · Guide' };
const FIRST_ROW = 3; // row 1: labels, row 2 (hidden): field keys, data from row 3
const GREY = '#eeeeec';
const PENDING = '#fff4c2';

// [key, label, editable, width]
const PRODUCT_COLS = [
  ['sku', 'SKU', false, 90], ['photo', 'Photo', false, 70], ['name', 'Product name', true, 220], ['collection', 'Collection', false, 120],
  ['type', 'Type', false, 130], ['status', 'Show on site', true, 90], ['mrp', 'MRP ₹ (incl. GST)', true, 95], ['price', 'Selling price ₹ (incl. GST)', true, 105],
  ['orderable', 'Can be ordered now', false, 90], ['stock', 'Total stock', false, 70], ['short', 'Short description (1–2 lines)', true, 230],
  ['description', 'Full description', true, 320], ['hsn', 'HSN code', true, 80], ['gst', 'GST %', true, 60], ['weight', 'Packed weight', true, 80],
  ['ships_from', 'Ships from (warehouse code)', true, 110], ['dispatch', 'Dispatch time (e.g. 3–5 working days)', true, 150],
  ['_note', 'Sync note', false, 220], ['_pending', 'Pending since', false, 120],
];

function onOpen() {
  SpreadsheetApp.getUi().createMenu('Schermo')
    .addItem('Sync now', 'syncNow')
    .addItem('Pull from site (discard unsent edits)', 'pullDiscard')
    .addSeparator()
    .addItem('Turn auto-sync on (every 5 min)', 'autoOn')
    .addItem('Turn auto-sync off', 'autoOff')
    .addSeparator()
    .addItem('Set up / change site and key', 'setup')
    .addToUi();
}

// ---------- Settings & signed calls ----------
function props_() { return PropertiesService.getDocumentProperties(); }

function setup() {
  const ui = SpreadsheetApp.getUi();
  const url = ui.prompt('Schermo sync — site address', 'e.g. https://schermo.thodalogic.com', ui.ButtonSet.OK_CANCEL);
  if (url.getSelectedButton() !== ui.Button.OK) return;
  const key = ui.prompt('Schermo sync — sheet key', 'From TL Commerce Hub → Settings → Google Sheet sync', ui.ButtonSet.OK_CANCEL);
  if (key.getSelectedButton() !== ui.Button.OK) return;
  props_().setProperties({ url: url.getResponseText().trim().replace(/\/+$/, ''), key: key.getResponseText().trim() });
  pull_(false);
  buildGuide_();
  const go = ui.alert('Connected', 'The Live tabs are filled from the site. Turn on auto-sync every 5 minutes?', ui.ButtonSet.YES_NO);
  if (go === ui.Button.YES) autoOn();
}

function call_(route, payload) {
  const p = props_().getProperties();
  if (!p.url || !p.key) throw new Error('Not set up yet: Schermo → Set up.');
  const path = (p.url.replace(/^https?:\/\/[^/]+/, '') || '') + '/wp-json/tlhub/v1/' + route;
  const body = JSON.stringify(payload || {});
  const ts = String(Math.floor(Date.now() / 1000));
  const raw = Utilities.computeHmacSha256Signature(ts + '\n' + 'POST' + '\n' + path + '\n' + body, p.key);
  const sig = raw.map((b) => ('0' + (b & 0xff).toString(16)).slice(-2)).join('');
  const res = UrlFetchApp.fetch(p.url + '/wp-json/tlhub/v1/' + route, {
    method: 'post', contentType: 'application/json', payload: body, muteHttpExceptions: true,
    headers: { 'X-TL-Store': 'sheet', 'X-TL-Timestamp': ts, 'X-TL-Signature': sig },
  });
  const code = res.getResponseCode();
  let json = null;
  try { json = JSON.parse(res.getContentText()); } catch (e) { /* not JSON */ }
  if (code < 200 || code >= 300 || !json) throw new Error('Site replied ' + code + ': ' + (json && json.message ? json.message : res.getContentText().slice(0, 200)));
  return json;
}

// ---------- Sync ----------
function syncNow() { run_(true, true); }
function autoSync() { run_(false, false); }
function pullDiscard() {
  const ui = SpreadsheetApp.getUi();
  if (ui.alert('Pull from site', 'Edits that were not sent yet will be replaced by the site\'s values. Continue?', ui.ButtonSet.YES_NO) !== ui.Button.YES) return;
  run_(false, true, true);
}

function run_(push, interactive, discard) {
  const lock = LockService.getDocumentLock();
  if (!lock.tryLock(20000)) { if (interactive) SpreadsheetApp.getActive().toast('A sync is already running — try again in a moment.'); return; }
  try {
    let msg = '';
    if (push !== false || !interactive) {
      const r = push_();
      msg = r.sent ? 'Sent ' + r.sent + ' rows: ' + r.applied + ' changes' + (r.errors ? ', ' + r.errors + ' rows need attention (see Sync note)' : '') + '. ' : '';
    }
    pull_(!discard);
    if (interactive) SpreadsheetApp.getActive().toast(msg + 'Refreshed from the site.', 'Schermo', 6);
  } catch (e) {
    if (interactive) SpreadsheetApp.getUi().alert('Sync problem', String(e.message || e), SpreadsheetApp.getUi().ButtonSet.OK);
    else console.error(e);
  } finally {
    lock.releaseLock();
  }
}

/** Marks edited rows as pending (simple trigger: runs on every edit by a person). */
function onEdit(e) {
  const sh = e.range.getSheet();
  const name = sh.getName();
  if (![TABS.products, TABS.stock, TABS.store].includes(name) || e.range.getLastRow() < FIRST_ROW) return;
  const keys = sh.getRange(2, 1, 1, sh.getLastColumn()).getValues()[0];
  const pc = keys.indexOf('_pending') + 1, nc = keys.indexOf('_note') + 1;
  if (!pc) return;
  const from = Math.max(FIRST_ROW, e.range.getRow()), to = e.range.getLastRow();
  const stamp = Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'dd MMM HH:mm');
  sh.getRange(from, pc, to - from + 1, 1).setValue(stamp);
  if (nc) sh.getRange(from, nc, to - from + 1, 1).setValue('');
  sh.getRange(from, 1, to - from + 1, sh.getLastColumn()).setBackground(PENDING);
}

function rowsOf_(sh) {
  if (!sh || sh.getLastRow() < FIRST_ROW) return { keys: [], rows: [] };
  const lastCol = sh.getLastColumn();
  const keys = sh.getRange(2, 1, 1, lastCol).getValues()[0];
  const rows = sh.getRange(FIRST_ROW, 1, sh.getLastRow() - FIRST_ROW + 1, lastCol).getValues();
  return { keys, rows };
}

function push_() {
  const ss = SpreadsheetApp.getActive();
  const payload = { products: [], stock: [], store: {} };
  const where = [];
  const P = rowsOf_(ss.getSheetByName(TABS.products));
  P.rows.forEach((r, i) => {
    if (!r[P.keys.indexOf('_pending')]) return;
    const o = { sku: String(r[P.keys.indexOf('sku')]) };
    PRODUCT_COLS.filter((c) => c[2]).forEach((c) => { o[c[0]] = r[P.keys.indexOf(c[0])]; });
    payload.products.push(o); where.push([TABS.products, i, o.sku]);
  });
  const S = rowsOf_(ss.getSheetByName(TABS.stock));
  S.rows.forEach((r, i) => {
    if (!r[S.keys.indexOf('_pending')]) return;
    const o = { sku: String(r[S.keys.indexOf('sku')]), wh: {} };
    S.keys.forEach((k, j) => { if (String(k).indexOf('wh:') === 0) o.wh[k.slice(3)] = r[j]; });
    payload.stock.push(o); where.push([TABS.stock, i, o.sku]);
  });
  const D = rowsOf_(ss.getSheetByName(TABS.store));
  D.rows.forEach((r, i) => {
    if (!r[D.keys.indexOf('_pending')]) return;
    payload.store[r[D.keys.indexOf('key')]] = r[D.keys.indexOf('value')];
    where.push([TABS.store, i, r[D.keys.indexOf('key')]]);
  });
  if (!where.length) return { sent: 0, applied: 0, errors: 0 };
  const res = call_('sheet/push', payload);
  const bad = {};
  (res.errors || []).forEach((e) => { bad[e.sku] = e.error; });
  // Clear "pending" on rows that went through; keep it (with the reason) on rows the site refused.
  where.forEach(([tab, i, id]) => {
    const sh = ss.getSheetByName(tab);
    const keys = sh.getRange(2, 1, 1, sh.getLastColumn()).getValues()[0];
    const row = FIRST_ROW + i;
    if (bad[id]) {
      sh.getRange(row, keys.indexOf('_note') + 1).setValue('Not saved: ' + bad[id]);
    } else {
      sh.getRange(row, keys.indexOf('_pending') + 1).setValue('');
      sh.getRange(row, keys.indexOf('_note') + 1).setValue('Saved ' + Utilities.formatDate(new Date(), Session.getScriptTimeZone(), 'dd MMM HH:mm'));
    }
  });
  return { sent: where.length, applied: res.applied || 0, errors: Object.keys(bad).length };
}

/** Refreshes the Live tabs from the site; rows still pending are kept as they are (unless discard). */
function pull_(keepPending) {
  const data = call_('sheet/pull', {});
  const ss = SpreadsheetApp.getActive();
  const codes = (data.warehouses || []).map((w) => w.code);

  // Products
  const pcols = PRODUCT_COLS.map((c) => c[0] === 'weight' ? [c[0], 'Packed weight (' + (data.weight_unit || 'kg') + ')', c[2], c[3]] : c);
  const P = sheet_(TABS.products, pcols);
  const keepP = keepPending ? pending_(P, 'sku') : {};
  const prow = data.products.map((p) => keepP[p.sku] || pcols.map((c) => c[0] === 'photo' ? (p.photo ? '=IMAGE("' + p.photo + '")' : '') : c[0] === '_note' || c[0] === '_pending' ? '' : (p[c[0]] === null || p[c[0]] === undefined ? '' : p[c[0]])));
  write_(P, pcols, prow, 60);
  validate_(P, pcols, 'status', ['Show', 'Hide']);
  validate_(P, pcols, 'gst', ['0', '3', '5', '12', '18', '28']);
  validate_(P, pcols, 'ships_from', [''].concat(codes));

  // Stock: one row per size, one column per warehouse
  const scols = [['sku', 'Size SKU', false, 110], ['product_sku', 'Product SKU', false, 90], ['name', 'Product', false, 220], ['size', 'Size', false, 70]]
    .concat((data.warehouses || []).map((w) => ['wh:' + w.code, 'Stock · ' + w.name + ' (' + w.code + ')', true, 120]))
    .concat([['total', 'Total', false, 70], ['_note', 'Sync note', false, 220], ['_pending', 'Pending since', false, 120]]);
  const S = sheet_(TABS.stock, scols);
  const keepS = keepPending ? pending_(S, 'sku') : {};
  const srow = data.stock.map((r) => keepS[r.sku] || scols.map((c) => c[0].indexOf('wh:') === 0 ? (r.wh[c[0].slice(3)] === null || r.wh[c[0].slice(3)] === undefined ? '' : r.wh[c[0].slice(3)]) : c[0] === '_note' || c[0] === '_pending' ? '' : (r[c[0]] === null || r[c[0]] === undefined ? '' : r[c[0]])));
  write_(S, scols, srow, 21);

  // Store details
  const dcols = [['label', 'Detail', false, 260], ['value', 'Value', true, 420], ['key', 'key', false, 10], ['_note', 'Sync note', false, 220], ['_pending', 'Pending since', false, 120]];
  const D = sheet_(TABS.store, dcols);
  const keepD = keepPending ? pending_(D, 'key') : {};
  const drow = data.store.map((r) => keepD[r.key] || [r.label, r.value, r.key, '', '']);
  write_(D, dcols, drow, 28);
  D.hideColumns(3);

  props_().setProperty('lastPull', new Date().toISOString());
}

function sheet_(name, cols) {
  const ss = SpreadsheetApp.getActive();
  let sh = ss.getSheetByName(name);
  if (!sh) sh = ss.insertSheet(name);
  sh.getRange(1, 1, 2, Math.max(sh.getMaxColumns(), cols.length)).clearContent();
  if (sh.getMaxColumns() < cols.length) sh.insertColumnsAfter(sh.getMaxColumns(), cols.length - sh.getMaxColumns());
  sh.getRange(1, 1, 1, cols.length).setValues([cols.map((c) => c[1])]).setFontWeight('bold').setFontColor('#ffffff').setBackground('#111111').setWrap(true).setVerticalAlignment('middle');
  sh.getRange(2, 1, 1, cols.length).setValues([cols.map((c) => c[0])]);
  sh.hideRows(2);
  sh.setFrozenRows(2);
  sh.setFrozenColumns(Math.min(3, cols.length));
  sh.setRowHeight(1, 42);
  cols.forEach((c, i) => sh.setColumnWidth(i + 1, c[3]));
  return sh;
}

/** {id: row values} for rows still pending, so a refresh does not overwrite them. */
function pending_(sh, idKey) {
  const R = rowsOf_(sh);
  const out = {};
  const pi = R.keys.indexOf('_pending'), ii = R.keys.indexOf(idKey);
  if (pi < 0 || ii < 0) return out;
  R.rows.forEach((r) => { if (r[pi]) out[r[ii]] = r; });
  return out;
}

function write_(sh, cols, rows, height) {
  const last = sh.getLastRow();
  if (last >= FIRST_ROW) sh.getRange(FIRST_ROW, 1, last - FIRST_ROW + 1, sh.getMaxColumns()).clearContent().setBackground(null);
  if (rows.length) {
    const fixed = rows.map((r) => cols.map((c, i) => (r[i] === undefined ? '' : r[i])));
    sh.getRange(FIRST_ROW, 1, fixed.length, cols.length).setValues(fixed).setVerticalAlignment('middle');
    sh.setRowHeights(FIRST_ROW, fixed.length, height);
    cols.forEach((c, i) => { if (!c[2]) sh.getRange(FIRST_ROW, i + 1, fixed.length, 1).setBackground(GREY); });
    const pi = cols.findIndex((c) => c[0] === '_pending');
    fixed.forEach((r, j) => { if (r[pi]) sh.getRange(FIRST_ROW + j, 1, 1, cols.length).setBackground(PENDING); });
  }
  const extra = sh.getMaxRows() - (FIRST_ROW + rows.length - 1);
  if (extra > 5) sh.deleteRows(FIRST_ROW + rows.length + 5, extra - 5);
}

function validate_(sh, cols, key, list) {
  const i = cols.findIndex((c) => c[0] === key);
  if (i < 0 || sh.getLastRow() < FIRST_ROW) return;
  const rule = SpreadsheetApp.newDataValidation().requireValueInList(list, true).setAllowInvalid(false).build();
  sh.getRange(FIRST_ROW, i + 1, sh.getLastRow() - FIRST_ROW + 1, 1).setDataValidation(rule);
}

function buildGuide_() {
  const ss = SpreadsheetApp.getActive();
  let sh = ss.getSheetByName(TABS.guide);
  if (!sh) sh = ss.insertSheet(TABS.guide, 0);
  sh.clear();
  const lines = [
    ['Schermo live sheet — how to use'],
    [''],
    ['• Edit the WHITE cells. Grey cells come from the site and are refreshed on every sync.'],
    ['• An edited row turns yellow ("pending"). It is sent to the site on the next sync — every 5 minutes when auto-sync is on, or at once with Schermo → Sync now.'],
    ['• After a sync the "Sync note" says "Saved …", or "Not saved: …" with the reason (the row stays yellow until it is fixed).'],
    ['• Live · Products: name, Show/Hide on site, MRP and selling price (incl. GST; a selling price below MRP shows as a sale), descriptions, HSN, GST %, packed weight, ships-from warehouse code, dispatch time.'],
    ['• Live · Stock: the count per size in each warehouse. Type the exact number on the shelf; empty means "not counted yet".'],
    ['• Live · Store details: the business details shown on invoices and the Contact / policy pages.'],
    ['• New products (with photos) are added on the site by the team; they appear here on the next sync.'],
    ['• Schermo → Pull from site throws away edits that were not sent yet.'],
  ];
  sh.getRange(1, 1, lines.length, 1).setValues(lines).setWrap(true);
  sh.getRange(1, 1).setFontSize(16).setFontWeight('bold');
  sh.setColumnWidth(1, 900);
}

// ---------- Auto-sync ----------
function autoOn() {
  autoOff(true);
  ScriptApp.newTrigger('autoSync').timeBased().everyMinutes(5).create();
  SpreadsheetApp.getActive().toast('Auto-sync is on: every 5 minutes.', 'Schermo', 5);
}

function autoOff(quiet) {
  ScriptApp.getProjectTriggers().filter((t) => t.getHandlerFunction() === 'autoSync').forEach((t) => ScriptApp.deleteTrigger(t));
  if (quiet !== true) SpreadsheetApp.getActive().toast('Auto-sync is off.', 'Schermo', 5);
}
