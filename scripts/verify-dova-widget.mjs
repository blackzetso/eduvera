/**
 * Verifies Dova widget renders on the public homepage.
 * Usage: node scripts/verify-dova-widget.mjs [baseUrl]
 */
import { chromium } from 'playwright'
import { mkdir, writeFile } from 'node:fs/promises'
import path from 'node:path'
import { fileURLToPath } from 'node:url'

const baseUrl = process.argv[2] ?? 'http://127.0.0.1:8000'
const outDir = path.join(path.dirname(fileURLToPath(import.meta.url)), '..', 'docs', 'dova', 'screenshots')

const browser = await chromium.launch({ headless: true })
const page = await browser.newPage({ viewport: { width: 1440, height: 900 } })
const errors = []

page.on('pageerror', (err) => errors.push(String(err)))
page.on('console', (msg) => {
  if (msg.type() === 'error') {
    errors.push(msg.text())
  }
})

await mkdir(outDir, { recursive: true })

await page.goto(`${baseUrl}/`, { waitUntil: 'networkidle', timeout: 30000 })

const launcher = page.locator('.dova-widget__launcher')
await launcher.waitFor({ state: 'visible', timeout: 15000 })

await page.screenshot({
  path: path.join(outDir, 'dova-homepage-launcher.png'),
  fullPage: false,
})

await launcher.click()

const panel = page.locator('.dova-widget__panel')
await panel.waitFor({ state: 'visible', timeout: 10000 })

const greeting = await page.locator('.dova-widget__bubble--assistant').first().textContent()
const suggestions = await page.locator('.dova-widget__suggestion').count()

await page.screenshot({
  path: path.join(outDir, 'dova-homepage-panel-open.png'),
  fullPage: false,
})

await page.locator('.dova-widget__suggestion').first().click()
await page.waitForTimeout(1500)

const actionChips = await page.locator('.dova-action-chip').count()

await page.screenshot({
  path: path.join(outDir, 'dova-homepage-with-actions.png'),
  fullPage: false,
})

const report = {
  url: `${baseUrl}/`,
  launcherVisible: await launcher.isVisible(),
  panelOpens: await panel.isVisible(),
  greeting: greeting?.trim() ?? '',
  suggestionCount: suggestions,
  actionChipCount: actionChips,
  consoleErrors: errors,
  screenshots: [
    'docs/dova/screenshots/dova-homepage-launcher.png',
    'docs/dova/screenshots/dova-homepage-panel-open.png',
    'docs/dova/screenshots/dova-homepage-with-actions.png',
  ],
}

await writeFile(path.join(outDir, 'verification-report.json'), JSON.stringify(report, null, 2))

await browser.close()

console.log(JSON.stringify(report, null, 2))

if (!report.launcherVisible || !report.panelOpens || errors.length > 0) {
  process.exit(1)
}
